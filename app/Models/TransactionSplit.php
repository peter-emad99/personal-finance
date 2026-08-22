<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionSplit extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_egp' => 'decimal:2'];
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
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
}
