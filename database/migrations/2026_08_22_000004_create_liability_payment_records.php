<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liability_payment_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('liability_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('payment_egp', 18, 2);
            $table->decimal('principal_egp', 18, 2)->default(0);
            $table->decimal('interest_egp', 18, 2)->default(0);
            $table->decimal('fees_egp', 18, 2)->default(0);
            $table->decimal('balance_after_egp', 18, 2)->nullable();
            $table->string('source', 120)->default('statement');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'liability_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_payment_records');
    }
};
