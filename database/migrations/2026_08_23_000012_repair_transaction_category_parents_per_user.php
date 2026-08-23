<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('transaction_categories')->where('kind', 'expense')->whereNotNull('parent_name')->get(['id', 'user_id', 'parent_name']) as $category) {
            $parent = DB::table('budget_categories')
                ->where('user_id', $category->user_id)
                ->where('kind', 'expense')
                ->whereRaw('lower(name) = ?', [strtolower(trim((string) $category->parent_name))])
                ->first(['id', 'name']);

            if ($parent !== null) {
                DB::table('transaction_categories')->where('id', $category->id)->update([
                    'budget_category_id' => $parent->id,
                    'parent_name' => $parent->name,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep repaired parent links on rollback; they are historical data fixes.
    }
};
