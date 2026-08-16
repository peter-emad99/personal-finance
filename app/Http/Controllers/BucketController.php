<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BucketController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        return Inertia::render('buckets', ['buckets' => Bucket::withTrashed()->with(['goal', 'assets'])->orderBy('name')->get()->map(fn (Bucket $bucket) => [
            'id' => $bucket->id, 'name' => $bucket->name, 'purpose' => $bucket->purpose, 'color' => $bucket->color,
            'goalName' => $bucket->goal?->name, 'targetAmount' => (float) $bucket->target_amount_egp, 'currentAmount' => $finance->bucketValue($bucket),
            'assetCount' => $bucket->assets->count(), 'archived' => $bucket->trashed(), 'goalId' => $bucket->goal_id,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Bucket::create($this->validated($request));

        return back()->with('success', 'Bucket created.');
    }

    public function update(Request $request, Bucket $bucket): RedirectResponse
    {
        $bucket->update($this->validated($request));

        return back()->with('success', 'Bucket updated.');
    }

    public function destroy(Bucket $bucket): RedirectResponse
    {
        abort_if($bucket->goal_id !== null, 422, 'Goal buckets are managed from the goal.');
        $bucket->delete();

        return back()->with('success', 'Bucket removed.');
    }

    public function restore(int $bucket): RedirectResponse
    {
        Bucket::withTrashed()->findOrFail($bucket)->restore();

        return back()->with('success', 'Bucket restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:200'],
            'target_amount_egp' => ['nullable', 'numeric', 'min:0'],
            'color' => ['required', 'string', 'max:20'],
            'goal_id' => ['nullable', 'exists:goals,id'],
        ]);
    }
}
