<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportRow extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['raw_data' => 'array', 'occurred_on' => 'date', 'amount' => 'decimal:2', 'exchange_rate' => 'decimal:10', 'amount_egp' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /** @return BelongsTo<ImportRow, $this> */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class, 'duplicate_of_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<TransactionCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TransactionCategory::class);
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }
}
