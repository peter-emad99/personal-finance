<?php

namespace App\Http\Controllers;

use App\Models\MonthlyFinancialReview;
use App\Services\FinanceService;
use App\Services\LedgerService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyReviewController extends Controller
{
    public function index(Request $request, FinanceService $finance): Response
    {
        $month = $this->month($request->input('month'));

        return Inertia::render('monthly-review', [
            'review' => $finance->monthlyReview($month),
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
        $ledger->deriveMonthlyReview($month, (bool) ($data['closed'] ?? false));

        return redirect()->route('monthly-review.index', ['month' => $month->format('Y-m')])->with('success', 'Monthly review derived from confirmed ledger transactions.');
    }

    public function close(MonthlyFinancialReview $review): RedirectResponse
    {
        $review->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('success', 'Monthly review closed.');
    }

    public function reopen(MonthlyFinancialReview $review): RedirectResponse
    {
        $review->update(['status' => 'open', 'reopened_at' => now()]);

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
                'expenses' => (float) $item->essential_expenses_egp + (float) $item->lifestyle_expenses_egp + (float) $item->recurring_commitments_egp + (float) $item->one_time_expenses_egp + (float) $item->debt_payments_egp,
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
}
