<?php

namespace App\Http\Controllers;

use App\Models\AllocationPlan;
use App\Models\Bucket;
use App\Models\BudgetCategory;
use App\Models\FinancialSetting;
use App\Models\Goal;
use App\Models\Liability;
use App\Models\MonthlyFinancialReview;
use App\Models\RecurringCommitment;
use App\Services\AllocationActualService;
use App\Services\BudgetRuleService;
use App\Services\FinanceService;
use App\Services\LedgerService;
use App\Services\MonthlyReviewActualService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyReviewController extends Controller
{
    public function index(Request $request, FinanceService $finance, AllocationActualService $actuals): Response
    {
        $month = $this->month($request->input('month'));
        $plan = AllocationPlan::with(['template', 'items.asset', 'items.bucket.goal', 'expenseItems.category', 'sourceReview'])->whereDate('month', $month)->first();
        $actualTracking = $plan ? $actuals->preview($plan) : null;
        $review = $finance->monthlyReview($month);
        $reviewModel = MonthlyFinancialReview::whereDate('month', $month)->first();
        $nextMonthProposal = null;
        if ($reviewModel?->status === 'closed') {
            $proposal = $this->nextMonthPlan($reviewModel, $finance);
            $nextMonthProposal = $proposal['alreadyExists'] ? null : $proposal;
        }

        return Inertia::render('monthly-review', [
            'review' => $review,
            'plan' => $plan ? [
                'templateName' => $plan->template?->name,
                'status' => $plan->status,
                'income' => (float) $plan->planned_income_egp,
                'expenses' => (float) $plan->planned_expenses_egp,
                'freeCashFlow' => (float) $plan->planned_income_egp - (float) $plan->planned_expenses_egp,
                'generationMethod' => $plan->generation_method,
                'sourceReviewId' => $plan->source_review_id,
                'sourceReviewMonth' => $plan->sourceReview?->month ? Carbon::parse($plan->sourceReview->month)->format('Y-m') : null,
                'generatedAt' => $plan->generated_at ? Carbon::parse($plan->generated_at)->toIso8601String() : null,
                'allocations' => $plan->items->map(fn ($item): array => [
                    'label' => ($item->asset_target ?: $item->asset?->name)
                        ? ($item->asset_target ?: $item->asset?->name).' → '.($item->bucket?->name ?? 'Unassigned bucket')
                        : ($item->bucket?->name ?? 'Unassigned bucket'),
                    'planned' => (float) $item->planned_amount_egp,
                    'actual' => $actualTracking && $actualTracking['source'] === 'confirmed_ledger'
                        ? (float) ($actualTracking['itemActuals'][$item->id] ?? $actualTracking['actuals'][$item->bucket_id] ?? 0)
                        : (float) $item->actual_amount_egp,
                ])->values(),
                'expenseItems' => $plan->expenseItems->map(fn ($item): array => [
                    'label' => $item->category?->name ?? 'Uncategorized',
                    'planned' => (float) $item->planned_amount_egp,
                    'actual' => $actualTracking && $actualTracking['source'] === 'confirmed_ledger'
                        ? (float) ($actualTracking['expenseActuals'][$item->budget_category_id] ?? 0)
                        : (float) $item->actual_amount_egp,
                ])->values(),
            ] : null,
            'actualTracking' => $actualTracking,
            'nextMonthProposal' => $nextMonthProposal,
            'history' => $this->history(),
        ]);
    }

    public function store(Request $request, MonthlyReviewActualService $manualActuals): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'income' => ['required', 'numeric', 'min:0'],
            'essential_expenses' => ['required', 'numeric', 'min:0'],
            'lifestyle_expenses' => ['required', 'numeric', 'min:0'],
            'recurring_commitments' => ['required', 'numeric', 'min:0'],
            'one_time_expenses' => ['required', 'numeric', 'min:0'],
            'debt_payments' => ['required', 'numeric', 'min:0'],
            'invested' => ['required', 'numeric', 'min:0'],
            'manual_adjustment_egp' => ['nullable', 'numeric'],
            'status' => ['sometimes', 'in:open'],
            'notes' => ['nullable', 'string'],
        ]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        $review = MonthlyFinancialReview::whereDate('month', $month->toDateString())->first() ?? new MonthlyFinancialReview(['month' => $month->toDateString()]);
        if ($review->exists && $review->status === 'closed') {
            throw ValidationException::withMessages(['review' => 'This month is closed. Reopen it before editing.']);
        }
        DB::transaction(function () use ($data, $month, $review, $manualActuals): void {
            $review->fill([
                'income_egp' => $data['income'],
                'essential_expenses_egp' => $data['essential_expenses'],
                'lifestyle_expenses_egp' => $data['lifestyle_expenses'],
                'recurring_commitments_egp' => $data['recurring_commitments'],
                'one_time_expenses_egp' => $data['one_time_expenses'],
                'debt_payments_egp' => $data['debt_payments'],
                'invested_egp' => $data['invested'],
                'manual_adjustment_egp' => $data['manual_adjustment_egp'] ?? 0,
                'status' => 'open',
                'reconciliation_status' => 'pending',
                'reconciled_at' => null,
                'notes' => ($data['notes'] ?? null) ?: null,
            ])->save();
            $manualActuals->syncManualPlanExpenseActuals($month, $data);
        });

        return redirect()->route('monthly-review.index', ['month' => $month->format('Y-m')])->with('success', 'Monthly review saved.');
    }

    public function destroy(MonthlyFinancialReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Monthly review archived.');
    }

    public function derive(Request $request, LedgerService $ledger): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        if ($request->boolean('closed')) {
            throw ValidationException::withMessages(['review' => 'Deriving actuals leaves the review open. Close it after review.']);
        }
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $existing = MonthlyFinancialReview::whereDate('month', $month->toDateString())->first();
        if ($existing?->status === 'closed') {
            throw ValidationException::withMessages(['review' => 'This month is closed. Reopen it before deriving new actuals.']);
        }
        $ledger->deriveMonthlyReview($month);

        return redirect()->route('monthly-review.index', ['month' => $month->format('Y-m')])->with('success', 'Monthly review derived from confirmed ledger transactions.');
    }

    public function close(MonthlyFinancialReview $review, FinanceService $finance, LedgerService $ledger): RedirectResponse
    {
        if ($review->status === 'closed') {
            return back()->with('success', 'Monthly review is already closed.');
        }
        $summary = $ledger->summarizeMonth(Carbon::parse($review->month), true);
        $hasConfirmedLedger = $summary['source'] === 'confirmed_ledger';
        $review->update([
            'status' => 'closed',
            'closed_at' => now(),
            'obligation_snapshot' => $this->obligationSnapshot(),
            'reconciliation_status' => $hasConfirmedLedger ? 'matched' : 'reviewed',
            'reconciled_at' => $hasConfirmedLedger ? now() : null,
            'source_transaction_count' => (int) ($summary['sourceTransactionCount'] ?? 0),
        ]);

        $message = 'Monthly review closed.';
        $settings = FinancialSetting::active();
        if ((bool) data_get($settings->policy, 'auto_prepare_next_month', false)) {
            $proposal = $this->nextMonthPlan($review, $finance);
            if (! $proposal['alreadyExists']) {
                $this->persistNextMonthPlan($review, $proposal);
                $message = 'Monthly review closed and the next month plan was prepared safely because no plan existed yet.';
            }
        }

        return back()->with('success', $message);
    }

    public function prepareNext(Request $request, MonthlyFinancialReview $review, FinanceService $finance): RedirectResponse
    {
        if ($review->status !== 'closed') {
            throw ValidationException::withMessages(['review' => 'Close this month before preparing the next month.']);
        }

        $redirectTarget = $request->validate([
            'redirect_emergency_to' => ['nullable', 'in:goals,investments'],
            'lesson' => ['nullable', 'string', 'max:2000'],
        ])['redirect_emergency_to'] ?? 'investments';
        $proposal = $this->nextMonthPlan($review, $finance, $redirectTarget);
        if ($proposal['alreadyExists']) {
            $nextMonth = Carbon::parse($review->month)->startOfMonth()->addMonth();

            return redirect()->route('allocations.index', ['month' => $nextMonth->format('Y-m')])->with('success', 'A plan for next month already exists.');
        }

        $this->persistNextMonthPlan($review, $proposal, $request->input('lesson'));

        $nextMonth = Carbon::parse($proposal['nextMonth']);

        return redirect()->route('allocations.index', ['month' => $nextMonth->format('Y-m')])->with('success', 'Next month’s plan was prepared from this review.');
    }

    /** @param array<string, mixed> $proposal */
    private function persistNextMonthPlan(MonthlyFinancialReview $review, array $proposal, ?string $lesson = null): void
    {
        DB::transaction(function () use ($review, $proposal, $lesson): void {
            $sourcePlan = AllocationPlan::with(['template'])->whereDate('month', Carbon::parse($review->month)->startOfMonth()->toDateString())->first();
            $rules = app(BudgetRuleService::class);
            $template = $sourcePlan?->template ?? $rules->ensureDefaultTemplate((float) $proposal['plannedIncome']);
            $plan = AllocationPlan::create([
                'month' => $proposal['nextMonth'],
                'plan_template_id' => $template->id,
                'planned_income_egp' => $proposal['plannedIncome'],
                'planned_expenses_egp' => $proposal['plannedExpenses'],
                'source_review_id' => $review->id,
                'generation_method' => 'prepared_from_review',
                'generated_at' => now(),
                'notes' => $lesson
                    ? 'Lesson carried forward: '.$lesson
                    : 'Prepared from the closed review and current obligations.',
            ]);
            foreach ($proposal['incomeItems'] ?? [['name' => 'Monthly income', 'planned' => $proposal['plannedIncome'], 'budgetRuleId' => null]] as $incomeItem) {
                $plan->incomeItems()->create([
                    'budget_rule_id' => $incomeItem['budgetRuleId'] ?? null,
                    'name' => $incomeItem['name'],
                    'planned_amount_egp' => $incomeItem['planned'],
                    'actual_amount_egp' => 0,
                ]);
            }
            foreach ($proposal['allocations'] as $item) {
                if ($item['amount'] > 0) {
                    $plan->items()->create([
                        'bucket_id' => $item['bucketId'],
                        'asset_id' => $item['assetId'] ?? null,
                        'asset_target' => $item['assetTarget'] ?? null,
                        'allocation_percent' => $item['allocationPercent'] ?? null,
                        'planned_amount_egp' => $item['amount'],
                        'actual_amount_egp' => 0,
                    ]);
                }
            }
            foreach ($proposal['expenseItems'] ?? [] as $expense) {
                $plan->expenseItems()->create(['budget_category_id' => $expense['categoryId'], 'planned_amount_egp' => $expense['planned'], 'actual_amount_egp' => 0]);
            }
        });
    }

    /** @return array<string, mixed> */
    private function nextMonthPlan(MonthlyFinancialReview $review, FinanceService $finance, string $redirectTarget = 'investments'): array
    {
        $nextMonth = Carbon::parse($review->month)->startOfMonth()->addMonth();
        if (AllocationPlan::whereDate('month', $nextMonth)->exists()) {
            return ['alreadyExists' => true, 'nextMonth' => $nextMonth->toDateString()];
        }
        $commitments = RecurringCommitment::where('is_active', true)->get();
        $liabilities = Liability::where('is_active', true)->get();
        $monthlyCommitments = round((float) $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2);
        $monthlyDebtPayments = round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp), 2);
        $plannedIncome = (float) $review->income_egp;
        $plannedExpenses = round((float) $review->essential_expenses_egp + (float) $review->lifestyle_expenses_egp + $monthlyCommitments + $monthlyDebtPayments, 2);
        $available = max(0, $plannedIncome - $plannedExpenses);
        $settings = FinancialSetting::active();
        $emergencyBucket = Bucket::where('purpose_type', 'emergency')->with('assets')->first();
        $currentEmergency = $emergencyBucket ? $finance->bucketValue($emergencyBucket) : 0;
        $monthlyBase = (float) $review->essential_expenses_egp + $monthlyCommitments + $monthlyDebtPayments;
        $emergencyTarget = round($monthlyBase * (int) ($settings->emergency_reserve_months ?: 6), 2);
        $emergencyGap = max(0, round($emergencyTarget - $currentEmergency, 2));

        $sourcePlan = AllocationPlan::with('template')->whereDate('month', Carbon::parse($review->month)->startOfMonth()->toDateString())->first();
        if ($sourcePlan?->template !== null) {
            $rules = app(BudgetRuleService::class);
            $template = $rules->template($sourcePlan->template->id, $plannedIncome);
            $incomeBase = $plannedIncome;
            $plannedIncome = $rules->templateIncome($template, $incomeBase);
            $expenseItems = $rules->templateExpenseSuggestions($template, $plannedIncome)
                ->map(fn (array $item): array => [
                    'categoryId' => $item['categoryId'],
                    'categoryName' => $item['categoryName'],
                    'planned' => (float) $item['planned'],
                ]);
            $commitmentCategory = $expenseItems->first(fn (array $item): bool => strtolower($item['categoryName']) === 'commitments');
            if ($monthlyDebtPayments > 0) {
                if ($commitmentCategory !== null) {
                    $expenseItems = $expenseItems->map(fn (array $item): array => $item['categoryId'] === $commitmentCategory['categoryId']
                        ? $item + ['planned' => round($item['planned'] + $monthlyDebtPayments, 2)]
                        : $item);
                } else {
                    $category = app(BudgetRuleService::class)->ensureDefaultCategories()->first(fn (BudgetCategory $item): bool => strtolower($item->name) === 'commitments');
                    if ($category !== null) {
                        $expenseItems->push(['categoryId' => $category->id, 'categoryName' => $category->name, 'planned' => $monthlyDebtPayments]);
                    }
                }
            }
            $plannedExpenses = round((float) $expenseItems->sum('planned'), 2);
            $available = max(0, $plannedIncome - $plannedExpenses);
            $allocations = $rules->templateAllocationSuggestions($template, $plannedIncome, $plannedExpenses)
                ->filter(fn (array $item): bool => $item['amount'] > 0)
                ->map(fn (array $item): array => [
                    'bucketId' => $item['bucketId'],
                    'label' => $item['label'],
                    'kind' => $item['kind'],
                    'amount' => $item['amount'],
                    'assetId' => $item['assetId'],
                    'assetTarget' => $item['assetTarget'],
                    'allocationPercent' => $item['allocationPercent'],
                ])->values();
            $emergencyContribution = round((float) $allocations->where('kind', 'emergency')->sum('amount'), 2);

            return [
                'alreadyExists' => false,
                'nextMonth' => $nextMonth->toDateString(),
                'plannedIncome' => round($plannedIncome, 2),
                'plannedExpenses' => $plannedExpenses,
                'available' => round($available, 2),
                'currentEmergency' => round($currentEmergency, 2),
                'emergencyTarget' => $emergencyTarget,
                'emergencyGap' => round($emergencyGap, 2),
                'emergencyContribution' => $emergencyContribution,
                'reserveComplete' => $emergencyGap <= 0.01,
                'redirectAmount' => 0,
                'redirectTarget' => null,
                'lesson' => $review->notes,
                'templateId' => $template->id,
                'templateName' => $template->name,
                'incomeItems' => $rules->templateIncomeSuggestions($template, $incomeBase)->map(fn (array $item): array => [
                    'budgetRuleId' => $item['id'],
                    'name' => $item['label'],
                    'planned' => $item['amount'],
                ])->values()->all(),
                'expenseItems' => $expenseItems->values()->all(),
                'allocations' => $allocations->all(),
            ];
        }

        $emergencyContribution = min($emergencyGap, round($available * 0.2, 2));
        $reserveComplete = $emergencyGap <= 0.01;
        $redirectAmount = $reserveComplete ? round(min($available, $available * 0.2), 2) : 0;
        $remaining = max(0, $available - $emergencyContribution);
        $goalAllocations = [];
        foreach (Goal::with('buckets')->where('status', 'active')->orderBy('priority')->get() as $goal) {
            $amount = min($remaining, max(0, (float) $goal->monthly_contribution_egp));
            $bucket = $goal->buckets->first();
            if ($bucket !== null && $amount > 0) {
                $goalAllocations[] = ['bucketId' => $bucket->id, 'label' => $bucket->name, 'kind' => 'goal', 'amount' => round($amount, 2)];
                $remaining = max(0, $remaining - $amount);
            }
        }
        $investmentContribution = $remaining;
        if ($reserveComplete && $redirectTarget === 'goals' && $redirectAmount > 0 && count($goalAllocations) > 0) {
            $goalAllocations[0]['amount'] = round($goalAllocations[0]['amount'] + min($redirectAmount, $investmentContribution), 2);
            $investmentContribution = max(0, $investmentContribution - $redirectAmount);
        }
        $investmentBucket = Bucket::where('purpose_type', 'investment')->orderBy('name')->first();
        $allocations = collect();
        if ($emergencyBucket !== null && $emergencyContribution > 0) {
            $allocations->push(['bucketId' => $emergencyBucket->id, 'label' => $emergencyBucket->name, 'kind' => 'emergency', 'amount' => round($emergencyContribution, 2)]);
        }
        foreach ($goalAllocations as $item) {
            $allocations->push($item);
        }
        if ($investmentBucket !== null && $investmentContribution > 0) {
            $allocations->push(['bucketId' => $investmentBucket->id, 'label' => $investmentBucket->name, 'kind' => 'investment', 'amount' => round($investmentContribution, 2)]);
        }

        $categories = app(BudgetRuleService::class)->ensureDefaultCategories()->keyBy(fn (BudgetCategory $category): string => strtolower($category->name));
        $expenseItems = collect([
            ['category' => 'essentials', 'planned' => (float) $review->essential_expenses_egp],
            ['category' => 'lifestyle', 'planned' => (float) $review->lifestyle_expenses_egp],
            ['category' => 'commitments', 'planned' => round($monthlyCommitments + $monthlyDebtPayments, 2)],
        ])->map(function (array $item) use ($categories): ?array {
            $category = $categories->get($item['category']);

            return $category ? ['categoryId' => $category->id, 'categoryName' => $category->name, 'planned' => round($item['planned'], 2)] : null;
        })->filter()->values();

        return [
            'alreadyExists' => false,
            'nextMonth' => $nextMonth->toDateString(),
            'plannedIncome' => round($plannedIncome, 2),
            'plannedExpenses' => $plannedExpenses,
            'available' => round($available, 2),
            'currentEmergency' => round($currentEmergency, 2),
            'emergencyTarget' => $emergencyTarget,
            'emergencyGap' => round($emergencyGap, 2),
            'emergencyContribution' => round($emergencyContribution, 2),
            'reserveComplete' => $reserveComplete,
            'redirectAmount' => $redirectAmount,
            'redirectTarget' => $reserveComplete ? $redirectTarget : null,
            'lesson' => $review->notes,
            'incomeItems' => [['budgetRuleId' => null, 'name' => 'Monthly income', 'planned' => round($plannedIncome, 2)]],
            'expenseItems' => $expenseItems->all(),
            'allocations' => $allocations->values()->all(),
        ];
    }

    public function reopen(MonthlyFinancialReview $review): RedirectResponse
    {
        $review->update(['status' => 'open', 'reopened_at' => now(), 'reconciliation_status' => 'pending', 'reconciled_at' => null]);

        return back()->with('success', 'Monthly review reopened for a recorded revision.');
    }

    public function restore(int $review): RedirectResponse
    {
        MonthlyFinancialReview::withTrashed()->findOrFail($review)->restore();

        return back()->with('success', 'Monthly review restored.');
    }

    /** @return list<array<string, mixed>> */
    private function history(): array
    {
        $history = [];
        foreach (MonthlyFinancialReview::orderByDesc('month')->limit(18)->get() as $item) {
            $history[] = [
                'month' => Carbon::parse($item->month)->format('Y-m'),
                'income' => (float) $item->income_egp,
                'expenses' => (float) $item->essential_expenses_egp + (float) $item->lifestyle_expenses_egp + (float) $item->recurring_commitments_egp + (float) $item->one_time_expenses_egp + (float) $item->debt_payments_egp + (float) $item->manual_adjustment_egp,
                'invested' => (float) $item->invested_egp,
                'status' => $item->status,
            ];
        }

        return $history;
    }

    private function month(?string $value): CarbonInterface
    {
        return $value && preg_match('/^\d{4}-\d{2}$/', $value)
            ? Carbon::createFromFormat('Y-m', $value)->startOfMonth()
            : now()->startOfMonth();
    }

    /** @return array<string, mixed> */
    private function obligationSnapshot(): array
    {
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $liabilities = Liability::where('is_active', true)->orderBy('name')->get();

        return [
            'capturedAt' => now()->toIso8601String(),
            'commitments' => [
                'configuredMonthly' => round((float) $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2),
                'items' => $commitments->map(fn (RecurringCommitment $commitment): array => [
                    'id' => $commitment->id,
                    'name' => $commitment->name,
                    'monthlyAmount' => $commitment->monthlyAmount(),
                ])->values()->all(),
            ],
            'liabilities' => [
                'configuredMonthlyPayments' => round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp), 2),
                'items' => $liabilities->map(fn (Liability $liability): array => [
                    'id' => $liability->id,
                    'name' => $liability->name,
                    'balance' => (float) $liability->balance_egp,
                    'monthlyPayment' => (float) $liability->monthly_payment_egp,
                ])->values()->all(),
            ],
        ];
    }
}
