<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 30)->default('expense');
            $table->string('color', 20)->default('#7c8cf8');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'name', 'kind']);
        });

        Schema::create('budget_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_category_id')->nullable()->constrained('budget_categories')->nullOnDelete();
            $table->string('name', 120);
            $table->string('direction', 20)->default('expense');
            $table->decimal('amount_egp', 18, 2)->nullable();
            $table->decimal('percent_of_income', 7, 3)->nullable();
            $table->string('frequency', 20)->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'direction', 'is_active']);
        });

        Schema::create('allocation_plan_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_category_id')->constrained('budget_categories')->cascadeOnDelete();
            $table->foreignId('budget_rule_id')->nullable()->constrained('budget_rules')->nullOnDelete();
            $table->decimal('planned_amount_egp', 18, 2)->default(0);
            $table->decimal('actual_amount_egp', 18, 2)->default(0);
            $table->string('actual_source', 40)->default('manual');
            $table->timestamp('actual_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['allocation_plan_id', 'budget_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_plan_expenses');
        Schema::dropIfExists('budget_rules');
        Schema::dropIfExists('budget_categories');
    }
};
