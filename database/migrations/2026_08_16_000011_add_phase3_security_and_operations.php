<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $ownedTables = [
        'assets', 'goals', 'buckets', 'asset_bucket_allocations', 'cash_flows',
        'allocation_plans', 'allocation_plan_items', 'snapshots',
        'monthly_financial_reviews', 'recurring_commitments', 'liabilities',
        'financial_settings', 'decision_journal_entries', 'accounts',
        'transaction_categories', 'import_batches', 'import_rows', 'transactions',
        'transaction_splits', 'asset_valuations', 'fx_rates',
        'liability_balance_histories', 'audit_logs',
    ];

    public function up(): void
    {
        $ownerId = DB::table('users')->where('email', config('finance.owner_email'))->value('id');
        if ($ownerId === null) {
            $ownerId = DB::table('users')->insertGetId([
                'name' => config('finance.owner_name'),
                'email' => config('finance.owner_email'),
                // The bootstrap command must be run before any non-local use.
                'password' => Hash::make((string) (config('finance.owner_password') ?: Str::random(64))),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->ownedTables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($ownerId): void {
                $table->foreignId('user_id')->default($ownerId)->after('id')->index();
            });
            DB::table($tableName)->whereNull('user_id')->update(['user_id' => $ownerId]);
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // These were single-user uniqueness rules. Make them tenant-safe.
        $this->replaceUnique('allocation_plans', 'allocation_plans_month_unique', ['user_id', 'month']);
        $this->replaceUnique('monthly_financial_reviews', 'monthly_financial_reviews_month_unique', ['user_id', 'month']);
        $this->replaceUnique('snapshots', 'snapshots_as_of_unique', ['user_id', 'as_of']);
        $this->replaceUnique('transaction_categories', 'transaction_categories_name_kind_unique', ['user_id', 'name', 'kind']);
        $this->replaceUnique('fx_rates', 'fx_rates_base_currency_quote_currency_rate_date_source_unique', ['user_id', 'base_currency', 'quote_currency', 'rate_date', 'source']);

        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_name', 240);
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('status', 30)->default('created');
            $table->boolean('encrypted')->default(true);
            $table->string('checksum', 128);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('retention_until')->nullable();
            $table->json('metadata')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('integrity_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('findings')->nullable();
            $table->string('request_id', 120)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /** @param list<string> $columns */
    private function replaceUnique(string $tableName, string $oldIndex, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }
        try {
            Schema::table($tableName, function (Blueprint $table) use ($oldIndex): void {
                $table->dropUnique($oldIndex);
            });
        } catch (Throwable) {
            // Fresh installs on SQLite can already have the rebuilt index.
        }
        Schema::table($tableName, function (Blueprint $table) use ($columns): void {
            $table->unique($columns);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_checks');
        Schema::dropIfExists('backups');
        foreach (array_reverse($this->ownedTables) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'user_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
