<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\CashFlow;
use App\Models\LedgerTransaction;
use App\Models\TransactionCategory;
use App\Services\AllocationActualService;
use App\Services\MonthlyReviewGuard;
use App\Services\TransactionCategoryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function index(Request $request, TransactionCategoryService $categoryDefaults): Response
    {
        $categoryDefaults->ensureDefaults();
        $month = $request->input('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()
            : now()->startOfMonth();
        $flows = CashFlow::whereBetween('occurred_on', [$month, $month->copy()->endOfMonth()])->orderByDesc('occurred_on')->get();

        return Inertia::render('cash-flow', [
            'flows' => $flows, 'month' => $month->toDateString(),
            'summary' => ['income' => $flows->where('type', 'income')->sum('amount_egp'), 'expenses' => $flows->whereIn('type', ['expense', 'obligation'])->sum('amount_egp')],
            'budgetCategories' => BudgetCategory::query()->where('kind', 'expense')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->map(fn (BudgetCategory $category): array => ['id' => $category->id, 'name' => $category->name])->values(),
            'categories' => TransactionCategory::query()->with('budgetCategory')->whereIn('kind', ['income', 'expense'])->orderBy('name')->get()->map(fn (TransactionCategory $category): array => ['id' => $category->id, 'name' => $category->name, 'kind' => $category->kind, 'budgetCategoryId' => $category->budget_category_id, 'budgetCategoryName' => $category->budgetCategory?->name])->values(),
        ]);
    }

    public function store(Request $request, AllocationActualService $actuals, MonthlyReviewGuard $reviewGuard): RedirectResponse
    {
        $data = $this->validated($request);
        $reviewGuard->assertEditable($data['occurred_on']);
        $cashFlow = DB::transaction(function () use ($data): CashFlow {
            $cashFlow = CashFlow::create($data);
            $this->syncLedgerTransaction($cashFlow, $data);

            return $cashFlow;
        });
        $actuals->syncMonth(Carbon::parse($cashFlow->occurred_on));

        return redirect()->route('cash-flow.index', ['month' => Carbon::parse($cashFlow->occurred_on)->format('Y-m')])->with('success', 'Actual income or expense recorded and monthly plan synced.');
    }

    public function update(Request $request, CashFlow $cashFlow, AllocationActualService $actuals, MonthlyReviewGuard $reviewGuard): RedirectResponse
    {
        $previousMonth = Carbon::parse($cashFlow->occurred_on);
        $data = $this->validated($request);
        $reviewGuard->assertEditable($previousMonth);
        $reviewGuard->assertEditable($data['occurred_on']);
        DB::transaction(function () use ($cashFlow, $data): void {
            $cashFlow->update($data);
            $this->syncLedgerTransaction($cashFlow, $data);
        });
        $actuals->syncMonth($previousMonth);
        $actuals->syncMonth(Carbon::parse($cashFlow->occurred_on));

        return redirect()->route('cash-flow.index', ['month' => Carbon::parse($cashFlow->occurred_on)->format('Y-m')])->with('success', 'Actual entry updated and monthly plan synced.');
    }

    public function restore(int $cashFlow, AllocationActualService $actuals, MonthlyReviewGuard $reviewGuard): RedirectResponse
    {
        $entry = CashFlow::withTrashed()->findOrFail($cashFlow);
        $reviewGuard->assertEditable($entry->occurred_on);
        $entry->restore();
        $transaction = $entry->ledger_transaction_id === null
            ? null
            : LedgerTransaction::withTrashed()->find($entry->ledger_transaction_id);
        if ($transaction !== null) {
            $transaction->restore();
            $transaction->update(['review_state' => 'confirmed', 'voided_at' => null]);
            $actuals->syncMonth(Carbon::parse($entry->occurred_on));
        }

        return back()->with('success', 'Cash flow entry restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:income,expense'], 'category' => ['nullable', 'string', 'max:80'],
            'transaction_category_id' => ['nullable', 'exists:transaction_categories,id'],
            'description' => ['nullable', 'string', 'max:240'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'in:EGP,USD'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
            'occurred_on' => ['required', 'date'], 'notes' => ['nullable', 'string'],
        ]);

        $rate = $data['currency'] === 'EGP' ? 1.0 : (float) ($data['exchange_rate'] ?? 0);
        if ($data['currency'] === 'USD' && $rate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'A USD entry needs its EGP exchange rate.',
            ]);
        }

        if (empty($data['category']) && empty($data['transaction_category_id'])) {
            throw ValidationException::withMessages(['transaction_category_id' => 'Choose a category.']);
        }
        if (empty($data['category']) && isset($data['transaction_category_id'])) {
            $data['category'] = TransactionCategory::findOrFail($data['transaction_category_id'])->name;
        }

        $data['exchange_rate'] = $rate;
        $data['amount_egp'] = round((float) $data['amount'] * $rate, 2);

        return $data;
    }

    public function destroy(CashFlow $cashFlow, AllocationActualService $actuals, MonthlyReviewGuard $reviewGuard): RedirectResponse
    {
        $month = Carbon::parse($cashFlow->occurred_on);
        $reviewGuard->assertEditable($month);
        if ($cashFlow->ledger_transaction_id !== null) {
            $transaction = LedgerTransaction::find($cashFlow->ledger_transaction_id);
            if ($transaction !== null) {
                $transaction->update(['review_state' => 'void', 'voided_at' => now()]);
                $transaction->delete();
            }
        }
        $cashFlow->delete();
        $actuals->syncMonth($month);

        return back()->with('success', 'Actual entry removed and monthly plan synced.');
    }

    /** @param array<string, mixed> $data */
    private function syncLedgerTransaction(CashFlow $cashFlow, array $data): LedgerTransaction
    {
        $kind = $data['type'];
        $category = isset($data['transaction_category_id'])
            ? TransactionCategory::findOrFail($data['transaction_category_id'])
            : TransactionCategory::withTrashed()->firstOrNew(['name' => $data['category'], 'kind' => $kind]);
        if ($category->kind !== $kind) {
            abort(422, 'The selected category does not match the entry type.');
        }
        if (! $category->exists) {
            $category->fill(['is_system' => false])->save();
        }
        if ($category->trashed()) {
            $category->restore();
        }

        $payload = [
            'category_id' => $category->id,
            'transaction_type' => $data['type'],
            'occurred_on' => $data['occurred_on'],
            'description' => ($data['description'] ?? null) ?: $category->name,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'exchange_rate' => $data['exchange_rate'],
            'amount_egp' => $data['amount_egp'],
            'review_state' => 'confirmed',
            'source' => 'cash_flow_legacy',
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => now(),
            'metadata' => ['cash_flow_id' => $cashFlow->id],
        ];
        $payload['fingerprint'] = LedgerTransaction::fingerprintFor($payload);
        $transaction = $cashFlow->ledger_transaction_id === null
            ? LedgerTransaction::create($payload)
            : LedgerTransaction::withTrashed()->findOrFail($cashFlow->ledger_transaction_id);
        if ($transaction->trashed()) {
            $transaction->restore();
        }
        $transaction->update($payload + ['voided_at' => null]);
        $cashFlow->update([
            'ledger_transaction_id' => $transaction->id,
            'transaction_category_id' => $category->id,
            'category' => $category->name,
            'description' => $data['description'] ?? null,
        ]);

        return $transaction;
    }
}
