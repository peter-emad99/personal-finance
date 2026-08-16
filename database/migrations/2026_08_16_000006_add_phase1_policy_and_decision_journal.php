<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_settings', function (Blueprint $table): void {
            $table->json('asset_class_targets')->nullable()->after('policy');
            $table->decimal('rebalancing_tolerance_percent', 5, 2)->default(5)->after('asset_class_targets');
            $table->string('goal_funding_policy', 40)->default('priority_order')->after('rebalancing_tolerance_percent');
            $table->decimal('minimum_cash_after_purchase_egp', 18, 2)->default(0)->after('goal_funding_policy');
            $table->decimal('maximum_monthly_payment_egp', 18, 2)->nullable()->after('minimum_cash_after_purchase_egp');
            $table->decimal('maximum_debt_burden_percent', 5, 2)->nullable()->after('maximum_monthly_payment_egp');
            $table->unsignedSmallInteger('valuation_freshness_days')->default(30)->after('maximum_debt_burden_percent');
        });

        Schema::table('goals', function (Blueprint $table): void {
            $table->decimal('monthly_contribution_egp', 18, 2)->nullable()->after('priority');
        });

        Schema::create('decision_journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('decision', 240);
            $table->json('assumptions')->nullable();
            $table->json('alternatives')->nullable();
            $table->json('rule_result')->nullable();
            $table->string('chosen_action', 240)->nullable();
            $table->date('review_date')->nullable();
            $table->text('outcome')->nullable();
            $table->string('status', 30)->default('open');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'review_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_journal_entries');
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropColumn('monthly_contribution_egp');
        });
        Schema::table('financial_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'asset_class_targets',
                'rebalancing_tolerance_percent',
                'goal_funding_policy',
                'minimum_cash_after_purchase_egp',
                'maximum_monthly_payment_egp',
                'maximum_debt_burden_percent',
                'valuation_freshness_days',
            ]);
        });
    }
};
