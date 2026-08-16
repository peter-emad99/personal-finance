<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Bucket;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        return Inertia::render('assets', [
            'assets' => Asset::with('buckets')->orderByDesc('current_value_egp')->get()->map(fn (Asset $asset) => $finance->assetPayload($asset))->values(),
            'buckets' => Bucket::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'type' => ['required', 'string', 'max:60'],
            'quantity' => ['nullable', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'max:8'],
            'cost_basis_egp' => ['nullable', 'numeric', 'min:0'], 'current_value_egp' => ['required', 'numeric', 'min:0'],
            'unit_price_egp' => ['nullable', 'numeric', 'min:0'], 'acquired_on' => ['nullable', 'date'],
            'account_name' => ['nullable', 'string', 'max:120'], 'liquidity' => ['required', 'string'],
            'is_liquid' => ['boolean'], 'notes' => ['nullable', 'string'],
        ]);
        Asset::create($data);

        return back()->with('success', 'Asset added.');
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $asset->update($request->validate([
            'name' => ['required', 'string', 'max:120'], 'type' => ['required', 'string', 'max:60'],
            'quantity' => ['nullable', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'max:8'],
            'cost_basis_egp' => ['nullable', 'numeric', 'min:0'], 'current_value_egp' => ['required', 'numeric', 'min:0'],
            'unit_price_egp' => ['nullable', 'numeric', 'min:0'], 'acquired_on' => ['nullable', 'date'],
            'account_name' => ['nullable', 'string', 'max:120'], 'liquidity' => ['required', 'string'],
            'is_liquid' => ['boolean'], 'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return back()->with('success', 'Asset removed.');
    }

    public function updateAllocations(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'allocations' => ['array'],
            'allocations.*.bucket_id' => ['required', 'exists:buckets,id'],
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
        $asset->buckets()->sync($syncData);

        return back()->with('success', 'Bucket allocations updated.');
    }
}
