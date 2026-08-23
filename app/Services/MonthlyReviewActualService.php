<?php

namespace App\Services;

use App\Models\AllocationPlan;
use Carbon\CarbonInterface;

/** Applies quick monthly totals to the matching plan expense rules. */
class MonthlyReviewActualService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @param array<string, mixed> $data */
    public function syncManualPlanExpenseActuals(CarbonInterface $month, array $data): void
    {
        $summary = $this->ledger->summarizeMonth($month, true);
        if ($summary['source'] === 'confirmed_ledger') {
            return;
        }

        $plan = AllocationPlan::with('expenseItems.category')
            ->whereDate('month', $month->toDateString())
            ->first();
        if ($plan === null || $plan->status === 'closed') {
            return;
        }

        $actuals = [
            'essentials' => (float) $data['essential_expenses'],
            'lifestyle' => (float) $data['lifestyle_expenses'],
            'commitments' => (float) $data['recurring_commitments'] + (float) $data['debt_payments'],
            'flexible / irregular' => (float) $data['one_time_expenses'] + (float) ($data['manual_adjustment_egp'] ?? 0),
        ];

        foreach ($plan->expenseItems as $item) {
            $category = strtolower(trim((string) $item->category?->name));
            if (! array_key_exists($category, $actuals)) {
                continue;
            }

            $item->update([
                'actual_amount_egp' => round($actuals[$category], 2),
                'actual_source' => 'manual_review',
                'actual_synced_at' => now(),
            ]);
        }
    }
}
