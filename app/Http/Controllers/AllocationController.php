<?php

namespace App\Http\Controllers;

use App\Models\AllocationPlan;
use App\Models\AllocationPlanItem;
use App\Models\Bucket;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AllocationController extends Controller
{
    public function reconciliation(FinanceService $finance): Response
    {
        $dashboard = $finance->dashboard();

        return Inertia::render('allocation-reconciliation', [
            'summary' => [
                'totalAssets' => $dashboard['summary']['totalAssets'],
                'fullyAssignedPurposeBalance' => $dashboard['summary']['allocatedToGoals'] + $dashboard['summary']['allocatedToNonGoals'],
                'allocatedToGoals' => $dashboard['summary']['allocatedToGoals'],
                'allocatedToNonGoals' => $dashboard['summary']['allocatedToNonGoals'],
                'trulyUnallocated' => $dashboard['summary']['unallocated'],
            ],
            'assets' => $dashboard['assets'],
            'buckets' => $dashboard['buckets'],
            'dataFreshness' => $dashboard['dataFreshness'],
        ]);
    }

    public function index(): Response
    {
        $month = now()->startOfMonth()->toDateString();
        $plan = AllocationPlan::with('items.bucket')->where('month', $month)->first();

        return Inertia::render('allocations', [
            'month' => $month,
            'plan' => $plan ? ['id' => $plan->id, 'income' => (float) $plan->planned_income_egp, 'expenses' => (float) $plan->planned_expenses_egp, 'items' => $plan->items->map(fn (AllocationPlanItem $item) => ['bucketId' => $item->bucket_id, 'bucketName' => $item->bucket->name, 'planned' => (float) $item->planned_amount_egp, 'actual' => (float) $item->actual_amount_egp])] : null,
            'buckets' => Bucket::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date'], 'planned_income_egp' => ['required', 'numeric', 'min:0'], 'planned_expenses_egp' => ['required', 'numeric', 'min:0'],
            'items' => ['array'], 'items.*.bucket_id' => ['required', 'distinct', 'exists:buckets,id'], 'items.*.planned_amount_egp' => ['required', 'numeric', 'min:0'], 'items.*.actual_amount_egp' => ['nullable', 'numeric', 'min:0'],
        ]);
        DB::transaction(function () use ($data): void {
            $plan = AllocationPlan::updateOrCreate(['month' => $data['month']], ['planned_income_egp' => $data['planned_income_egp'], 'planned_expenses_egp' => $data['planned_expenses_egp']]);
            $plan->items()->delete();
            foreach ($data['items'] ?? [] as $item) {
                $plan->items()->create(['bucket_id' => $item['bucket_id'], 'planned_amount_egp' => $item['planned_amount_egp'], 'actual_amount_egp' => $item['actual_amount_egp'] ?? 0]);
            }
        });

        return back()->with('success', 'Monthly allocation plan saved.');
    }

    public function destroy(AllocationPlan $allocationPlan): RedirectResponse
    {
        $allocationPlan->delete();

        return back()->with('success', 'Allocation plan archived.');
    }

    public function restore(int $allocationPlan): RedirectResponse
    {
        AllocationPlan::withTrashed()->findOrFail($allocationPlan)->restore();

        return back()->with('success', 'Allocation plan restored.');
    }
}
