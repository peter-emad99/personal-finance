<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allocation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bucket_id')->constrained()->cascadeOnDelete();
            $table->string('asset_target', 160);
            $table->decimal('allocation_percent', 7, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'bucket_id', 'asset_target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allocation_rules');
    }
};
