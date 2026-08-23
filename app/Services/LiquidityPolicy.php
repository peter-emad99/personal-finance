<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Bucket;
use App\Models\FinancialSetting;
use Illuminate\Support\Collection;

/**
 * The one source of truth for what can be used quickly.
 *
 * `is_liquid` is retained for backwards-compatible data import only. It is
 * deliberately not used in calculations; the explicit liquidity tier is.
 */
class LiquidityPolicy
{
    private const ORDER = [
        'immediate' => 0,
        'within_3_days' => 1,
        'longer_term' => 2,
        'illiquid' => 3,
    ];

    public function __construct(private readonly ?FinancialSetting $settings = null) {}

    public function settings(): FinancialSetting
    {
        return $this->settings ?? FinancialSetting::active();
    }

    public function allows(string $tier, string $maximum): bool
    {
        return (self::ORDER[$tier] ?? 3) <= (self::ORDER[$maximum] ?? 0);
    }

    /** @param Collection<int, Asset> $assets */
    public function totalFor(Collection $assets, string $maximum): float
    {
        return $this->sumMoney($assets->filter(fn (Asset $asset): bool => $this->allows((string) $asset->liquidity, $maximum)), fn (Asset $asset): string => (string) ($asset->current_value_egp ?? '0'));
    }

    /** @param Collection<int, Bucket> $buckets */
    public function emergencyEligibleAmount(Collection $buckets, string $maximum): float
    {
        return $this->sumMoney($buckets->filter(fn (Bucket $bucket): bool => ($bucket->purpose_type ?? 'other') === 'emergency'), function (Bucket $bucket) use ($maximum): string {
            return (string) $this->sumMoney($bucket->assets->filter(fn (Asset $asset): bool => $this->allows((string) $asset->liquidity, $maximum)), fn (Asset $asset): string => (string) data_get($asset, 'pivot.amount_egp', '0'));
        });
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array{availableNow: float, availableWithinThreeDays: float, totalAssets: float}
     */
    public function availability(Collection $assets): array
    {
        return [
            'availableNow' => $this->totalFor($assets, 'immediate'),
            'availableWithinThreeDays' => $this->totalFor($assets, 'within_3_days'),
            'totalAssets' => $this->sumMoney($assets, fn (Asset $asset): string => (string) ($asset->current_value_egp ?? '0')),
        ];
    }

    /** @param Collection<int, mixed> $items @param callable(mixed): string $value */
    private function sumMoney(Collection $items, callable $value): float
    {
        $total = '0.00';
        foreach ($items as $item) {
            $total = bcadd($total, $this->numericString($value($item)), 2);
        }

        return (float) $total;
    }

    /** @return numeric-string */
    private function numericString(string $value): string
    {
        return is_numeric($value) ? $value : '0';
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $settings = $this->settings();
        $targets = $settings->asset_class_targets ?: FinancialSetting::defaultAssetClassTargets();
        $customPolicy = is_array($settings->policy) ? $settings->policy : [];

        return [
            'baseCurrency' => $settings->base_currency ?? 'EGP',
            'emergencyReserveMonths' => (int) ($settings->emergency_reserve_months ?? 6),
            'emergencyEligibleLiquidity' => $settings->emergency_eligible_liquidity ?? 'within_3_days',
            'tiers' => array_keys(self::ORDER),
            'assetClassTargets' => $targets,
            'rebalancingTolerancePercent' => (float) ($settings->rebalancing_tolerance_percent ?? 5),
            'goalFundingPolicy' => $settings->goal_funding_policy ?? 'priority_order',
            'minimumCashAfterPurchase' => (float) ($settings->minimum_cash_after_purchase_egp ?? 0),
            'maximumMonthlyPayment' => $settings->maximum_monthly_payment_egp !== null ? (float) $settings->maximum_monthly_payment_egp : null,
            'maximumDebtBurdenPercent' => $settings->maximum_debt_burden_percent !== null ? (float) $settings->maximum_debt_burden_percent : null,
            'valuationFreshnessDays' => (int) ($settings->valuation_freshness_days ?? 30),
            'financialFreedom' => array_merge(FinancialSetting::defaultFinancialFreedom(), is_array($customPolicy['financial_freedom'] ?? null) ? $customPolicy['financial_freedom'] : []),
            'varianceThresholds' => array_merge([
                'income_percent' => 10,
                'expenses_percent' => 10,
                'investment_minimum_percent' => 80,
            ], is_array($customPolicy['variance_thresholds'] ?? null) ? $customPolicy['variance_thresholds'] : []),
            'autoPrepareNextMonth' => (bool) ($customPolicy['auto_prepare_next_month'] ?? false),
            'source' => 'financial_settings',
        ];
    }
}
