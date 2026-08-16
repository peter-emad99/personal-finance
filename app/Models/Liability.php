<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Liability extends Model
{
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
}
