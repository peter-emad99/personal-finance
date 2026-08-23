<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Bucket;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BucketController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        return Inertia::render('buckets', ['buckets' => Bucket::withTrashed()->with(['goal', 'assets.buckets.goal'])->orderBy('name')->get()->map(fn (Bucket $bucket) => [
            'id' => $bucket->id, 'name' => $bucket->name, 'purpose' => $bucket->purpose, 'purposeType' => $bucket->goal_id !== null ? 'goal' : ($bucket->purpose_type ?? 'other'), 'color' => $bucket->color,
            'goalName' => $bucket->goal?->name, 'targetAmount' => (float) $bucket->target_amount_egp, 'currentAmount' => $finance->bucketValue($bucket),
            'assetCount' => $bucket->assets->count(), 'archived' => $bucket->trashed(), 'goalId' => $bucket->goal_id,
            'assetAllocations' => $bucket->assets->map(fn (Asset $asset): array => [
                'assetId' => $asset->id, 'assetName' => $asset->name, 'assetType' => $asset->type,
                'amount' => (float) data_get($asset, 'pivot.amount_egp', 0),
            ])->values(),
        ]), 'assets' => Asset::with(['buckets.goal'])->orderByDesc('current_value_egp')->get()->map(fn (Asset $asset): array => [
            'id' => $asset->id, 'name' => $asset->name, 'type' => $asset->type, 'currentValue' => (float) $asset->current_value_egp,
            'allocated' => (float) $asset->buckets->sum(fn (Bucket $bucket): float => (float) data_get($bucket, 'pivot.amount_egp', 0)),
            'bucketAllocations' => $asset->buckets->map(fn (Bucket $bucket): array => [
                'bucketId' => $bucket->id, 'bucketName' => $bucket->name, 'purpose' => $bucket->purpose,
                'goalName' => $bucket->goal?->name, 'amount' => (float) data_get($bucket, 'pivot.amount_egp', 0),
            ])->values(),
        ])->values()]);
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

    public function updateAllocations(Request $request, Bucket $bucket): RedirectResponse
    {
        $data = $request->validate([
            'allocations' => ['array'],
            'allocations.*.asset_id' => ['required', 'distinct', 'exists:assets,id'],
            'allocations.*.amount_egp' => ['required', 'numeric', 'min:0'],
        ]);
        $requested = collect($data['allocations'] ?? [])->mapWithKeys(fn (array $allocation): array => [(int) $allocation['asset_id'] => (float) $allocation['amount_egp']])->filter(fn (float $amount): bool => $amount > 0);
        $assets = Asset::with('buckets')->whereIn('id', $requested->keys())->get()->keyBy('id');

        foreach ($requested as $assetId => $amount) {
            $asset = $assets->get($assetId);
            $assignedElsewhere = (float) $asset->buckets->where('id', '!=', $bucket->id)->sum(fn (Bucket $item): float => (float) data_get($item, 'pivot.amount_egp', 0));
            if ($assignedElsewhere + $amount > (float) $asset->current_value_egp + 0.005) {
                throw ValidationException::withMessages(['allocations' => "{$asset->name} does not have enough unassigned value for this bucket."]);
            }
        }

        if ($bucket->target_amount_egp !== null && $requested->sum() > (float) $bucket->target_amount_egp + 0.005) {
            throw ValidationException::withMessages(['allocations' => 'This bucket cannot exceed its target amount.']);
        }

        $syncData = $requested->map(fn (float $amount): array => ['amount_egp' => $amount])->all();
        DB::transaction(fn (): array => $bucket->assets()->sync($syncData));

        return back()->with('success', 'Bucket funding updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'purpose' => ['nullable', 'string', 'max:200'],
            'target_amount_egp' => ['nullable', 'numeric', 'min:0'],
            'color' => ['required', 'string', 'max:20'],
            'goal_id' => ['nullable', 'exists:goals,id'],
            'purpose_type' => ['required', 'in:emergency,goal,investment,other'],
        ]);

        if (($data['goal_id'] ?? null) !== null) {
            $data['purpose_type'] = 'goal';
        }

        return $data;
    }
}
