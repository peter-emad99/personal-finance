<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('assets', 'type')) {
            DB::table('assets')->whereNull('asset_type_id')->orderBy('id')->get()->each(function (object $asset): void {
                $ownerId = (int) $asset->user_id;
                $legacyLabel = trim((string) ($asset->type ?: $asset->name ?: 'Other asset'));
                $baseKey = 'legacy_'.Str::slug($legacyLabel, '_');
                $baseKey = $baseKey !== 'legacy_' ? $baseKey : 'legacy_other_asset';
                $key = $baseKey;
                $suffix = 2;
                while (DB::table('asset_types')->where('user_id', $ownerId)->where('key', $key)->exists()) {
                    $key = $baseKey.'_'.$suffix++;
                }
                $id = DB::table('asset_types')->insertGetId([
                    'user_id' => $ownerId,
                    'key' => $key,
                    'label' => $legacyLabel,
                    'class' => $this->classFor($legacyLabel, (string) $asset->currency),
                    'default_liquidity' => in_array((string) $asset->liquidity, ['immediate', 'within_3_days', 'longer_term', 'illiquid'], true) ? $asset->liquidity : 'within_3_days',
                    'pricing_behavior' => $this->pricingFor($legacyLabel, (string) $asset->currency),
                    'is_active' => true,
                    'is_system' => false,
                    'is_core' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('assets')->where('id', $asset->id)->update(['asset_type_id' => $id]);
            });
        }

        if (Schema::hasColumn('assets', 'type')) {
            Schema::table('assets', function (Blueprint $table): void {
                $table->dropColumn('type');
            });
        }

        if (Schema::hasColumn('asset_types', 'asset_group_id')) {
            Schema::table('asset_types', function (Blueprint $table): void {
                $table->dropForeign(['asset_group_id']);
                $table->dropColumn('asset_group_id');
            });
        }
        Schema::dropIfExists('asset_groups');
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('type')->nullable()->after('name');
        });
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
};
