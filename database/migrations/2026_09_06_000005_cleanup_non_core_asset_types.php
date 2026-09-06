<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyTypes = DB::table('asset_types')->where('is_system', true)->where('is_core', false)->get();

        foreach ($legacyTypes as $systemType) {
            $ownerIds = DB::table('assets')->where('asset_type_id', $systemType->id)->distinct()->pluck('user_id');
            foreach ($ownerIds as $ownerId) {
                $replacement = DB::table('asset_types')->where('user_id', $ownerId)->where('key', $systemType->key)->first();
                if ($replacement === null) {
                    $replacementId = DB::table('asset_types')->insertGetId([
                        'user_id' => $ownerId,
                        'key' => $systemType->key,
                        'label' => $systemType->label,
                        'class' => $systemType->class,
                        'default_liquidity' => $systemType->default_liquidity,
                        'pricing_behavior' => $systemType->pricing_behavior,
                        'is_active' => true,
                        'is_system' => false,
                        'is_core' => false,
                        'asset_group_id' => $systemType->asset_group_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $replacementId = $replacement->id;
                }

                DB::table('assets')->where('user_id', $ownerId)->where('asset_type_id', $systemType->id)->update(['asset_type_id' => $replacementId]);

                // The default portfolio view is shared, but its memberships
                // are still presentation-only. Preserve the old system type's
                // line for each owner replacement before archiving the system
                // type, otherwise current assets would fall into Unassigned.
                $defaultViewId = DB::table('asset_views')->whereNull('user_id')->where('is_system', true)->where('key', 'default_portfolio')->value('id');
                if ($defaultViewId !== null) {
                    DB::table('asset_view_line_asset_type')
                        ->where('asset_view_id', $defaultViewId)
                        ->where('asset_type_id', $systemType->id)
                        ->get()
                        ->each(function (object $membership) use ($defaultViewId, $replacementId): void {
                            DB::table('asset_view_line_asset_type')->insertOrIgnore([
                                'asset_view_id' => $defaultViewId,
                                'asset_view_line_id' => $membership->asset_view_line_id,
                                'asset_type_id' => $replacementId,
                                'sort_order' => $membership->sort_order,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        });
                }

                $viewIds = DB::table('asset_views')->where('user_id', $ownerId)->pluck('id');
                foreach ($viewIds as $viewId) {
                    $memberships = DB::table('asset_view_line_asset_type')->where('asset_view_id', $viewId)->where('asset_type_id', $systemType->id)->get();
                    foreach ($memberships as $membership) {
                        DB::table('asset_view_line_asset_type')->insertOrIgnore([
                            'asset_view_id' => $membership->asset_view_id,
                            'asset_view_line_id' => $membership->asset_view_line_id,
                            'asset_type_id' => $replacementId,
                            'sort_order' => $membership->sort_order,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        DB::table('asset_view_line_asset_type')->where('id', $membership->id)->delete();
                    }
                }
            }

            DB::table('asset_types')->where('id', $systemType->id)->update(['is_active' => false]);

            DB::table('asset_view_line_asset_type')
                ->where('asset_type_id', $systemType->id)
                ->whereIn('asset_view_id', DB::table('asset_views')->whereNull('user_id')->where('is_system', true)->pluck('id'))
                ->delete();
        }
    }

    public function down(): void
    {
        // Numeric assets are intentionally not reassigned back to compatibility rows.
    }
};
