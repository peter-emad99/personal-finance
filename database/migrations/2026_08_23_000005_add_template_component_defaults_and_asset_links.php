<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_categories', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        DB::table('budget_categories')
            ->whereIn('name', ['Essentials', 'Lifestyle', 'Commitments', 'Flexible / irregular'])
            ->update(['is_default' => true]);

        Schema::table('allocation_rules', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->after('plan_template_id')->constrained('assets')->nullOnDelete();
            $table->dropUnique('allocation_rules_template_bucket_asset_unique');
            $table->unique(['user_id', 'plan_template_id', 'asset_id', 'bucket_id', 'asset_target'], 'allocation_rules_template_asset_bucket_unique');
        });

        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->after('bucket_id')->constrained('assets')->nullOnDelete();
            $table->index(['allocation_plan_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            $table->dropIndex(['allocation_plan_id', 'asset_id']);
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });

        Schema::table('allocation_rules', function (Blueprint $table): void {
            $table->dropUnique('allocation_rules_template_asset_bucket_unique');
            $table->unique(['user_id', 'plan_template_id', 'bucket_id', 'asset_target'], 'allocation_rules_template_bucket_asset_unique');
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });

        Schema::table('budget_categories', function (Blueprint $table): void {
            $table->dropColumn('is_default');
        });
    }
};
