<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Bucket;
use App\Models\CashFlow;
use App\Models\Goal;
use App\Models\MonthlyFinancialReview;
use App\Models\RecurringCommitment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $car = Goal::firstOrCreate(['name' => 'Car'], [
            'target_amount_egp' => 1600000, 'deadline' => '2026-12-31', 'priority' => 1, 'status' => 'active',
            'notes' => 'Demo goal based on the product brief.',
        ]);

        $buckets = [
            'Emergency Fund' => Bucket::firstOrCreate(['name' => 'Emergency Fund'], ['purpose' => 'Six months of essential expenses', 'target_amount_egp' => 135000, 'color' => '#4ade80']),
            'Car Fund' => Bucket::firstOrCreate(['name' => 'Car Fund'], ['goal_id' => $car->id, 'purpose' => 'Reserved for the car purchase', 'target_amount_egp' => 1600000, 'color' => '#f6c453']),
            'Long-Term Wealth' => Bucket::firstOrCreate(['name' => 'Long-Term Wealth'], ['purpose' => 'Long-term investing', 'color' => '#7c8cf8']),
            'Opportunity Fund' => Bucket::firstOrCreate(['name' => 'Opportunity Fund'], ['purpose' => 'Flexible capital for opportunities', 'color' => '#f08da1']),
            'Monthly Spending' => Bucket::firstOrCreate(['name' => 'Monthly Spending'], ['purpose' => 'Near-term spending buffer', 'color' => '#79c2d0']),
        ];

        $assets = [
            'Cash reserve' => ['type' => 'Cash', 'quantity' => 50000, 'currency' => 'EGP', 'cost_basis_egp' => 50000, 'current_value_egp' => 50000, 'liquidity' => 'immediate', 'is_liquid' => true, 'account_name' => 'Current account'],
            'USD reserve' => ['type' => 'USD', 'quantity' => 4000, 'currency' => 'USD', 'cost_basis_egp' => 180000, 'current_value_egp' => 200000, 'unit_price_egp' => 50, 'liquidity' => 'immediate', 'is_liquid' => true, 'account_name' => 'USD account'],
            'Gold holdings' => ['type' => 'Gold', 'quantity' => 150, 'currency' => 'Gold', 'cost_basis_egp' => 600000, 'current_value_egp' => 750000, 'unit_price_egp' => 5000, 'liquidity' => 'longer_term', 'is_liquid' => true, 'account_name' => 'Physical holdings'],
            'Egyptian equities' => ['type' => 'Egyptian equities', 'quantity' => null, 'currency' => 'EGP', 'cost_basis_egp' => 60000, 'current_value_egp' => 70000, 'liquidity' => 'longer_term', 'is_liquid' => true, 'account_name' => 'Brokerage'],
            'Money market fund' => ['type' => 'Fixed income', 'quantity' => 300000, 'currency' => 'EGP', 'cost_basis_egp' => 300000, 'current_value_egp' => 300000, 'liquidity' => 'within_3_days', 'is_liquid' => true, 'account_name' => 'Money market fund'],
        ];

        foreach ($assets as $name => $attributes) {
            $asset = Asset::updateOrCreate(['name' => $name], $attributes);
            $allocations = match ($name) {
                'Cash reserve' => [['bucket' => 'Monthly Spending', 'amount' => 15000], ['bucket' => 'Emergency Fund', 'amount' => 35000]],
                'USD reserve' => [['bucket' => 'Long-Term Wealth', 'amount' => 200000]],
                'Gold holdings' => [['bucket' => 'Car Fund', 'amount' => 650000], ['bucket' => 'Long-Term Wealth', 'amount' => 100000]],
                'Egyptian equities' => [['bucket' => 'Long-Term Wealth', 'amount' => 70000]],
                default => [['bucket' => 'Emergency Fund', 'amount' => 85000], ['bucket' => 'Car Fund', 'amount' => 200000], ['bucket' => 'Opportunity Fund', 'amount' => 15000]],
            };
            foreach ($allocations as $allocation) {
                DB::table('asset_bucket_allocations')->updateOrInsert(
                    ['asset_id' => $asset->id, 'bucket_id' => $buckets[$allocation['bucket']]->id],
                    ['amount_egp' => $allocation['amount'], 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        CashFlow::firstOrCreate(['type' => 'income', 'category' => 'salary', 'occurred_on' => now()->startOfMonth()->toDateString()], ['amount_egp' => 140000, 'notes' => 'Demo monthly income']);
        CashFlow::firstOrCreate(['type' => 'expense', 'category' => 'essential', 'occurred_on' => now()->startOfMonth()->addDays(4)->toDateString()], ['amount_egp' => 15000, 'notes' => 'Demo normal expenses']);

        $commitments = [
            ['name' => 'Connectivity bundle', 'category' => 'utilities', 'amount_egp' => 1200, 'frequency' => 'monthly', 'next_due_on' => now()->startOfMonth()->addDays(8)->toDateString()],
            ['name' => 'Digital subscriptions', 'category' => 'subscription', 'amount_egp' => 800, 'frequency' => 'monthly', 'next_due_on' => now()->startOfMonth()->addDays(12)->toDateString()],
            ['name' => 'Annual insurance reserve', 'category' => 'insurance', 'amount_egp' => 6000, 'frequency' => 'yearly', 'next_due_on' => now()->addMonths(4)->startOfMonth()->toDateString()],
        ];
        foreach ($commitments as $commitment) {
            RecurringCommitment::updateOrCreate(['name' => $commitment['name']], $commitment + ['is_active' => true]);
        }

        MonthlyFinancialReview::updateOrCreate(['month' => now()->startOfMonth()->toDateString()], [
            'income_egp' => 140000,
            'essential_expenses_egp' => 12500,
            'lifestyle_expenses_egp' => 0,
            'recurring_commitments_egp' => 2500,
            'one_time_expenses_egp' => 0,
            'debt_payments_egp' => 0,
            'invested_egp' => 0,
            'status' => 'open',
            'notes' => 'Demo monthly review. Replace with your actual month before relying on decisions.',
        ]);
    }
}
