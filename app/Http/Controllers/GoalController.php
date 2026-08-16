<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Models\Goal;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        $archivedGoals = [];
        foreach (Goal::withTrashed()->whereNotNull('deleted_at')->orderByDesc('deleted_at')->get() as $goal) {
            $archivedGoals[] = [
                'id' => $goal->id,
                'name' => $goal->name,
                'targetAmount' => (float) $goal->target_amount_egp,
                'deadline' => $goal->deadline ? Carbon::parse($goal->deadline)->toDateString() : null,
                'archived' => true,
            ];
        }

        return Inertia::render('goals', [
            'goals' => $finance->dashboard()['goals'],
            'archivedGoals' => $archivedGoals,
            'buckets' => Bucket::with('goal')->orderBy('name')->get()->map(fn (Bucket $bucket) => [
                'id' => $bucket->id, 'name' => $bucket->name, 'goalId' => $bucket->goal_id,
                'currentAmount' => $finance->bucketValue($bucket), 'targetAmount' => (float) $bucket->target_amount_egp,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'target_amount_egp' => ['required', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'], 'priority' => ['required', 'integer', 'min:1', 'max:99'], 'monthly_contribution_egp' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:active,completed,paused'], 'notes' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($data): void {
            $goal = Goal::create($data + ['status' => 'active']);
            Bucket::create(['goal_id' => $goal->id, 'name' => $goal->name.' Fund', 'purpose' => 'Reserved for '.$goal->name, 'target_amount_egp' => $goal->target_amount_egp, 'color' => '#7c8cf8']);
        });

        return back()->with('success', 'Goal created with a dedicated bucket.');
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'target_amount_egp' => ['required', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'], 'priority' => ['required', 'integer', 'min:1', 'max:99'], 'monthly_contribution_egp' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,completed,paused'], 'notes' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($goal, $data): void {
            $goal->update($data);
            $goal->buckets()->update([
                'name' => $goal->name.' Fund',
                'purpose' => 'Reserved for '.$goal->name,
                'target_amount_egp' => $goal->target_amount_egp,
            ]);
        });

        return back()->with('success', 'Goal updated.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        DB::transaction(function () use ($goal): void {
            $goal->buckets()->delete();
            $goal->delete();
        });

        return back()->with('success', 'Goal removed.');
    }

    public function restore(int $goal): RedirectResponse
    {
        DB::transaction(function () use ($goal): void {
            $model = Goal::withTrashed()->findOrFail($goal);
            $model->restore();
            Bucket::withTrashed()->where('goal_id', $model->id)->restore();
        });

        return back()->with('success', 'Goal and its dedicated bucket restored.');
    }
}
