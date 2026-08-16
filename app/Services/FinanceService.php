<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AllocationPlan;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Bucket;
use App\Models\CashFlow;
use App\Models\DecisionJournalEntry;
use App\Models\FinancialSetting;
use App\Models\FxRate;
use App\Models\Goal;
use App\Models\ImportBatch;
use App\Models\IntegrityCheck;
use App\Models\LedgerTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalanceHistory;
use App\Models\MonthlyFinancialReview;
use App\Models\RecurringCommitment;
use App\Models\Snapshot;
use App\Models\TransactionCategory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class FinanceService
{
    public const TARGET_ALLOCATION = [
        'Gold' => 20,
        'USD' => 25,
        'Egyptian equities' => 25,
        'Fixed income' => 20,
        'Cash' => 10,
    ];

    private readonly LiquidityPolicy $liquidityPolicy;

    private readonly LedgerService $ledgerService;

    public function __construct(?LiquidityPolicy $liquidityPolicy = null, ?LedgerService $ledgerService = null)
    {
        $this->liquidityPolicy = $liquidityPolicy ?? new LiquidityPolicy;
        $this->ledgerService = $ledgerService ?? new LedgerService;
    }

    /** @return array<string, mixed> */
    public function dashboard(?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        if (! $asOf->isToday()) {
            throw new InvalidArgumentException('Historical dashboard calculations are not supported until dated valuation and ledger records exist.');
        }
        $monthStart = $asOf->copy()->startOfMonth();
        $assets = Asset::with(['buckets', 'valuations'])->orderByDesc('current_value_egp')->get();
        $buckets = Bucket::with('goal')->get();
        $goals = Goal::with('buckets')->where('status', 'active')->orderBy('priority')->get();
        $flows = CashFlow::whereBetween('occurred_on', [$monthStart, $asOf])->get();
        $ledgerSummary = $this->ledgerService->summarizeMonth($monthStart, true);
        $review = MonthlyFinancialReview::whereDate('month', $monthStart)->first();
        $useManualReview = $review !== null && $ledgerSummary['source'] !== 'confirmed_ledger';
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $liabilities = Liability::where('is_active', true)->orderByDesc('balance_egp')->get();
        $decisions = DecisionJournalEntry::query()->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('review_date')->limit(10)->get();
        $latestBackup = Backup::query()->latest()->first();
        $latestIntegrityCheck = IntegrityCheck::query()->latest()->first();

        $totalLiabilities = $this->sumMoney($liabilities, fn (Liability $liability): string => (string) $liability->balance_egp);
        $totalAssetValue = $this->sumMoney($assets, fn (Asset $asset): string => (string) $asset->current_value_egp);
        $netWorth = round($totalAssetValue - $totalLiabilities, 2);
        $availability = $this->liquidityPolicy->availability($assets);
        $reservedForGoals = (float) $this->goalBuckets($buckets)->sum(function (Bucket $bucket) {
            return $this->bucketValue($bucket);
        });
        $income = $useManualReview ? (float) $review->income_egp : (float) $ledgerSummary['income'];
        $expenses = $useManualReview
            ? $this->reviewExpenses($review)
            : round((float) $ledgerSummary['essentialExpenses'] + (float) $ledgerSummary['lifestyleExpenses'] + (float) $ledgerSummary['recurringCommitments'] + (float) $ledgerSummary['oneTimeExpenses'] + (float) $ledgerSummary['debtPayments'], 2);
        $essentialExpenses = $useManualReview
            ? (float) $review->essential_expenses_egp + (float) $review->recurring_commitments_egp + (float) $review->debt_payments_egp
            : round((float) $ledgerSummary['essentialExpenses'] + (float) $ledgerSummary['recurringCommitments'] + (float) $ledgerSummary['debtPayments'], 2);
        $policy = $this->liquidityPolicy->payload();
        $sourceStatus = $this->sourceStatus($review, $assets, $asOf);
        $emergency = $this->liquidityPolicy->emergencyEligibleAmount(
            $buckets,
            (string) $policy['emergencyEligibleLiquidity'],
        );
        $monthlyBase = $essentialExpenses ?: $expenses;
        $invested = $useManualReview ? (float) $review->invested_egp : (float) $ledgerSummary['invested'];
        $recurringMonthly = $useManualReview
            ? (float) $review->recurring_commitments_egp
            : ($ledgerSummary['source'] === 'confirmed_ledger'
                ? (float) $ledgerSummary['recurringCommitments']
                : (float) $commitments->sum(fn (RecurringCommitment $commitment) => $commitment->monthlyAmount()));

        $allocatedToGoals = $this->sumMoney($this->goalBuckets($buckets), fn (Bucket $bucket): string => (string) $this->bucketValue($bucket));
        $allocatedToNonGoals = $this->sumMoney($buckets->filter(fn (Bucket $bucket): bool => $bucket->goal_id === null), fn (Bucket $bucket): string => (string) $this->bucketValue($bucket));
        $allocated = $allocatedToGoals + $allocatedToNonGoals;
        $goalPayloads = $goals->map(fn (Goal $goal) => $this->goalPayload($goal, $asOf))->values();
        $remainingGoalCashFlow = max(0, $income - $expenses);
        $goalPayloads = $goalPayloads->map(function (array $goal) use (&$remainingGoalCashFlow, $policy): array {
            $required = (float) $goal['requiredMonthlyContribution'];
            $planned = ($policy['goalFundingPolicy'] ?? 'priority_order') === 'manual_contributions' && $goal['plannedMonthlyContribution'] !== null
                ? (float) $goal['plannedMonthlyContribution']
                : $required;
            $goal['assignedMonthlyContribution'] = round(min($planned, $remainingGoalCashFlow), 2);
            // Feasibility is portfolio-wide: once a higher-priority goal is
            // funded, the same EGP cannot also fund every later goal.
            $goal['onTrack'] = $planned >= $required && $planned <= $remainingGoalCashFlow;
            $goal['feasibilityStatus'] = $goal['onTrack'] ? 'funded_in_priority_order' : 'funding_conflict';
            $goal['gapPerMonth'] = max(0, round($required - $goal['assignedMonthlyContribution'], 2));
            $remainingGoalCashFlow = max(0, $remainingGoalCashFlow - $planned);

            return $goal;
        });
        $attentionSummary = [
            'emergencyCoverageMonths' => $emergency > 0 && $monthlyBase > 0 ? round($emergency / $monthlyBase, 1) : 0,
            'emergencyReserveMonths' => (int) $policy['emergencyReserveMonths'],
            'unallocated' => max(0, round($availability['totalAssets'] - $allocated, 2)),
            'freeCashFlow' => $income - $expenses,
        ];

        return [
            'asOf' => $asOf->toDateString(),
            'dataFreshness' => [
                'calculationAsOf' => $asOf->toDateString(),
                'source' => $useManualReview ? 'monthly_review' : $ledgerSummary['source'],
                'status' => $useManualReview ? $review->status : ($ledgerSummary['source'] === 'confirmed_ledger' ? 'confirmed' : 'incomplete'),
                'lastUpdated' => $this->latestUpdate($review, $assets),
                'cashFlowSource' => $useManualReview ? ($review->status === 'closed' ? 'closed_review' : 'open_review') : $ledgerSummary['source'],
                'valuationFreshness' => $sourceStatus['valuation'],
                'reviewStatus' => $useManualReview ? $review->status : ($ledgerSummary['source'] === 'confirmed_ledger' ? 'derived' : 'missing'),
                'demoDataWarning' => $this->hasDemoData($assets, $review),
                'backup' => $latestBackup ? ['status' => $latestBackup->status, 'createdAt' => $latestBackup->created_at?->toIso8601String(), 'verifiedAt' => $latestBackup->verified_at?->toIso8601String()] : ['status' => 'missing'],
                'integrity' => $latestIntegrityCheck ? ['status' => $latestIntegrityCheck->status, 'createdAt' => $latestIntegrityCheck->created_at?->toIso8601String(), 'errorCount' => $latestIntegrityCheck->error_count] : ['status' => 'not_run'],
                'limitations' => ['Current-state asset values are not historical valuations.'],
            ],
            'data_freshness' => [
                'calculation_as_of' => $asOf->toDateString(),
                'source' => $useManualReview ? 'monthly_review' : $ledgerSummary['source'],
                'status' => $useManualReview ? $review->status : ($ledgerSummary['source'] === 'confirmed_ledger' ? 'confirmed' : 'incomplete'),
                'last_updated' => $this->latestUpdate($review, $assets),
                'cash_flow_source' => $useManualReview ? ($review->status === 'closed' ? 'closed_review' : 'open_review') : $ledgerSummary['source'],
                'valuation_freshness' => $sourceStatus['valuation'],
                'review_status' => $useManualReview ? $review->status : ($ledgerSummary['source'] === 'confirmed_ledger' ? 'derived' : 'missing'),
                'demo_data_warning' => $this->hasDemoData($assets, $review),
                'backup' => $latestBackup ? ['status' => $latestBackup->status, 'created_at' => $latestBackup->created_at?->toIso8601String(), 'verified_at' => $latestBackup->verified_at?->toIso8601String()] : ['status' => 'missing'],
                'integrity' => $latestIntegrityCheck ? ['status' => $latestIntegrityCheck->status, 'created_at' => $latestIntegrityCheck->created_at?->toIso8601String(), 'error_count' => $latestIntegrityCheck->error_count] : ['status' => 'not_run'],
                'limitations' => ['Current-state asset values are not historical valuations.'],
            ],
            'limitations' => ['Current-state asset values are not historical valuations.'],
            'policy' => $policy,
            'sourceStatus' => $sourceStatus,
            'figureSources' => [
                'netWorth' => ['status' => 'manual_value', 'source' => 'assets.current_value_egp − liabilities.balance_egp'],
                'cashSafety' => ['status' => 'manual_value', 'source' => 'assets.liquidity + emergency purpose allocations'],
                'monthlyCashFlow' => ['status' => $useManualReview ? ($review->status === 'closed' ? 'closed_review' : 'current_estimate') : ($ledgerSummary['source'] === 'confirmed_ledger' ? 'confirmed' : 'needs_review'), 'source' => $useManualReview ? 'monthly_financial_reviews' : $ledgerSummary['source']],
                'goals' => ['status' => 'current_estimate', 'source' => 'goals + purpose buckets + monthly cash flow'],
                'allocation' => ['status' => 'configured_policy', 'source' => 'financial_settings + assets'],
            ],
            'summary' => [
                'netWorth' => $netWorth,
                'investableNetWorth' => max(0, $netWorth - $reservedForGoals),
                // Keep liquidAssets as a compatibility alias for the safe, three-day tier.
                'liquidAssets' => $availability['availableWithinThreeDays'],
                'availableNow' => $availability['availableNow'],
                'availableWithinThreeDays' => $availability['availableWithinThreeDays'],
                'totalAssets' => $availability['totalAssets'],
                'liabilities' => $totalLiabilities,
                'reservedForGoals' => $reservedForGoals,
                'allocatedToGoals' => $allocatedToGoals,
                'allocatedToNonGoals' => $allocatedToNonGoals,
                'unallocated' => max(0, round($availability['totalAssets'] - $allocated, 2)),
                'income' => $income,
                'expenses' => $expenses,
                'freeCashFlow' => $income - $expenses,
                'emergencyFund' => $emergency,
                'emergencyCoverageMonths' => $monthlyBase > 0 ? round($emergency / $monthlyBase, 1) : 0,
                'emergencyReserveMonths' => (int) $policy['emergencyReserveMonths'],
                'emergencyEligibleLiquidity' => $policy['emergencyEligibleLiquidity'],
                'investedThisMonth' => $invested,
                'recurringCommitments' => $recurringMonthly,
                'savingsRate' => $income > 0 ? round(($income - $expenses) / $income * 100, 1) : 0,
                'investmentRate' => $income > 0 ? round($invested / $income * 100, 1) : 0,
            ],
            'assets' => $assets->map(fn (Asset $asset) => $this->assetPayload($asset))->values(),
            'assetAllocation' => $this->allocation($assets),
            'currencyExposure' => $this->currencyExposure($assets),
            'liquidity' => $this->liquidity($assets),
            'buckets' => $buckets->map(fn (Bucket $bucket) => [
                'id' => $bucket->id,
                'name' => $bucket->name,
                'purpose' => $bucket->purpose,
                'color' => $bucket->color,
                'goalId' => $bucket->goal_id,
                'goalName' => $bucket->goal?->name,
                'targetAmount' => (float) $bucket->target_amount_egp,
                'currentAmount' => $this->bucketValue($bucket),
            ])->values(),
            'goals' => $goalPayloads,
            'liabilities' => $liabilities->map(fn (Liability $liability) => $this->liabilityPayload($liability))->values(),
            'recurringCommitments' => $commitments->map(fn (RecurringCommitment $commitment) => $this->commitmentPayload($commitment))->values(),
            'monthlyReview' => $this->monthlyReviewPayload($review, $monthStart, $flows, $commitments, $ledgerSummary),
            'reconciliation' => $this->ledgerService->reconciliation($monthStart),
            'wealthMetrics' => $this->wealthMetrics($income, $expenses, $invested, $totalLiabilities, $netWorth),
            'wealthTrend' => $this->wealthTrend($asOf, $netWorth, max(0, $netWorth - $reservedForGoals)),
            // Keep the legacy targetAllocation map for existing consumers,
            // while exposing configured ranges and tolerance for decisions.
            'targetAllocation' => $this->targetAllocation($policy),
            'allocationPolicy' => $this->allocationPolicy($assets, $policy),
            'debtSummary' => $this->debtSummary($liabilities, $commitments),
            'attentionQueue' => $this->attentionQueue($assets, $goalPayloads, $attentionSummary, $policy, $liabilities),
            'decisionJournal' => $decisions->map(fn (DecisionJournalEntry $entry): array => $this->decisionPayload($entry))->values(),
            'insights' => $this->insights($assets, $goals, $buckets, $income - $expenses, $emergency, $monthlyBase, (int) $policy['emergencyReserveMonths']),
        ];
    }

    /** @return array<string, mixed> */
    public function exportContext(?CarbonInterface $asOf = null, string $scope = 'full_financial_context'): array
    {
        $data = $this->dashboard($asOf);
        $flows = CashFlow::orderByDesc('occurred_on')->limit(500)->get()->map(fn (CashFlow $flow): array => [
            'id' => $flow->id,
            'type' => $flow->type,
            'category' => $flow->category,
            'amount' => (float) $flow->amount_egp,
            'occurredOn' => Carbon::parse($flow->occurred_on)->toDateString(),
            'notes' => $flow->notes,
        ])->values()->all();
        $transactions = LedgerTransaction::with(['account', 'counterAccount', 'category', 'splits'])->orderByDesc('occurred_on')->limit(1000)->get()->map(fn (LedgerTransaction $transaction): array => [
            'id' => $transaction->id,
            'accountId' => $transaction->account_id,
            'counterAccountId' => $transaction->counter_account_id,
            'categoryId' => $transaction->category_id,
            'type' => $transaction->transaction_type,
            'occurredOn' => Carbon::parse($transaction->occurred_on)->toDateString(),
            'description' => $transaction->description,
            'amount' => (float) $transaction->amount,
            'currency' => $transaction->currency,
            'amountEgp' => (float) $transaction->amount_egp,
            'reviewState' => $transaction->review_state,
            'source' => $transaction->source,
            'fingerprint' => $transaction->fingerprint,
            'transferGroupId' => $transaction->transfer_group_id,
            'notes' => $transaction->notes,
            'archived' => $transaction->trashed(),
        ])->values()->all();
        $accounts = Account::withTrashed()->orderBy('name')->get()->map(fn (Account $account): array => $account->toArray() + ['ledger_balance_egp' => $account->ledgerBalance()])->values()->all();
        $valuations = AssetValuation::withTrashed()->orderByDesc('valued_on')->limit(1000)->get()->map(fn (AssetValuation $valuation): array => $valuation->toArray())->values()->all();
        $fxRates = FxRate::withTrashed()->orderByDesc('rate_date')->limit(1000)->get()->map(fn (FxRate $rate): array => $rate->toArray())->values()->all();
        $liabilityBalanceHistories = LiabilityBalanceHistory::withTrashed()->with('liability')->orderByDesc('as_of')->limit(1000)->get()->map(fn (LiabilityBalanceHistory $history): array => $history->toArray())->values()->all();
        $imports = ImportBatch::withTrashed()->with('rows')->latest()->limit(100)->get()->map(fn (ImportBatch $batch): array => $batch->toArray() + ['rows' => $batch->rows->toArray()])->values()->all();
        $categories = TransactionCategory::withTrashed()->orderBy('kind')->orderBy('name')->get()->map(fn (TransactionCategory $category): array => $category->toArray())->values()->all();
        $snapshots = Snapshot::orderByDesc('as_of')->limit(120)->get()->map(fn (Snapshot $snapshot): array => [
            'id' => $snapshot->id,
            'asOf' => Carbon::parse($snapshot->as_of)->toDateString(),
            'netWorth' => (float) $snapshot->net_worth_egp,
            'captureBasis' => $snapshot->capture_basis,
            'historicalSource' => $snapshot->historical_source ?? $snapshot->capture_basis,
            'changeAttribution' => $snapshot->change_attribution,
            'capturedAt' => $snapshot->captured_at ? Carbon::parse($snapshot->captured_at)->toIso8601String() : null,
        ])->values()->all();
        $allocationPlans = AllocationPlan::with('items')->orderByDesc('month')->limit(24)->get()->map(fn (AllocationPlan $plan): array => $plan->toArray())->values()->all();
        $settings = FinancialSetting::withTrashed()->orderByDesc('id')->get()->map(fn (FinancialSetting $setting): array => $setting->toArray())->values()->all();
        $auditReferences = AuditLog::latest()->limit(200)->get(['id', 'action', 'entity_type', 'entity_id', 'tool_name', 'dashboard_version', 'created_at'])->toArray();

        return [
            'schema_version' => 'phase2.v1',
            'generated_at' => now()->toIso8601String(),
            'as_of' => $data['asOf'],
            'base_currency' => $data['policy']['baseCurrency'] ?? 'EGP',
            'data_freshness' => $data['dataFreshness'],
            'limitations' => $data['dataFreshness']['limitations'],
            'policy' => $data['policy'],
            'dashboard_version' => $this->dashboardVersion($data),
            'monthly' => [
                'income_egp' => $data['summary']['income'],
                'expenses_egp' => $data['summary']['expenses'],
                'free_cash_flow_egp' => $data['summary']['freeCashFlow'],
            ],
            'summary' => $data['summary'],
            'source_status' => $data['sourceStatus'],
            'attention_queue' => $data['attentionQueue'],
            'allocation_policy' => $data['allocationPolicy'],
            'debt_summary' => $data['debtSummary'],
            'reconciliation' => $data['reconciliation'],
            'assets' => $data['assets']->all(),
            'accounts' => $accounts,
            'valuations' => $valuations,
            'fx_rates' => $fxRates,
            'liability_balance_histories' => $liabilityBalanceHistories,
            'goals' => $data['goals']->all(),
            'buckets' => $data['buckets']->all(),
            'allocations' => $data['assets']->map(fn (array $asset): array => ['asset_id' => $asset['id'], 'allocations' => $asset['bucketAllocations'] ?? []])->values()->all(),
            'liabilities' => $data['liabilities']->all(),
            'recurring_commitments' => $data['recurringCommitments']->all(),
            'monthly_review' => $data['monthlyReview'],
            'wealth_metrics' => $data['wealthMetrics'],
            'wealth_trend' => $data['wealthTrend'],
            'asset_allocation' => $data['assetAllocation'],
            'currency_exposure' => $data['currencyExposure'],
            'liquidity' => $data['liquidity'],
            'insights' => $data['insights'],
            'cash_flows' => $flows,
            'transactions' => $transactions,
            'transaction_categories' => $categories,
            'imports' => $imports,
            'snapshots' => $snapshots,
            'allocation_plans' => $allocationPlans,
            'scenarios' => [],
            'financial_settings' => $settings,
            'audit_references' => $auditReferences,
            'decision_journal' => DecisionJournalEntry::withTrashed()->latest()->limit(200)->get()->map(fn (DecisionJournalEntry $entry): array => $this->decisionPayload($entry))->values()->all(),
        ];
    }

    public function bucketValue(Bucket $bucket): float
    {
        return $this->sumMoney($bucket->assets, fn (Asset $asset): string => (string) data_get($asset, 'pivot.amount_egp', '0'));
    }

    /** @return array<string, mixed> */
    public function monthlyReview(CarbonInterface $month): array
    {
        $month = $month->copy()->startOfMonth();
        $flows = CashFlow::whereBetween('occurred_on', [$month, $month->copy()->endOfMonth()])->get();
        $ledgerSummary = $this->ledgerService->summarizeMonth($month, true);
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $review = MonthlyFinancialReview::whereDate('month', $month)->first();

        return $this->monthlyReviewPayload($review, $month, $flows, $commitments, $ledgerSummary);
    }

    /** @return array<string, mixed> */
    public function commitmentPayloadForAgent(RecurringCommitment $commitment): array
    {
        return $this->commitmentPayload($commitment);
    }

    /** @return array<string, mixed> */
    public function liabilityPayloadForAgent(Liability $liability): array
    {
        return $this->liabilityPayload($liability);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function purchaseAnalysis(array $inputs, ?CarbonInterface $asOf = null): array
    {
        $dashboard = $this->dashboard($asOf);
        $price = max(0, (float) ($inputs['price'] ?? 0));
        $mode = ($inputs['mode'] ?? 'cash') === 'finance' ? 'finance' : 'cash';
        $downPayment = $mode === 'cash' ? $price : min($price, max(0, (float) ($inputs['down_payment'] ?? 0)));
        $principal = max(0, $price - $downPayment);
        $months = max(1, (int) ($inputs['tenure'] ?? 36));
        $annualInterest = max(0, (float) ($inputs['interest_rate'] ?? 0));
        $monthlyRate = $annualInterest / 100 / 12;
        $payment = $principal === 0 ? 0 : ($monthlyRate === 0
            ? $principal / $months
            : ($principal * $monthlyRate * (1 + $monthlyRate) ** $months) / ((1 + $monthlyRate) ** $months - 1));
        $totalFinancing = $payment * $months;
        $afterPurchaseLiquid = max(0, (float) $dashboard['summary']['availableNow'] - $downPayment);
        $existingMonthlyExpenses = max(1, (float) $dashboard['summary']['expenses']);
        $coverage = $afterPurchaseLiquid / $existingMonthlyExpenses;
        $afterPurchaseFreeCashFlow = array_key_exists('monthly_savings_after_purchase', $inputs) || array_key_exists('monthly_savings', $inputs)
            ? (float) ($inputs['monthly_savings_after_purchase'] ?? $inputs['monthly_savings'])
            : (float) $dashboard['summary']['freeCashFlow'] - $payment;
        $reserveMonths = max(1, (int) ($dashboard['summary']['emergencyReserveMonths'] ?? 6));
        $minimumCash = (float) ($dashboard['policy']['minimumCashAfterPurchase'] ?? 0);
        $maximumPayment = $dashboard['policy']['maximumMonthlyPayment'];
        $paymentPasses = $maximumPayment === null || $payment <= (float) $maximumPayment;
        $cashPasses = $afterPurchaseLiquid >= $minimumCash;
        $reservePasses = $coverage >= $reserveMonths;
        $cashFlowPasses = $afterPurchaseFreeCashFlow >= 0;
        $maximumDebtBurden = $dashboard['policy']['maximumDebtBurdenPercent'] ?? null;
        $monthlyRequiredPayments = (float) ($dashboard['debtSummary']['monthlyRequiredPayments'] ?? 0);
        $income = max(0, (float) $dashboard['summary']['income']);
        $debtBurden = $income > 0 ? round(($monthlyRequiredPayments + $payment) / $income * 100, 1) : 0;
        $debtBurdenPasses = $maximumDebtBurden === null || $debtBurden <= (float) $maximumDebtBurden;
        $confidence = $reservePasses && $cashFlowPasses && $paymentPasses && $cashPasses && $debtBurdenPasses ? 'passes_configured_checks' : 'needs_review';

        return [
            'price' => $price,
            'mode' => $mode,
            'downPayment' => $downPayment,
            'principal' => $principal,
            'monthlyPayment' => round($payment, 2),
            'totalInterest' => round(max(0, $totalFinancing - $principal), 2),
            'afterPurchaseLiquid' => round($afterPurchaseLiquid, 2),
            'emergencyCoverageMonths' => round($coverage, 1),
            'afterPurchaseFreeCashFlow' => round($afterPurchaseFreeCashFlow, 2),
            'confidence' => $confidence,
            'ruleResult' => [
                'passes' => $reservePasses && $cashFlowPasses && $paymentPasses && $cashPasses,
                'minimumCashAfterPurchase' => $minimumCash,
                'maximumMonthlyPayment' => $maximumPayment,
                'maximumDebtBurdenPercent' => $maximumDebtBurden,
                'debtBurdenPercent' => $debtBurden,
                'reserveMonths' => $reserveMonths,
                'sources' => ['availableNow' => 'liquidity policy', 'freeCashFlow' => $dashboard['dataFreshness']['source']],
            ],
            'warnings' => array_values(array_filter([
                $coverage < $reserveMonths ? "The purchase would leave less than {$reserveMonths} months of current expenses in the immediate liquidity tier." : null,
                $afterPurchaseFreeCashFlow < 0 ? 'The monthly payment would exceed current free cash flow.' : null,
                ! $cashPasses ? "The purchase would leave less than {$minimumCash} EGP of immediate cash." : null,
                ! $paymentPasses ? "The monthly payment exceeds your configured maximum of {$maximumPayment} EGP." : null,
                ! $debtBurdenPasses ? "The resulting debt burden is {$debtBurden}%, above your configured maximum of {$maximumDebtBurden}%." : null,
                $mode === 'finance' && $annualInterest > 0 ? 'Add insurance, maintenance, fees, and depreciation before deciding.' : null,
            ])),
        ];
    }

    /**
     * @param  Collection<int, CashFlow>  $flows
     * @param  Collection<int, RecurringCommitment>  $commitments
     * @param  array<string, mixed>|null  $ledgerSummary
     * @return array<string, mixed>
     */
    private function monthlyReviewPayload(?MonthlyFinancialReview $review, CarbonInterface $month, Collection $flows, Collection $commitments, ?array $ledgerSummary = null): array
    {
        if ($review && ($ledgerSummary === null || $ledgerSummary['source'] !== 'confirmed_ledger')) {
            return [
                'id' => $review->id,
                'month' => $month->toDateString(),
                'income' => (float) $review->income_egp,
                'essentialExpenses' => (float) $review->essential_expenses_egp,
                'lifestyleExpenses' => (float) $review->lifestyle_expenses_egp,
                'recurringCommitments' => (float) $review->recurring_commitments_egp,
                'oneTimeExpenses' => (float) $review->one_time_expenses_egp,
                'debtPayments' => (float) $review->debt_payments_egp,
                'invested' => (float) $review->invested_egp,
                'manualAdjustment' => (float) $review->manual_adjustment_egp,
                'expenses' => $this->reviewExpenses($review),
                'notes' => $review->notes,
                'status' => $review->status,
                'source' => 'review',
            ];
        }

        $ledgerSummary ??= $this->ledgerService->summarizeMonth($month, true);
        if ($ledgerSummary['source'] === 'confirmed_ledger') {
            return [
                'id' => null,
                'month' => $month->toDateString(),
                'income' => $ledgerSummary['income'],
                'expenses' => round($ledgerSummary['essentialExpenses'] + $ledgerSummary['lifestyleExpenses'] + $ledgerSummary['recurringCommitments'] + $ledgerSummary['oneTimeExpenses'] + $ledgerSummary['debtPayments'], 2),
                'essentialExpenses' => $ledgerSummary['essentialExpenses'],
                'lifestyleExpenses' => $ledgerSummary['lifestyleExpenses'],
                'recurringCommitments' => $ledgerSummary['recurringCommitments'],
                'oneTimeExpenses' => $ledgerSummary['oneTimeExpenses'],
                'debtPayments' => $ledgerSummary['debtPayments'],
                'invested' => $ledgerSummary['invested'],
                'notes' => $review?->notes,
                'status' => $review ? $review->status : 'open',
                'source' => 'confirmed_ledger',
                'sourceTransactionCount' => $ledgerSummary['sourceTransactionCount'],
            ];
        }
        $income = (float) $flows->where('type', 'income')->sum('amount_egp');
        // A fallback review is built from actual entries only. Commitments are
        // projections and must not be added to an actual obligation entry.
        $expenses = (float) $flows->whereIn('type', ['expense', 'obligation'])->sum('amount_egp');

        return [
            'id' => null,
            'month' => $month->toDateString(),
            'income' => $income,
            'expenses' => $expenses,
            'essentialExpenses' => (float) $flows->where('type', 'expense')->where('category', 'essential')->sum('amount_egp'),
            'lifestyleExpenses' => max(0, (float) $flows->where('type', 'expense')->sum('amount_egp') - (float) $flows->where('type', 'expense')->where('category', 'essential')->sum('amount_egp')),
            'recurringCommitments' => 0,
            'oneTimeExpenses' => 0,
            'debtPayments' => (float) $flows->where('type', 'obligation')->sum('amount_egp'),
            'invested' => 0,
            'manualAdjustment' => 0,
            'notes' => null,
            'status' => 'open',
            'source' => 'cash_flow_fallback',
        ];
    }

    private function reviewExpenses(MonthlyFinancialReview $review): float
    {
        return (float) $review->essential_expenses_egp
            + (float) $review->lifestyle_expenses_egp
            + (float) $review->recurring_commitments_egp
            + (float) $review->one_time_expenses_egp
            + (float) $review->debt_payments_egp
            + (float) $review->manual_adjustment_egp;
    }

    /** @return array<string, mixed> */
    private function commitmentPayload(RecurringCommitment $commitment): array
    {
        return [
            'id' => $commitment->id,
            'name' => $commitment->name,
            'category' => $commitment->category,
            'amount' => (float) $commitment->amount_egp,
            'frequency' => $commitment->frequency,
            'monthlyAmount' => round($commitment->monthlyAmount(), 2),
            'annualAmount' => round($commitment->annualAmount(), 2),
            'nextDueOn' => $commitment->next_due_on ? Carbon::parse($commitment->next_due_on)->toDateString() : null,
            'renewalOn' => $commitment->renewal_on ? Carbon::parse($commitment->renewal_on)->toDateString() : null,
            'isActive' => $commitment->is_active,
            'notes' => $commitment->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function liabilityPayload(Liability $liability): array
    {
        return [
            'id' => $liability->id,
            'name' => $liability->name,
            'type' => $liability->type,
            'balance' => (float) $liability->balance_egp,
            'originalBalance' => $liability->original_balance_egp !== null ? (float) $liability->original_balance_egp : null,
            'interestRate' => $liability->interest_rate_percent !== null ? (float) $liability->interest_rate_percent : null,
            'monthlyPayment' => (float) $liability->monthly_payment_egp,
            'dueDay' => $liability->due_day,
            'payoffOn' => $liability->payoff_on ? Carbon::parse($liability->payoff_on)->toDateString() : null,
            'isActive' => $liability->is_active,
            'notes' => $liability->notes,
        ];
    }

    /** @return array<string, float> */
    private function wealthMetrics(float $income, float $expenses, float $invested, float $liabilities, float $netWorth): array
    {
        return [
            'savingsRate' => $income > 0 ? round(($income - $expenses) / $income * 100, 1) : 0,
            'investmentRate' => $income > 0 ? round($invested / $income * 100, 1) : 0,
            'monthlyWealthContribution' => round($invested, 2),
            'debtToNetWorth' => $netWorth > 0 ? round($liabilities / $netWorth * 100, 1) : 0,
            'committedIncomeRate' => $income > 0 ? round(($expenses / $income) * 100, 1) : 0,
        ];
    }

    /** @return list<array{asOf: string, netWorth: float, investableNetWorth: float}> */
    private function wealthTrend(CarbonInterface $asOf, float $currentNetWorth, float $currentInvestableNetWorth): array
    {
        $snapshots = Snapshot::whereDate('as_of', '<=', $asOf)->orderByDesc('as_of')->limit(12)->get()->sortBy('as_of')->values();
        $trend = [];
        foreach ($snapshots as $snapshot) {
            $trend[] = [
                'asOf' => Carbon::parse($snapshot->as_of)->toDateString(),
                'netWorth' => (float) $snapshot->net_worth_egp,
                'investableNetWorth' => (float) $snapshot->investable_net_worth_egp,
                'changeAttribution' => $snapshot->change_attribution ?? null,
                'historicalSource' => $snapshot->historical_source ?? $snapshot->capture_basis,
            ];
        }

        foreach (AssetValuation::query()->whereDate('valued_on', '<=', $asOf->toDateString())->distinct()->pluck('valued_on') as $valuedOn) {
            $date = Carbon::parse($valuedOn)->toDateString();
            if (collect($trend)->contains('asOf', $date)) {
                continue;
            }
            $projection = $this->ledgerService->snapshotAt(Carbon::parse($date));
            if ($projection['status'] === 'confirmed') {
                $trend[] = ['asOf' => $date, 'netWorth' => $projection['netWorth'], 'investableNetWorth' => $projection['netWorth'], 'changeAttribution' => $projection['attribution'], 'historicalSource' => 'dated_ledger'];
            }
        }
        usort($trend, fn (array $left, array $right): int => strcmp($left['asOf'], $right['asOf']));

        $last = $trend[count($trend) - 1] ?? null;
        if ($last === null || $last['asOf'] !== $asOf->toDateString()) {
            $trend[] = ['asOf' => $asOf->toDateString(), 'netWorth' => $currentNetWorth, 'investableNetWorth' => $currentInvestableNetWorth, 'changeAttribution' => null, 'historicalSource' => 'current_projection'];
        }

        return $trend;
    }

    /**
     * @param  Collection<int, Bucket>  $buckets
     * @return Collection<int, Bucket>
     */
    private function goalBuckets(Collection $buckets): Collection
    {
        return $buckets->filter(fn (Bucket $bucket) => $bucket->goal_id !== null);
    }

    /** @return array<string, mixed> */
    private function goalPayload(Goal $goal, CarbonInterface $asOf): array
    {
        $allocated = (float) $goal->buckets->sum(fn (Bucket $bucket) => $this->bucketValue($bucket));
        $remaining = max(0, (float) $goal->target_amount_egp - $allocated);
        $months = $goal->deadline ? round(max(0, $asOf->diffInMonths($goal->deadline, false)), 1) : null;
        $required = $months && $months > 0 ? $remaining / $months : $remaining;
        $review = MonthlyFinancialReview::whereDate('month', $asOf->copy()->startOfMonth())->first();
        $ledgerSummary = $this->ledgerService->summarizeMonth($asOf->copy()->startOfMonth(), true);
        $monthlyFreeCashFlow = $review && $ledgerSummary['source'] !== 'confirmed_ledger'
            ? (float) $review->income_egp - $this->reviewExpenses($review)
            : (float) $ledgerSummary['income'] - round((float) $ledgerSummary['essentialExpenses'] + (float) $ledgerSummary['lifestyleExpenses'] + (float) $ledgerSummary['recurringCommitments'] + (float) $ledgerSummary['oneTimeExpenses'] + (float) $ledgerSummary['debtPayments'], 2);

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'status' => $goal->status,
            'priority' => $goal->priority,
            'notes' => $goal->notes,
            'targetAmount' => (float) $goal->target_amount_egp,
            'allocatedAmount' => $allocated,
            'remainingAmount' => $remaining,
            'deadline' => $goal->deadline ? Carbon::parse($goal->deadline)->toDateString() : null,
            'monthsRemaining' => $months,
            'requiredMonthlyContribution' => round($required, 2),
            'plannedMonthlyContribution' => $goal->monthly_contribution_egp !== null ? (float) $goal->monthly_contribution_egp : null,
            'fundingPercent' => $goal->target_amount_egp > 0 ? round($allocated / (float) $goal->target_amount_egp * 100, 1) : 0,
            'onTrack' => $required <= $monthlyFreeCashFlow,
            'gapPerMonth' => max(0, round($required - $monthlyFreeCashFlow, 2)),
        ];
    }

    /** @return array<string, mixed> */
    public function assetPayload(Asset $asset): array
    {
        $latestValuation = $asset->relationLoaded('valuations')
            ? $asset->valuations->sortByDesc('valued_on')->first()
            : $asset->valuations()->latest('valued_on')->first();

        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'type' => $asset->type,
            'quantity' => $asset->quantity !== null ? (float) $asset->quantity : null,
            'currency' => $asset->currency,
            'costBasis' => (float) $asset->cost_basis_egp,
            'currentValue' => (float) $asset->current_value_egp,
            'unitPrice' => $asset->unit_price_egp !== null ? (float) $asset->unit_price_egp : null,
            'liquidity' => $asset->liquidity,
            'isLiquid' => $this->liquidityPolicy->allows((string) $asset->liquidity, 'within_3_days'),
            'accountName' => $asset->account_name,
            'accountId' => $asset->account_id,
            'notes' => $asset->notes,
            'acquiredOn' => $asset->acquired_on ? Carbon::parse($asset->acquired_on)->toDateString() : null,
            'gainLoss' => (float) $asset->current_value_egp - (float) ($asset->cost_basis_egp ?: 0),
            'latestValuation' => $latestValuation ? ['id' => $latestValuation->id, 'valuedOn' => Carbon::parse($latestValuation->valued_on)->toDateString(), 'valueEgp' => (float) $latestValuation->value_egp, 'source' => $latestValuation->source, 'method' => $latestValuation->valuation_method] : null,
            'archived' => $asset->trashed(),
            'bucketAllocations' => $asset->buckets->map(fn (Bucket $bucket) => [
                'bucketId' => $bucket->id,
                'bucketName' => $bucket->name,
                'amount' => (float) data_get($bucket, 'pivot.amount_egp', 0),
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function allocation(Collection $assets): array
    {
        $total = max(1, $this->sumMoney($assets, fn (Asset $asset): string => (string) $asset->current_value_egp));

        return $assets->groupBy('type')->map(fn (Collection $items, string $type) => [
            'label' => $type,
            'value' => (float) $items->sum('current_value_egp'),
            'percent' => round((float) $items->sum('current_value_egp') / $total * 100, 1),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function currencyExposure(Collection $assets): array
    {
        $total = max(1, $this->sumMoney($assets, fn (Asset $asset): string => (string) $asset->current_value_egp));

        return $assets->groupBy('currency')->map(fn (Collection $items, string $currency) => [
            'label' => $currency,
            'value' => (float) $items->sum('current_value_egp'),
            'percent' => round((float) $items->sum('current_value_egp') / $total * 100, 1),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<int, array<string, mixed>>
     */
    private function liquidity(Collection $assets): array
    {
        $groups = ['immediate' => 0, 'within_3_days' => 0, 'longer_term' => 0, 'illiquid' => 0];
        foreach ($assets as $asset) {
            $tier = array_key_exists($asset->liquidity, $groups) ? $asset->liquidity : 'illiquid';
            $groups[$tier] = round($groups[$tier] + (float) $asset->current_value_egp, 2);
        }

        return collect($groups)->map(fn (float $value, string $label) => [
            'label' => $label,
            'value' => $value,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  Collection<int, Goal>  $goals
     * @param  Collection<int, Bucket>  $buckets
     * @return list<string>
     */
    private function insights(Collection $assets, Collection $goals, Collection $buckets, float $freeCashFlow, float $emergency, float $monthlyBase, int $reserveMonths): array
    {
        $insights = [];
        foreach ($goals as $goal) {
            $payload = $this->goalPayload($goal, now());
            if (! $payload['onTrack'] && $payload['gapPerMonth'] > 0) {
                $insights[] = "{$goal->name} needs ".number_format($payload['requiredMonthlyContribution']).' EGP/month, above your current free cash flow.';
            }
        }
        if ($monthlyBase > 0 && $emergency / $monthlyBase < $reserveMonths) {
            $insights[] = "Your emergency fund is below the {$reserveMonths}-month baseline.";
        }
        $allocated = $this->sumMoney($buckets, fn (Bucket $bucket): string => (string) $this->bucketValue($bucket));
        $unallocated = max(0, $this->sumMoney($assets, fn (Asset $asset): string => (string) $asset->current_value_egp) - $allocated);
        if ($unallocated > 0) {
            $insights[] = number_format($unallocated).' EGP is not currently assigned to a purpose bucket.';
        }

        return $insights;
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<string, mixed>
     */
    private function sourceStatus(?MonthlyFinancialReview $review, Collection $assets, CarbonInterface $asOf): array
    {
        $freshnessDays = (int) ($this->liquidityPolicy->settings()->valuation_freshness_days ?? 30);
        $latestValuation = AssetValuation::query()->latest('valued_on')->first();
        $latestAssetUpdate = $assets->max(fn (Asset $asset) => $asset->updated_at ? $asset->updated_at->timestamp : 0);
        $age = $latestValuation?->valued_on
            ? max(0, now()->startOfDay()->diffInDays(Carbon::parse($latestValuation->valued_on)->startOfDay()))
            : ($latestAssetUpdate > 0 ? max(0, now()->diffInDays(Carbon::createFromTimestamp($latestAssetUpdate))) : null);

        return [
            'summary' => $review ? ($review->status === 'closed' ? 'closed_review' : 'open_review') : 'cash_flow_fallback',
            'assets' => $assets->isEmpty() ? 'incomplete' : 'manual_value',
            'valuation' => [
                'status' => $age === null ? 'incomplete' : ($age <= $freshnessDays ? 'current' : 'stale'),
                'ageDays' => $age,
                'freshnessPeriodDays' => $freshnessDays,
                'source' => $latestValuation ? 'asset_valuations' : 'asset.current_value_egp',
                'asOf' => $asOf->toDateString(),
            ],
            'policy' => 'configured_policy',
        ];
    }

    /** @param Collection<int, Asset> $assets */
    private function latestUpdate(?MonthlyFinancialReview $review, Collection $assets): ?string
    {
        $timestamps = $assets->map(fn (Asset $asset) => $asset->updated_at ? $asset->updated_at->timestamp : 0)->push($review && $review->updated_at ? $review->updated_at->timestamp : 0);
        $latest = (int) $timestamps->max();

        return $latest > 0 ? Carbon::createFromTimestamp($latest)->toIso8601String() : null;
    }

    /** @param Collection<int, Asset> $assets */
    private function hasDemoData(Collection $assets, ?MonthlyFinancialReview $review = null): bool
    {
        return $assets->contains(fn (Asset $asset): bool => str_contains(strtolower((string) $asset->notes), 'demo'))
            || ($review !== null && str_contains(strtolower((string) $review->notes), 'demo'));
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  array<string, mixed>  $policy
     * @return list<array<string, mixed>>
     */
    private function allocationPolicy(Collection $assets, array $policy): array
    {
        $ranges = is_array($policy['assetClassTargets'] ?? null) ? $policy['assetClassTargets'] : [];
        $current = [];
        foreach ($this->allocation($assets) as $row) {
            $current[(string) $row['label']] = $row;
        }
        $result = [];
        foreach ($ranges as $label => $range) {
            if (! is_array($range)) {
                continue;
            }
            $percent = (float) ($current[(string) $label]['percent'] ?? 0);
            $min = (float) ($range['min'] ?? 0);
            $max = (float) ($range['max'] ?? 0);
            $tolerance = (float) ($policy['rebalancingTolerancePercent'] ?? 5);
            $status = $percent < $min - $tolerance ? 'below_range' : ($percent > $max + $tolerance ? 'above_range' : ($percent < $min || $percent > $max ? 'outside_range' : 'within_range'));
            $result[] = ['label' => (string) $label, 'currentPercent' => $percent, 'targetPercent' => (float) ($range['target'] ?? 0), 'minPercent' => $min, 'maxPercent' => $max, 'tolerancePercent' => $tolerance, 'status' => $status, 'rule' => "Keep {$label} between {$min}% and {$max}% (tolerance {$tolerance}%).", 'source' => 'financial_settings'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return array<string, float>
     */
    private function targetAllocation(array $policy): array
    {
        $targets = is_array($policy['assetClassTargets'] ?? null) ? $policy['assetClassTargets'] : [];
        $result = [];
        foreach ($targets as $label => $range) {
            if (is_array($range)) {
                $result[(string) $label] = (float) ($range['target'] ?? 0);
            }
        }

        return $result;
    }

    /**
     * @param  Collection<int, Liability>  $liabilities
     * @param  Collection<int, RecurringCommitment>  $commitments
     * @return array<string, mixed>
     */
    private function debtSummary(Collection $liabilities, Collection $commitments): array
    {
        $nextDue = $commitments->pluck('next_due_on')->filter()->sort()->first();

        return [
            'monthlyRequiredPayments' => round($liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp) + $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2),
            'liabilityBalance' => round($liabilities->sum(fn (Liability $liability): float => (float) $liability->balance_egp), 2),
            'nextDueOn' => $nextDue ? Carbon::parse($nextDue)->toDateString() : null,
            'dueSoon' => $nextDue ? Carbon::parse($nextDue)->lte(now()->addDays(14)) : false,
            'source' => 'liabilities_and_commitments',
        ];
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @param  Collection<int, mixed>  $goals
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $policy
     * @param  Collection<int, Liability>  $liabilities
     * @return list<array<string, mixed>>
     */
    private function attentionQueue(Collection $assets, Collection $goals, array $summary, array $policy, Collection $liabilities): array
    {
        $alerts = [];
        if ($summary['emergencyCoverageMonths'] < $summary['emergencyReserveMonths']) {
            $alerts[] = ['key' => 'emergency_reserve', 'severity' => 'high', 'title' => 'Emergency reserve below policy', 'reason' => "{$summary['emergencyCoverageMonths']} months available vs configured {$summary['emergencyReserveMonths']} months.", 'rule' => "Emergency reserve must cover {$summary['emergencyReserveMonths']} months of essential expenses.", 'source' => 'financial_settings + purpose buckets', 'actionUrl' => '/buckets'];
        }
        $conflict = $goals->first(fn (mixed $goal): bool => is_array($goal) && ($goal['feasibilityStatus'] ?? '') === 'funding_conflict');
        if ($conflict) {
            $alerts[] = ['key' => 'goal_conflict', 'severity' => 'high', 'title' => 'Goal funding conflict', 'reason' => "{$conflict['name']} is behind after higher-priority goals use available cash flow.", 'rule' => 'Goals are funded in ascending priority order.', 'source' => 'monthly review or cash-flow fallback', 'actionUrl' => '/goals'];
        }
        if ((float) $summary['unallocated'] > 0.01) {
            $alerts[] = ['key' => 'unallocated_assets', 'severity' => 'medium', 'title' => 'Assets need a purpose', 'reason' => number_format((float) $summary['unallocated'], 2).' EGP is not assigned to a bucket.', 'rule' => 'Every asset balance should be assigned once, without exceeding its value.', 'source' => 'assets + buckets', 'actionUrl' => '/allocation-reconciliation'];
        }
        $stale = $this->sourceStatus(null, $assets, now())['valuation']['status'] === 'stale';
        if ($stale) {
            $alerts[] = ['key' => 'stale_valuation', 'severity' => 'medium', 'title' => 'Valuations need review', 'reason' => 'One or more manual asset values are older than the configured freshness period.', 'rule' => 'Update manual values within the policy freshness period.', 'source' => 'asset updated_at + financial settings', 'actionUrl' => '/assets'];
        }
        $liability = $liabilities->first(fn (Liability $item): bool => $item->due_day !== null && (int) $item->due_day <= (int) now()->addDays(14)->day);
        if ($liability) {
            $alerts[] = ['key' => 'debt_due', 'severity' => 'medium', 'title' => 'Debt payment due soon', 'reason' => "{$liability->name} has a payment day of {$liability->due_day}.", 'rule' => 'Review liabilities with a due date in the next 14 days.', 'source' => 'liabilities', 'actionUrl' => '/liabilities'];
        }

        return array_slice($alerts, 0, 3);
    }

    /** @return array<string, mixed> */
    private function decisionPayload(DecisionJournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'decision' => $entry->decision,
            'assumptions' => $entry->assumptions ?? [],
            'alternatives' => $entry->alternatives ?? [],
            'ruleResult' => $entry->rule_result ?? [],
            'chosenAction' => $entry->chosen_action,
            'reviewDate' => $entry->review_date ? Carbon::parse($entry->review_date)->toDateString() : null,
            'outcome' => $entry->outcome,
            'status' => $entry->status,
            'archived' => $entry->trashed(),
        ];
    }

    /** @param array<string, mixed> $dashboard */
    private function dashboardVersion(array $dashboard): string
    {
        return hash('sha256', json_encode($dashboard, JSON_THROW_ON_ERROR));
    }

    /** @param Collection<int, mixed> $items @param callable(mixed): string $value */
    private function sumMoney(Collection $items, callable $value): float
    {
        $total = '0.00';
        foreach ($items as $item) {
            $total = bcadd($total, $this->numericString($value($item)), 2);
        }

        return (float) $total;
    }

    /** @return numeric-string */
    private function numericString(string $value): string
    {
        return is_numeric($value) ? $value : '0';
    }
}
