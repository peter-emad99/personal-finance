<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Models\Goal;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        return Inertia::render('goals', [
            'goals' => $finance->dashboard()['goals'],
            'buckets' => Bucket::with('goal')->orderBy('name')->get()->map(fn (Bucket $bucket) => [
                'id' => $bucket->id, 'name' => $bucket->name, 'goalId' => $bucket->goal_id,
                'currentAmount' => $finance->bucketValue($bucket), 'targetAmount' => (float) $bucket->target_amount_egp,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $goal = Goal::create($request->validate([
            'name' => ['required', 'string', 'max:120'], 'target_amount_egp' => ['required', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'], 'priority' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string'],
        ]));
        Bucket::create(['goal_id' => $goal->id, 'name' => $goal->name.' Fund', 'purpose' => 'Reserved for '.$goal->name, 'target_amount_egp' => $goal->target_amount_egp, 'color' => '#7c8cf8']);

        return back()->with('success', 'Goal created with a dedicated bucket.');
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        $goal->update($request->validate([
            'name' => ['required', 'string', 'max:120'], 'target_amount_egp' => ['required', 'numeric', 'min:0'],
            'deadline' => ['nullable', 'date'], 'priority' => ['required', 'integer', 'min:1', 'max:99'],
            'status' => ['required', 'in:active,completed,paused'], 'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Goal updated.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        $goal->delete();

        return back()->with('success', 'Goal removed.');
    }
}
