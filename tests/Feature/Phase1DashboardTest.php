<?php

namespace Tests\Feature;

use App\Mcp\FinancialMcpServer;
use App\Models\DecisionJournalEntry;
use App\Models\FinancialSetting;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class Phase1DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_policy_ranges_and_guardrails_are_reflected_in_dashboard(): void
    {
        $setting = FinancialSetting::active();
        $setting->save();
        $setting->update([
            'asset_class_targets' => ['Cash' => ['min' => 20, 'target' => 30, 'max' => 40]],
            'rebalancing_tolerance_percent' => 4,
            'minimum_cash_after_purchase_egp' => 500,
            'maximum_monthly_payment_egp' => 1000,
            'valuation_freshness_days' => 14,
        ]);

        $dashboard = app(FinanceService::class)->dashboard();

        $this->assertSame(30.0, (float) $dashboard['policy']['assetClassTargets']['Cash']['target']);
        $this->assertSame(4.0, $dashboard['policy']['rebalancingTolerancePercent']);
        $this->assertSame('financial_settings', $dashboard['policy']['source']);
        $this->assertArrayHasKey('valuation', $dashboard['sourceStatus']);
    }

    public function test_decision_journal_is_visible_in_dashboard_and_context(): void
    {
        DecisionJournalEntry::create([
            'decision' => 'Wait before replacing car',
            'assumptions' => ['income_stable'],
            'alternatives' => ['repair'],
            'rule_result' => ['passes' => false],
            'chosen_action' => 'Wait',
            'status' => 'open',
        ]);

        $dashboard = app(FinanceService::class)->dashboard();
        $context = app(FinanceService::class)->exportContext();

        $this->assertSame('Wait before replacing car', $dashboard['decisionJournal']->first()['decision']);
        $this->assertSame('Wait before replacing car', $context['decision_journal'][0]['decision']);
    }

    public function test_decision_journal_mcp_mutation_returns_audit_and_dashboard_delta(): void
    {
        $handle = new ReflectionMethod(app(FinancialMcpServer::class), 'handle');
        $handle->setAccessible(true);
        $response = $handle->invoke(app(FinancialMcpServer::class), [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
                'name' => 'create_decision_journal_entry',
                'arguments' => ['decision' => 'Test decision', 'assumptions' => ['x'], 'alternatives' => [], 'rule_result' => ['passes' => true], 'status' => 'open'],
            ],
        ]);

        $data = $response['result']['structuredContent']['data'];
        $this->assertNotEmpty($data['audit_id']);
        $this->assertArrayHasKey('summary', $data['dashboard_delta']);
        $this->assertDatabaseHas('decision_journal_entries', ['decision' => 'Test decision']);
    }
}
