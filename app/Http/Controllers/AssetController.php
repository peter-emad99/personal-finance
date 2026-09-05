<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Bucket;
use App\Services\AuditLogger;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        return Inertia::render('assets', [
            'assets' => Asset::withTrashed()->with(['buckets.goal', 'assetType'])->orderByDesc('current_value_egp')->get()->map(fn (Asset $asset) => $finance->assetPayload($asset))->values(),
            'assetTypes' => AssetType::query()->availableToOwner()->where('is_active', true)->orderBy('class')->orderBy('label')->get()->map(fn (AssetType $type): array => [
                'id' => $type->id,
                'key' => $type->key,
                'label' => $type->label,
                'class' => $type->class,
                'classLabel' => str($type->class)->replace('_', ' ')->title()->toString(),
                'defaultLiquidity' => $type->default_liquidity,
                'pricingBehavior' => $type->pricing_behavior,
                'isSystem' => (bool) $type->is_system,
            ])->values(),
            'buckets' => Bucket::with('goal:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'purpose', 'goal_id'])
                ->map(fn (Bucket $bucket): array => [
                    'id' => $bucket->id,
                    'name' => $bucket->name,
                    'purpose' => $bucket->purpose,
                    'goalName' => $bucket->goal?->name,
                    'targetAmount' => (float) $bucket->target_amount_egp,
                    'currentAmount' => $finance->bucketValue($bucket),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Asset::create($data);

        return back()->with('success', 'Asset added.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $asset->update($this->validated($request));

        return back()->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('success', 'Asset removed.');
    }

    public function restore(int $asset): RedirectResponse
    {
        Asset::withTrashed()->findOrFail($asset)->restore();

        return back()->with('success', 'Asset restored.');
    }

    public function updateAllocations(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'allocations' => ['array'],
            'allocations.*.bucket_id' => ['required', 'distinct', 'exists:buckets,id'],
            'allocations.*.amount_egp' => ['required', 'numeric', 'min:0'],
        ]);
        $syncData = [];
        $total = 0.0;
        foreach ($data['allocations'] ?? [] as $allocation) {
            $amount = (float) $allocation['amount_egp'];
            if ($amount <= 0) {
                continue;
            }
            $syncData[(int) $allocation['bucket_id']] = ['amount_egp' => $amount];
            $total += $amount;
        }
        abort_if($total > (float) $asset->current_value_egp, 422, 'Bucket allocations cannot exceed the asset value.');
        $before = $asset->buckets()->get()->map(fn (Bucket $bucket): array => [
            'bucket_id' => $bucket->id,
            'amount_egp' => (float) data_get($bucket, 'pivot.amount_egp', 0),
        ])->values()->all();
        DB::transaction(fn (): bool => (bool) $asset->buckets()->sync($syncData));
        $after = $asset->fresh('buckets')?->buckets->map(fn (Bucket $bucket): array => [
            'bucket_id' => $bucket->id,
            'amount_egp' => (float) data_get($bucket, 'pivot.amount_egp', 0),
        ])->values()->all() ?? [];
        AuditLogger::record('allocation_sync', $asset, ['bucket_allocations' => $before], ['bucket_allocations' => $after]);

        return back()->with('success', 'Bucket allocations updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'type' => ['required', 'string', 'max:60'], 'asset_type_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'max:8'],
            'cost_basis_egp' => ['nullable', 'numeric', 'min:0'], 'current_value_egp' => ['required', 'numeric', 'min:0'],
            'unit_price_egp' => ['nullable', 'numeric', 'min:0'], 'acquired_on' => ['nullable', 'date'],
            'account_name' => ['nullable', 'string', 'max:120'], 'account_id' => ['nullable', 'exists:accounts,id'], 'liquidity' => ['required', 'in:immediate,within_3_days,longer_term,illiquid'],
            'is_liquid' => ['sometimes', 'boolean'], 'notes' => ['nullable', 'string'],
        ]);
        $data['is_liquid'] = in_array($data['liquidity'], ['immediate', 'within_3_days'], true);

        if (! empty($data['asset_type_id'])) {
            $assetType = AssetType::query()->availableToOwner()->whereKey($data['asset_type_id'])->where('is_active', true)->firstOrFail();
            $data['type'] = $assetType->label;
        }

        return $data;
    }
}
