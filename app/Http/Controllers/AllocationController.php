<?php

namespace App\Http\Controllers;

use App\Models\AllocationPlan;
use App\Models\AllocationPlanItem;
use App\Models\Bucket;
use App\Services\AllocationActualService;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public function index(Request $request, FinanceService $finance, AllocationActualService $actuals): Response
    {
        $month = $request->input('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()->toDateString()
            : now()->startOfMonth()->toDateString();
        $plan = AllocationPlan::with('items.bucket.goal')->whereDate('month', $month)->first();
        $actualTracking = $plan ? $actuals->preview($plan) : null;
        $dashboard = $finance->dashboard();

        return Inertia::render('allocations', [
            'month' => $month,
            'plan' => $plan ? ['id' => $plan->id, 'income' => (float) $plan->planned_income_egp, 'expenses' => (float) $plan->planned_expenses_egp, 'sourceReviewId' => $plan->source_review_id, 'generationMethod' => $plan->generation_method, 'items' => $plan->items->map(fn (AllocationPlanItem $item) => ['bucketId' => $item->bucket_id, 'bucketName' => $item->bucket->name, 'planned' => (float) $item->planned_amount_egp, 'actual' => $actualTracking['source'] === 'confirmed_ledger' ? (float) ($actualTracking['actuals'][$item->bucket_id] ?? 0) : (float) $item->actual_amount_egp, 'actualSource' => $actualTracking['source'] === 'confirmed_ledger' ? 'confirmed_ledger' : ($item->actual_source ?? 'manual')])] : null,
            'actualTracking' => $actualTracking,
            'buckets' => Bucket::orderBy('name')->get(['id', 'name']),
            'defaults' => [
                'income' => $dashboard['summary']['income'],
                'expenses' => $dashboard['summary']['expenses'],
                'items' => $dashboard['monthlyPlan']['allocationItems'],
                'source' => $dashboard['monthlyPlan']['source'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date'], 'planned_income_egp' => ['required', 'numeric', 'min:0'], 'planned_expenses_egp' => ['required', 'numeric', 'min:0'],
            'items' => ['array'], 'items.*.bucket_id' => ['required', 'distinct', 'exists:buckets,id'], 'items.*.planned_amount_egp' => ['required', 'numeric', 'min:0'], 'items.*.actual_amount_egp' => ['nullable', 'numeric', 'min:0'],
        ]);
        $available = (float) $data['planned_income_egp'] - (float) $data['planned_expenses_egp'];
        $plannedTotal = 0.0;
        foreach ($data['items'] ?? [] as $item) {
            $plannedTotal += (float) ($item['planned_amount_egp'] ?? 0);
        }
        if ($plannedTotal > max(0, $available) + 0.01) {
            throw ValidationException::withMessages([
                'items' => 'Monthly allocations cannot exceed the income left after planned expenses.',
            ]);
        }
        $month = Carbon::parse($data['month'])->startOfMonth();

        DB::transaction(function () use ($data, $month): void {
            $plan = AllocationPlan::whereDate('month', $month->toDateString())->first() ?? new AllocationPlan;
            $plan->fill(['month' => $month, 'planned_income_egp' => $data['planned_income_egp'], 'planned_expenses_egp' => $data['planned_expenses_egp']])->save();
            $plan->items()->delete();
            foreach ($data['items'] ?? [] as $item) {
                $plan->items()->create(['bucket_id' => $item['bucket_id'], 'planned_amount_egp' => $item['planned_amount_egp'], 'actual_amount_egp' => $item['actual_amount_egp'] ?? 0]);
            }
        });

        return back()->with('success', 'Monthly allocation plan saved.');
    }

    public function syncActuals(AllocationPlan $allocationPlan, AllocationActualService $actuals): RedirectResponse
    {
        $result = $actuals->sync($allocationPlan);

        return back()->with('success', $result['synced'] ? 'Actual amounts synced from confirmed ledger transactions.' : 'No confirmed ledger transactions were available for this month.');
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
