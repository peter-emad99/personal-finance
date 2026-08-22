<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerTransaction extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $table = 'transactions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'posted_on' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:10',
            'amount_egp' => 'decimal:2',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function counterAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counter_account_id');
    }

    /** @return BelongsTo<TransactionCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    /** @return BelongsTo<Bucket, $this> */
    public function purposeBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'purpose_bucket_id');
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    /** @return HasMany<TransactionSplit, $this> */
    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class, 'transaction_id');
    }

    /**
     * @param  Builder<LedgerTransaction>  $query
     * @return Builder<LedgerTransaction>
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('review_state', 'confirmed')->whereNull('voided_at');
    }

    public function isTransfer(): bool
    {
        return $this->transaction_type === 'transfer' || $this->transfer_group_id !== null;
    }

    /** @param array<string, mixed> $data */
    public static function fingerprintFor(array $data): string
    {
        return hash('sha256', implode('|', [
            (string) ($data['account_id'] ?? ''),
            (string) ($data['occurred_on'] ?? ''),
            number_format((float) ($data['amount_egp'] ?? $data['amount'] ?? 0), 2, '.', ''),
            strtolower(trim((string) ($data['description'] ?? ''))),
            strtoupper((string) ($data['currency'] ?? 'EGP')),
        ]));
    }
}
