<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Snapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'as_of' => 'date',
            'net_worth_egp' => 'decimal:2',
            'liquid_assets_egp' => 'decimal:2',
            'investable_net_worth_egp' => 'decimal:2',
            'income_egp' => 'decimal:2',
            'expenses_egp' => 'decimal:2',
            'free_cash_flow_egp' => 'decimal:2',
            'emergency_coverage_months' => 'decimal:2',
            'asset_breakdown' => 'array',
        ];
    }
}
