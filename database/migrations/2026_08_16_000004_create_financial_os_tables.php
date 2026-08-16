<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_financial_reviews', function (Blueprint $table) {
            $table->id();
            $table->date('month')->unique();
            $table->decimal('income_egp', 18, 2)->default(0);
            $table->decimal('essential_expenses_egp', 18, 2)->default(0);
            $table->decimal('lifestyle_expenses_egp', 18, 2)->default(0);
            $table->decimal('recurring_commitments_egp', 18, 2)->default(0);
            $table->decimal('one_time_expenses_egp', 18, 2)->default(0);
            $table->decimal('debt_payments_egp', 18, 2)->default(0);
            $table->decimal('invested_egp', 18, 2)->default(0);
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('recurring_commitments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('subscription');
            $table->decimal('amount_egp', 18, 2);
            $table->string('frequency')->default('monthly');
            $table->date('next_due_on')->nullable();
            $table->date('renewal_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('liabilities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('loan');
            $table->decimal('balance_egp', 18, 2)->default(0);
            $table->decimal('original_balance_egp', 18, 2)->nullable();
            $table->decimal('interest_rate_percent', 8, 3)->nullable();
            $table->decimal('monthly_payment_egp', 18, 2)->default(0);
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->date('payoff_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liabilities');
        Schema::dropIfExists('recurring_commitments');
        Schema::dropIfExists('monthly_financial_reviews');
    }
};
