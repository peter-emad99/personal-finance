<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('purpose_bucket_id')->nullable()->after('category_id')->constrained('buckets')->nullOnDelete();
            $table->index(['purpose_bucket_id', 'occurred_on']);
        });

        Schema::table('transaction_splits', function (Blueprint $table): void {
            $table->foreignId('purpose_bucket_id')->nullable()->after('category_id')->constrained('buckets')->nullOnDelete();
            $table->index(['purpose_bucket_id', 'transaction_id']);
        });

        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            $table->string('actual_source', 40)->default('manual')->after('actual_amount_egp');
            $table->timestamp('actual_synced_at')->nullable()->after('actual_source');
        });
    }

    public function down(): void
    {
        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            $table->dropColumn(['actual_source', 'actual_synced_at']);
        });

        Schema::table('transaction_splits', function (Blueprint $table): void {
            $table->dropForeign(['purpose_bucket_id']);
            $table->dropIndex(['purpose_bucket_id', 'transaction_id']);
            $table->dropColumn('purpose_bucket_id');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['purpose_bucket_id']);
            $table->dropIndex(['purpose_bucket_id', 'occurred_on']);
            $table->dropColumn('purpose_bucket_id');
        });
    }
};
