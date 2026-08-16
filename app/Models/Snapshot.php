<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Snapshot extends Model
{
    use BelongsToUser, SoftDeletes;

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
            'change_attribution' => 'array',
            'captured_at' => 'datetime',
        ];
    }
}
