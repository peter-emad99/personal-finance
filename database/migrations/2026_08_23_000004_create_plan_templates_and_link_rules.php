<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'name']);
        });

        Schema::table('budget_rules', function (Blueprint $table): void {
            $table->foreignId('plan_template_id')->nullable()->after('user_id')->constrained('plan_templates')->nullOnDelete();
            $table->foreignId('recurring_commitment_id')->nullable()->after('budget_category_id')->constrained('recurring_commitments')->nullOnDelete();
            $table->index(['user_id', 'plan_template_id', 'direction']);
        });

        Schema::table('allocation_rules', function (Blueprint $table): void {
            $table->foreignId('plan_template_id')->nullable()->after('user_id')->constrained('plan_templates')->nullOnDelete();
            $table->dropUnique('allocation_rules_user_id_bucket_id_asset_target_unique');
            $table->unique(['user_id', 'plan_template_id', 'bucket_id', 'asset_target'], 'allocation_rules_template_bucket_asset_unique');
        });

        Schema::table('allocation_plans', function (Blueprint $table): void {
            $table->foreignId('plan_template_id')->nullable()->after('month')->constrained('plan_templates')->nullOnDelete();
            $table->string('status', 20)->default('open')->after('generation_method');
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->index(['user_id', 'month', 'status']);
        });

        foreach (DB::table('users')->pluck('id') as $userId) {
            $templateId = DB::table('plan_templates')->insertGetId([
                'user_id' => $userId,
                'name' => 'Normal month',
                'description' => 'The standard income, expense and savings allocation plan.',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('budget_rules')->where('user_id', $userId)->whereNull('plan_template_id')->update(['plan_template_id' => $templateId]);
            DB::table('allocation_rules')->where('user_id', $userId)->whereNull('plan_template_id')->update(['plan_template_id' => $templateId]);
        }
    }

    public function down(): void
    {
        Schema::table('allocation_plans', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'month', 'status']);
            $table->dropForeign(['plan_template_id']);
            $table->dropColumn(['plan_template_id', 'status', 'closed_at']);
        });

        Schema::table('allocation_rules', function (Blueprint $table): void {
            $table->dropUnique('allocation_rules_template_bucket_asset_unique');
            $table->unique(['user_id', 'bucket_id', 'asset_target']);
            $table->dropForeign(['plan_template_id']);
            $table->dropColumn('plan_template_id');
        });

        Schema::table('budget_rules', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'plan_template_id', 'direction']);
            $table->dropForeign(['recurring_commitment_id']);
            $table->dropForeign(['plan_template_id']);
            $table->dropColumn(['plan_template_id', 'recurring_commitment_id']);
        });

        Schema::dropIfExists('plan_templates');
    }
};
