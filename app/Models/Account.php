<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_balance_egp' => 'decimal:2',
            'reported_balance_egp' => 'decimal:2',
            'reported_balance_as_of' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<LedgerTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    /** @return HasMany<LedgerTransaction, $this> */
    public function counterTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class, 'counter_account_id');
    }

    public function ledgerBalance(): float
    {
        $balance = (float) $this->opening_balance_egp;
        foreach ($this->transactions()->where('review_state', 'confirmed')->whereNull('voided_at')->get() as $transaction) {
            $balance += in_array($transaction->transaction_type, ['income', 'contribution', 'dividend', 'interest', 'withdrawal_reversal'], true)
                ? (float) $transaction->amount_egp
                : -1 * (float) $transaction->amount_egp;
        }
        foreach ($this->counterTransactions()->where('review_state', 'confirmed')->whereNull('voided_at')->where('transaction_type', 'transfer')->get() as $transaction) {
            $balance += (float) $transaction->amount_egp;
        }

        return round($balance, 2);
    }
}
