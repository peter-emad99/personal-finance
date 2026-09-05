<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AllocationPlan;
use App\Models\AllocationPlanItem;
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
use App\Models\GoldPrice;
use App\Models\ImportBatch;
use App\Models\IntegrityCheck;
use App\Models\LedgerTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalanceHistory;
use App\Models\LiabilityPaymentRecord;
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

    private readonly AllocationActualService $allocationActuals;

    private readonly ObligationChangeService $obligationChanges;

    private readonly MarketDataService $marketData;

    private readonly BudgetRuleService $budgetRules;

    public function __construct(?LiquidityPolicy $liquidityPolicy = null, ?LedgerService $ledgerService = null, ?AllocationActualService $allocationActuals = null, ?ObligationChangeService $obligationChanges = null, ?MarketDataService $marketData = null, ?BudgetRuleService $budgetRules = null)
    {
        $this->liquidityPolicy = $liquidityPolicy ?? new LiquidityPolicy;
        $this->ledgerService = $ledgerService ?? new LedgerService;
        $this->allocationActuals = $allocationActuals ?? new AllocationActualService($this->ledgerService);
        $this->obligationChanges = $obligationChanges ?? new ObligationChangeService;
        $this->marketData = $marketData ?? new MarketDataService;
        $this->budgetRules = $budgetRules ?? new BudgetRuleService;
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
        $goals = Goal::with('buckets.assets')->where('status', 'active')->orderBy('priority')->get();
        $flows = CashFlow::whereBetween('occurred_on', [$monthStart, $asOf])->get();
        $ledgerSummary = $this->ledgerService->summarizeMonth($monthStart, true);
        $review = MonthlyFinancialReview::whereDate('month', $monthStart)->first();
        $useManualReview = $review !== null && $ledgerSummary['source'] !== 'confirmed_ledger';
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $liabilities = Liability::with('paymentRecords')->where('is_active', true)->orderByDesc('balance_egp')->get();
        $decisions = DecisionJournalEntry::query()->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('review_date')->limit(10)->get();
        $latestBackup = Backup::query()->latest()->first();
        $latestIntegrityCheck = IntegrityCheck::query()->latest()->first();
        $marketRates = $this->marketData->dashboardPayload();

        $totalLiabilities = $this->sumMoney($liabilities, fn (Liability $liability): string => (string) $liability->balance_egp);
        $totalAssetValue = $this->sumMoney($assets, fn (Asset $asset): string => (string) $asset->current_value_egp);
        $netWorth = round($totalAssetValue - $totalLiabilities, 2);
        $heldElsewhere = $this->sumMoney(
            $assets->filter(fn (Asset $asset): bool => str_contains(strtolower((string) $asset->type), 'receivable')),
            fn (Asset $asset): string => (string) $asset->current_value_egp,
        );
        $directlyControlledAssets = round(max(0, $totalAssetValue - $heldElsewhere), 2);
        $availability = $this->liquidityPolicy->availability($assets);
        $reservedForGoals = (float) $this->goalBuckets($buckets)->sum(function (Bucket $bucket) {
            return $this->bucketValue($bucket);
        });
        $income = $useManualReview ? (float) $review->income_egp : (float) $ledgerSummary['income'];
        $expenses = $useManualReview
            ? $this->reviewExpenses($review)
            : (float) ($ledgerSummary['totalOutflow'] ?? round((float) $ledgerSummary['essentialExpenses'] + (float) $ledgerSummary['lifestyleExpenses'] + (float) $ledgerSummary['recurringCommitments'] + (float) $ledgerSummary['oneTimeExpenses'] + (float) $ledgerSummary['debtPayments'], 2));
        $policy = $this->liquidityPolicy->payload();
        $sourceStatus = $this->sourceStatus($review, $assets, $asOf);
        $emergency = $this->liquidityPolicy->emergencyEligibleAmount(
            $buckets,
            (string) $policy['emergencyEligibleLiquidity'],
        );
        // Emergency coverage should be measured against the user's configured
        // monthly plan when one exists. The confirmed ledger can be partial
        // during the current month (for example, it may contain only one
        // purchase), so using actual outflow here would understate the reserve
        // target and make the dashboard misleading.
        $savedPlan = AllocationPlan::query()
            ->whereDate('month', $monthStart->toDateString())
            ->first();
        $monthlyBase = $savedPlan !== null
            ? (float) $savedPlan->planned_expenses_egp
            : ($useManualReview
                ? (float) $review->essential_expenses_egp + (float) $review->recurring_commitments_egp + (float) $review->debt_payments_egp
                : $expenses);
        $invested = $useManualReview ? (float) $review->invested_egp : (float) $ledgerSummary['invested'];
        $recurringMonthly = $useManualReview
            ? (float) $review->recurring_commitments_egp
            : ($ledgerSummary['source'] === 'confirmed_ledger'
                ? 0.0
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

        $monthlyPlan = $this->monthlyPlanPayload(
            $monthStart,
            $flows,
            $review,
            $ledgerSummary,
            $useManualReview,
            $goalPayloads,
            $buckets,
            $income,
            $expenses,
            $emergency,
            $monthlyBase,
            (int) $policy['emergencyReserveMonths'],
        );
        $actualTracking = $this->allocationActuals->previewForMonth($monthStart);
        $obligationChanges = $this->obligationChanges->compare($this->reviewSnapshot($review), $commitments, $liabilities);
        if ($actualTracking !== null && $actualTracking['source'] === 'confirmed_ledger') {
            $monthlyPlan['allocationItems'] = collect($monthlyPlan['allocationItems'] ?? [])
                ->map(function (mixed $item) use ($actualTracking): mixed {
                    if (is_array($item)) {
                        $item['actual'] = (float) ($actualTracking['itemActuals'][$item['planItemId'] ?? 0]
                            ?? $actualTracking['actuals'][$item['bucketId'] ?? 0]
                            ?? 0);
                    }

                    return $item;
                })->values();
            $monthlyPlan['plannedExpenseCategories'] = collect($monthlyPlan['plannedExpenseCategories'] ?? [])
                ->map(function (mixed $item) use ($actualTracking): mixed {
                    if (is_array($item) && ($item['categoryId'] ?? null) !== null) {
                        $item['actual'] = (float) ($actualTracking['expenseActuals'][$item['categoryId']] ?? 0);
                    }

                    return $item;
                })->values();
        }
        if ($actualTracking !== null) {
            $monthlyPlan['actualTracking'] = $actualTracking;
        }
        $monthlyRatios = $this->monthlyRatios(
            $income,
            $expenses,
            $invested,
            $monthlyPlan,
        );
        $financialFreedom = $this->financialFreedom($netWorth, $reservedForGoals, $emergency, $expenses, $invested, $policy);
        $wealthStage = $this->wealthStage($income - $expenses, $monthlyBase, $emergency, $invested, $totalLiabilities, $netWorth, $financialFreedom, (int) $policy['emergencyReserveMonths']);

        return [
            'asOf' => $asOf->toDateString(),
            'dataFreshness' => [
                'calculationAsOf' => $asOf->toDateString(),
                'source' => $useManualReview ? 'monthly_review' : $ledgerSummary['source'],
                'status' => $useManualReview ? $review->status : ($ledgerSummary['source'] === 'confirmed_ledger' ? 'confirmed' : 'incomplete'),
                'lastUpdated' => $this->latestUpdate($review, $assets, $marketRates),
                'marketRates' => $marketRates,
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
                'last_updated' => $this->latestUpdate($review, $assets, $marketRates),
                'market_rates' => $marketRates,
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
            'marketRates' => $marketRates,
            'figureSources' => [
                'netWorth' => ['status' => 'manual_value', 'source' => 'assets.current_value_egp − liabilities.balance_egp'],
                'directlyControlledAssets' => ['status' => 'manual_value', 'source' => 'assets.current_value_egp excluding receivables'],
                'heldElsewhere' => ['status' => 'manual_value', 'source' => 'assets classified as receivables'],
                'cashSafety' => ['status' => 'manual_value', 'source' => 'assets.liquidity + emergency purpose allocations'],
                'monthlyCashFlow' => ['status' => $useManualReview ? ($review->status === 'closed' ? 'closed_review' : 'current_estimate') : ($ledgerSummary['source'] === 'confirmed_ledger' ? 'confirmed' : 'needs_review'), 'source' => $useManualReview ? 'monthly_financial_reviews' : $ledgerSummary['source']],
                'goals' => ['status' => 'current_estimate', 'source' => 'goals + purpose buckets + monthly cash flow'],
                'allocation' => ['status' => 'configured_policy', 'source' => 'financial_settings + assets'],
            ],
            'summary' => [
                'netWorth' => $netWorth,
                'directlyControlledAssets' => $directlyControlledAssets,
                'heldElsewhere' => $heldElsewhere,
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
            'wealthBreakdown' => $this->wealthBreakdown($assets, $marketRates),
            'liquiditySummary' => $this->liquiditySummary($assets, $totalLiabilities),
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
            'monthlyReview' => $this->monthlyReviewPayload($review, $monthStart, $flows, $commitments, $liabilities, $ledgerSummary, $obligationChanges),
            'monthlyPlan' => $monthlyPlan,
            'actualTracking' => $actualTracking,
            'obligationChanges' => $obligationChanges,
            'monthlyHistory' => $this->monthlyHistory($asOf),
            'monthlyFlow' => $this->monthlyFlowPayload(
                $income,
                $expenses,
                $useManualReview ? (float) $review->recurring_commitments_egp : 0.0,
                $useManualReview ? (float) $review->debt_payments_egp : (float) $ledgerSummary['debtPayments'],
                $invested,
                $monthlyPlan,
                $commitments,
                $liabilities,
                $policy,
                $useManualReview ? 'monthly_review' : (string) $ledgerSummary['source'],
            ),
            'monthlyRatios' => $monthlyRatios,
            'financialFreedom' => $financialFreedom,
            'wealthStage' => $wealthStage,
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
        $goldPrices = GoldPrice::withTrashed()->orderByDesc('price_date')->limit(1000)->get()->map(fn (GoldPrice $price): array => $price->toArray())->values()->all();
        $liabilityBalanceHistories = LiabilityBalanceHistory::withTrashed()->with('liability')->orderByDesc('as_of')->limit(1000)->get()->map(fn (LiabilityBalanceHistory $history): array => $history->toArray())->values()->all();
        $liabilityPaymentRecords = LiabilityPaymentRecord::withTrashed()->with('liability')->orderByDesc('paid_on')->limit(1000)->get()->map(fn (LiabilityPaymentRecord $record): array => $record->toArray())->values()->all();
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
        $allocationPlans = AllocationPlan::with(['incomeItems', 'items', 'expenseItems'])->orderByDesc('month')->limit(24)->get()->map(fn (AllocationPlan $plan): array => $plan->toArray())->values()->all();
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
            'gold_prices' => $goldPrices,
            'market_rates' => $data['marketRates'],
            'liability_balance_histories' => $liabilityBalanceHistories,
            'liability_payment_records' => $liabilityPaymentRecords,
            'goals' => $data['goals']->all(),
            'buckets' => $data['buckets']->all(),
            'allocations' => $data['assets']->map(fn (array $asset): array => ['asset_id' => $asset['id'], 'allocations' => $asset['bucketAllocations'] ?? []])->values()->all(),
            'liabilities' => $data['liabilities']->all(),
            'recurring_commitments' => $data['recurringCommitments']->all(),
            'monthly_review' => $data['monthlyReview'],
            'monthly_flow' => $data['monthlyFlow'],
            'wealth_metrics' => $data['wealthMetrics'],
            'wealth_trend' => $data['wealthTrend'],
            'monthly_ratios' => $data['monthlyRatios'],
            'financial_freedom' => $data['financialFreedom'],
            'wealth_stage' => $data['wealthStage'],
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
        $liabilities = Liability::where('is_active', true)->orderByDesc('balance_egp')->get();
        $review = MonthlyFinancialReview::whereDate('month', $month)->first();

        $obligationChanges = $this->obligationChanges->compare($this->reviewSnapshot($review), $commitments, $liabilities);

        return $this->monthlyReviewPayload($review, $month, $flows, $commitments, $liabilities, $ledgerSummary, $obligationChanges);
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
     * @param  Collection<int, Liability>  $liabilities
     * @param  array<string, mixed>|null  $obligationChanges
     * @param  array<string, mixed>|null  $ledgerSummary
     * @return array<string, mixed>
     */
    private function monthlyReviewPayload(?MonthlyFinancialReview $review, CarbonInterface $month, Collection $flows, Collection $commitments, Collection $liabilities, ?array $ledgerSummary = null, ?array $obligationChanges = null): array
    {
        $obligationChanges ??= $this->obligationChanges->compare($this->reviewSnapshot($review), $commitments, $liabilities);
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
                'reconciliationStatus' => $review->reconciliation_status,
                'reconciledAt' => $review->reconciled_at?->toIso8601String(),
                'obligationSnapshot' => $review->obligation_snapshot,
            ] + $this->monthlyReviewLinkage((float) $review->recurring_commitments_egp, (float) $review->debt_payments_egp, $commitments, $liabilities, 'review') + ['obligationChanges' => $obligationChanges];
        }

        $ledgerSummary ??= $this->ledgerService->summarizeMonth($month, true);
        if ($ledgerSummary['source'] === 'confirmed_ledger') {
            return [
                'id' => null,
                'month' => $month->toDateString(),
                'income' => $ledgerSummary['income'],
                'expenses' => (float) ($ledgerSummary['totalOutflow'] ?? round($ledgerSummary['essentialExpenses'] + $ledgerSummary['lifestyleExpenses'] + $ledgerSummary['recurringCommitments'] + $ledgerSummary['oneTimeExpenses'] + $ledgerSummary['debtPayments'], 2)),
                'essentialExpenses' => $ledgerSummary['essentialExpenses'],
                'lifestyleExpenses' => $ledgerSummary['lifestyleExpenses'],
                'recurringCommitments' => $ledgerSummary['recurringCommitments'],
                'oneTimeExpenses' => $ledgerSummary['oneTimeExpenses'],
                'debtPayments' => $ledgerSummary['debtPayments'],
                'invested' => $ledgerSummary['invested'],
                'manualAdjustment' => 0,
                'notes' => $review?->notes,
                'status' => $review ? $review->status : 'open',
                'source' => 'confirmed_ledger',
                'sourceTransactionCount' => $ledgerSummary['sourceTransactionCount'],
                'reconciliationStatus' => $review?->reconciliation_status ?? 'pending',
                'reconciledAt' => $review?->reconciled_at?->toIso8601String(),
                'obligationSnapshot' => $review?->obligation_snapshot,
            ] + $this->monthlyReviewLinkage(0.0, (float) $ledgerSummary['debtPayments'], $commitments, $liabilities, 'confirmed_ledger') + ['obligationChanges' => $obligationChanges];
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
            'reconciliationStatus' => 'pending',
            'reconciledAt' => null,
            'obligationSnapshot' => null,
        ] + $this->monthlyReviewLinkage(0, (float) $flows->where('type', 'obligation')->sum('amount_egp'), $commitments, $liabilities, 'cash_flow_fallback') + ['obligationChanges' => $obligationChanges];
    }

    /** @return array<string, mixed> */
    private function monthlyReviewLinkage(float $recordedCommitments, float $recordedDebtPayments, Collection $commitments, Collection $liabilities, string $source): array
    {
        $configuredCommitments = round((float) $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2);
        $configuredDebtPayments = round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp), 2);

        return [
            'linkage' => [
                'source' => $source,
                'commitments' => [
                    'configuredMonthly' => $configuredCommitments,
                    'recorded' => round($recordedCommitments, 2),
                    'variance' => round($recordedCommitments - $configuredCommitments, 2),
                    'items' => $commitments->map(fn (RecurringCommitment $commitment): array => $this->commitmentPayload($commitment))->values(),
                ],
                'liabilities' => [
                    'configuredMonthlyPayments' => $configuredDebtPayments,
                    'recorded' => round($recordedDebtPayments, 2),
                    'variance' => round($recordedDebtPayments - $configuredDebtPayments, 2),
                    'balance' => round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->balance_egp), 2),
                    'items' => $liabilities->map(fn (Liability $liability): array => $this->liabilityPayload($liability))->values(),
                ],
            ],
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

    /** @return array<string, mixed>|null */
    private function reviewSnapshot(?MonthlyFinancialReview $review): ?array
    {
        $snapshot = $review?->getAttribute('obligation_snapshot');

        return is_array($snapshot) ? $snapshot : null;
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
        $payoff = $this->debtPayoffProjection($liability);
        $records = $liability->relationLoaded('paymentRecords')
            ? $liability->paymentRecords
            : $liability->paymentRecords()->latest('paid_on')->get();
        $recordedSummary = [
            'count' => $records->count(),
            'totalPayments' => round((float) $records->sum('payment_egp'), 2),
            'principalPaid' => round((float) $records->sum('principal_egp'), 2),
            'interestPaid' => round((float) $records->sum('interest_egp'), 2),
            'feesPaid' => round((float) $records->sum('fees_egp'), 2),
            'lastPaidOn' => $records->sortByDesc('paid_on')->first()?->paid_on?->toDateString(),
        ];

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
            'payoffProjection' => $payoff,
            'recordedPaymentSummary' => $recordedSummary,
            'paymentRecords' => $records->sortByDesc('paid_on')->take(12)->map(fn (LiabilityPaymentRecord $record): array => [
                'id' => $record->id,
                'paidOn' => $record->paid_on?->toDateString(),
                'payment' => (float) $record->payment_egp,
                'principal' => (float) $record->principal_egp,
                'interest' => (float) $record->interest_egp,
                'fees' => (float) $record->fees_egp,
                'balanceAfter' => $record->balance_after_egp !== null ? (float) $record->balance_after_egp : null,
                'source' => $record->source,
                'notes' => $record->notes,
            ])->values()->all(),
            'isActive' => $liability->is_active,
            'notes' => $liability->notes,
        ];
    }

    /** @return array<string, mixed> */
    private function debtPayoffProjection(Liability $liability, float $extraMonthlyPayment = 0, bool $includeScenarios = true): array
    {
        $balance = max(0, (float) $liability->balance_egp);
        $payment = max(0, (float) $liability->monthly_payment_egp + $extraMonthlyPayment);
        $annualRate = max(0, (float) ($liability->interest_rate_percent ?? 0));
        $monthlyRate = $annualRate / 100 / 12;
        $interest = round($balance * $monthlyRate, 2);
        $principal = round(min($balance, max(0, $payment - $interest)), 2);
        $remainingMonths = null;
        $projectedPayoffOn = null;
        $status = 'needs_payment';
        $totalInterest = 0.0;

        if ($balance <= 0) {
            $remainingMonths = 0;
            $projectedPayoffOn = now()->startOfMonth()->toDateString();
            $status = 'paid_off';
        } elseif ($payment > 0 && ($monthlyRate === 0 || $payment > $interest)) {
            $projectedBalance = $balance;
            $months = 0;
            while ($projectedBalance > 0.01 && $months < 1200) {
                $projectedInterest = $projectedBalance * $monthlyRate;
                $projectedPrincipal = min($projectedBalance, $payment - $projectedInterest);
                if ($projectedPrincipal <= 0) {
                    break;
                }
                $projectedBalance = max(0, $projectedBalance - $projectedPrincipal);
                $totalInterest += $projectedInterest;
                $months++;
            }
            $remainingMonths = $months > 0 && $months < 1200 ? $months : null;
            $projectedPayoffOn = $remainingMonths !== null
                ? now()->startOfMonth()->addMonths($remainingMonths)->toDateString()
                : null;
            $status = $remainingMonths !== null ? 'projected' : 'payment_too_low';
        } elseif ($payment > 0) {
            $status = 'payment_too_low';
        }

        $result = [
            'status' => $status,
            'extraMonthlyPayment' => round($extraMonthlyPayment, 2),
            'monthlyPayment' => round($payment, 2),
            'estimatedMonthlyInterest' => $interest,
            'estimatedMonthlyPrincipal' => $principal,
            'estimatedTotalInterest' => round($totalInterest, 2),
            'estimatedTotalPrincipal' => round($balance, 2),
            'estimatedRemainingMonths' => $remainingMonths,
            'estimatedPayoffOn' => $projectedPayoffOn,
            'recordedPayoffOn' => $liability->payoff_on ? Carbon::parse($liability->payoff_on)->toDateString() : null,
            'note' => 'Estimate uses the current balance, rate, and payment; verify against the lender statement.',
        ];

        if ($includeScenarios) {
            $result['extraPaymentScenarios'] = collect([1000, 3000, 5000])
                ->map(fn (int $extra): array => $this->debtPayoffProjection($liability, (float) $extra, false))
                ->all();
        }

        return $result;
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

    /** @return list<array<string, mixed>> */
    private function monthlyHistory(CarbonInterface $asOf): array
    {
        $history = [];
        $start = $asOf->copy()->startOfMonth()->subMonths(11);
        for ($index = 0; $index < 12; $index++) {
            $month = $start->copy()->addMonths($index);
            $review = MonthlyFinancialReview::whereDate('month', $month)->first();
            $summary = $this->ledgerService->summarizeMonth($month, true);
            $income = $review && $summary['source'] !== 'confirmed_ledger'
                ? (float) $review->income_egp
                : (float) $summary['income'];
            $expenses = $review && $summary['source'] !== 'confirmed_ledger'
                ? $this->reviewExpenses($review)
                : (float) ($summary['totalOutflow'] ?? round((float) $summary['essentialExpenses'] + (float) $summary['lifestyleExpenses'] + (float) $summary['recurringCommitments'] + (float) $summary['oneTimeExpenses'] + (float) $summary['debtPayments'], 2));
            $invested = $review && $summary['source'] !== 'confirmed_ledger' ? (float) $review->invested_egp : (float) $summary['invested'];
            $debtPayments = $review && $summary['source'] !== 'confirmed_ledger' ? (float) $review->debt_payments_egp : (float) $summary['debtPayments'];
            $history[] = [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'income' => round($income, 2),
                'expenses' => round($expenses, 2),
                'freeCashFlow' => round($income - $expenses, 2),
                'invested' => round($invested, 2),
                'debtPayments' => round($debtPayments, 2),
                'savingsRate' => $income > 0 ? round(($income - $expenses) / $income * 100, 1) : 0,
                'investmentRate' => $income > 0 ? round($invested / $income * 100, 1) : 0,
                'source' => $summary['source'],
            ];
        }

        return $history;
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
            : (float) $ledgerSummary['income'] - (float) ($ledgerSummary['totalOutflow'] ?? round((float) $ledgerSummary['essentialExpenses'] + (float) $ledgerSummary['lifestyleExpenses'] + (float) $ledgerSummary['recurringCommitments'] + (float) $ledgerSummary['oneTimeExpenses'] + (float) $ledgerSummary['debtPayments'], 2));
        $fundingSources = $goal->buckets
            ->flatMap(fn (Bucket $bucket) => $bucket->assets->map(fn (Asset $asset): array => [
                'assetId' => $asset->id,
                'assetName' => $asset->name,
                'assetType' => $asset->type,
                'currency' => $asset->currency,
                'bucketName' => $bucket->name,
                'amount' => (float) data_get($asset, 'pivot.amount_egp', 0),
            ]))
            ->groupBy('assetId')
            ->map(fn (Collection $items): array => [
                'assetId' => $items->first()['assetId'],
                'assetName' => $items->first()['assetName'],
                'assetType' => $items->first()['assetType'],
                'currency' => $items->first()['currency'],
                'bucketNames' => $items->pluck('bucketName')->unique()->values()->all(),
                'amount' => round((float) $items->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values();

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
            'fundingSources' => $fundingSources,
        ];
    }

    /**
     * @param  Collection<int, CashFlow>  $flows
     * @param  array<string, mixed>  $ledgerSummary
     * @param  Collection<int, mixed>  $goals
     * @param  Collection<int, Bucket>  $buckets
     * @return array<string, mixed>
     */
    private function monthlyPlanPayload(
        CarbonInterface $month,
        Collection $flows,
        ?MonthlyFinancialReview $review,
        array $ledgerSummary,
        bool $useManualReview,
        Collection $goals,
        Collection $buckets,
        float $income,
        float $expenses,
        float $emergencyFund,
        float $monthlyBase,
        int $reserveMonths,
    ): array {
        $incomeSources = collect();
        $expenseCategories = collect();

        if ($ledgerSummary['source'] === 'confirmed_ledger') {
            $transactions = $this->ledgerService->confirmedForMonth($month);
            $incomeTypes = ['income', 'interest', 'dividend', 'withdrawal_reversal'];
            $expenseTypes = ['expense', 'fee', 'tax', 'withdrawal', 'debt_payment', 'obligation'];
            $incomeSources = $transactions
                ->filter(fn (LedgerTransaction $transaction): bool => ! $transaction->isTransfer() && in_array($transaction->transaction_type, $incomeTypes, true))
                ->groupBy(fn (LedgerTransaction $transaction): string => trim($transaction->description) ?: ($transaction->category?->name ?? 'Income'))
                ->map(fn (Collection $items, string $label): array => [
                    'label' => $label,
                    'currency' => $items->pluck('currency')->unique()->count() === 1 ? (string) $items->first()->currency : 'MIXED',
                    'nativeAmount' => round((float) $items->sum('amount'), 2),
                    'amount' => round((float) $items->sum('amount_egp'), 2),
                ])->sortByDesc('amount')->values();
            $expenseCategories = $transactions
                ->filter(fn (LedgerTransaction $transaction): bool => ! $transaction->isTransfer() && in_array($transaction->transaction_type, $expenseTypes, true))
                ->groupBy(fn (LedgerTransaction $transaction): string => $transaction->category?->name ?? match ($transaction->transaction_type) {
                    'debt_payment', 'obligation' => 'Debt & obligations',
                    'fee' => 'Fees',
                    'tax' => 'Taxes',
                    default => 'Other expenses',
                })
                ->map(fn (Collection $items, string $label): array => [
                    'label' => $label,
                    'amount' => round((float) $items->sum('amount_egp'), 2),
                ])->sortByDesc('amount')->values();
        } elseif ($useManualReview && $review !== null) {
            $incomeSources = collect([['label' => 'Monthly income', 'currency' => 'EGP', 'nativeAmount' => (float) $review->income_egp, 'amount' => (float) $review->income_egp]]);
            $expenseCategories = collect([
                ['label' => 'Essentials', 'amount' => (float) $review->essential_expenses_egp],
                ['label' => 'Lifestyle', 'amount' => (float) $review->lifestyle_expenses_egp],
                ['label' => 'Recurring commitments', 'amount' => (float) $review->recurring_commitments_egp],
                ['label' => 'One-time expenses', 'amount' => (float) $review->one_time_expenses_egp],
                ['label' => 'Other adjustment', 'amount' => (float) $review->manual_adjustment_egp],
                ['label' => 'Debt payments', 'amount' => (float) $review->debt_payments_egp],
            ])->filter(fn (array $item): bool => $item['amount'] != 0)->values();
        } else {
            $incomeSources = $flows->where('type', 'income')
                ->groupBy(fn (CashFlow $flow): string => $flow->category.'|'.($flow->currency ?: 'EGP'))
                ->map(function (Collection $items, string $key): array {
                    [$label, $currency] = explode('|', $key, 2);

                    return ['label' => $label, 'currency' => $currency, 'nativeAmount' => round((float) $items->sum(fn (CashFlow $flow): float => (float) ($flow->amount ?? $flow->amount_egp)), 2), 'amount' => round((float) $items->sum('amount_egp'), 2)];
                })->sortByDesc('amount')->values();
            $expenseCategories = $flows->whereIn('type', ['expense', 'obligation'])
                ->groupBy('category')
                ->map(fn (Collection $items, string $label): array => ['label' => $label, 'amount' => round((float) $items->sum('amount_egp'), 2)])
                ->sortByDesc('amount')->values();
        }

        $freeCashFlow = round($income - $expenses, 2);
        $emergencyTarget = round(max(0, $monthlyBase * $reserveMonths), 2);
        $emergencyGap = round(max(0, $emergencyTarget - $emergencyFund), 2);
        $savedPlan = AllocationPlan::with([
            'template',
            'incomeItems.budgetRule',
            'items.asset',
            'items.bucket.goal',
            'expenseItems.category',
        ])->whereDate('month', $month->toDateString())->first();
        $template = $savedPlan?->template ?? $this->budgetRules->ensureDefaultTemplate($income);
        $plannedIncome = $savedPlan ? (float) $savedPlan->planned_income_egp : $this->budgetRules->templateIncome($template, $income);
        if ($savedPlan !== null) {
            $incomeRules = $savedPlan->incomeItems->isNotEmpty()
                ? $savedPlan->incomeItems->map(fn ($item): array => [
                    'id' => $item->budget_rule_id,
                    'label' => $item->name,
                    'amount' => (float) $item->planned_amount_egp,
                    'percent' => null,
                ])->values()
                : collect([[
                    'label' => 'Saved planned income',
                    'amount' => (float) $savedPlan->planned_income_egp,
                    'percent' => null,
                ]]);
            $plannedIncomeSources = $savedPlan->incomeItems->isNotEmpty()
                ? $savedPlan->incomeItems->map(fn ($item): array => [
                    'id' => $item->id,
                    'label' => $item->name,
                    'amount' => (float) $item->planned_amount_egp,
                ])->values()
                : collect([[
                    'label' => 'Saved planned income',
                    'amount' => (float) $savedPlan->planned_income_egp,
                ]]);
        } else {
            $incomeRules = $this->budgetRules->templateIncomeSuggestions($template, $income);
            $plannedIncomeSources = $incomeRules;
        }

        if ($savedPlan !== null) {
            $plannedExpenseCategories = $savedPlan->expenseItems->isNotEmpty()
                ? $savedPlan->expenseItems->map(fn ($item): array => [
                    'categoryId' => $item->budget_category_id,
                    'label' => $item->category?->name ?? 'Uncategorized',
                    'planned' => (float) $item->planned_amount_egp,
                    'actual' => (float) $item->actual_amount_egp,
                ])->values()
                : collect([[
                    'categoryId' => null,
                    'label' => 'Saved planned outflow',
                    'planned' => (float) $savedPlan->planned_expenses_egp,
                    'actual' => 0.0,
                ]]);
            $plannedExpenses = (float) $savedPlan->planned_expenses_egp;
            $allocationItems = $savedPlan->items->map(function (AllocationPlanItem $item): array {
                $bucketName = $item->bucket?->name ?? 'Unassigned bucket';
                $assetName = $item->asset_target ?: ($item->asset?->name ?? null);

                return [
                    'planItemId' => $item->id,
                    'bucketId' => $item->bucket_id,
                    'label' => $assetName ? $assetName.' → '.$bucketName : $bucketName,
                    'amount' => (float) $item->planned_amount_egp,
                    'actual' => (float) $item->actual_amount_egp,
                    'kind' => $item->bucket?->goal_id !== null ? 'goal' : ($item->bucket?->purpose_type ?? 'other'),
                    'assetId' => $item->asset_id,
                    'assetName' => $item->asset?->name,
                    'assetTarget' => $item->asset_target,
                    'allocationPercent' => $item->allocation_percent !== null ? (float) $item->allocation_percent : null,
                ];
            })->values();
            $planSource = 'saved_plan';
        } else {
            $plannedExpenseCategories = $this->budgetRules->templateExpenseSuggestions($template, $plannedIncome)->map(fn (array $item): array => [
                'categoryId' => $item['categoryId'],
                'label' => $item['categoryName'],
                'planned' => (float) $item['planned'],
                'actual' => 0.0,
            ])->values();
            $plannedExpenses = round((float) $plannedExpenseCategories->sum('planned'), 2);
            $allocationItems = $this->budgetRules->templateAllocationSuggestions($template, $plannedIncome, $plannedExpenses);
            $planSource = 'plan_template';
        }

        $plannedFreeCashFlow = round($plannedIncome - $plannedExpenses, 2);
        $plannedTotal = round((float) $allocationItems->sum('amount'), 2);

        return [
            'incomeSources' => $incomeSources,
            'expenseCategories' => $expenseCategories,
            'incomeRules' => $incomeRules,
            'plannedIncomeSources' => $plannedIncomeSources,
            'plannedExpenseCategories' => $plannedExpenseCategories,
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'plannedIncome' => round($plannedIncome, 2),
            'plannedExpenses' => round($plannedExpenses, 2),
            'plannedFreeCashFlow' => $plannedFreeCashFlow,
            'freeCashFlow' => $freeCashFlow,
            'emergencyTarget' => $emergencyTarget,
            'emergencyGap' => $emergencyGap,
            'allocationItems' => $allocationItems,
            'plannedTotal' => $plannedTotal,
            'unallocated' => round(max(0, $plannedFreeCashFlow - $plannedTotal), 2),
            'templateId' => $template->id,
            'templateName' => $template->name,
            'planStatus' => $savedPlan?->status,
            'source' => $planSource,
        ];
    }

    /** @return array<string, mixed> */
    private function monthlyFlowPayload(
        float $income,
        float $expenses,
        float $recordedCommitments,
        float $recordedDebtPayments,
        float $invested,
        array $monthlyPlan,
        Collection $commitments,
        Collection $liabilities,
        array $policy,
        string $source,
    ): array {
        $configuredCommitments = round((float) $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2);
        $configuredDebtPayments = round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp), 2);
        $plannedIncome = (float) ($monthlyPlan['plannedIncome'] ?? $income);
        $plannedExpenses = (float) ($monthlyPlan['plannedExpenses'] ?? $expenses);
        $plannedFreeCashFlow = round($plannedIncome - $plannedExpenses, 2);
        $freeCashFlow = round($income - $expenses, 2);
        $plannedAllocations = collect($monthlyPlan['allocationItems'] ?? []);
        $plannedExpenseItems = collect($monthlyPlan['plannedExpenseCategories'] ?? []);
        $plannedTotal = round((float) $plannedAllocations->sum('amount'), 2);
        $plannedUnassigned = round(max(0, $plannedFreeCashFlow - $plannedTotal), 2);
        $plannedOverAllocated = round(max(0, $plannedTotal - $plannedFreeCashFlow), 2);
        $actualAllocationTotal = round((float) $plannedAllocations->sum('actual'), 2);
        $actualUnassigned = round(max(0, $freeCashFlow - $actualAllocationTotal), 2);
        $actualOverAllocated = round(max(0, $actualAllocationTotal - $freeCashFlow), 2);
        $commitmentVariance = round($recordedCommitments - $configuredCommitments, 2);
        $debtVariance = round($recordedDebtPayments - $configuredDebtPayments, 2);
        $planIncomeVariance = round($income - $plannedIncome, 2);
        $planExpensesVariance = round($expenses - $plannedExpenses, 2);
        $emergencyAllocation = (float) $plannedAllocations->where('kind', 'emergency')->sum('amount');
        $hasLinkageMismatch = abs($commitmentVariance) > 0.01 || abs($debtVariance) > 0.01;
        $thresholds = is_array($policy['varianceThresholds'] ?? null) ? $policy['varianceThresholds'] : [];
        $incomeThreshold = max(0, (float) ($thresholds['income_percent'] ?? 10));
        $expenseThreshold = max(0, (float) ($thresholds['expenses_percent'] ?? 10));
        $investmentMinimum = max(0, min(100, (float) ($thresholds['investment_minimum_percent'] ?? 80)));
        $incomeVariancePercent = $plannedIncome > 0 ? round(abs($planIncomeVariance) / $plannedIncome * 100, 1) : ($income > 0 ? 100.0 : 0.0);
        $expenseVariancePercent = $plannedExpenses > 0 ? round(abs($planExpensesVariance) / $plannedExpenses * 100, 1) : ($expenses > 0 ? 100.0 : 0.0);
        $investmentTargetPercent = $plannedIncome > 0 && $plannedAllocations->isNotEmpty()
            ? round((float) $plannedAllocations->where('kind', 'investment')->sum('amount') / $plannedIncome * 100, 1)
            : 0.0;
        $investmentRate = $income > 0 ? round($invested / $income * 100, 1) : 0.0;
        $investmentFloorPercent = round($investmentTargetPercent * $investmentMinimum / 100, 1);
        $linkedActualOutflow = round((float) $plannedExpenseItems->sum('actual'), 2);
        $trackedUnmappedOutflow = (float) data_get($monthlyPlan, 'actualTracking.unmappedExpenseAmount', 0);
        $unmappedOutflow = round(max(0, $trackedUnmappedOutflow ?: $expenses - $linkedActualOutflow), 2);
        $dynamicOutflows = $plannedExpenseItems->map(fn (array $item): array => [
            'key' => 'plan_expense_'.((string) ($item['categoryId'] ?? $item['label'] ?? 'unmapped')),
            'label' => (string) ($item['label'] ?? 'Uncategorized plan expense'),
            'amount' => round((float) ($item['actual'] ?? 0), 2),
            'expected' => round((float) ($item['planned'] ?? 0), 2),
            'kind' => 'plan_category',
        ])->filter(fn (array $item): bool => $item['amount'] !== 0 || $item['expected'] !== 0)->values();
        if ($unmappedOutflow > 0.01) {
            $dynamicOutflows->push([
                'key' => 'unmapped_actual_outflow',
                'label' => 'Actual outflow not linked to this plan',
                'amount' => $unmappedOutflow,
                'kind' => 'unmapped',
            ]);
        }
        $varianceAlerts = array_values(array_filter([
            $incomeVariancePercent >= $incomeThreshold && abs($planIncomeVariance) > 0.01 ? [
                'code' => 'income_variance',
                'label' => 'Income changed from plan',
                'actual' => round($income, 2),
                'planned' => round($plannedIncome, 2),
                'variance' => $planIncomeVariance,
                'variancePercent' => $incomeVariancePercent,
                'thresholdPercent' => $incomeThreshold,
            ] : null,
            $expenseVariancePercent >= $expenseThreshold && abs($planExpensesVariance) > 0.01 ? [
                'code' => 'expense_variance',
                'label' => 'Outflow changed from plan',
                'actual' => round($expenses, 2),
                'planned' => round($plannedExpenses, 2),
                'variance' => $planExpensesVariance,
                'variancePercent' => $expenseVariancePercent,
                'thresholdPercent' => $expenseThreshold,
            ] : null,
            $investmentTargetPercent > 0 && $investmentRate + 0.01 < $investmentFloorPercent ? [
                'code' => 'investment_below_target',
                'label' => 'Investment pace is below target',
                'actual' => round($invested, 2),
                'planned' => round($income * $investmentTargetPercent / 100, 2),
                'variance' => round($invested - ($income * $investmentTargetPercent / 100), 2),
                'variancePercent' => $investmentRate,
                'thresholdPercent' => $investmentFloorPercent,
                'targetPercent' => $investmentTargetPercent,
            ] : null,
        ]));

        $warnings = array_values(array_filter([
            abs($commitmentVariance) > 0.01 ? 'The review does not match your active recurring commitments.' : null,
            abs($debtVariance) > 0.01 ? 'The review does not match the monthly payments on your active liabilities.' : null,
            abs($planIncomeVariance) > 0.01 ? 'Actual income differs from the income used in the monthly plan.' : null,
            abs($planExpensesVariance) > 0.01 ? 'Actual outflow differs from the outflow used in the monthly plan.' : null,
            $plannedOverAllocated > 0.01 ? 'Your planned allocations are higher than the planned free cash flow.' : null,
            $plannedUnassigned > 0.01 ? 'The current plan still has free cash flow with no assigned purpose.' : null,
            $actualOverAllocated > 0.01 ? 'Actual linked allocations are higher than actual free cash flow.' : null,
            $actualUnassigned > 0.01 ? 'Actual free cash flow still has no linked allocation.' : null,
            (float) ($monthlyPlan['emergencyGap'] ?? 0) <= 0 && $emergencyAllocation > 0 ? 'Your emergency reserve is already at target; consider redirecting this contribution.' : null,
            ...array_map(fn (array $alert): string => match ($alert['code']) {
                'income_variance' => sprintf('Income is %.1f%% different from the plan.', $alert['variancePercent']),
                'expense_variance' => sprintf('Outflow is %.1f%% different from the plan.', $alert['variancePercent']),
                default => sprintf('Investment pace is %.1f%% of income; your target is %.1f%%.', $alert['variancePercent'], $alert['targetPercent'] ?? $alert['thresholdPercent']),
            }, $varianceAlerts),
        ]));

        $status = max($plannedOverAllocated, $actualOverAllocated) > 0.01
            ? 'over_allocated'
            : ($hasLinkageMismatch ? 'needs_sync' : ($plannedUnassigned > 0.01 || $actualUnassigned > 0.01 ? 'needs_direction' : (count($warnings) > 0 ? 'needs_direction' : 'balanced')));

        return [
            'source' => $source,
            'status' => $status,
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'freeCashFlow' => $freeCashFlow,
            'outflows' => $dynamicOutflows->all(),
            'planned' => [
                'income' => round($plannedIncome, 2),
                'expenses' => round($plannedExpenses, 2),
                'freeCashFlow' => $plannedFreeCashFlow,
                'varianceIncome' => $planIncomeVariance,
                'varianceExpenses' => $planExpensesVariance,
            ],
            'obligations' => [
                'commitments' => ['configuredMonthly' => $configuredCommitments, 'recorded' => round($recordedCommitments, 2), 'variance' => $commitmentVariance],
                'liabilities' => ['configuredMonthlyPayments' => $configuredDebtPayments, 'recorded' => round($recordedDebtPayments, 2), 'variance' => $debtVariance, 'balance' => round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->balance_egp), 2)],
                'totalConfiguredMonthly' => round($configuredCommitments + $configuredDebtPayments, 2),
            ],
            'allocations' => $plannedAllocations->map(fn (array $item): array => [
                'label' => $item['label'],
                'kind' => $item['kind'],
                'planned' => round((float) $item['amount'], 2),
                'actual' => round((float) $item['actual'], 2),
                'variance' => round((float) $item['actual'] - (float) $item['amount'], 2),
            ])->values()->all(),
            'allocationTotal' => $plannedTotal,
            'actualAllocationTotal' => $actualAllocationTotal,
            'plannedUnassigned' => $plannedUnassigned,
            'actualUnassigned' => $actualUnassigned,
            'unassigned' => $plannedUnassigned,
            'plannedOverAllocated' => $plannedOverAllocated,
            'actualOverAllocated' => $actualOverAllocated,
            'overAllocated' => max($plannedOverAllocated, $actualOverAllocated),
            'varianceAlerts' => $varianceAlerts,
            'warnings' => $warnings,
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
            'classification' => $this->assetClassification($asset),
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
                'purpose' => $bucket->purpose,
                'goalName' => $bucket->relationLoaded('goal') ? $bucket->goal?->name : null,
                'targetAmount' => (float) $bucket->target_amount_egp,
                'currentAmount' => $this->bucketValue($bucket),
                'amount' => (float) data_get($bucket, 'pivot.amount_egp', 0),
            ])->values(),
        ];
    }

    private function assetClassification(Asset $asset): string
    {
        $type = strtolower(trim((string) $asset->type));
        $currency = strtoupper(trim((string) $asset->currency));

        return match (true) {
            str_contains($type, 'receivable') => 'receivable',
            $type === 'cash reserve' || (str_contains($type, 'reserve') && str_contains($type, 'cash')) => 'reserved_cash',
            str_contains($type, 'gold') || $currency === 'GOLD' => 'gold',
            $type === 'certificate' || str_contains($type, 'certificate') => 'certificate',
            $type === 'cash' || $type === 'usd' => 'cash',
            $type === 'etf'
                || str_contains($type, 'fund')
                || str_contains($type, 'equity')
                || str_contains($type, 'equities')
                || str_contains($type, 'stock') => 'investment',
            default => 'other',
        };
    }

    /**
     * Explicit wealth groups for the dashboard. This intentionally does not
     * infer investments from whatever remains after subtracting other groups.
     *
     * @param  Collection<int, Asset>  $assets
     * @param  array<string, mixed>  $marketRates
     * @return array<string, mixed>
     */
    private function wealthBreakdown(Collection $assets, array $marketRates): array
    {
        $total = max(1, $this->sumMoney($assets, fn (Asset $asset): string => (string) $asset->current_value_egp));
        $usdRate = (float) data_get($marketRates, 'usdToEgp.rate', 0);
        $groups = [
            ['key' => 'cash_egp', 'label' => 'EGP cash', 'detail' => 'Bank accounts and wallet money available now', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'cash' && strtoupper((string) $asset->currency) === 'EGP'],
            ['key' => 'cash_usd', 'label' => 'USD cash', 'detail' => 'USD cash balance, shown in USD and EGP', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'cash' && strtoupper((string) $asset->currency) === 'USD'],
            ['key' => 'reserved_cash', 'label' => 'Reserved cash', 'detail' => 'Held for your brother; not available for normal spending', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'reserved_cash'],
            ['key' => 'gold', 'label' => 'Gold', 'detail' => '24K physical gold', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'gold'],
            ['key' => 'investment_egp', 'label' => 'EGP investments', 'detail' => 'Egyptian equities and EGP funds', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'investment' && strtoupper((string) $asset->currency) !== 'USD'],
            ['key' => 'investment_usd', 'label' => 'USD investments', 'detail' => 'USD-denominated investments such as VOO', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'investment' && strtoupper((string) $asset->currency) === 'USD'],
            ['key' => 'certificate', 'label' => 'NBE certificate', 'detail' => 'Long-term / locked asset', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'certificate'],
            ['key' => 'receivables', 'label' => 'Loans receivable', 'detail' => 'Money owed to you; not cash now', 'filter' => fn (Asset $asset): bool => $this->assetClassification($asset) === 'receivable'],
        ];

        $payload = collect($groups)->map(function (array $group) use ($assets, $total, $usdRate): array {
            /** @var Collection<int, Asset> $items */
            $items = $assets->filter($group['filter']);
            $value = round((float) $items->sum('current_value_egp'), 2);
            $nativeCurrency = null;
            $nativeAmount = null;

            if (in_array($group['key'], ['cash_usd', 'reserved_cash', 'investment_usd'], true)) {
                $nativeCurrency = 'USD';
                $nativeAmount = $usdRate > 0
                    ? round((float) $items->sum(function (Asset $asset) use ($usdRate): float {
                        if ($asset->quantity !== null && strtoupper((string) $asset->currency) === 'USD') {
                            return (float) $asset->quantity;
                        }

                        return (float) $asset->current_value_egp / $usdRate;
                    }), 2)
                    : null;
            } elseif ($group['key'] === 'gold') {
                $nativeCurrency = 'g';
                $nativeAmount = round((float) $items->sum('quantity'), 2);
            }

            $detail = $group['detail'];
            if ($group['key'] === 'certificate') {
                $maturityDate = $items->map(fn (Asset $asset): ?string => $this->maturityDateFromNotes($asset))->filter()->first();
                if ($maturityDate !== null) {
                    $detail .= ' · matures '.$maturityDate;
                }
            }

            return [
                'key' => $group['key'],
                'label' => $group['label'],
                'detail' => $detail,
                'valueEgp' => $value,
                'percent' => round($value / $total * 100, 1),
                'nativeAmount' => $nativeAmount,
                'nativeCurrency' => $nativeCurrency,
            ];
        })->filter(fn (array $group): bool => $group['valueEgp'] > 0)->values()->all();

        return [
            'groups' => $payload,
            'usdToEgp' => $usdRate > 0 ? $usdRate : null,
        ];
    }

    /**
     * Liquidity is a separate lens from net worth. Liabilities are subtracted
     * here only for the after-liabilities cash figure; net worth subtracts
     * them independently exactly once.
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<string, mixed>
     */
    private function liquiditySummary(Collection $assets, float $totalLiabilities): array
    {
        $grossImmediate = round((float) $assets
            ->filter(fn (Asset $asset): bool => (string) $asset->liquidity === 'immediate')
            ->sum('current_value_egp'), 2);
        $reservedCash = round((float) $assets
            ->filter(fn (Asset $asset): bool => $this->assetClassification($asset) === 'reserved_cash' && (string) $asset->liquidity === 'immediate')
            ->sum('current_value_egp'), 2);
        $controllableBeforeLiabilities = round($grossImmediate - $reservedCash, 2);

        return [
            'grossImmediateLiquidAssets' => $grossImmediate,
            'reservedCash' => $reservedCash,
            'controllableCashBeforeLiabilities' => $controllableBeforeLiabilities,
            'activeLiabilities' => round($totalLiabilities, 2),
            'controllableCashAfterLiabilities' => round($controllableBeforeLiabilities - $totalLiabilities, 2),
        ];
    }

    private function maturityDateFromNotes(Asset $asset): ?string
    {
        if (! is_string($asset->notes)) {
            return null;
        }

        preg_match('/\b20\d{2}-\d{2}-\d{2}\b/', $asset->notes, $matches);

        return $matches[0] ?? null;
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
     * Turn the current month into a plan-owned comparison view.
     *
     * Rows come from the selected month plan. This method deliberately does
     * not manufacture semantic groups from labels such as "essential" or
     * "investment"; those are user-owned categories and purpose buckets.
     *
     * @param  array<string, mixed>  $monthlyPlan
     * @return array<string, mixed>
     */
    private function monthlyRatios(
        float $income,
        float $expenses,
        float $invested,
        array $monthlyPlan,
    ): array {
        $freeCashFlow = max(0, $income - $expenses);
        $plannedIncome = max(0, (float) ($monthlyPlan['plannedIncome'] ?? $income));
        $plannedExpenses = collect($monthlyPlan['plannedExpenseCategories'] ?? []);
        $allocationItems = collect($monthlyPlan['allocationItems'] ?? []);
        $plannedFreeCashFlow = max(0, $plannedIncome - (float) $plannedExpenses->sum('planned'));
        $plannedTotal = (float) $allocationItems->sum('amount');
        $plannedUnallocated = max(0, $plannedFreeCashFlow - $plannedTotal);
        $targetPercent = static fn (float $amount): ?float => $plannedIncome > 0 ? round($amount / $plannedIncome * 100, 1) : null;
        $rows = [];
        foreach ($plannedExpenses as $item) {
            $planned = (float) ($item['planned'] ?? 0);
            $rows[] = [
                'key' => 'expense_'.((string) ($item['categoryId'] ?? $item['label'] ?? 'unmapped')),
                'label' => (string) ($item['label'] ?? 'Uncategorized plan expense'),
                'amount' => (float) ($item['actual'] ?? 0),
                'target' => $targetPercent($planned),
            ];
        }
        foreach ($allocationItems as $item) {
            $planned = (float) ($item['amount'] ?? 0);
            $rows[] = [
                'key' => 'allocation_'.((string) ($item['planItemId'] ?? $item['bucketId'] ?? $item['label'] ?? 'unmapped')),
                'label' => (string) ($item['label'] ?? 'Unassigned allocation row'),
                'amount' => (float) ($item['actual'] ?? 0),
                'target' => $targetPercent($planned),
            ];
        }
        $actualTracking = is_array($monthlyPlan['actualTracking'] ?? null) ? $monthlyPlan['actualTracking'] : [];
        if ((float) ($actualTracking['unmappedExpenseAmount'] ?? 0) > 0) {
            $rows[] = ['key' => 'unmapped_expenses', 'label' => 'Unmapped actual expenses', 'amount' => (float) $actualTracking['unmappedExpenseAmount'], 'target' => null];
        }
        if ((float) ($actualTracking['unmappedPurposeAmount'] ?? 0) > 0) {
            $rows[] = ['key' => 'unmapped_allocations', 'label' => 'Unlinked actual allocations', 'amount' => (float) $actualTracking['unmappedPurposeAmount'], 'target' => null];
        }
        $rows[] = ['key' => 'planned_unallocated', 'label' => 'Planned unassigned remainder', 'amount' => 0.0, 'target' => $targetPercent($plannedUnallocated)];

        return [
            'income' => round($income, 2),
            'freeCashFlow' => round($freeCashFlow, 2),
            'savingsRate' => $income > 0 ? round($freeCashFlow / $income * 100, 1) : 0,
            'investmentRate' => $income > 0 ? round($invested / $income * 100, 1) : 0,
            'items' => collect($rows)->map(function (array $row) use ($income): array {
                $amount = round((float) $row['amount'], 2);

                return [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'amount' => $amount,
                    'percent' => $income > 0 ? round($amount / $income * 100, 1) : 0,
                    'targetPercent' => $row['target'] !== null ? (float) $row['target'] : null,
                ];
            })->values()->all(),
            'source' => 'current_month_review_or_confirmed_ledger',
        ];
    }

    /** @param array<string, mixed> $policy @return array<string, mixed> */
    private function financialFreedom(float $netWorth, float $reservedForGoals, float $emergency, float $expenses, float $invested, array $policy): array
    {
        $settings = is_array($policy['financialFreedom'] ?? null) ? $policy['financialFreedom'] : FinancialSetting::defaultFinancialFreedom();
        $withdrawalRate = max(1, min(10, (float) ($settings['withdrawal_rate_percent'] ?? 4)));
        $annualSpending = (float) ($settings['annual_spending_override_egp'] ?? 0) > 0
            ? (float) $settings['annual_spending_override_egp']
            : max(0, $expenses * 12);
        $target = $annualSpending > 0 ? $annualSpending / ($withdrawalRate / 100) : 0;
        $currentInvestable = max(0, $netWorth - $reservedForGoals - $emergency);
        $gap = max(0, $target - $currentInvestable);
        $annualInvestment = max(0, $invested * 12);

        return [
            'annualSpending' => round($annualSpending, 2),
            'withdrawalRatePercent' => $withdrawalRate,
            'target' => round($target, 2),
            'currentInvestable' => round($currentInvestable, 2),
            'gap' => round($gap, 2),
            'progressPercent' => $target > 0 ? round(min(100, $currentInvestable / $target * 100), 1) : 0,
            'annualInvestmentPace' => round($annualInvestment, 2),
            'yearsAtCurrentPace' => $gap > 0 && $annualInvestment > 0 ? round($gap / $annualInvestment, 1) : null,
            'source' => 'current_month_spending_and_configured_assumption',
            'limitations' => ['Planning estimate only; excludes investment growth, taxes, fees, inflation, and sequence-of-returns risk.'],
        ];
    }

    /** @param array<string, mixed> $financialFreedom @return array<string, mixed> */
    private function wealthStage(float $freeCashFlow, float $monthlyBase, float $emergency, float $invested, float $liabilities, float $netWorth, array $financialFreedom, int $reserveMonths): array
    {
        $coverage = $monthlyBase > 0 ? $emergency / $monthlyBase : 0;
        $debtRatio = $netWorth > 0 ? $liabilities / $netWorth : 1;
        $foundationReady = $freeCashFlow > 0 && $coverage >= 1 && $debtRatio <= 0.2;
        $growthReady = $foundationReady && $invested > 0;
        $freedomReady = (float) ($financialFreedom['progressPercent'] ?? 0) >= 100;
        $stage = $freedomReady ? 'freedom' : ($growthReady ? 'growth' : 'foundation');
        $labels = [
            'foundation' => ['number' => 1, 'label' => 'Foundation', 'description' => 'Create breathing room, protect the basics, and make your money predictable.', 'nextAction' => 'Keep essential spending and the emergency reserve visible.'],
            'growth' => ['number' => 2, 'label' => 'Growth', 'description' => 'Keep investing consistently while improving income, goals, and asset mix.', 'nextAction' => 'Protect your investment pace and review your monthly split.'],
            'freedom' => ['number' => 3, 'label' => 'Freedom', 'description' => 'Your configured portfolio target can cover the lifestyle you entered.', 'nextAction' => 'Review the assumption, spending plan, and withdrawal strategy regularly.'],
        ];
        $steps = [
            ['key' => 'foundation', 'number' => 1, 'label' => 'Foundation', 'ready' => $foundationReady],
            ['key' => 'growth', 'number' => 2, 'label' => 'Growth', 'ready' => $growthReady],
            ['key' => 'freedom', 'number' => 3, 'label' => 'Freedom', 'ready' => $freedomReady],
        ];

        return $labels[$stage] + [
            'key' => $stage,
            'coverageMonths' => round($coverage, 1),
            'reserveMonths' => $reserveMonths,
            'steps' => $steps,
        ];
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
    private function latestUpdate(?MonthlyFinancialReview $review, Collection $assets, ?array $marketRates = null): ?string
    {
        $timestamps = $assets->map(fn (Asset $asset) => $asset->updated_at ? $asset->updated_at->timestamp : 0)
            ->push($review && $review->updated_at ? $review->updated_at->timestamp : 0)
            ->push($marketRates && $marketRates['updatedAt'] ? Carbon::parse($marketRates['updatedAt'])->timestamp : 0);
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
        $projections = $liabilities->map(fn (Liability $liability): array => $this->debtPayoffProjection($liability));

        return [
            'monthlyRequiredPayments' => round($liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp) + $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2),
            'liabilityBalance' => round($liabilities->sum(fn (Liability $liability): float => (float) $liability->balance_egp), 2),
            'nextDueOn' => $nextDue ? Carbon::parse($nextDue)->toDateString() : null,
            'dueSoon' => $nextDue ? Carbon::parse($nextDue)->lte(now()->addDays(14)) : false,
            'estimatedMonthlyInterest' => round((float) $projections->sum('estimatedMonthlyInterest'), 2),
            'estimatedMonthlyPrincipal' => round((float) $projections->sum('estimatedMonthlyPrincipal'), 2),
            'projectedPayoffMonths' => $projections->pluck('estimatedRemainingMonths')->filter(fn (mixed $months): bool => is_int($months) || is_float($months))->max(),
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
