<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liability_balance_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('liability_id')->constrained()->cascadeOnDelete();
            $table->date('as_of');
            $table->decimal('balance_egp', 18, 2);
            $table->string('source', 120)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['liability_id', 'as_of']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_balance_histories');
    }
};
