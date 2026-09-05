<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<string, array<string, string>> */
    private array $defaults = [
        'bank_cash' => ['label' => 'Bank cash', 'class' => 'cash', 'liquidity' => 'immediate', 'pricing' => 'manual'],
        'foreign_currency' => ['label' => 'Foreign currency cash', 'class' => 'cash', 'liquidity' => 'immediate', 'pricing' => 'fx'],
        'cash_reserve' => ['label' => 'Reserved cash', 'class' => 'reserved_cash', 'liquidity' => 'immediate', 'pricing' => 'fx'],
        'etf' => ['label' => 'ETF', 'class' => 'investment', 'liquidity' => 'longer_term', 'pricing' => 'manual'],
        'mutual_fund' => ['label' => 'Mutual fund', 'class' => 'investment', 'liquidity' => 'longer_term', 'pricing' => 'manual'],
        'money_market_fund' => ['label' => 'Money market fund', 'class' => 'fixed_income', 'liquidity' => 'within_3_days', 'pricing' => 'manual'],
        'egyptian_equity' => ['label' => 'Egyptian equity', 'class' => 'investment', 'liquidity' => 'longer_term', 'pricing' => 'manual'],
        'gold_24k' => ['label' => '24K gold', 'class' => 'gold', 'liquidity' => 'longer_term', 'pricing' => 'gold'],
        'certificate' => ['label' => 'Certificate', 'class' => 'fixed_income', 'liquidity' => 'longer_term', 'pricing' => 'manual'],
        'personal_loan' => ['label' => 'Personal loan receivable', 'class' => 'receivable', 'liquidity' => 'illiquid', 'pricing' => 'manual'],
        'other' => ['label' => 'Other asset', 'class' => 'other', 'liquidity' => 'illiquid', 'pricing' => 'manual'],
    ];

    public function up(): void
    {
        Schema::create('asset_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->string('class', 40);
            $table->string('default_liquidity', 40)->default('within_3_days');
            $table->string('pricing_behavior', 40)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'key']);
            $table->index(['is_system', 'is_active']);
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('asset_type_id')->nullable()->after('type')->constrained('asset_types')->nullOnDelete();
        });

        foreach ($this->defaults as $key => $default) {
            DB::table('asset_types')->updateOrInsert(
                ['user_id' => null, 'key' => $key],
                [
                    'label' => $default['label'],
                    'class' => $default['class'],
                    'default_liquidity' => $default['liquidity'],
                    'pricing_behavior' => $default['pricing'],
                    'is_active' => true,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $systemIds = DB::table('asset_types')->where('is_system', true)->pluck('id', 'key');
        DB::table('assets')->orderBy('id')->get()->each(function (object $asset) use ($systemIds): void {
            $key = $this->keyForLegacyType((string) $asset->type, (string) $asset->currency);
            $typeId = $systemIds[$key] ?? null;

            if ($typeId === null) {
                $key = 'legacy_'.Str::slug((string) $asset->type, '_');
                DB::table('asset_types')->updateOrInsert(
                    ['user_id' => $asset->user_id, 'key' => $key],
                    [
                        'label' => (string) $asset->type,
                        'class' => 'other',
                        'default_liquidity' => (string) ($asset->liquidity ?: 'within_3_days'),
                        'pricing_behavior' => 'manual',
                        'is_active' => true,
                        'is_system' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
                $typeId = DB::table('asset_types')->where('user_id', $asset->user_id)->where('key', $key)->value('id');
            }

            DB::table('assets')->where('id', $asset->id)->update(['asset_type_id' => $typeId]);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropForeign(['asset_type_id']);
            $table->dropColumn('asset_type_id');
        });
        Schema::dropIfExists('asset_types');
    }

    private function keyForLegacyType(string $type, string $currency): string
    {
        $normalized = strtolower(trim($type));
        $currency = strtoupper(trim($currency));

        return match (true) {
            str_contains($normalized, 'receivable') || str_contains($normalized, 'loan') => 'personal_loan',
            $normalized === 'cash reserve' || (str_contains($normalized, 'reserve') && str_contains($normalized, 'cash')) => 'cash_reserve',
            str_contains($normalized, 'gold') || in_array($currency, ['GOLD', 'XAU'], true) => 'gold_24k',
            str_contains($normalized, 'certificate') => 'certificate',
            $normalized === 'cash' && $currency === 'USD' => 'foreign_currency',
            $normalized === 'cash' || $normalized === 'usd' => 'bank_cash',
            $normalized === 'etf' => 'etf',
            str_contains($normalized, 'fund') => 'mutual_fund',
            str_contains($normalized, 'equity') || str_contains($normalized, 'stock') => 'egyptian_equity',
            str_contains($normalized, 'fixed income') => 'certificate',
            default => 'other',
        };
    }
};
