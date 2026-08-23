<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            if ($this->hasIndex('allocation_plan_items_allocation_plan_id_bucket_id_unique')) {
                $table->dropUnique('allocation_plan_items_allocation_plan_id_bucket_id_unique');
            }
            if (! $this->hasIndex('allocation_plan_items_plan_asset_bucket_unique')) {
                $table->unique(['allocation_plan_id', 'asset_id', 'bucket_id'], 'allocation_plan_items_plan_asset_bucket_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('allocation_plan_items', function (Blueprint $table): void {
            if ($this->hasIndex('allocation_plan_items_plan_asset_bucket_unique')) {
                $table->dropUnique('allocation_plan_items_plan_asset_bucket_unique');
            }
            if (! $this->hasIndex('allocation_plan_items_allocation_plan_id_bucket_id_unique')) {
                $table->unique(['allocation_plan_id', 'bucket_id']);
            }
        });
    }

    private function hasIndex(string $name): bool
    {
        return collect(DB::select("PRAGMA index_list('allocation_plan_items')"))
            ->contains(fn (object $index): bool => (string) ($index->name ?? '') === $name);
    }
};
