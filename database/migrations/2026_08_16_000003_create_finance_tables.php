<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->decimal('quantity', 18, 6)->nullable();
            $table->string('currency', 8)->default('EGP');
            $table->decimal('cost_basis_egp', 18, 2)->nullable();
            $table->decimal('current_value_egp', 18, 2)->default(0);
            $table->decimal('unit_price_egp', 18, 6)->nullable();
            $table->date('acquired_on')->nullable();
            $table->string('account_name')->nullable();
            $table->string('liquidity')->default('within_3_days');
            $table->boolean('is_liquid')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('target_amount_egp', 18, 2);
            $table->date('deadline')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('priority')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('purpose')->nullable();
            $table->decimal('target_amount_egp', 18, 2)->nullable();
            $table->string('color')->default('#7c8cf8');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_bucket_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bucket_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_egp', 18, 2);
            $table->timestamps();
            $table->unique(['asset_id', 'bucket_id']);
        });

        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('category');
            $table->decimal('amount_egp', 18, 2);
            $table->date('occurred_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('allocation_plans', function (Blueprint $table) {
            $table->id();
            $table->date('month');
            $table->decimal('planned_income_egp', 18, 2)->default(0);
            $table->decimal('planned_expenses_egp', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('month');
        });

        Schema::create('allocation_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bucket_id')->constrained()->cascadeOnDelete();
            $table->decimal('planned_amount_egp', 18, 2)->default(0);
            $table->decimal('actual_amount_egp', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['allocation_plan_id', 'bucket_id']);
        });

        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('as_of');
            $table->decimal('net_worth_egp', 18, 2)->default(0);
            $table->decimal('liquid_assets_egp', 18, 2)->default(0);
            $table->decimal('investable_net_worth_egp', 18, 2)->default(0);
            $table->decimal('income_egp', 18, 2)->default(0);
            $table->decimal('expenses_egp', 18, 2)->default(0);
            $table->decimal('free_cash_flow_egp', 18, 2)->default(0);
            $table->decimal('emergency_coverage_months', 10, 2)->default(0);
            $table->json('asset_breakdown')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('as_of');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
        Schema::dropIfExists('allocation_plan_items');
        Schema::dropIfExists('allocation_plans');
        Schema::dropIfExists('cash_flows');
        Schema::dropIfExists('asset_bucket_allocations');
        Schema::dropIfExists('buckets');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('assets');
    }
};
