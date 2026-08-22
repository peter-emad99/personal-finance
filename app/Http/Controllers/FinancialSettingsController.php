<?php

namespace App\Http\Controllers;

use App\Models\FinancialSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinancialSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('financial-settings', [
            'settings' => FinancialSetting::query()->orderByDesc('is_active')->orderByDesc('id')->get(),
            'defaults' => FinancialSetting::defaultAssetClassTargets(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $settings = FinancialSetting::create($this->validated($request));
        $this->activate($settings);

        return back()->with('success', 'Financial policy saved.');
    }

    public function update(Request $request, FinancialSetting $financialSetting): RedirectResponse
    {
        $financialSetting->update($this->validated($request));
        $this->activate($financialSetting);

        return back()->with('success', 'Financial policy updated.');
    }

    public function destroy(FinancialSetting $financialSetting): RedirectResponse
    {
        abort_if(FinancialSetting::query()->where('is_active', true)->count() <= 1 && $financialSetting->is_active, 422, 'The active financial policy cannot be archived.');
        $financialSetting->delete();

        return back()->with('success', 'Financial policy archived.');
    }

    public function restore(int $financialSetting): RedirectResponse
    {
        $settings = FinancialSetting::withTrashed()->findOrFail($financialSetting);
        $settings->restore();
        $this->activate($settings);

        return back()->with('success', 'Financial policy restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_currency' => ['required', 'string', 'max:8'],
            'emergency_reserve_months' => ['required', 'integer', 'between:1,36'],
            'emergency_eligible_liquidity' => ['required', 'in:immediate,within_3_days'],
            'policy' => ['nullable', 'array'],
            'policy.monthly_allocation_targets' => ['nullable', 'array'],
            'policy.monthly_allocation_targets.*' => ['required', 'numeric', 'between:0,100'],
            'policy.financial_freedom' => ['nullable', 'array'],
            'policy.financial_freedom.withdrawal_rate_percent' => ['nullable', 'numeric', 'between:1,10'],
            'policy.financial_freedom.annual_spending_override_egp' => ['nullable', 'numeric', 'min:0'],
            'policy.variance_thresholds' => ['nullable', 'array'],
            'policy.variance_thresholds.income_percent' => ['nullable', 'numeric', 'between:0,100'],
            'policy.variance_thresholds.expenses_percent' => ['nullable', 'numeric', 'between:0,100'],
            'policy.variance_thresholds.investment_minimum_percent' => ['nullable', 'numeric', 'between:0,100'],
            'policy.auto_prepare_next_month' => ['nullable', 'boolean'],
            'asset_class_targets' => ['nullable', 'array'],
            'asset_class_targets.*.min' => ['required', 'numeric', 'between:0,100'],
            'asset_class_targets.*.max' => ['required', 'numeric', 'between:0,100'],
            'asset_class_targets.*.target' => ['required', 'numeric', 'between:0,100'],
            'rebalancing_tolerance_percent' => ['required', 'numeric', 'between:0,100'],
            'goal_funding_policy' => ['required', 'in:priority_order,manual_contributions'],
            'minimum_cash_after_purchase_egp' => ['required', 'numeric', 'min:0'],
            'maximum_monthly_payment_egp' => ['nullable', 'numeric', 'min:0'],
            'maximum_debt_burden_percent' => ['nullable', 'numeric', 'between:0,100'],
            'valuation_freshness_days' => ['required', 'integer', 'between:1,3650'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $targets = $data['asset_class_targets'] ?? FinancialSetting::defaultAssetClassTargets();
        foreach ($targets as $class => $range) {
            if ((float) $range['min'] > (float) $range['target'] || (float) $range['target'] > (float) $range['max']) {
                throw ValidationException::withMessages(['asset_class_targets' => "Allocation target for {$class} must be between its minimum and maximum."]);
            }
        }
        $totalMin = array_sum(array_map(fn (array $range): float => (float) $range['min'], $targets));
        $totalMax = array_sum(array_map(fn (array $range): float => (float) $range['max'], $targets));
        if ($totalMin > 100 || $totalMax < 100) {
            throw ValidationException::withMessages(['asset_class_targets' => 'Allocation ranges must be able to contain 100% of the portfolio.']);
        }

        $data['asset_class_targets'] = $targets;

        if (isset($data['policy']['monthly_allocation_targets'])) {
            $allocationTargets = array_map('floatval', $data['policy']['monthly_allocation_targets']);
            if (abs(array_sum($allocationTargets) - 100) > 0.01) {
                throw ValidationException::withMessages(['policy.monthly_allocation_targets' => 'Monthly allocation targets must add up to 100%.']);
            }
            $data['policy']['monthly_allocation_targets'] = $allocationTargets;
        }

        if (isset($data['policy']['financial_freedom']['withdrawal_rate_percent'])) {
            $data['policy']['financial_freedom']['withdrawal_rate_percent'] = (float) $data['policy']['financial_freedom']['withdrawal_rate_percent'];
        }
        if (array_key_exists('annual_spending_override_egp', $data['policy']['financial_freedom'] ?? [])) {
            $data['policy']['financial_freedom']['annual_spending_override_egp'] = $data['policy']['financial_freedom']['annual_spending_override_egp'] !== null
                ? (float) $data['policy']['financial_freedom']['annual_spending_override_egp']
                : null;
        }

        if (isset($data['policy']['variance_thresholds'])) {
            $data['policy']['variance_thresholds'] = array_merge([
                'income_percent' => 10,
                'expenses_percent' => 10,
                'investment_minimum_percent' => 80,
            ], array_map('floatval', $data['policy']['variance_thresholds']));
        }

        return $data;
    }

    private function activate(FinancialSetting $settings): void
    {
        DB::transaction(function () use ($settings): void {
            FinancialSetting::query()->whereKeyNot($settings->id)->update(['is_active' => false]);
            $settings->updateQuietly(['is_active' => true]);
        });
    }
}
