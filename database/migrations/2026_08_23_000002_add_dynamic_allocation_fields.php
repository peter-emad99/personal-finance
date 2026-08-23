<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            $table->string('asset_target', 160)->nullable()->after('bucket_id');
            $table->decimal('allocation_percent', 7, 3)->nullable()->after('planned_amount_egp');
        });
    }

    public function down(): void
    {
        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            $table->dropColumn(['asset_target', 'allocation_percent']);
        });
    }
};
