<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assets', 'account_id')) {
            Schema::table('assets', function (Blueprint $table): void {
                $table->foreignId('account_id')->nullable()->after('account_name')->constrained('accounts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assets', 'account_id')) {
            Schema::table('assets', function (Blueprint $table): void {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }
    }
};
