<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyFinancialReview extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'income_egp' => 'decimal:2',
            'essential_expenses_egp' => 'decimal:2',
            'lifestyle_expenses_egp' => 'decimal:2',
            'recurring_commitments_egp' => 'decimal:2',
            'one_time_expenses_egp' => 'decimal:2',
            'debt_payments_egp' => 'decimal:2',
            'invested_egp' => 'decimal:2',
            'manual_adjustment_egp' => 'decimal:2',
            'obligation_snapshot' => 'array',
            'reconciled_at' => 'datetime',
        ];
    }
}
