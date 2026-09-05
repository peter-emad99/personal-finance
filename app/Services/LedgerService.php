<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\CashFlow;
use App\Models\ImportRow;
use App\Models\LedgerTransaction;
use App\Models\Liability;
use App\Models\MonthlyFinancialReview;
use App\Models\RecurringCommitment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Canonical ledger and historical projection service.
 *
 * A confirmed ledger transaction is the only source used for a derived month.
 * Legacy cash_flows remain a read-only migration fallback until the first
 * confirmed transaction exists for that month; they are never added to a
 * ledger-derived total.
 */
class LedgerService
{
    public function __construct(private readonly ?MonthlyReviewGuard $reviewGuard = null) {}

    /** @return Collection<int, LedgerTransaction> */
    public function confirmedForMonth(CarbonInterface $month): Collection
    {
        return LedgerTransaction::confirmed()
            ->whereBetween('occurred_on', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->with(['category.budgetCategory', 'splits.category.budgetCategory'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    public function summarizeMonth(CarbonInterface $month, bool $includeLegacyFallback = true): array
    {
        $transactions = $this->confirmedForMonth($month);
        if ($transactions->isNotEmpty()) {
            return $this->summarizeTransactions($transactions) + [
                'source' => 'confirmed_ledger',
                'sourceTransactionCount' => $transactions->count(),
                'legacyFallbackUsed' => false,
            ];
        }

        if (! $includeLegacyFallback) {
            return $this->emptySummary('ledger_incomplete');
        }

        $legacy = CashFlow::whereBetween('occurred_on', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])->get();
        if ($legacy->isEmpty()) {
            return $this->emptySummary('ledger_incomplete');
        }

        return [
            'income' => round((float) $legacy->where('type', 'income')->sum('amount_egp'), 2),
            'essentialExpenses' => round((float) $legacy->where('type', 'expense')->where('category', 'essential')->sum('amount_egp'), 2),
            'lifestyleExpenses' => round((float) $legacy->where('type', 'expense')->filter(fn (CashFlow $flow): bool => $flow->category !== 'essential')->sum('amount_egp'), 2),
            'recurringCommitments' => 0.0,
            'oneTimeExpenses' => round((float) $legacy->where('type', 'expense')->where('category', '!=', 'essential')->sum('amount_egp'), 2),
            'debtPayments' => round((float) $legacy->where('type', 'obligation')->sum('amount_egp'), 2),
            'invested' => 0.0,
            'totalOutflow' => round((float) $legacy->whereIn('type', ['expense', 'obligation'])->sum('amount_egp'), 2),
            'source' => 'legacy_cash_flow',
            'sourceTransactionCount' => 0,
            'legacyFallbackUsed' => true,
        ];
    }

    /**
     * @param  Collection<int, LedgerTransaction>  $transactions
     * @return array<string, mixed>
     */
    private function summarizeTransactions(Collection $transactions): array
    {
        $income = $essential = $lifestyle = $recurring = $oneTime = $debt = $invested = $totalOutflow = 0.0;
        foreach ($transactions as $transaction) {
            if ($transaction->isTransfer()) {
                continue;
            }
            $parts = $transaction->splits->isNotEmpty()
                ? $transaction->splits->map(fn ($split): array => ['amount' => (float) $split->amount_egp, 'type' => (string) $split->transaction_type, 'category' => $this->categoryLabel($split->category ?? $transaction->category)])
                : collect([['amount' => (float) $transaction->amount_egp, 'type' => (string) $transaction->transaction_type, 'category' => $this->categoryLabel($transaction->category)]]);
            foreach ($parts as $part) {
                $amount = $part['amount'];
                $type = $part['type'];
                $category = $part['category'];
                if (in_array($type, ['expense', 'fee', 'tax', 'withdrawal', 'debt_payment', 'obligation'], true)) {
                    $totalOutflow += $amount;
                }
                if (in_array($type, ['income', 'interest', 'dividend', 'withdrawal_reversal'], true)) {
                    $income += $amount;

                    continue;
                }
                if (in_array($type, ['contribution', 'investment'], true)) {
                    $invested += $amount;

                    continue;
                }
                if (in_array($type, ['debt_payment', 'obligation'], true)) {
                    $debt += $amount;

                    continue;
                }
                if (in_array($type, ['expense', 'fee', 'tax', 'withdrawal'], true)) {
                    if ($type === 'fee' || $type === 'tax') {
                        $oneTime += $amount;
                    } elseif (str_contains($category, 'recurring')) {
                        $recurring += $amount;
                    } elseif (in_array($category, ['essential', 'housing', 'food', 'health', 'utility', 'utilities'], true)) {
                        $essential += $amount;
                    } else {
                        $lifestyle += $amount;
                    }
                }
            }
        }

        return [
            'income' => round($income, 2),
            'essentialExpenses' => round($essential, 2),
            'lifestyleExpenses' => round($lifestyle, 2),
            'recurringCommitments' => round($recurring, 2),
            'oneTimeExpenses' => round($oneTime, 2),
            'debtPayments' => round($debt, 2),
            'invested' => round($invested, 2),
            // The dashboard uses this exact transaction-type total. The
            // named fields above remain only for legacy review compatibility.
            'totalOutflow' => round($totalOutflow, 2),
        ];
    }

    private function categoryLabel(mixed $category): string
    {
        $parent = $category?->budgetCategory?->name;

        return strtolower(trim(($parent ? $parent.' ' : '').((string) ($category?->name ?? ''))));
    }

    /** @return array<string, mixed> */
    private function emptySummary(string $source): array
    {
        return [
            'income' => 0.0, 'essentialExpenses' => 0.0, 'lifestyleExpenses' => 0.0,
            'recurringCommitments' => 0.0, 'oneTimeExpenses' => 0.0, 'debtPayments' => 0.0,
            'invested' => 0.0, 'totalOutflow' => 0.0, 'source' => $source, 'sourceTransactionCount' => 0,
            'legacyFallbackUsed' => false,
        ];
    }

    public function deriveMonthlyReview(CarbonInterface $month, bool $closed = false, ?string $notes = null): MonthlyFinancialReview
    {
        $month = $month->copy()->startOfMonth();
        $summary = $this->summarizeMonth($month, false);

        return DB::transaction(function () use ($month, $summary, $closed, $notes): MonthlyFinancialReview {
            $review = MonthlyFinancialReview::firstOrNew(['month' => $month->toDateString()]);
            if ($review->exists && $review->status === 'closed') {
                throw new \InvalidArgumentException('This month is closed. Reopen it before deriving new actuals.');
            }
            $review->fill([
                'income_egp' => $summary['income'],
                'essential_expenses_egp' => $summary['essentialExpenses'],
                'lifestyle_expenses_egp' => $summary['lifestyleExpenses'],
                'recurring_commitments_egp' => $summary['recurringCommitments'],
                'one_time_expenses_egp' => $summary['oneTimeExpenses'],
                'debt_payments_egp' => $summary['debtPayments'],
                'invested_egp' => $summary['invested'],
                'status' => $closed ? 'closed' : ($review->status ?: 'open'),
                'source_type' => 'confirmed_ledger',
                'source_transaction_count' => $summary['sourceTransactionCount'],
                'derived_at' => now(),
                'closed_at' => $closed ? now() : $review->closed_at,
                'notes' => $notes ?? $review->notes,
            ])->save();

            return $review;
        });
    }

    /** @return array<string, mixed> */
    public function snapshotAt(CarbonInterface $asOf): array
    {
        $assets = Asset::query()->with(['valuations', 'assetType'])->get();
        $assetValue = 0.0;
        $valuationMovement = 0.0;
        $breakdown = [];
        $missingAssetValuations = [];
        foreach ($assets as $asset) {
            $valuation = $asset->valuations
                ->filter(fn (AssetValuation $item): bool => Carbon::parse($item->valued_on)->lte($asOf))
                ->sortByDesc(fn (AssetValuation $item): string => Carbon::parse($item->valued_on)->toDateString())
                ->first();
            $previous = $asset->valuations
                ->filter(fn (AssetValuation $item): bool => Carbon::parse($item->valued_on)->lt($asOf))
                ->sortByDesc(fn (AssetValuation $item): string => Carbon::parse($item->valued_on)->toDateString())
                ->first();
            if (! $valuation) {
                $missingAssetValuations[] = $asset->id;

                continue;
            }
            $value = (float) $valuation->value_egp;
            $assetValue += $value;
            if ($previous) {
                $valuationMovement += $value - (float) $previous->value_egp;
            }
            $breakdown[] = [
                'assetId' => $asset->id,
                'type' => $asset->type,
                'typeKey' => $asset->assetType?->key,
                'class' => $asset->assetType?->class,
                'value' => round($value, 2),
                'valuedOn' => Carbon::parse($valuation->valued_on)->toDateString(),
                'source' => $valuation->source,
            ];
        }
        $liabilities = Liability::query()->where('is_active', true)->with('balanceHistories')->get();
        $liabilityValue = 0.0;
        $liabilityBreakdown = [];
        $missingLiabilityHistories = [];
        foreach ($liabilities as $liability) {
            $history = $liability->balanceHistories
                ->filter(fn ($item): bool => Carbon::parse($item->as_of)->lte($asOf))
                ->sortByDesc(fn ($item): string => Carbon::parse($item->as_of)->toDateString())
                ->first();
            if (! $history) {
                $missingLiabilityHistories[] = $liability->id;

                continue;
            }
            $balance = (float) $history->balance_egp;
            $liabilityValue += $balance;
            $liabilityBreakdown[] = ['liabilityId' => $liability->id, 'name' => $liability->name, 'balance' => round($balance, 2), 'asOf' => Carbon::parse($history->as_of)->toDateString(), 'source' => $history->source];
        }
        $transactions = LedgerTransaction::confirmed()->whereDate('occurred_on', '<=', $asOf->toDateString())->get();

        $attribution = $this->attribution($transactions);
        $attribution['investmentMovement'] = round($valuationMovement, 2);
        $missingHistoricalSources = [
            'assetsWithoutValuation' => $missingAssetValuations,
            'liabilitiesWithoutBalanceHistory' => $missingLiabilityHistories,
        ];
        $attribution['historicalSourcesComplete'] = $missingHistoricalSources['assetsWithoutValuation'] === [] && $missingHistoricalSources['liabilitiesWithoutBalanceHistory'] === [];
        $attribution['missingHistoricalSources'] = $missingHistoricalSources;
        $attribution['netAttributedChange'] = round($attribution['contributions'] - $attribution['withdrawals'] + $attribution['dividends'] + $attribution['interest'] - $attribution['fees'] - $attribution['taxes'] + $attribution['investmentMovement'] + $attribution['fxMovement'] + $attribution['liabilityChange'] + $attribution['corrections'], 2);
        $hasHistoricalSource = $assets->isNotEmpty() || $liabilities->isNotEmpty();
        $status = $attribution['historicalSourcesComplete'] && $hasHistoricalSource ? 'confirmed' : 'incomplete';
        $limitations = [];
        foreach ($missingAssetValuations as $assetId) {
            $limitations[] = "Asset {$assetId} has no valuation dated on or before {$asOf->toDateString()}.";
        }
        foreach ($missingLiabilityHistories as $liabilityId) {
            $limitations[] = "Liability {$liabilityId} has no balance history dated on or before {$asOf->toDateString()}.";
        }

        return [
            'asOf' => $asOf->toDateString(),
            'totalAssets' => round($assetValue, 2),
            'liabilities' => round($liabilityValue, 2),
            'netWorth' => round($assetValue - $liabilityValue, 2),
            'assetBreakdown' => $breakdown,
            'liabilityBreakdown' => $liabilityBreakdown,
            'attribution' => $attribution,
            'source' => 'dated_valuations_liability_history_and_confirmed_ledger',
            'status' => $status,
            'missingHistoricalSources' => $missingHistoricalSources,
            'limitations' => $limitations,
        ];
    }

    /**
     * @param  Collection<int, LedgerTransaction>  $transactions
     * @return array<string, mixed>
     */
    public function attribution(Collection $transactions): array
    {
        $result = ['contributions' => 0.0, 'withdrawals' => 0.0, 'dividends' => 0.0, 'interest' => 0.0, 'fees' => 0.0, 'taxes' => 0.0, 'investmentMovement' => 0.0, 'fxMovement' => 0.0, 'liabilityChange' => 0.0, 'corrections' => 0.0];
        foreach ($transactions as $transaction) {
            $key = match ($transaction->transaction_type) {
                'contribution', 'investment' => 'contributions',
                'withdrawal' => 'withdrawals',
                'dividend' => 'dividends',
                'interest' => 'interest',
                'fee' => 'fees',
                'tax' => 'taxes',
                'correction' => 'corrections',
                default => null,
            };
            if ($key !== null) {
                $result[$key] = round($result[$key] + (float) $transaction->amount_egp, 2);
            }
        }

        return $result + ['netAttributedChange' => round($result['contributions'] - $result['withdrawals'] + $result['dividends'] + $result['interest'] - $result['fees'] - $result['taxes'] + $result['corrections'], 2)];
    }

    /** @return array<string, mixed> */
    public function reconciliation(?CarbonInterface $month = null): array
    {
        $month ??= now()->startOfMonth();
        $accounts = Account::query()->where('is_active', true)->get();
        $rows = $accounts->map(function (Account $account): array {
            $ledger = $account->ledgerBalance();
            $reported = $account->reported_balance_egp !== null ? (float) $account->reported_balance_egp : null;

            return ['id' => $account->id, 'name' => $account->name, 'currency' => $account->currency, 'ledgerBalance' => $ledger, 'reportedBalance' => $reported, 'difference' => $reported === null ? null : round($reported - $ledger, 2), 'status' => $reported === null ? 'needs_statement_balance' : (abs($reported - $ledger) < 0.01 ? 'reconciled' : 'difference')];
        })->values()->all();
        $summary = $this->summarizeMonth($month, false);
        $review = MonthlyFinancialReview::whereDate('month', $month->toDateString())->first();

        return [
            'month' => $month->format('Y-m'),
            'accounts' => $rows,
            'income' => ['expected' => 0.0, 'received' => $summary['income'], 'difference' => round(-$summary['income'], 2), 'status' => $summary['source'] === 'confirmed_ledger' ? 'reviewed' : 'incomplete'],
            'commitments' => ['expected' => round((float) RecurringCommitment::where('is_active', true)->get()->sum(fn (RecurringCommitment $item): float => $item->monthlyAmount()), 2), 'paid' => $summary['recurringCommitments'], 'status' => $summary['source'] === 'confirmed_ledger' ? 'reviewed' : 'incomplete'],
            'review' => ['status' => $review->status ?? 'missing', 'source' => $review->source_type ?? $summary['source'], 'transactionCount' => $summary['sourceTransactionCount']],
            'pendingImports' => ImportRow::where('review_state', 'pending')->count(),
            'status' => collect($rows)->contains(fn (array $row): bool => $row['status'] === 'difference') ? 'difference' : ($summary['source'] === 'confirmed_ledger' ? 'reconciled' : 'incomplete'),
        ];
    }

    /** @param array<string, mixed> $overrides */
    public function acceptImportRow(ImportRow $row, array $overrides = []): LedgerTransaction
    {
        return DB::transaction(function () use ($row, $overrides): LedgerTransaction {
            $row->refresh();
            if ($row->review_state !== 'pending') {
                throw ValidationException::withMessages(['row' => 'Only pending import rows can be accepted.']);
            }
            if ($row->duplicate_of_id !== null) {
                throw ValidationException::withMessages(['row' => 'Duplicate rows must be rejected or reviewed as a correction; they are never posted automatically.']);
            }
            if ($row->occurred_on === null || $row->amount === null || $row->amount <= 0) {
                throw ValidationException::withMessages(['row' => 'The imported row needs a valid date and positive amount before it can be posted.']);
            }
            if (strtoupper((string) $row->currency) !== 'EGP' && $row->exchange_rate === null && $row->amount_egp === null) {
                throw ValidationException::withMessages(['row' => 'A non-EGP imported row needs an explicit exchange rate or EGP amount before it can be posted.']);
            }
            $data = array_merge($row->only(['account_id', 'category_id', 'occurred_on', 'description', 'amount', 'currency', 'transaction_type', 'exchange_rate', 'amount_egp']), $overrides);
            ($this->reviewGuard ?? app(MonthlyReviewGuard::class))->assertEditable($data['occurred_on']);
            $data['amount_egp'] = $data['amount_egp'] ?? round((float) $data['amount'] * (float) ($data['exchange_rate'] ?? 1), 2);
            $data['review_state'] = 'confirmed';
            $data['source'] = 'csv_import';
            $data['import_batch_id'] = $row->import_batch_id;
            $data['import_row_id'] = $row->id;
            $data['fingerprint'] = $row->fingerprint;
            $data['reviewed_at'] = now();
            $transaction = LedgerTransaction::create($data);
            $row->update(['review_state' => 'accepted', 'transaction_id' => $transaction->id, 'reviewed_at' => now()]);
            $row->batch?->refreshCounts();

            return $transaction;
        });
    }
}
