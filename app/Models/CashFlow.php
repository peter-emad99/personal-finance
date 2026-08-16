<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount_egp' => 'decimal:2', 'occurred_on' => 'date'];
    }
}
