<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Bucket;
use App\Models\CashFlow;
use App\Models\Goal;
use App\Models\Liability;
use App\Models\MonthlyFinancialReview;
use App\Models\RecurringCommitment;
use App\Models\Snapshot;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FinanceService
{
    public const TARGET_ALLOCATION = [
        'Gold' => 20,
        'USD' => 25,
        'Egyptian equities' => 25,
        'Fixed income' => 20,
        'Cash' => 10,
    ];

    /** @return array<string, mixed> */
    public function dashboard(?CarbonInterface $asOf = null): array
    {
        $asOf ??= now();
        $monthStart = $asOf->copy()->startOfMonth();
        $assets = Asset::with('buckets')->orderByDesc('current_value_egp')->get();
        $buckets = Bucket::with('goal')->get();
        $goals = Goal::with('buckets')->where('status', 'active')->orderBy('priority')->get();
        $flows = CashFlow::whereBetween('occurred_on', [$monthStart, $asOf])->get();
        $review = MonthlyFinancialReview::whereDate('month', $monthStart)->first();
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $liabilities = Liability::where('is_active', true)->orderByDesc('balance_egp')->get();

        $totalLiabilities = (float) $liabilities->sum('balance_egp');
        $netWorth = (float) $assets->sum('current_value_egp') - $totalLiabilities;
        $liquidAssets = (float) $assets->where('is_liquid', true)->sum('current_value_egp');
        $reservedForGoals = (float) $this->goalBuckets($buckets)->sum(function (Bucket $bucket) {
            return $this->bucketValue($bucket);
        });
        $income = $review ? (float) $review->income_egp : (float) $flows->where('type', 'income')->sum('amount_egp');
        $expenses = $review
            ? $this->reviewExpenses($review)
            : (float) $flows->whereIn('type', ['expense', 'obligation'])->sum('amount_egp');
        $essentialExpenses = $review
            ? (float) $review->essential_expenses_egp + (float) $review->recurring_commitments_egp + (float) $review->debt_payments_egp
            : (float) $flows->where('type', 'expense')->where('category', 'essential')->sum('amount_egp');
        $emergency = (float) $buckets->filter(fn (Bucket $bucket) => str_contains(strtolower($bucket->name), 'emergency'))
            ->sum(fn (Bucket $bucket) => $this->bucketValue($bucket));
        $monthlyBase = $essentialExpenses ?: $expenses;
        $invested = $review ? (float) $review->invested_egp : 0;
        $recurringMonthly = $review
            ? (float) $review->recurring_commitments_egp
            : (float) $commitments->sum(fn (RecurringCommitment $commitment) => $commitment->monthlyAmount());

        return [
            'asOf' => $asOf->toDateString(),
            'summary' => [
                'netWorth' => $netWorth,
                'investableNetWorth' => max(0, $netWorth - $reservedForGoals),
                'liquidAssets' => $liquidAssets,
                'liabilities' => $totalLiabilities,
                'reservedForGoals' => $reservedForGoals,
                'income' => $income,
                'expenses' => $expenses,
                'freeCashFlow' => $income - $expenses,
                'emergencyFund' => $emergency,
                'emergencyCoverageMonths' => $monthlyBase > 0 ? round($emergency / $monthlyBase, 1) : 0,
                'investedThisMonth' => $invested,
                'recurringCommitments' => $recurringMonthly,
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
            'goals' => $goals->map(fn (Goal $goal) => $this->goalPayload($goal, $asOf))->values(),
            'liabilities' => $liabilities->map(fn (Liability $liability) => $this->liabilityPayload($liability))->values(),
            'recurringCommitments' => $commitments->map(fn (RecurringCommitment $commitment) => $this->commitmentPayload($commitment))->values(),
            'monthlyReview' => $this->monthlyReviewPayload($review, $monthStart, $flows, $commitments),
            'wealthMetrics' => $this->wealthMetrics($income, $expenses, $invested, $totalLiabilities, $netWorth),
            'wealthTrend' => $this->wealthTrend($asOf, $netWorth),
            'targetAllocation' => self::TARGET_ALLOCATION,
            'insights' => $this->insights($assets, $goals, $buckets, $income - $expenses, $emergency, $monthlyBase),
        ];
    }

    /** @return array<string, mixed> */
    public function exportContext(?CarbonInterface $asOf = null): array
    {
        $data = $this->dashboard($asOf);

        return [
            'as_of' => $data['asOf'],
            'monthly' => [
                'income_egp' => $data['summary']['income'],
                'expenses_egp' => $data['summary']['expenses'],
                'free_cash_flow_egp' => $data['summary']['freeCashFlow'],
            ],
            'summary' => $data['summary'],
            'assets' => $data['assets']->all(),
            'goals' => $data['goals']->all(),
            'buckets' => $data['buckets']->all(),
            'liabilities' => $data['liabilities']->all(),
            'recurring_commitments' => $data['recurringCommitments']->all(),
            'monthly_review' => $data['monthlyReview'],
            'wealth_metrics' => $data['wealthMetrics'],
            'wealth_trend' => $data['wealthTrend'],
            'asset_allocation' => $data['assetAllocation'],
            'currency_exposure' => $data['currencyExposure'],
            'liquidity' => $data['liquidity'],
            'insights' => $data['insights'],
        ];
    }

    public function bucketValue(Bucket $bucket): float
    {
        return (float) $bucket->assets->sum(fn (Asset $asset) => (float) data_get($asset, 'pivot.amount_egp', 0));
    }

    /** @return array<string, mixed> */
    public function monthlyReview(CarbonInterface $month): array
    {
        $month = $month->copy()->startOfMonth();
        $flows = CashFlow::whereBetween('occurred_on', [$month, $month->copy()->endOfMonth()])->get();
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $review = MonthlyFinancialReview::whereDate('month', $month)->first();

        return $this->monthlyReviewPayload($review, $month, $flows, $commitments);
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
        $afterPurchaseLiquid = max(0, (float) $dashboard['summary']['liquidAssets'] - $downPayment);
        $existingMonthlyExpenses = max(1, (float) $dashboard['summary']['expenses']);
        $coverage = $afterPurchaseLiquid / $existingMonthlyExpenses;
        $afterPurchaseFreeCashFlow = (float) $dashboard['summary']['freeCashFlow'] - $payment;
        $confidence = $coverage >= 6 && $afterPurchaseFreeCashFlow >= 0 ? 'comfortable' : ($coverage >= 3 && $afterPurchaseFreeCashFlow >= 0 ? 'review' : 'not ready');

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
            'warnings' => array_values(array_filter([
                $coverage < 6 ? 'The purchase would leave less than six months of current expenses liquid.' : null,
                $afterPurchaseFreeCashFlow < 0 ? 'The monthly payment would exceed current free cash flow.' : null,
                $mode === 'finance' && $annualInterest > 0 ? 'Add insurance, maintenance, fees, and depreciation before deciding.' : null,
            ])),
        ];
    }

    /**
     * @param  Collection<int, CashFlow>  $flows
     * @param  Collection<int, RecurringCommitment>  $commitments
     * @return array<string, mixed>
     */
    private function monthlyReviewPayload(?MonthlyFinancialReview $review, CarbonInterface $month, Collection $flows, Collection $commitments): array
    {
        if ($review) {
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
                'notes' => $review->notes,
                'status' => $review->status,
                'source' => 'review',
            ];
        }

        $income = (float) $flows->where('type', 'income')->sum('amount_egp');
        $expenses = (float) $flows->whereIn('type', ['expense', 'obligation'])->sum('amount_egp');

        return [
            'id' => null,
            'month' => $month->toDateString(),
            'income' => $income,
            'essentialExpenses' => (float) $flows->where('type', 'expense')->where('category', 'essential')->sum('amount_egp'),
            'lifestyleExpenses' => max(0, $expenses - (float) $flows->where('type', 'expense')->where('category', 'essential')->sum('amount_egp')),
            'recurringCommitments' => (float) $commitments->sum(fn (RecurringCommitment $commitment) => $commitment->monthlyAmount()),
            'oneTimeExpenses' => 0,
            'debtPayments' => (float) $flows->where('type', 'obligation')->sum('amount_egp'),
            'invested' => 0,
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
            + (float) $review->debt_payments_egp;
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
            'savingsRate' => $income > 0 ? round($invested / $income * 100, 1) : 0,
            'monthlyWealthContribution' => round($invested, 2),
            'debtToNetWorth' => $netWorth > 0 ? round($liabilities / $netWorth * 100, 1) : 0,
            'committedIncomeRate' => $income > 0 ? round(($expenses / $income) * 100, 1) : 0,
        ];
    }

    /** @return list<array{asOf: string, netWorth: float, investableNetWorth: float}> */
    private function wealthTrend(CarbonInterface $asOf, float $currentNetWorth): array
    {
        $snapshots = Snapshot::whereDate('as_of', '<=', $asOf)->orderByDesc('as_of')->limit(12)->get()->sortBy('as_of')->values();
        $trend = [];
        foreach ($snapshots as $snapshot) {
            $trend[] = [
                'asOf' => Carbon::parse($snapshot->as_of)->toDateString(),
                'netWorth' => (float) $snapshot->net_worth_egp,
                'investableNetWorth' => (float) $snapshot->investable_net_worth_egp,
            ];
        }

        $last = $trend[count($trend) - 1] ?? null;
        if ($last === null || $last['asOf'] !== $asOf->toDateString()) {
            $trend[] = ['asOf' => $asOf->toDateString(), 'netWorth' => $currentNetWorth, 'investableNetWorth' => $currentNetWorth];
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
        $monthlyFreeCashFlow = $review
            ? (float) $review->income_egp - $this->reviewExpenses($review)
            : (float) CashFlow::whereBetween('occurred_on', [$asOf->copy()->startOfMonth(), $asOf])
                ->where('type', 'income')->sum('amount_egp') - (float) CashFlow::whereBetween('occurred_on', [$asOf->copy()->startOfMonth(), $asOf])
                ->whereIn('type', ['expense', 'obligation'])->sum('amount_egp');

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'targetAmount' => (float) $goal->target_amount_egp,
            'allocatedAmount' => $allocated,
            'remainingAmount' => $remaining,
            'deadline' => $goal->deadline ? Carbon::parse($goal->deadline)->toDateString() : null,
            'monthsRemaining' => $months,
            'requiredMonthlyContribution' => round($required, 2),
            'fundingPercent' => $goal->target_amount_egp > 0 ? round($allocated / (float) $goal->target_amount_egp * 100, 1) : 0,
            'onTrack' => $required <= $monthlyFreeCashFlow,
            'gapPerMonth' => max(0, round($required - $monthlyFreeCashFlow, 2)),
        ];
    }

    /** @return array<string, mixed> */
    public function assetPayload(Asset $asset): array
    {
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
            'isLiquid' => $asset->is_liquid,
            'accountName' => $asset->account_name,
            'acquiredOn' => $asset->acquired_on ? Carbon::parse($asset->acquired_on)->toDateString() : null,
            'gainLoss' => (float) $asset->current_value_egp - (float) ($asset->cost_basis_egp ?: 0),
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
        $total = max(1, (float) $assets->sum('current_value_egp'));

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
        $total = max(1, (float) $assets->sum('current_value_egp'));

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
            $groups[$asset->liquidity] = ($groups[$asset->liquidity] ?? 0) + (float) $asset->current_value_egp;
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
    private function insights(Collection $assets, Collection $goals, Collection $buckets, float $freeCashFlow, float $emergency, float $monthlyBase): array
    {
        $insights = [];
        foreach ($goals as $goal) {
            $payload = $this->goalPayload($goal, now());
            if (! $payload['onTrack'] && $payload['gapPerMonth'] > 0) {
                $insights[] = "{$goal->name} needs ".number_format($payload['requiredMonthlyContribution']).' EGP/month, above your current free cash flow.';
            }
        }
        if ($monthlyBase > 0 && $emergency / $monthlyBase < 6) {
            $insights[] = 'Your emergency fund is below the 6-month baseline.';
        }
        $unallocated = (float) $assets->sum('current_value_egp') - (float) $this->goalBuckets($buckets)->sum(fn (Bucket $bucket) => $this->bucketValue($bucket));
        if ($unallocated > 0) {
            $insights[] = number_format($unallocated).' EGP is not currently assigned to a goal bucket.';
        }

        return $insights;
    }
}
