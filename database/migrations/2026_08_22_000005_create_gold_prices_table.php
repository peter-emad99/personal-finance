<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('karat')->default(24);
            $table->string('unit', 20)->default('gram');
            $table->string('currency', 8)->default('EGP');
            $table->date('price_date');
            $table->decimal('price', 18, 6);
            $table->string('source', 120);
            $table->string('method', 80)->default('spot');
            $table->timestamp('source_updated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'karat', 'unit', 'currency', 'price_date', 'source']);
            $table->index(['user_id', 'karat', 'price_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_prices');
    }
};
