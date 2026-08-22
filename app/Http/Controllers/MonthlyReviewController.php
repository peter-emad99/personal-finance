<?php

namespace App\Http\Controllers;

use App\Models\MonthlyFinancialReview;
use App\Models\AllocationPlan;
use App\Models\Bucket;
use App\Models\FinancialSetting;
use App\Models\Goal;
use App\Models\Liability;
use App\Models\RecurringCommitment;
use App\Services\FinanceService;
use App\Services\AllocationActualService;
use App\Services\LedgerService;
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
        $plan = AllocationPlan::with(['items.bucket.goal', 'sourceReview'])->whereDate('month', $month)->first();
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
                'income' => (float) $plan->planned_income_egp,
                'expenses' => (float) $plan->planned_expenses_egp,
                'freeCashFlow' => (float) $plan->planned_income_egp - (float) $plan->planned_expenses_egp,
                'generationMethod' => $plan->generation_method,
                'sourceReviewId' => $plan->source_review_id,
                'sourceReviewMonth' => $plan->sourceReview?->month ? Carbon::parse($plan->sourceReview->month)->format('Y-m') : null,
                'generatedAt' => $plan->generated_at ? Carbon::parse($plan->generated_at)->toIso8601String() : null,
                'allocations' => $plan->items->map(fn ($item): array => [
                    'label' => $item->bucket->name,
                    'planned' => (float) $item->planned_amount_egp,
                    'actual' => $actualTracking && $actualTracking['source'] === 'confirmed_ledger'
                        ? (float) ($actualTracking['actuals'][$item->bucket_id] ?? 0)
                        : (float) $item->actual_amount_egp,
                ])->values(),
            ] : null,
            'actualTracking' => $actualTracking,
            'nextMonthProposal' => $nextMonthProposal,
            'history' => $this->history(),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            'status' => ['required', 'in:open,closed'],
            'notes' => ['nullable', 'string'],
        ]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();

        $review = MonthlyFinancialReview::whereDate('month', $month->toDateString())->first() ?? new MonthlyFinancialReview(['month' => $month->toDateString()]);
        $review->fill([
            'income_egp' => $data['income'],
            'essential_expenses_egp' => $data['essential_expenses'],
            'lifestyle_expenses_egp' => $data['lifestyle_expenses'],
            'recurring_commitments_egp' => $data['recurring_commitments'],
            'one_time_expenses_egp' => $data['one_time_expenses'],
            'debt_payments_egp' => $data['debt_payments'],
            'invested_egp' => $data['invested'],
            'manual_adjustment_egp' => $data['manual_adjustment_egp'] ?? 0,
            'status' => $data['status'],
            'notes' => $data['notes'] ?: null,
        ])->save();

        if ($data['status'] === 'closed') {
            $review->update([
                'obligation_snapshot' => $this->obligationSnapshot(),
                'reconciliation_status' => 'matched',
                'reconciled_at' => now(),
            ]);
        }

        return redirect()->route('monthly-review.index', ['month' => $month->format('Y-m')])->with('success', 'Monthly review saved.');
    }

    public function destroy(MonthlyFinancialReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Monthly review archived.');
    }

    public function derive(Request $request, LedgerService $ledger): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m'], 'closed' => ['sometimes', 'boolean']]);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $review = $ledger->deriveMonthlyReview($month, (bool) ($data['closed'] ?? false));
        if ((bool) ($data['closed'] ?? false)) {
            $review->update([
                'obligation_snapshot' => $this->obligationSnapshot(),
                'reconciliation_status' => 'matched',
                'reconciled_at' => now(),
            ]);
        }

        return redirect()->route('monthly-review.index', ['month' => $month->format('Y-m')])->with('success', 'Monthly review derived from confirmed ledger transactions.');
    }

    public function close(MonthlyFinancialReview $review, FinanceService $finance): RedirectResponse
    {
        $review->update([
            'status' => 'closed',
            'closed_at' => now(),
            'obligation_snapshot' => $this->obligationSnapshot(),
            'reconciliation_status' => 'matched',
            'reconciled_at' => now(),
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
            $plan = AllocationPlan::create([
                'month' => $proposal['nextMonth'],
                'planned_income_egp' => $proposal['plannedIncome'],
                'planned_expenses_egp' => $proposal['plannedExpenses'],
                'source_review_id' => $review->id,
                'generation_method' => 'prepared_from_review',
                'generated_at' => now(),
                'notes' => $lesson
                    ? 'Lesson carried forward: '.$lesson
                    : 'Prepared from the closed review and current obligations.',
            ]);
            foreach ($proposal['allocations'] as $item) {
                if ($item['amount'] > 0) {
                    $plan->items()->create(['bucket_id' => $item['bucketId'], 'planned_amount_egp' => $item['amount'], 'actual_amount_egp' => 0]);
                }
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
        $emergencyBucket = Bucket::whereNull('goal_id')->where('name', 'like', '%Emergency%')->with('assets')->first();
        $currentEmergency = $emergencyBucket ? $finance->bucketValue($emergencyBucket) : 0;
        $monthlyBase = (float) $review->essential_expenses_egp + $monthlyCommitments + $monthlyDebtPayments;
        $emergencyTarget = round($monthlyBase * (int) ($settings->emergency_reserve_months ?: 6), 2);
        $emergencyGap = max(0, round($emergencyTarget - $currentEmergency, 2));
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
        $investmentBucket = Bucket::whereNull('goal_id')->where('name', 'not like', '%Emergency%')->orderBy('name')->first();
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
