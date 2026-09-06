<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\AssetView;
use App\Models\AssetViewLine;
use App\Support\OwnerContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AssetViewService
{
    /** @return Collection<int, AssetView> */
    public function availableViews(?int $ownerId = null, bool $includeArchived = false): Collection
    {
        $query = AssetView::query()->availableToOwner($ownerId);
        if (! $includeArchived) {
            $query->where('is_active', true);
        }

        return $query->with(['lines' => function ($lines) use ($includeArchived): void {
            if (! $includeArchived) {
                $lines->where('is_active', true);
            }
            $lines->with(['assetTypes' => fn ($types) => $types->where('asset_types.is_active', true)]);
        }])->orderByDesc('is_system')->orderBy('sort_order')->orderBy('label')->get();
    }

    /** @return array<string, mixed> */
    public function dashboardPayload(Collection $assets, ?int $ownerId = null): array
    {
        $views = $this->availableViews($ownerId);
        $availableTypeIds = $this->availableTypeIds($ownerId);
        $allocations = [];

        foreach ($views as $view) {
            $allocations[(string) $view->id] = $this->allocation($assets, $view, $availableTypeIds);
        }

        return [
            'views' => $views->map(fn (AssetView $view): array => $this->payload($view, $availableTypeIds))->values()->all(),
            'allocations' => $allocations,
            'defaultViewId' => $views->first()?->id,
        ];
    }

    /** @return array<string, mixed> */
    public function payload(AssetView $view, ?array $availableTypeIds = null): array
    {
        $availableTypeIds ??= $this->availableTypeIds();

        return [
            'id' => $view->id,
            'key' => $view->key,
            'label' => $view->label,
            'description' => $view->description,
            'sortOrder' => (int) $view->sort_order,
            'isSystem' => (bool) $view->is_system,
            'isCore' => (bool) $view->is_core,
            'isActive' => (bool) $view->is_active,
            'editable' => ! $view->is_system,
            'lines' => $view->lines->sortBy(['sort_order', 'id'])->map(fn (AssetViewLine $line): array => [
                'id' => $line->id,
                'key' => $line->key,
                'label' => $line->label,
                'description' => $line->description,
                'color' => $line->color,
                'sortOrder' => (int) $line->sort_order,
                'isActive' => (bool) $line->is_active,
                'isFallback' => (bool) $line->is_fallback,
                'assetTypes' => $line->assetTypes->filter(fn (AssetType $type): bool => in_array($type->id, $availableTypeIds, true))->map(fn (AssetType $type): array => [
                    'id' => $type->id,
                    'key' => $type->key,
                    'label' => $type->label,
                    'class' => $type->class,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @param Collection<int, Asset> $assets @param array<int, int>|null $availableTypeIds */
    public function allocation(Collection $assets, AssetView $view, ?array $availableTypeIds = null): array
    {
        $availableTypeIds ??= $this->availableTypeIds();
        $lines = $view->lines->where('is_active', true)->sortBy(['sort_order', 'id'])->values();
        $fallback = $lines->first(fn (AssetViewLine $line): bool => $line->is_fallback) ?? new AssetViewLine(['id' => 0, 'key' => 'unassigned_other', 'label' => 'Unassigned / Other', 'sort_order' => PHP_INT_MAX, 'is_fallback' => true]);
        $membership = [];

        foreach ($lines as $line) {
            foreach ($line->assetTypes->filter(fn (AssetType $type): bool => in_array($type->id, $availableTypeIds, true)) as $type) {
                $membership[$type->id] = $line->id;
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            $value = (float) $assets->filter(fn (Asset $asset): bool => ($membership[$asset->asset_type_id] ?? $fallback->id) === $line->id)->sum('current_value_egp');
            if ($value > 0 || $line->is_fallback) {
                $rows[(int) $line->id] = ['key' => $line->key, 'label' => $line->label, 'value' => round($value, 2), 'percent' => 0.0, 'lineId' => $line->id, 'isFallback' => (bool) $line->is_fallback];
            }
        }

        $total = max(1, (float) $assets->sum('current_value_egp'));

        return collect($rows)->sortBy(fn (array $row): int => (int) ($row['lineId'] ?? PHP_INT_MAX))->map(function (array $row) use ($total): array {
            $row['percent'] = round((float) $row['value'] / $total * 100, 1);

            return $row;
        })->values()->all();
    }

    /** @return array<int, int> */
    private function availableTypeIds(?int $ownerId = null): array
    {
        return AssetType::query()->availableToOwner($ownerId)->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function ownerId(): ?int
    {
        return Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
    }
}
