<?php

namespace App\Http\Controllers;

use App\Models\AllocationPlan;
use App\Services\AllocationActualService;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyPlansController extends Controller
{
    public function index(AllocationActualService $actuals): Response
    {
        return Inertia::render('monthly-plans', [
            'plans' => AllocationPlan::with(['template', 'items.asset', 'items.bucket.goal', 'expenseItems.category'])->orderByDesc('month')->get()->map(function (AllocationPlan $plan) use ($actuals): array {
                $tracking = $actuals->preview($plan);
                $actualExpenses = $tracking['source'] === 'confirmed_ledger'
                    ? (float) collect($tracking['expenseActuals'])->sum()
                    : (float) $plan->expenseItems->sum('actual_amount_egp');
                $actualSavings = $tracking['source'] === 'confirmed_ledger'
                    ? (float) collect($tracking['itemActuals'])->sum()
                    : (float) $plan->items->sum('actual_amount_egp');

                return [
                    'id' => $plan->id,
                    'month' => $plan->month->format('Y-m'),
                    'monthLabel' => $plan->month->format('F Y'),
                    'templateId' => $plan->plan_template_id,
                    'templateName' => $plan->template?->name ?? 'Manual / legacy',
                    'income' => (float) $plan->planned_income_egp,
                    'expenses' => (float) $plan->planned_expenses_egp,
                    'savings' => (float) $plan->items->sum('planned_amount_egp'),
                    'actualExpenses' => $actualExpenses,
                    'actualSavings' => $actualSavings,
                    'status' => $plan->status,
                    'closedAt' => $plan->closed_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }
}
