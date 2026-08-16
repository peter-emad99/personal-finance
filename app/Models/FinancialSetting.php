<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialSetting extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'emergency_reserve_months' => 'integer',
            'is_active' => 'boolean',
            'policy' => 'array',
            'asset_class_targets' => 'array',
            'rebalancing_tolerance_percent' => 'decimal:2',
            'minimum_cash_after_purchase_egp' => 'decimal:2',
            'maximum_monthly_payment_egp' => 'decimal:2',
            'maximum_debt_burden_percent' => 'decimal:2',
            'valuation_freshness_days' => 'integer',
        ];
    }

    public static function active(): self
    {
        return static::query()->where('is_active', true)->latest('id')->first()
            ?? new self([
                'base_currency' => 'EGP',
                'emergency_reserve_months' => 6,
                'emergency_eligible_liquidity' => 'within_3_days',
                'asset_class_targets' => self::defaultAssetClassTargets(),
                'rebalancing_tolerance_percent' => 5,
                'goal_funding_policy' => 'priority_order',
                'minimum_cash_after_purchase_egp' => 0,
                'maximum_monthly_payment_egp' => null,
                'maximum_debt_burden_percent' => null,
                'valuation_freshness_days' => 30,
                'is_active' => true,
            ]);
    }

    /** @return array<string, array{min: float, max: float, target: float}> */
    public static function defaultAssetClassTargets(): array
    {
        return [
            'Gold' => ['min' => 15, 'max' => 25, 'target' => 20],
            'USD' => ['min' => 20, 'max' => 30, 'target' => 25],
            'Egyptian equities' => ['min' => 20, 'max' => 30, 'target' => 25],
            'Fixed income' => ['min' => 15, 'max' => 25, 'target' => 20],
            'Cash' => ['min' => 5, 'max' => 15, 'target' => 10],
        ];
    }
}
