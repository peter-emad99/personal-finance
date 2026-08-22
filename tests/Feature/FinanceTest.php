<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AllocationPlan;
use App\Models\Bucket;
use App\Models\CashFlow;
use App\Models\Goal;
use App\Models\Account;
use App\Models\Liability;
use App\Models\LiabilityPaymentRecord;
use App\Models\LedgerTransaction;
use App\Models\FinancialSetting;
use App\Models\MonthlyFinancialReview;
use App\Models\RecurringCommitment;
use App\Models\User;
use App\Services\AllocationActualService;
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

    public function test_demo_reset_rebuilds_only_the_demo_workspace(): void
    {
        $demo = User::factory()->create([
            'name' => config('finance.demo_name'),
            'email' => config('finance.demo_email'),
        ]);

        $this->actingAs($demo)->post(route('demo.reset'))->assertRedirect();

        $this->assertDatabaseCount('monthly_financial_reviews', 12);
        $this->assertDatabaseCount('liability_payment_records', 12);
        $this->assertDatabaseHas('goals', [
            'user_id' => $demo->id,
            'name' => 'Family car',
            'target_amount_egp' => 1500000,
        ]);
        $this->assertDatabaseHas('goals', [
            'user_id' => $demo->id,
            'name' => 'Home renovation fund',
            'target_amount_egp' => 180000,
        ]);
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

    public function test_monthly_allocation_plan_can_be_edited_when_date_is_stored_with_time(): void
    {
        AllocationPlan::create([
            'month' => '2026-08-01 00:00:00',
            'planned_income_egp' => 40000,
            'planned_expenses_egp' => 15000,
        ]);

        $this->post(route('allocations.store'), [
            'month' => '2026-08-15',
            'planned_income_egp' => 50000,
            'planned_expenses_egp' => 20000,
            'items' => [],
        ])->assertRedirect();

        $this->assertSame(1, AllocationPlan::count());
        $this->assertDatabaseHas('allocation_plans', [
            'month' => '2026-08-01 00:00:00',
            'planned_income_egp' => 50000,
            'planned_expenses_egp' => 20000,
        ]);
    }

    public function test_monthly_flow_links_review_values_to_active_obligations(): void
    {
        MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'income_egp' => 50000,
            'essential_expenses_egp' => 12000,
            'lifestyle_expenses_egp' => 3000,
            'recurring_commitments_egp' => 0,
            'one_time_expenses_egp' => 0,
            'debt_payments_egp' => 0,
            'invested_egp' => 30000,
            'status' => 'open',
        ]);
        RecurringCommitment::create(['name' => 'Internet', 'category' => 'utilities', 'amount_egp' => 500, 'frequency' => 'monthly', 'is_active' => true]);
        Liability::create(['name' => 'Loan', 'type' => 'loan', 'balance_egp' => 25000, 'monthly_payment_egp' => 2000, 'is_active' => true]);

        $flow = app(FinanceService::class)->dashboard()['monthlyFlow'];

        $this->assertSame('needs_sync', $flow['status']);
        $this->assertSame(2500.0, $flow['obligations']['totalConfiguredMonthly']);
        $this->assertCount(2, $flow['warnings']);
    }

    public function test_closed_review_explains_item_level_obligation_changes_and_debt_projection(): void
    {
        $commitment = RecurringCommitment::create(['name' => 'Streaming', 'category' => 'subscription', 'amount_egp' => 600, 'frequency' => 'monthly', 'is_active' => true]);
        Liability::create(['name' => 'Loan', 'type' => 'loan', 'balance_egp' => 60000, 'interest_rate_percent' => 14, 'monthly_payment_egp' => 3000, 'is_active' => true]);
        MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'income_egp' => 50000,
            'essential_expenses_egp' => 12000,
            'status' => 'closed',
            'obligation_snapshot' => [
                'capturedAt' => now()->subDay()->toIso8601String(),
                'commitments' => ['configuredMonthly' => 400, 'items' => [['id' => $commitment->id, 'name' => 'Streaming', 'monthlyAmount' => 400]]],
                'liabilities' => ['configuredMonthlyPayments' => 3000, 'items' => [['id' => 1, 'name' => 'Loan', 'balance' => 60000, 'monthlyPayment' => 3000]]],
            ],
        ]);

        $dashboard = app(FinanceService::class)->dashboard();

        $this->assertSame('changed', $dashboard['obligationChanges']['status']);
        $this->assertSame(200.0, $dashboard['obligationChanges']['summary']['monthlyDelta']);
        $this->assertCount(1, $dashboard['obligationChanges']['commitments']['changed']);
        $this->assertSame(700.0, $dashboard['debtSummary']['estimatedMonthlyInterest']);
        $this->assertSame(2300.0, $dashboard['debtSummary']['estimatedMonthlyPrincipal']);
        $this->assertSame(12, count($dashboard['monthlyHistory']));
    }

    public function test_monthly_allocation_plan_cannot_exceed_available_cash_flow(): void
    {
        $bucket = Bucket::create(['name' => 'Investing', 'color' => '#fff']);

        $this->post(route('allocations.store'), [
            'month' => now()->startOfMonth()->toDateString(),
            'planned_income_egp' => 50000,
            'planned_expenses_egp' => 20000,
            'items' => [[
                'bucket_id' => $bucket->id,
                'planned_amount_egp' => 30001,
                'actual_amount_egp' => 0,
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('allocation_plans', 0);
    }

    public function test_closed_review_prepares_next_month_plan_from_current_obligations(): void
    {
        $goal = Goal::create(['name' => 'Travel', 'target_amount_egp' => 100000, 'monthly_contribution_egp' => 8000, 'status' => 'active']);
        Bucket::create(['name' => 'Emergency Reserve', 'color' => '#4ade80']);
        Bucket::create(['name' => 'Travel', 'goal_id' => $goal->id, 'color' => '#f6c453']);
        Bucket::create(['name' => 'Long-Term Investing', 'color' => '#7c8cf8']);
        RecurringCommitment::create(['name' => 'Internet', 'category' => 'utilities', 'amount_egp' => 500, 'frequency' => 'monthly', 'is_active' => true]);
        Liability::create(['name' => 'Loan', 'type' => 'loan', 'balance_egp' => 25000, 'monthly_payment_egp' => 2000, 'is_active' => true]);
        $review = MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'income_egp' => 50000,
            'essential_expenses_egp' => 12000,
            'lifestyle_expenses_egp' => 3000,
            'recurring_commitments_egp' => 500,
            'debt_payments_egp' => 2000,
            'status' => 'closed',
        ]);

        $this->post(route('monthly-review.prepare-next', $review), ['lesson' => 'Keep the travel contribution intentional.'])->assertRedirect(route('allocations.index', ['month' => now()->startOfMonth()->addMonth()->format('Y-m')]));

        $plan = AllocationPlan::whereDate('month', now()->startOfMonth()->addMonth())->firstOrFail();
        $this->assertSame($review->id, $plan->source_review_id);
        $this->assertSame('prepared_from_review', $plan->generation_method);
        $this->assertSame(32500.0, (float) $plan->planned_income_egp - (float) $plan->planned_expenses_egp);
        $this->assertSame(32500.0, (float) $plan->items()->sum('planned_amount_egp'));
        $this->assertStringContainsString('Keep the travel contribution intentional.', (string) $plan->notes);
    }

    public function test_dashboard_emits_configurable_plan_and_investment_warnings(): void
    {
        $settings = FinancialSetting::active();
        $settings->update([
            'policy' => [
                'monthly_allocation_targets' => FinancialSetting::defaultMonthlyAllocationTargets(),
                'variance_thresholds' => [
                    'income_percent' => 10,
                    'expenses_percent' => 10,
                    'investment_minimum_percent' => 80,
                ],
            ],
        ]);
        MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'income_egp' => 10000,
            'essential_expenses_egp' => 500,
            'lifestyle_expenses_egp' => 500,
            'recurring_commitments_egp' => 0,
            'one_time_expenses_egp' => 0,
            'debt_payments_egp' => 0,
            'invested_egp' => 1000,
            'status' => 'open',
        ]);
        AllocationPlan::create([
            'month' => now()->startOfMonth(),
            'planned_income_egp' => 12000,
            'planned_expenses_egp' => 1000,
        ]);

        $alerts = app(FinanceService::class)->dashboard()['monthlyFlow']['varianceAlerts'];

        $this->assertSame(['income_variance', 'investment_below_target'], array_column($alerts, 'code'));
        $this->assertSame(15.0, (float) $alerts[1]['targetPercent']);
        $this->assertSame(12.0, (float) $alerts[1]['thresholdPercent']);
    }

    public function test_lender_payment_records_and_extra_payment_scenarios_are_reflected(): void
    {
        $liability = Liability::create([
            'name' => 'Statement loan',
            'type' => 'loan',
            'balance_egp' => 60000,
            'original_balance_egp' => 80000,
            'interest_rate_percent' => 12,
            'monthly_payment_egp' => 3000,
            'is_active' => true,
        ]);

        $this->post(route('liabilities.payments.store', $liability), [
            'paid_on' => now()->startOfMonth()->toDateString(),
            'payment_egp' => 3000,
            'principal_egp' => 2400,
            'interest_egp' => 600,
            'fees_egp' => 0,
            'balance_after_egp' => 57600,
            'source' => 'statement',
        ])->assertRedirect();

        $payload = app(FinanceService::class)->liabilityPayloadForAgent($liability->fresh());

        $this->assertSame(2400.0, $payload['recordedPaymentSummary']['principalPaid']);
        $this->assertCount(1, $payload['paymentRecords']);
        $this->assertCount(3, $payload['payoffProjection']['extraPaymentScenarios']);
        $this->assertLessThan(
            $payload['payoffProjection']['estimatedRemainingMonths'],
            $payload['payoffProjection']['extraPaymentScenarios'][0]['estimatedRemainingMonths'],
        );
        $this->assertDatabaseHas('liability_payment_records', ['liability_id' => $liability->id, 'principal_egp' => 2400]);
    }

    public function test_optional_auto_prepare_on_close_only_creates_a_missing_next_plan(): void
    {
        $settings = FinancialSetting::active();
        $settings->update(['policy' => ['auto_prepare_next_month' => true]]);
        $review = MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'income_egp' => 50000,
            'essential_expenses_egp' => 12000,
            'lifestyle_expenses_egp' => 3000,
            'recurring_commitments_egp' => 0,
            'one_time_expenses_egp' => 0,
            'debt_payments_egp' => 0,
            'invested_egp' => 5000,
            'status' => 'open',
        ]);

        $this->post(route('monthly-review.close', $review))->assertRedirect();

        $this->assertDatabaseHas('allocation_plans', [
            'source_review_id' => $review->id,
            'generation_method' => 'prepared_from_review',
        ]);
        $this->post(route('monthly-review.close', $review))->assertRedirect();
        $this->assertSame(1, AllocationPlan::where('source_review_id', $review->id)->count());
    }

    public function test_confirmed_ledger_updates_allocation_actuals_without_double_counting(): void
    {
        $account = Account::create(['name' => 'Brokerage', 'type' => 'investment', 'currency' => 'EGP']);
        $bucket = Bucket::create(['name' => 'Long-Term Investing', 'color' => '#7c8cf8']);
        $plan = AllocationPlan::create([
            'month' => now()->startOfMonth(),
            'planned_income_egp' => 50000,
            'planned_expenses_egp' => 20000,
        ]);
        $plan->items()->create([
            'bucket_id' => $bucket->id,
            'planned_amount_egp' => 30000,
            'actual_amount_egp' => 0,
        ]);
        LedgerTransaction::create([
            'account_id' => $account->id,
            'purpose_bucket_id' => $bucket->id,
            'transaction_type' => 'contribution',
            'occurred_on' => now()->startOfMonth(),
            'description' => 'Monthly investment',
            'amount' => 12000,
            'currency' => 'EGP',
            'amount_egp' => 12000,
            'review_state' => 'confirmed',
            'source' => 'manual',
        ]);
        LedgerTransaction::create([
            'account_id' => $account->id,
            'transaction_type' => 'debt_payment',
            'occurred_on' => now()->startOfMonth(),
            'description' => 'Loan payment',
            'amount' => 2000,
            'currency' => 'EGP',
            'amount_egp' => 2000,
            'review_state' => 'confirmed',
            'source' => 'manual',
        ]);

        $service = app(AllocationActualService::class);
        $preview = $service->preview($plan->load('items.bucket'));
        $this->assertSame('confirmed_ledger', $preview['source']);
        $this->assertSame(12000.0, $preview['actuals'][$bucket->id]);
        $this->assertSame(2000.0, $preview['summary']['debtPayments']);

        $service->sync($plan);
        $service->sync($plan->fresh());

        $this->assertDatabaseHas('allocation_plan_items', [
            'allocation_plan_id' => $plan->id,
            'bucket_id' => $bucket->id,
            'actual_amount_egp' => 12000,
            'actual_source' => 'confirmed_ledger',
        ]);
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
