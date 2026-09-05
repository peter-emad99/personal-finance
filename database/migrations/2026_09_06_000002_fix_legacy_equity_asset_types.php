<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $equityTypeId = DB::table('asset_types')
            ->where('is_system', true)
            ->where('key', 'egyptian_equity')
            ->value('id');
        $otherTypeId = DB::table('asset_types')
            ->where('is_system', true)
            ->where('key', 'other')
            ->value('id');

        if ($equityTypeId === null || $otherTypeId === null) {
            return;
        }

        DB::table('assets')
            ->where('asset_type_id', $otherTypeId)
            ->where(function ($query): void {
                $query->whereRaw("lower(type) like '%equities%'")
                    ->orWhereRaw("lower(type) like '%stock%'");
            })
            ->update(['asset_type_id' => $equityTypeId]);
    }

    public function down(): void
    {
        // The correction is intentionally not reversed because the previous
        // classification was incorrect and would reintroduce the bug.
    }
};
