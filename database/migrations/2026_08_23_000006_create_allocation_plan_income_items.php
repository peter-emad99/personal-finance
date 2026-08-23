<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_plan_income_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allocation_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_rule_id')->nullable()->constrained('budget_rules')->nullOnDelete();
            $table->string('name', 120);
            $table->decimal('planned_amount_egp', 18, 2)->default(0);
            $table->decimal('actual_amount_egp', 18, 2)->default(0);
            $table->string('actual_source', 40)->default('manual');
            $table->timestamp('actual_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['allocation_plan_id', 'name']);
            $table->index(['user_id', 'allocation_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_plan_income_items');
    }
};
