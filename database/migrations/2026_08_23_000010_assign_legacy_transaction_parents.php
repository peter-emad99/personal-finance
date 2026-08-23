<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parents = DB::table('budget_categories')->get(['id', 'name'])->keyBy(fn (object $category): string => strtolower(trim((string) $category->name)));
        $mapping = [
            'debt' => 'commitments',
            'obligation' => 'commitments',
        ];
        foreach ($mapping as $categoryName => $parentName) {
            $parent = $parents->get($parentName);
            if ($parent !== null) {
                DB::table('transaction_categories')->where('kind', 'expense')->whereNull('budget_category_id')->whereRaw('lower(name) = ?', [$categoryName])->update([
                    'budget_category_id' => $parent->id,
                    'parent_name' => $parent->name,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Parent assignment is safe historical normalization; keep it on rollback.
    }
};
