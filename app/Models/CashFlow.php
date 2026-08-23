<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashFlow extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_egp' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'occurred_on' => 'date',
        ];
    }

    public function transactionCategory()
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }
}
