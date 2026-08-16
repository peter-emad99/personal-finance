<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->decimal('exchange_rate', 20, 10)->nullable()->after('amount');
            $table->decimal('amount_egp', 18, 2)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropColumn(['exchange_rate', 'amount_egp']);
        });
    }
};
