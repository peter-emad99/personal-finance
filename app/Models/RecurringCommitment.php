<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringCommitment extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_egp' => 'decimal:2',
            'next_due_on' => 'date',
            'renewal_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function monthlyAmount(): float
    {
        return match ($this->frequency) {
            'weekly' => (float) $this->amount_egp * 52 / 12,
            'quarterly' => (float) $this->amount_egp / 3,
            'yearly', 'annual' => (float) $this->amount_egp / 12,
            default => (float) $this->amount_egp,
        };
    }

    public function annualAmount(): float
    {
        return match ($this->frequency) {
            'weekly' => (float) $this->amount_egp * 52,
            'quarterly' => (float) $this->amount_egp * 4,
            'yearly', 'annual' => (float) $this->amount_egp,
            default => (float) $this->amount_egp * 12,
        };
    }
}
