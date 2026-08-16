<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Bucket;
use App\Models\CashFlow;
use App\Models\Goal;
use App\Models\RecurringCommitment;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase0SafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_availability_uses_explicit_liquidity_tiers_not_legacy_boolean(): void
    {
        Asset::create(['name' => 'Now', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 1000, 'liquidity' => 'immediate', 'is_liquid' => false]);
        Asset::create(['name' => 'Three days', 'type' => 'Fund', 'currency' => 'EGP', 'current_value_egp' => 1000, 'liquidity' => 'within_3_days', 'is_liquid' => true]);
        Asset::create(['name' => 'Long term', 'type' => 'Gold', 'currency' => 'Gold', 'current_value_egp' => 1000, 'liquidity' => 'longer_term', 'is_liquid' => true]);
        Asset::create(['name' => 'Illiquid', 'type' => 'Property', 'currency' => 'EGP', 'current_value_egp' => 1000, 'liquidity' => 'illiquid', 'is_liquid' => true]);

        $summary = app(FinanceService::class)->dashboard()['summary'];

        $this->assertSame(1000.0, $summary['availableNow']);
        $this->assertSame(2000.0, $summary['availableWithinThreeDays']);
        $this->assertSame(4000.0, $summary['totalAssets']);
    }

    public function test_money_aggregation_rounds_decimal_storage_at_the_cent_boundary(): void
    {
        Asset::create(['name' => 'A', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => '0.10', 'liquidity' => 'immediate']);
        Asset::create(['name' => 'B', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => '0.20', 'liquidity' => 'immediate']);

        $summary = app(FinanceService::class)->dashboard()['summary'];

        $this->assertSame(0.3, $summary['totalAssets']);
        $this->assertSame(0.3, $summary['availableNow']);
    }

    public function test_cash_flow_fallback_does_not_add_recurring_commitments_to_actual_expenses(): void
    {
        CashFlow::create(['type' => 'income', 'category' => 'salary', 'amount_egp' => 1000, 'occurred_on' => now()->startOfMonth()]);
        CashFlow::create(['type' => 'obligation', 'category' => 'loan', 'amount_egp' => 100, 'occurred_on' => now()->startOfMonth()]);
        RecurringCommitment::create(['name' => 'Internet', 'category' => 'utility', 'amount_egp' => 50, 'frequency' => 'monthly', 'is_active' => true]);

        $dashboard = app(FinanceService::class)->dashboard();
        $review = $dashboard['monthlyReview'];

        $this->assertSame(100.0, $dashboard['summary']['expenses']);
        $this->assertSame(100.0, $review['expenses'] ?? ($review['essentialExpenses'] + $review['lifestyleExpenses'] + $review['debtPayments']));
        $this->assertSame(0, $review['recurringCommitments']);
    }

    public function test_past_manual_snapshots_are_rejected(): void
    {
        $response = $this->post(route('snapshots.store'), ['as_of' => now()->subDay()->toDateString()]);

        $response->assertSessionHasErrors('as_of');
        $this->assertDatabaseCount('snapshots', 0);
    }

    public function test_goal_feasibility_is_priority_ordered_across_the_portfolio(): void
    {
        CashFlow::create(['type' => 'income', 'category' => 'salary', 'amount_egp' => 10000, 'occurred_on' => now()->startOfMonth()]);
        CashFlow::create(['type' => 'expense', 'category' => 'essential', 'amount_egp' => 0, 'occurred_on' => now()->startOfMonth()]);
        $first = Goal::create(['name' => 'First', 'target_amount_egp' => 10000, 'deadline' => now()->addMonth(), 'priority' => 1, 'status' => 'active']);
        $second = Goal::create(['name' => 'Second', 'target_amount_egp' => 10000, 'deadline' => now()->addMonth(), 'priority' => 2, 'status' => 'active']);
        Bucket::create(['goal_id' => $first->id, 'name' => 'First fund', 'color' => '#fff']);
        Bucket::create(['goal_id' => $second->id, 'name' => 'Second fund', 'color' => '#fff']);

        $goals = app(FinanceService::class)->dashboard()['goals'];

        $this->assertTrue($goals->firstWhere('id', $first->id)['onTrack']);
        $this->assertFalse($goals->firstWhere('id', $second->id)['onTrack']);
    }
}
