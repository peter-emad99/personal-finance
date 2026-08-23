<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buckets', function (Blueprint $table): void {
            $table->string('purpose_type', 20)->default('other')->after('purpose')->index();
        });
    }

    public function down(): void
    {
        Schema::table('buckets', function (Blueprint $table): void {
            $table->dropIndex(['purpose_type']);
            $table->dropColumn('purpose_type');
        });
    }
};
