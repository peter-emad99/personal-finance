<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultViewId = DB::table('asset_views')
            ->whereNull('user_id')
            ->where('is_system', true)
            ->where('key', 'default_portfolio')
            ->value('id');

        if ($defaultViewId === null) {
            return;
        }

        $lines = DB::table('asset_view_lines')
            ->where('asset_view_id', $defaultViewId)
            ->where('is_active', true)
            ->get()
            ->keyBy('key');
        $fallback = $lines->get('other') ?? $lines->firstWhere('is_fallback', true);
        if ($fallback === null) {
            return;
        }

        $classLines = [
            'cash' => $lines->get('cash'),
            'reserved_cash' => $lines->get('reserves'),
            'investment' => $lines->get('investments'),
            'gold' => $lines->get('gold'),
            'fixed_income' => $lines->get('fixed_income'),
            'receivable' => $lines->get('receivables'),
            'other' => $fallback,
        ];

        $ownerIds = DB::table('assets')->whereNull('deleted_at')->distinct()->pluck('user_id');
        foreach ($ownerIds as $ownerId) {
            $usedTypeIds = DB::table('assets')->where('user_id', $ownerId)->whereNull('deleted_at')->whereNotNull('asset_type_id')->distinct()->pluck('asset_type_id');
            foreach (DB::table('asset_types')->whereIn('id', $usedTypeIds)->where('is_active', true)->get() as $type) {
                $alreadyAssigned = DB::table('asset_view_line_asset_type')->where('asset_view_id', $defaultViewId)->where('asset_type_id', $type->id)->exists();
                if ($alreadyAssigned) {
                    continue;
                }

                $line = $classLines[$type->class] ?? $fallback;
                DB::table('asset_view_line_asset_type')->insertOrIgnore([
                    'asset_view_id' => $defaultViewId,
                    'asset_view_line_id' => $line->id,
                    'asset_type_id' => $type->id,
                    'sort_order' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Presentation memberships are intentionally retained on rollback.
    }
};
