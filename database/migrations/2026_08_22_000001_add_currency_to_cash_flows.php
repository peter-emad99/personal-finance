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
            $table->decimal('amount', 18, 2)->nullable()->after('category');
            $table->string('currency', 3)->default('EGP')->after('amount');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency');
        });

        DB::table('cash_flows')->update([
            'amount' => DB::raw('amount_egp'),
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->dropColumn(['amount', 'currency', 'exchange_rate']);
        });
    }
};
