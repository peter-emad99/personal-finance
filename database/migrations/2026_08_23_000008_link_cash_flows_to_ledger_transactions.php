<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->foreignId('ledger_transaction_id')->nullable()->after('id')->constrained('transactions')->nullOnDelete();
        });

        foreach (DB::table('cash_flows')->whereNull('deleted_at')->whereNull('ledger_transaction_id')->get() as $flow) {
            $kind = $flow->type === 'income' ? 'income' : 'expense';
            $category = DB::table('transaction_categories')
                ->where('user_id', $flow->user_id)
                ->where('name', $flow->category)
                ->where('kind', $kind)
                ->first();

            if ($category === null) {
                $categoryId = DB::table('transaction_categories')->insertGetId([
                    'user_id' => $flow->user_id,
                    'name' => $flow->category,
                    'kind' => $kind,
                    'is_system' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $categoryId = $category->id;
            }

            $amount = (float) ($flow->amount ?? $flow->amount_egp);
            $currency = (string) ($flow->currency ?? 'EGP');
            $exchangeRate = (float) ($flow->exchange_rate ?? ($currency === 'EGP' ? 1 : 0));
            $amountEgp = (float) $flow->amount_egp;
            $fingerprint = hash('sha256', implode('|', [
                '',
                (string) $flow->occurred_on,
                number_format($amountEgp, 2, '.', ''),
                strtolower(trim((string) $flow->category)),
                strtoupper($currency),
            ]));
            $transactionId = DB::table('transactions')->insertGetId([
                'user_id' => $flow->user_id,
                'category_id' => $categoryId,
                'transaction_type' => $flow->type === 'obligation' ? 'obligation' : $flow->type,
                'occurred_on' => $flow->occurred_on,
                'description' => $flow->category,
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'amount_egp' => $amountEgp,
                'review_state' => 'confirmed',
                'source' => 'cash_flow_legacy',
                'fingerprint' => $fingerprint,
                'metadata' => json_encode(['cash_flow_id' => $flow->id], JSON_THROW_ON_ERROR),
                'notes' => $flow->notes,
                'reviewed_at' => now(),
                'created_at' => $flow->created_at,
                'updated_at' => now(),
            ]);

            DB::table('cash_flows')->where('id', $flow->id)->update(['ledger_transaction_id' => $transactionId]);
        }
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->dropForeign(['ledger_transaction_id']);
            $table->dropColumn('ledger_transaction_id');
        });
    }
};
