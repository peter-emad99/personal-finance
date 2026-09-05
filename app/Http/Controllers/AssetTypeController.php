<?php

namespace App\Http\Controllers;

use App\Models\AssetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AssetTypeController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('asset-types', [
            'assetTypes' => AssetType::query()
                ->availableToOwner()
                ->withCount('assets')
                ->orderByDesc('is_system')
                ->orderBy('class')
                ->orderBy('label')
                ->get()
                ->map(fn (AssetType $type): array => $this->pagePayload($type))
                ->values(),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json($this->payloads());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $type = AssetType::create($data + ['user_id' => $request->user()->id, 'is_system' => false, 'is_active' => true]);

        return response()->json($this->payload($type), 201);
    }

    public function storePage(Request $request): RedirectResponse
    {
        AssetType::create($this->validated($request) + [
            'user_id' => $request->user()->id,
            'is_system' => false,
            'is_active' => true,
        ]);

        return back()->with('success', 'Asset type added.');
    }

    public function update(Request $request, AssetType $assetType): JsonResponse
    {
        abort_if($assetType->is_system || $assetType->user_id !== $request->user()->id, 404);
        $data = $this->validated($request, $assetType);
        $assetType->update($data);

        return response()->json($this->payload($assetType->fresh()));
    }

    public function updatePage(Request $request, AssetType $assetType): RedirectResponse
    {
        abort_if($assetType->is_system || $assetType->user_id !== $request->user()->id, 404);
        $assetType->update($this->validated($request, $assetType));

        return back()->with('success', 'Asset type updated.');
    }

    public function archive(Request $request, AssetType $assetType): RedirectResponse
    {
        abort_if($assetType->is_system || $assetType->user_id !== $request->user()->id, 404);
        $assetType->update(['is_active' => false]);

        return back()->with('success', 'Asset type archived. Existing assets keep their history.');
    }

    public function restore(Request $request, AssetType $assetType): RedirectResponse
    {
        abort_if($assetType->is_system || $assetType->user_id !== $request->user()->id, 404);
        $assetType->update(['is_active' => true]);

        return back()->with('success', 'Asset type restored.');
    }

    /** @return array<int, array<string, mixed>> */
    private function payloads(): array
    {
        return AssetType::query()->availableToOwner()->where('is_active', true)->orderBy('class')->orderBy('label')->get()->map(fn (AssetType $type): array => $this->payload($type))->values()->all();
    }

    /** @return array<string, mixed> */
    private function payload(AssetType $type): array
    {
        return [
            'id' => $type->id,
            'key' => $type->key,
            'label' => $type->label,
            'class' => $type->class,
            'classLabel' => Str::of($type->class)->replace('_', ' ')->title()->toString(),
            'defaultLiquidity' => $type->default_liquidity,
            'pricingBehavior' => $type->pricing_behavior,
            'isSystem' => (bool) $type->is_system,
        ];
    }

    /** @return array<string, mixed> */
    private function pagePayload(AssetType $type): array
    {
        return $this->payload($type) + [
            'assetCount' => (int) ($type->assets_count ?? 0),
            'isActive' => (bool) $type->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AssetType $editing = null): array
    {
        $keyRule = [$editing === null ? 'required' : 'sometimes', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'];
        if ($editing === null) {
            $keyRule[] = Rule::unique('asset_types')->where(fn ($query) => $query->where('user_id', $request->user()->id));
        }

        return $request->validate([
            'key' => $keyRule,
            'label' => ['required', 'string', 'max:120'],
            'class' => ['required', 'in:cash,reserved_cash,investment,gold,fixed_income,receivable,other'],
            'default_liquidity' => ['required', 'in:immediate,within_3_days,longer_term,illiquid'],
            'pricing_behavior' => ['required', 'in:manual,fx,gold'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
