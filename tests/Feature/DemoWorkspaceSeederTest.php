<?php

namespace Tests\Feature;

use App\Models\AllocationPlan;
use App\Models\PlanTemplate;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\FinanceService;
use App\Support\OwnerContext;
use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoWorkspaceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_workspace_is_generic_and_reseeding_keeps_income_consistent(): void
    {
        config(['finance.demo_enabled' => true]);

        $seeder = app(DemoWorkspaceSeeder::class);
        $seeder->run();

        $demo = User::query()->where('email', config('finance.demo_email'))->firstOrFail();
        OwnerContext::set($demo);
        $this->actingAs($demo);
        $salaryCategory = TransactionCategory::query()->where('name', 'salary')->firstOrFail();
        DB::table('transactions')->insert([
            'user_id' => $demo->id,
            'category_id' => $salaryCategory->id,
            'transaction_type' => 'income',
            'occurred_on' => now()->startOfMonth()->toDateString(),
            'description' => 'salary',
            'amount' => 50000,
            'currency' => 'EGP',
            'amount_egp' => 50000,
            'review_state' => 'confirmed',
            'source' => 'cash_flow_legacy',
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        OwnerContext::clear();
        $seeder->run();

        OwnerContext::set($demo->fresh());
        $this->actingAs($demo->fresh());
        $dashboard = app(FinanceService::class)->dashboard();
        $monthlyPlan = $dashboard['monthlyPlan'];

        $this->assertSame(40000.0, (float) $dashboard['summary']['income']);
        $this->assertSame(40000.0, (float) $monthlyPlan['plannedIncome']);
        $this->assertSame(40000.0, (float) $monthlyPlan['incomeSources']->sum('amount'));
        $this->assertSame(20000.0, (float) $dashboard['summary']['expenses']);
        $this->assertSame(20000.0, (float) $monthlyPlan['plannedTotal']);
        $this->assertSame(12, AllocationPlan::query()->count());

        $this->assertDatabaseHas('plan_templates', ['name' => 'Family car priority']);
        $this->assertDatabaseMissing('plan_templates', ['name' => 'Kia K4 priority']);
        $this->assertDatabaseMissing('transactions', ['description' => 'Kia K4']);

        foreach (['Normal month', 'Family car priority', 'Investment-heavy'] as $templateName) {
            $this->assertSame(
                100.0,
                (float) PlanTemplate::query()->where('name', $templateName)->firstOrFail()->allocationRules()->sum('allocation_percent'),
            );
        }
    }
}
