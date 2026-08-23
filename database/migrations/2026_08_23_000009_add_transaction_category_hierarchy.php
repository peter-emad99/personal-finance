<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table): void {
            $table->foreignId('budget_category_id')->nullable()->after('kind')->constrained('budget_categories')->nullOnDelete();
            $table->index(['user_id', 'kind', 'budget_category_id']);
        });

        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->foreignId('transaction_category_id')->nullable()->after('ledger_transaction_id')->constrained('transaction_categories')->nullOnDelete();
            $table->string('description', 240)->nullable()->after('category');
        });

        $budgetCategories = DB::table('budget_categories')->get(['id', 'name']);
        foreach (DB::table('transaction_categories')->where('kind', 'expense')->whereNull('budget_category_id')->get() as $category) {
            $parentName = strtolower(trim((string) ($category->parent_name ?? '')));
            $fallbackName = match (strtolower(trim((string) $category->name))) {
                'essential', 'essentials' => 'essentials',
                'lifestyle' => 'lifestyle',
                'recurring commitments', 'commitments' => 'commitments',
                'one_time', 'one-time', 'fees', 'flexible / irregular' => 'flexible / irregular',
                default => $parentName,
            };
            $parent = $budgetCategories->first(fn (object $item): bool => strtolower(trim((string) $item->name)) === $fallbackName);
            if ($parent !== null) {
                DB::table('transaction_categories')->where('id', $category->id)->update([
                    'budget_category_id' => $parent->id,
                    'parent_name' => $parent->name,
                ]);
            }
        }

        DB::table('cash_flows')->whereNotNull('ledger_transaction_id')->orderBy('id')->each(function (object $flow): void {
            $categoryId = DB::table('transactions')->where('id', $flow->ledger_transaction_id)->value('category_id');
            $description = DB::table('transactions')->where('id', $flow->ledger_transaction_id)->value('description');
            DB::table('cash_flows')->where('id', $flow->id)->update([
                'transaction_category_id' => $categoryId,
                'description' => $description,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->dropForeign(['transaction_category_id']);
            $table->dropColumn(['transaction_category_id', 'description']);
        });
        Schema::table('transaction_categories', function (Blueprint $table): void {
            $table->dropForeign(['budget_category_id']);
            $table->dropIndex(['user_id', 'kind', 'budget_category_id']);
            $table->dropColumn('budget_category_id');
        });
    }
};
