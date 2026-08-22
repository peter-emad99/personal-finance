<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Liability extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'balance_egp' => 'decimal:2',
            'original_balance_egp' => 'decimal:2',
            'interest_rate_percent' => 'decimal:3',
            'monthly_payment_egp' => 'decimal:2',
            'due_day' => 'integer',
            'payoff_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<LiabilityBalanceHistory, $this> */
    public function balanceHistories(): HasMany
    {
        return $this->hasMany(LiabilityBalanceHistory::class);
    }

    /** @return HasMany<LiabilityPaymentRecord, $this> */
    public function paymentRecords(): HasMany
    {
        return $this->hasMany(LiabilityPaymentRecord::class);
    }
}
