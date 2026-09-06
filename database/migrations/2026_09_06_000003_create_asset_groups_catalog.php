<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, string|int>> */
    private array $groups = [
        'cash' => ['label' => 'Cash', 'description' => 'Bank balances and spendable cash.', 'color' => '#7c8cf8', 'sort_order' => 10],
        'reserves' => ['label' => 'Reserves', 'description' => 'Money set aside for a specific future use.', 'color' => '#f3aa5c', 'sort_order' => 20],
        'investments' => ['label' => 'Investments', 'description' => 'Market and long-term investment holdings.', 'color' => '#4bb79f', 'sort_order' => 30],
        'gold' => ['label' => 'Gold', 'description' => 'Physical gold holdings.', 'color' => '#e9a85e', 'sort_order' => 40],
        'fixed_income' => ['label' => 'Fixed income', 'description' => 'Certificates and fixed-income products.', 'color' => '#6d7fd7', 'sort_order' => 50],
        'receivables' => ['label' => 'Receivables', 'description' => 'Money owed to you by another person or party.', 'color' => '#d04d63', 'sort_order' => 60],
        'other' => ['label' => 'Other', 'description' => 'Holdings that do not fit another group yet.', 'color' => '#9b8cf5', 'sort_order' => 70],
    ];

    /** @var array<string, string> */
    private array $typeGroups = [
        'bank_cash' => 'cash',
        'foreign_currency' => 'cash',
        'cash_reserve' => 'reserves',
        'investment_holding' => 'investments',
        'etf' => 'investments',
        'mutual_fund' => 'investments',
        'money_market_fund' => 'fixed_income',
        'egyptian_equity' => 'investments',
        'gold_24k' => 'gold',
        'certificate' => 'fixed_income',
        'personal_loan' => 'receivables',
        'other' => 'other',
    ];

    public function up(): void
    {
        Schema::create('asset_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->string('color', 32)->nullable();
            $table->string('icon', 80)->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'key']);
            $table->index(['is_system', 'is_active', 'sort_order']);
        });

        Schema::table('asset_types', function (Blueprint $table): void {
            $table->foreignId('asset_group_id')->nullable()->after('user_id')->constrained('asset_groups')->nullOnDelete();
            $table->boolean('is_core')->default(false)->after('is_system');
            $table->index(['is_system', 'is_core', 'is_active']);
        });

        $groupIds = [];
        foreach ($this->groups as $key => $group) {
            DB::table('asset_groups')->updateOrInsert(
                ['user_id' => null, 'key' => $key],
                [
                    ...$group,
                    'is_active' => true,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
            $groupIds[$key] = DB::table('asset_groups')->whereNull('user_id')->where('key', $key)->value('id');
        }

        $coreTypes = [
            'bank_cash', 'foreign_currency', 'cash_reserve', 'investment_holding',
            'gold_24k', 'certificate', 'personal_loan', 'other',
        ];

        DB::table('asset_types')->where('is_system', true)->update(['is_core' => false]);
        foreach ($coreTypes as $key) {
            $type = DB::table('asset_types')->whereNull('user_id')->where('key', $key)->first();
            if ($type !== null) {
                DB::table('asset_types')->where('id', $type->id)->update(['is_core' => true]);
            }
        }

        $investmentGroupId = $groupIds['investments'];
        $investmentType = DB::table('asset_types')->whereNull('user_id')->where('key', 'investment_holding')->first();
        if ($investmentType === null) {
            $now = now();
            $investmentTypeId = DB::table('asset_types')->insertGetId([
                'user_id' => null,
                'key' => 'investment_holding',
                'label' => 'Investment holding',
                'class' => 'investment',
                'default_liquidity' => 'longer_term',
                'pricing_behavior' => 'manual',
                'is_active' => true,
                'is_system' => true,
                'is_core' => true,
                'asset_group_id' => $investmentGroupId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $investmentType = (object) ['id' => $investmentTypeId];
        } else {
            DB::table('asset_types')->where('id', $investmentType->id)->update(['is_core' => true]);
        }

        foreach ($this->typeGroups as $typeKey => $groupKey) {
            $groupId = $groupIds[$groupKey] ?? null;
            if ($groupId !== null) {
                DB::table('asset_types')->where('key', $typeKey)->update(['asset_group_id' => $groupId]);
            }
        }

        // Existing owner-created types inherit a presentation group from their
        // class. This is metadata only and does not touch asset amounts.
        foreach (['cash' => 'cash', 'reserved_cash' => 'reserves', 'investment' => 'investments', 'gold' => 'gold', 'fixed_income' => 'fixed_income', 'receivable' => 'receivables', 'other' => 'other'] as $class => $groupKey) {
            DB::table('asset_types')
                ->where('class', $class)
                ->whereNull('asset_group_id')
                ->update(['asset_group_id' => $groupIds[$groupKey]]);
        }
    }

    public function down(): void
    {
        Schema::table('asset_types', function (Blueprint $table): void {
            $table->dropIndex(['is_system', 'is_core', 'is_active']);
            $table->dropForeign(['asset_group_id']);
            $table->dropColumn(['asset_group_id', 'is_core']);
        });

        Schema::dropIfExists('asset_groups');
    }
};
