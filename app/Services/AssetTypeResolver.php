<?php

namespace App\Services;

use App\Models\AssetType;
use App\Support\OwnerContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssetTypeResolver
{
    public function resolve(?int $assetTypeId, ?string $legacyType, string $currency = 'EGP', string $liquidity = 'within_3_days'): AssetType
    {
        $ownerId = Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
        if ($ownerId === null) {
            throw new InvalidArgumentException('An owner is required to resolve an asset type.');
        }

        if ($assetTypeId !== null) {
            return AssetType::query()->availableToOwner($ownerId)->where('is_active', true)->findOrFail($assetTypeId);
        }

        $label = trim((string) ($legacyType ?: 'Other asset'));
        $existing = AssetType::query()->availableToOwner($ownerId)->where('is_active', true)->where(function ($query) use ($label): void {
            $query->whereRaw('lower(label) = ?', [strtolower($label)])->orWhere('key', Str::slug($label, '_'));
        })->first();
        if ($existing !== null) {
            return $existing;
        }

        $baseKey = 'legacy_'.Str::slug($label, '_');
        $baseKey = $baseKey !== 'legacy_' ? $baseKey : 'legacy_other_asset';
        $key = $baseKey;
        $suffix = 2;
        while (AssetType::query()->where('user_id', $ownerId)->where('key', $key)->exists()) {
            $key = $baseKey.'_'.$suffix++;
        }

        return AssetType::create([
            'user_id' => $ownerId,
            'key' => $key,
            'label' => $label,
            'class' => $this->classFor($label, $currency),
            'default_liquidity' => in_array($liquidity, ['immediate', 'within_3_days', 'longer_term', 'illiquid'], true) ? $liquidity : 'within_3_days',
            'pricing_behavior' => $this->pricingFor($label, $currency),
            'is_active' => true,
            'is_system' => false,
            'is_core' => false,
        ]);
    }

    private function classFor(string $label, string $currency): string
    {
        $normalized = strtolower(trim($label));
        $currency = strtoupper(trim($currency));

        return match (true) {
            str_contains($normalized, 'loan') || str_contains($normalized, 'receivable') => 'receivable',
            str_contains($normalized, 'reserve') => 'reserved_cash',
            str_contains($normalized, 'gold') || in_array($currency, ['GOLD', 'XAU'], true) => 'gold',
            str_contains($normalized, 'certificate') || str_contains($normalized, 'fixed income') => 'fixed_income',
            $normalized === 'cash' || $normalized === 'usd' => 'cash',
            str_contains($normalized, 'fund') || str_contains($normalized, 'stock') || str_contains($normalized, 'equity') || str_contains($normalized, 'etf') => 'investment',
            default => 'other',
        };
    }

    private function pricingFor(string $label, string $currency): string
    {
        $normalized = strtolower(trim($label));
        $currency = strtoupper(trim($currency));

        return str_contains($normalized, 'gold') || in_array($currency, ['GOLD', 'XAU'], true) ? 'gold' : ($currency === 'USD' ? 'fx' : 'manual');
    }
}
