<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Bucket;
use App\Models\CashFlow;
use App\Models\Goal;
use App\Models\Liability;
use App\Models\MonthlyFinancialReview;
use App\Models\RecurringCommitment;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_is_available_with_financial_data(): void
    {
        Asset::create(['name' => 'Cash', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 100000, 'is_liquid' => true, 'liquidity' => 'immediate']);
        CashFlow::create(['type' => 'income', 'category' => 'salary', 'amount_egp' => 140000, 'occurred_on' => now()->startOfMonth()]);
        CashFlow::create(['type' => 'expense', 'category' => 'essential', 'amount_egp' => 15000, 'occurred_on' => now()->startOfMonth()]);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_context_export_contains_summary_and_assets(): void
    {
        Asset::create(['name' => 'USD reserve', 'type' => 'USD', 'currency' => 'USD', 'quantity' => 1000, 'current_value_egp' => 50000, 'is_liquid' => true, 'liquidity' => 'immediate']);

        $this->get(route('export.context'))
            ->assertOk()
            ->assertJsonPath('summary.netWorth', 50000)
            ->assertJsonPath('assets.0.name', 'USD reserve');
    }

    public function test_asset_can_be_split_across_purpose_buckets(): void
    {
        $asset = Asset::create(['name' => 'Money market', 'type' => 'Fixed income', 'currency' => 'EGP', 'current_value_egp' => 100000, 'is_liquid' => true, 'liquidity' => 'within_3_days']);
        $emergency = Bucket::create(['name' => 'Emergency', 'color' => '#4ade80']);
        $car = Bucket::create(['name' => 'Car', 'color' => '#f6c453']);

        $this->put(route('assets.allocations.update', $asset), ['allocations' => [
            ['bucket_id' => $emergency->id, 'amount_egp' => 40000],
            ['bucket_id' => $car->id, 'amount_egp' => 60000],
        ]])->assertRedirect();

        $this->assertDatabaseHas('asset_bucket_allocations', ['asset_id' => $asset->id, 'bucket_id' => $emergency->id, 'amount_egp' => 40000]);
        $this->assertDatabaseHas('asset_bucket_allocations', ['asset_id' => $asset->id, 'bucket_id' => $car->id, 'amount_egp' => 60000]);
    }

    public function test_bucket_can_be_funded_from_assets_without_exceeding_asset_or_goal_limits(): void
    {
        $asset = Asset::create(['name' => 'Money market', 'type' => 'Fixed income', 'currency' => 'EGP', 'current_value_egp' => 100000, 'liquidity' => 'within_3_days']);
        $car = Bucket::create(['name' => 'Car Fund', 'target_amount_egp' => 60000, 'color' => '#fff']);
        $reserve = Bucket::create(['name' => 'Reserve', 'color' => '#fff']);
        $asset->buckets()->attach($reserve, ['amount_egp' => 40000]);

        $this->put(route('buckets.allocations.update', $car), ['allocations' => [
            ['asset_id' => $asset->id, 'amount_egp' => 60000],
        ]])->assertRedirect();

        $this->assertDatabaseHas('asset_bucket_allocations', ['asset_id' => $asset->id, 'bucket_id' => $car->id, 'amount_egp' => 60000]);

        $this->put(route('buckets.allocations.update', $car), ['allocations' => [
            ['asset_id' => $asset->id, 'amount_egp' => 60001],
        ]])->assertSessionHasErrors('allocations');
    }

    public function test_monthly_review_can_be_created_and_edited(): void
    {
        $this->post(route('monthly-review.store'), [
            'month' => '2026-08',
            'income' => 100000,
            'essential_expenses' => 12000,
            'lifestyle_expenses' => 5000,
            'recurring_commitments' => 3000,
            'one_time_expenses' => 0,
            'debt_payments' => 0,
            'invested' => 80000,
            'status' => 'closed',
            'notes' => 'Reviewed',
        ])->assertRedirect();

        $this->assertDatabaseHas('monthly_financial_reviews', ['month' => '2026-08-01 00:00:00', 'income_egp' => 100000, 'status' => 'closed']);

        $this->post(route('monthly-review.store'), [
            'month' => '2026-08',
            'income' => 110000,
            'essential_expenses' => 12000,
            'lifestyle_expenses' => 5000,
            'recurring_commitments' => 3000,
            'one_time_expenses' => 0,
            'debt_payments' => 0,
            'invested' => 90000,
            'status' => 'closed',
            'notes' => 'Updated',
        ])->assertRedirect();

        $this->assertSame(1, MonthlyFinancialReview::count());
        $this->assertDatabaseHas('monthly_financial_reviews', ['month' => '2026-08-01 00:00:00', 'income_egp' => 110000]);
    }

    public function test_liabilities_reduce_net_worth_and_commitments_are_available(): void
    {
        Asset::create(['name' => 'Cash', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 100000, 'is_liquid' => true, 'liquidity' => 'immediate']);
        Liability::create(['name' => 'Loan', 'type' => 'loan', 'balance_egp' => 25000, 'monthly_payment_egp' => 2000, 'is_active' => true]);
        RecurringCommitment::create(['name' => 'Internet', 'category' => 'utilities', 'amount_egp' => 500, 'frequency' => 'monthly', 'is_active' => true]);

        $this->get(route('export.context'))
            ->assertOk()
            ->assertJsonPath('summary.netWorth', 75000)
            ->assertJsonPath('summary.liabilities', 25000)
            ->assertJsonPath('recurring_commitments.0.name', 'Internet');
    }

    public function test_cash_flow_accepts_usd_sources_and_keeps_the_egp_value(): void
    {
        $this->post(route('cash-flow.store'), [
            'type' => 'income',
            'category' => 'freelance',
            'amount' => 100,
            'currency' => 'USD',
            'exchange_rate' => 50,
            'occurred_on' => now()->startOfMonth()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('cash_flows', [
            'category' => 'freelance',
            'amount' => 100,
            'currency' => 'USD',
            'amount_egp' => 5000,
        ]);

        $source = app(FinanceService::class)->dashboard()['monthlyPlan']['incomeSources']->first();
        $this->assertSame('USD', $source['currency']);
        $this->assertSame(100.0, $source['nativeAmount']);
        $this->assertSame(5000.0, $source['amount']);
    }

    public function test_goal_payload_explains_which_assets_back_the_goal(): void
    {
        $goal = Goal::create(['name' => 'Car', 'target_amount_egp' => 1000000, 'status' => 'active', 'priority' => 1]);
        $bucket = Bucket::create(['goal_id' => $goal->id, 'name' => 'Car fund', 'color' => '#fff']);
        $asset = Asset::create(['name' => 'Money market fund', 'type' => 'Fixed income', 'currency' => 'EGP', 'current_value_egp' => 250000, 'liquidity' => 'within_3_days']);
        $asset->buckets()->attach($bucket, ['amount_egp' => 200000]);

        $source = app(FinanceService::class)->dashboard()['goals']->first()['fundingSources']->first();

        $this->assertSame('Money market fund', $source['assetName']);
        $this->assertSame('Fixed income', $source['assetType']);
        $this->assertSame(200000.0, $source['amount']);
    }
}
