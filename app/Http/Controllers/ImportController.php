<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\LedgerTransaction;
use App\Models\TransactionCategory;
use App\Services\LedgerService;
use App\Services\AllocationActualService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['file_name' => ['nullable', 'string', 'max:240'], 'source' => ['nullable', 'string', 'max:80'], 'csv' => ['nullable', 'string'], 'rows' => ['nullable', 'array']]);
        $rows = $data['rows'] ?? $this->parseCsv($data['csv'] ?? '');
        if ($rows === []) {
            return back()->withErrors(['csv' => 'Provide CSV data or at least one parsed row.']);
        }

        try {
            DB::transaction(function () use ($data, $rows): void {
                $batch = ImportBatch::create(['file_name' => $data['file_name'] ?? null, 'source' => $data['source'] ?? 'csv', 'status' => 'review', 'metadata' => ['no_silent_posting' => true]]);
                foreach ($rows as $index => $raw) {
                    $normalized = $this->normalizeRow($raw);
                    if ($normalized['category_id'] === null && isset($raw['category']) && trim((string) $raw['category']) !== '') {
                        $normalized['category_id'] = TransactionCategory::firstOrCreate([
                            'name' => trim((string) $raw['category']),
                            'kind' => in_array($normalized['transaction_type'], ['income', 'expense'], true) ? $normalized['transaction_type'] : 'adjustment',
                        ])->id;
                    }
                    $fingerprint = LedgerTransaction::fingerprintFor($normalized);
                    $duplicate = ImportRow::query()->where('fingerprint', $fingerprint)->first();
                    ImportRow::create($normalized + ['import_batch_id' => $batch->id, 'row_number' => $index + 1, 'raw_data' => $raw, 'fingerprint' => $fingerprint, 'duplicate_of_id' => $duplicate?->id, 'review_state' => $duplicate ? 'duplicate' : 'pending']);
                }
                $batch->refreshCounts();
            });
        } catch (\Throwable $exception) {
            Log::error('finance.import.failed', ['request_id' => $request->attributes->get('request_id'), 'user_id' => $request->user()?->id, 'file_name' => $data['file_name'] ?? null, 'exception' => $exception->getMessage()]);

            return back()->withErrors(['csv' => 'The import could not be queued. Nothing was posted.']);
        }

        return back()->with('success', 'CSV rows queued for review. Nothing was posted automatically.');
    }

    public function accept(Request $request, ImportRow $row, LedgerService $ledger, AllocationActualService $actuals): RedirectResponse
    {
        $data = $request->validate(['account_id' => ['nullable', 'exists:accounts,id'], 'category_id' => ['nullable', 'exists:transaction_categories,id'], 'transaction_type' => ['nullable', 'in:income,expense,transfer,contribution,withdrawal,dividend,interest,fee,tax,debt_payment,obligation,correction'], 'occurred_on' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:240'], 'amount' => ['nullable', 'numeric', 'gt:0'], 'currency' => ['nullable', 'string', 'size:3']]);
        $transaction = $ledger->acceptImportRow($row, array_filter($data, fn ($value): bool => $value !== null));
        $actuals->syncMonth(Carbon::parse($transaction->occurred_on));

        return back()->with('success', 'Import row accepted and posted as a confirmed transaction.');
    }

    public function updateBatch(Request $request, ImportBatch $batch): RedirectResponse
    {
        $batch->update($request->validate(['file_name' => ['nullable', 'string', 'max:240'], 'status' => ['sometimes', 'in:review,partially_posted,posted,rejected,archived'], 'notes' => ['nullable', 'string']]));

        return back()->with('success', 'Import batch updated.');
    }

    public function updateRow(Request $request, ImportRow $row): RedirectResponse
    {
        $row->update($request->validate(['account_id' => ['nullable', 'exists:accounts,id'], 'category_id' => ['nullable', 'exists:transaction_categories,id'], 'occurred_on' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:240'], 'amount' => ['nullable', 'numeric', 'gt:0'], 'currency' => ['sometimes', 'string', 'size:3'], 'transaction_type' => ['sometimes', 'in:income,expense,transfer,contribution,withdrawal,dividend,interest,fee,tax,debt_payment,obligation,correction'], 'review_notes' => ['nullable', 'string']]));

        return back()->with('success', 'Import row updated for review.');
    }

    public function reject(ImportRow $row): RedirectResponse
    {
        $row->update(['review_state' => $row->duplicate_of_id ? 'rejected_duplicate' : 'rejected', 'reviewed_at' => now()]);
        $row->batch?->refreshCounts();

        return back()->with('success', 'Import row rejected; no ledger transaction was posted.');
    }

    public function archive(int $batch): RedirectResponse
    {
        ImportBatch::findOrFail($batch)->delete();

        return back()->with('success', 'Import batch archived.');
    }

    public function restore(int $batch): RedirectResponse
    {
        ImportBatch::withTrashed()->findOrFail($batch)->restore();

        return back()->with('success', 'Import batch restored.');
    }

    public function restoreRow(int $row): RedirectResponse
    {
        ImportRow::withTrashed()->findOrFail($row)->restore();

        return back()->with('success', 'Import row restored.');
    }

    /** @return list<array<string, string>> */
    private function parseCsv(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $csv);
        rewind($handle);
        $headers = fgetcsv($handle) ?: [];
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($headers, array_pad($values, count($headers), null)) ?: [];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'account_id' => isset($row['account_id']) && $row['account_id'] !== '' ? (int) $row['account_id'] : null,
            'category_id' => isset($row['category_id']) && $row['category_id'] !== '' ? (int) $row['category_id'] : null,
            'occurred_on' => $row['occurred_on'] ?? $row['date'] ?? null,
            'description' => $row['description'] ?? $row['memo'] ?? $row['name'] ?? null,
            'amount' => isset($row['amount']) ? abs((float) $row['amount']) : null,
            'exchange_rate' => isset($row['exchange_rate']) && $row['exchange_rate'] !== '' ? (float) $row['exchange_rate'] : null,
            'amount_egp' => isset($row['amount_egp']) && $row['amount_egp'] !== '' ? abs((float) $row['amount_egp']) : null,
            'currency' => strtoupper((string) ($row['currency'] ?? 'EGP')),
            'transaction_type' => $row['transaction_type'] ?? $row['type'] ?? ((isset($row['amount']) && (float) $row['amount'] < 0) ? 'expense' : 'income'),
        ];
    }
}
