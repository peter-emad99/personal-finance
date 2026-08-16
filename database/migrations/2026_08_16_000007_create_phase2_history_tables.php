<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('institution', 120)->nullable();
            $table->string('type', 40)->default('bank');
            $table->string('currency', 8)->default('EGP');
            $table->decimal('opening_balance_egp', 18, 2)->default(0);
            $table->decimal('reported_balance_egp', 18, 2)->nullable();
            $table->date('reported_balance_as_of')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'currency']);
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->after('account_name')->constrained('accounts')->nullOnDelete();
        });

        Schema::create('transaction_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('kind', 30)->default('expense');
            $table->string('parent_name', 100)->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['name', 'kind']);
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name', 240)->nullable();
            $table->string('source', 80)->default('csv');
            $table->string('status', 30)->default('review');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->string('fingerprint', 64);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('import_rows')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->date('occurred_on')->nullable();
            $table->string('description', 240)->nullable();
            $table->decimal('amount', 18, 2)->nullable();
            $table->string('currency', 8)->default('EGP');
            $table->string('transaction_type', 30)->default('expense');
            $table->string('review_state', 30)->default('pending');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['import_batch_id', 'review_state']);
            $table->index('fingerprint');
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('counter_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('import_row_id')->nullable();
            $table->uuid('transfer_group_id')->nullable();
            $table->string('transaction_type', 30)->default('expense');
            $table->date('occurred_on');
            $table->date('posted_on')->nullable();
            $table->string('description', 240)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 8)->default('EGP');
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->decimal('amount_egp', 18, 2);
            $table->string('review_state', 30)->default('pending');
            $table->string('source', 80)->default('manual');
            $table->string('external_id', 180)->nullable();
            $table->string('fingerprint', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['account_id', 'occurred_on', 'review_state']);
            $table->index(['transaction_type', 'occurred_on']);
            $table->index('transfer_group_id');
            $table->index('fingerprint');
        });

        Schema::table('import_rows', function (Blueprint $table): void {
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreign('import_row_id')->references('id')->on('import_rows')->nullOnDelete();
        });

        Schema::create('transaction_splits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('transaction_categories')->nullOnDelete();
            $table->decimal('amount_egp', 18, 2);
            $table->string('transaction_type', 30)->default('expense');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('valued_on');
            $table->decimal('value_egp', 18, 2);
            $table->decimal('quantity', 18, 6)->nullable();
            $table->string('currency', 8)->default('EGP');
            $table->string('source', 120)->default('manual');
            $table->string('valuation_method', 80)->default('manual_mark');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['asset_id', 'valued_on']);
        });

        Schema::create('fx_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('base_currency', 8);
            $table->string('quote_currency', 8);
            $table->date('rate_date');
            $table->decimal('rate', 20, 10);
            $table->string('source', 120)->default('manual');
            $table->string('method', 80)->default('closing');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['base_currency', 'quote_currency', 'rate_date', 'source']);
        });

        Schema::table('monthly_financial_reviews', function (Blueprint $table): void {
            $table->string('source_type', 40)->default('manual')->after('status');
            $table->unsignedInteger('source_transaction_count')->default(0)->after('source_type');
            $table->decimal('manual_adjustment_egp', 18, 2)->default(0)->after('source_transaction_count');
            $table->timestamp('derived_at')->nullable()->after('manual_adjustment_egp');
            $table->timestamp('closed_at')->nullable()->after('derived_at');
            $table->timestamp('reopened_at')->nullable()->after('closed_at');
        });

        Schema::table('snapshots', function (Blueprint $table): void {
            $table->json('change_attribution')->nullable()->after('asset_breakdown');
            $table->string('historical_source', 40)->default('manual_checkpoint')->after('capture_basis');
            $table->unsignedBigInteger('revision_of_id')->nullable()->after('historical_source');
            $table->foreign('revision_of_id')->references('id')->on('snapshots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropForeign(['revision_of_id']);
            $table->dropColumn(['change_attribution', 'historical_source', 'revision_of_id']);
        });
        Schema::table('monthly_financial_reviews', function (Blueprint $table): void {
            $table->dropColumn(['source_type', 'source_transaction_count', 'manual_adjustment_egp', 'derived_at', 'closed_at', 'reopened_at']);
        });
        Schema::dropIfExists('fx_rates');
        Schema::dropIfExists('asset_valuations');
        Schema::dropIfExists('transaction_splits');
        Schema::table('import_rows', function (Blueprint $table): void {
            $table->dropForeign(['transaction_id']);
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['import_row_id']);
        });
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('transaction_categories');
        if (Schema::hasColumn('assets', 'account_id')) {
            Schema::table('assets', function (Blueprint $table): void {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }
        Schema::dropIfExists('accounts');
    }
};
