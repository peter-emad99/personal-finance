<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'assets', 'buckets', 'cash_flows', 'allocation_plans', 'snapshots',
            'monthly_financial_reviews', 'recurring_commitments', 'liabilities', 'goals',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        Schema::table('snapshots', function (Blueprint $table): void {
            $table->string('capture_basis')->default('manual_current_state')->after('notes');
            $table->timestamp('captured_at')->nullable()->after('capture_basis');
        });

        Schema::create('financial_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('Default policy');
            $table->string('base_currency', 8)->default('EGP');
            $table->unsignedTinyInteger('emergency_reserve_months')->default(6);
            $table->enum('emergency_eligible_liquidity', ['immediate', 'within_3_days'])->default('within_3_days');
            $table->boolean('is_active')->default(true);
            $table->json('policy')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('action', 40);
            $table->string('entity_type', 120);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('tool_name', 120)->nullable();
            $table->string('agent_id', 120)->nullable();
            $table->string('request_id', 120)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('dashboard_version', 64)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        DB::table('financial_settings')->insert([
            'name' => 'Default policy',
            'base_currency' => 'EGP',
            'emergency_reserve_months' => 6,
            'emergency_eligible_liquidity' => 'within_3_days',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('financial_settings');
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropColumn(['capture_basis', 'captured_at']);
        });
        foreach ([
            'assets', 'buckets', 'cash_flows', 'allocation_plans', 'snapshots',
            'monthly_financial_reviews', 'recurring_commitments', 'liabilities', 'goals',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
