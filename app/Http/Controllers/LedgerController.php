<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\LedgerTransaction;
use App\Models\TransactionCategory;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(Request $request, LedgerService $ledger): Response
    {
        $month = $request->string('month')->toString();
        $monthDate = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : now()->startOfMonth();

        return Inertia::render('ledger', [
            'month' => $monthDate->format('Y-m'),
            'accounts' => Account::query()->orderBy('name')->get(),
            'transactions' => LedgerTransaction::with(['account', 'category'])->whereBetween('occurred_on', [$monthDate, $monthDate->copy()->endOfMonth()])->latest('occurred_on')->get(),
            'categories' => TransactionCategory::query()->orderBy('kind')->orderBy('name')->get(),
            'imports' => ImportBatch::with('rows')->latest()->limit(20)->get(),
            'reconciliation' => $ledger->reconciliation($monthDate),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        Account::create($request->validate($this->accountRules()));

        return back()->with('success', 'Account added.');
    }

    public function updateAccount(Request $request, Account $account): RedirectResponse
    {
        $account->update($request->validate($this->accountRules()));

        return back()->with('success', 'Account updated.');
    }

    public function destroyAccount(Account $account): RedirectResponse
    {
        $account->delete();

        return back()->with('success', 'Account archived.');
    }

    public function restoreAccount(int $account): RedirectResponse
    {
        Account::withTrashed()->findOrFail($account)->restore();

        return back()->with('success', 'Account restored.');
    }

    public function storeTransaction(Request $request): RedirectResponse
    {
        $data = $request->validate($this->transactionRules());
        $this->assertConversion($data);
        $data['amount_egp'] = $data['amount_egp'] ?? round((float) $data['amount'] * (float) ($data['exchange_rate'] ?? 1), 2);
        $data['review_state'] = $data['review_state'] ?? 'confirmed';
        $data['source'] = $data['source'] ?? 'manual';
        $data['fingerprint'] = LedgerTransaction::fingerprintFor($data);
        $data['reviewed_at'] = $data['review_state'] === 'confirmed' ? now() : null;
        LedgerTransaction::create($data);

        return back()->with('success', 'Ledger transaction recorded.');
    }

    public function updateTransaction(Request $request, LedgerTransaction $transaction): RedirectResponse
    {
        $data = $request->validate($this->transactionRules());
        $this->assertConversion($data);
        $data['amount_egp'] = $data['amount_egp'] ?? round((float) $data['amount'] * (float) ($data['exchange_rate'] ?? 1), 2);
        $data['fingerprint'] = LedgerTransaction::fingerprintFor($data);
        $transaction->update($data + ['reviewed_at' => ($data['review_state'] ?? $transaction->review_state) === 'confirmed' ? now() : $transaction->reviewed_at]);

        return back()->with('success', 'Ledger transaction updated.');
    }

    public function destroyTransaction(LedgerTransaction $transaction): RedirectResponse
    {
        $transaction->update(['voided_at' => now(), 'review_state' => 'void']);
        $transaction->delete();

        return back()->with('success', 'Ledger transaction voided and archived.');
    }

    public function restoreTransaction(int $transaction): RedirectResponse
    {
        LedgerTransaction::withTrashed()->findOrFail($transaction)->restore();

        return back()->with('success', 'Ledger transaction restored.');
    }

    /** @return array<string, string|array<int, string>> */
    private function accountRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'institution' => ['nullable', 'string', 'max:120'],
            'type' => ['required', 'in:bank,cash,brokerage,card,investment,other'], 'currency' => ['required', 'string', 'size:3'],
            'opening_balance_egp' => ['nullable', 'numeric'], 'reported_balance_egp' => ['nullable', 'numeric'],
            'reported_balance_as_of' => ['nullable', 'date'], 'is_active' => ['sometimes', 'boolean'], 'notes' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string|array<int, string>> */
    private function transactionRules(): array
    {
        return [
            'account_id' => ['nullable', 'exists:accounts,id'], 'counter_account_id' => ['nullable', 'exists:accounts,id', 'different:account_id'],
            'category_id' => ['nullable', 'exists:transaction_categories,id'], 'transaction_type' => ['required', 'in:income,expense,transfer,contribution,withdrawal,dividend,interest,fee,tax,debt_payment,obligation,correction'],
            'occurred_on' => ['required', 'date'], 'posted_on' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:240'],
            'amount' => ['required', 'numeric', 'gt:0'], 'currency' => ['required', 'string', 'size:3'], 'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'amount_egp' => ['nullable', 'numeric', 'gt:0'], 'review_state' => ['sometimes', 'in:pending,confirmed,rejected,void'], 'source' => ['sometimes', 'string', 'max:80'], 'notes' => ['nullable', 'string'],
            'splits' => ['sometimes', 'array'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function assertConversion(array $data): void
    {
        if (strtoupper((string) $data['currency']) !== 'EGP' && ! isset($data['exchange_rate']) && ! isset($data['amount_egp'])) {
            abort(422, 'A non-EGP transaction needs an explicit exchange rate or EGP amount.');
        }
    }
}
