<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocation_plans', function (Blueprint $table): void {
            $table->foreignId('source_review_id')->nullable()->after('month')->constrained('monthly_financial_reviews')->nullOnDelete();
            $table->string('generation_method', 40)->default('manual')->after('source_review_id');
            $table->timestamp('generated_at')->nullable()->after('generation_method');
            $table->index(['user_id', 'generation_method']);
        });

        Schema::table('monthly_financial_reviews', function (Blueprint $table): void {
            $table->json('obligation_snapshot')->nullable()->after('manual_adjustment_egp');
            $table->string('reconciliation_status', 30)->default('pending')->after('obligation_snapshot');
            $table->timestamp('reconciled_at')->nullable()->after('reconciliation_status');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_financial_reviews', function (Blueprint $table): void {
            $table->dropColumn(['obligation_snapshot', 'reconciliation_status', 'reconciled_at']);
        });

        Schema::table('allocation_plans', function (Blueprint $table): void {
            $table->dropForeign(['source_review_id']);
            $table->dropIndex(['user_id', 'generation_method']);
            $table->dropColumn(['source_review_id', 'generation_method', 'generated_at']);
        });
    }
};
