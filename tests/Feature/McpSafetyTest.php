<?php

namespace Tests\Feature;

use App\Mcp\FinancialMcpServer;
use App\Models\Account;
use App\Models\AllocationPlan;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Bucket;
use App\Models\LedgerTransaction;
use App\Models\MonthlyFinancialReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class McpSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_lists_explicit_validated_read_and_write_tools(): void
    {
        $server = app(FinancialMcpServer::class);
        $handle = new ReflectionMethod($server, 'handle');
        $handle->setAccessible(true);
        $response = $handle->invoke($server, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []]);
        $tools = collect($response['result']['tools']);

        $this->assertTrue($tools->contains('name', 'get_dashboard'));
        $this->assertTrue($tools->contains('name', 'get_asset'));
        $this->assertTrue($tools->contains('name', 'create_asset'));
        $this->assertTrue($tools->contains('name', 'archive_asset'));
        $this->assertTrue($tools->contains('name', 'restore_asset'));
        $this->assertTrue($tools->contains('name', 'get_redacted_context'));
        $this->assertTrue($tools->contains('name', 'get_allocation_reconciliation'));
        $this->assertTrue($tools->contains('name', 'reallocate_asset_balance'));
        $this->assertTrue($tools->contains('name', 'get_bucket_allocations'));
        $this->assertTrue($tools->contains('name', 'set_bucket_allocations'));
        $this->assertTrue($tools->contains('name', 'import_csv'));
        $this->assertTrue($tools->contains('name', 'prepare_next_month'));
        $this->assertTrue($tools->contains('name', 'sync_allocation_plan_actuals'));
        $this->assertTrue($tools->contains('name', 'list_plan_templates'));
        $this->assertTrue($tools->contains('name', 'create_plan_template'));
        $this->assertTrue($tools->contains('name', 'create_allocation_rule'));
        $this->assertTrue($tools->contains('name', 'close_allocation_plan'));
        $this->assertFalse($tools->firstWhere('name', 'create_asset')['annotations']['readOnlyHint']);
        $createAsset = $tools->firstWhere('name', 'create_asset');
        $this->assertArrayNotHasKey('id', $createAsset['inputSchema']['properties']);
        $this->assertContains('name', $createAsset['inputSchema']['required']);
        $transaction = $tools->firstWhere('name', 'create_transaction');
        $this->assertArrayHasKey('purpose_bucket_id', $transaction['inputSchema']['properties']);
        $allocationPlan = $tools->firstWhere('name', 'create_allocation_plan');
        $this->assertArrayHasKey('source_review_id', $allocationPlan['inputSchema']['properties']);
    }

    public function test_mcp_create_returns_audit_and_reflected_dashboard_delta(): void
    {
        $server = app(FinancialMcpServer::class);
        $handle = new ReflectionMethod($server, 'handle');
        $handle->setAccessible(true);
        $response = $handle->invoke($server, [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'create_asset', 'arguments' => ['name' => 'MCP cash', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 1000, 'liquidity' => 'immediate']],
        ]);
        $data = $response['result']['structuredContent']['data'];

        $this->assertSame(1000.0, $data['dashboard_delta']['summary']['availableNow']);
        $this->assertNotEmpty($data['audit_id']);
        $this->assertNotEmpty($data['dashboard_version']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'tool_name' => 'create_asset']);
    }

    public function test_mcp_templates_and_commitments_share_the_same_rule_model(): void
    {
        $templateResponse = $this->callTool(app(FinancialMcpServer::class), 'create_plan_template', [
            'name' => 'MCP investment month',
            'description' => 'A reusable investment-focused month.',
        ]);
        $this->assertFalse($templateResponse['result']['isError'] ?? false);
        $templateId = $templateResponse['result']['structuredContent']['data']['entity']['id'];

        $commitmentResponse = $this->callTool(app(FinancialMcpServer::class), 'create_commitment', [
            'name' => 'MCP internet',
            'category' => 'utilities',
            'amount_egp' => 700,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
        $this->assertFalse($commitmentResponse['result']['isError'] ?? false);
        $this->assertDatabaseHas('budget_rules', [
            'plan_template_id' => $templateId,
            'name' => 'Commitment: MCP internet',
            'amount_egp' => 700,
        ]);
    }

    public function test_mcp_cannot_edit_a_closed_monthly_review(): void
    {
        $review = MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'income_egp' => 50000,
            'status' => 'closed',
        ]);

        $response = $this->callTool(app(FinancialMcpServer::class), 'update_monthly_review', [
            'id' => $review->id,
            'month' => now()->format('Y-m'),
            'income' => 55000,
            'essential_expenses' => 12000,
            'lifestyle_expenses' => 3000,
            'recurring_commitments' => 0,
            'one_time_expenses' => 0,
            'debt_payments' => 0,
            'invested' => 5000,
            'status' => 'open',
        ]);

        $this->assertTrue($response['result']['isError'] ?? false);
        $this->assertDatabaseHas('monthly_financial_reviews', [
            'id' => $review->id,
            'income_egp' => 50000,
            'status' => 'closed',
        ]);
    }

    public function test_mcp_cannot_create_a_transaction_in_a_closed_month(): void
    {
        MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'status' => 'closed',
        ]);

        $response = $this->callTool(app(FinancialMcpServer::class), 'create_transaction', [
            'transaction_type' => 'expense',
            'occurred_on' => now()->startOfMonth()->toDateString(),
            'description' => 'Late MCP entry',
            'amount' => 100,
            'currency' => 'EGP',
            'amount_egp' => 100,
        ]);

        $this->assertTrue($response['result']['isError'] ?? false);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_mcp_allocation_rule_requires_a_bucket_assigned_to_the_asset(): void
    {
        $asset = Asset::create(['name' => 'MCP fund', 'type' => 'Fund', 'currency' => 'EGP', 'current_value_egp' => 5000, 'liquidity' => 'within_3_days']);
        $bucket = Bucket::create(['name' => 'MCP reserve', 'color' => '#123456']);
        $asset->buckets()->attach($bucket, ['amount_egp' => 5000]);
        $template = $this->callTool(app(FinancialMcpServer::class), 'create_plan_template', ['name' => 'MCP allocation template']);
        $templateId = $template['result']['structuredContent']['data']['entity']['id'];

        $response = $this->callTool(app(FinancialMcpServer::class), 'create_allocation_rule', [
            'plan_template_id' => $templateId,
            'asset_id' => $asset->id,
            'bucket_id' => $bucket->id,
            'allocation_percent' => 100,
        ]);

        $this->assertFalse($response['result']['isError'] ?? false);
        $this->assertDatabaseHas('allocation_rules', ['plan_template_id' => $templateId, 'asset_id' => $asset->id, 'bucket_id' => $bucket->id]);
    }

    public function test_mcp_accepts_full_asset_value_allocations_and_audits_before_and_after_states(): void
    {
        $asset = Asset::create([
            'name' => 'Allocation test asset',
            'type' => 'Cash',
            'currency' => 'EGP',
            'current_value_egp' => 1000,
            'liquidity' => 'immediate',
        ]);
        $bucket = Bucket::create(['name' => 'Existing purpose', 'color' => '#123456']);
        $asset->buckets()->attach($bucket, ['amount_egp' => 400]);

        $response = $this->callTool(app(FinancialMcpServer::class), 'set_asset_allocations', [
            'asset_id' => $asset->id,
            'allocations' => [['bucket_id' => $bucket->id, 'amount_egp' => 1000]],
        ]);

        $this->assertFalse($response['result']['isError'] ?? false);
        $data = $response['result']['structuredContent']['data'];
        $audit = AuditLog::findOrFail($data['audit_id']);

        $this->assertEquals(400.0, (float) data_get($audit->before_state, 'buckets.0.pivot.amount_egp'));
        $this->assertEquals(1000.0, (float) data_get($audit->after_state, 'buckets.0.pivot.amount_egp'));
        $this->assertEquals(1000.0, (float) $asset->fresh()->buckets()->first()->pivot->amount_egp);
    }

    public function test_builtin_mcp_tools_reject_unsupported_arguments(): void
    {
        $server = app(FinancialMcpServer::class);
        $cases = [
            ['get_dashboard', ['unexpected' => true]],
            ['get_monthly_review', ['month' => now()->format('Y-m'), 'unexpected' => true]],
            ['list_assets', ['unexpected' => true]],
            ['get_financial_settings', ['unexpected' => true]],
            ['get_audit_log', ['unexpected' => true]],
            ['create_asset', ['name' => 'Bad argument', 'unexpected' => true]],
            ['set_asset_allocations', ['asset_id' => 1, 'allocations' => [], 'unexpected' => true]],
        ];

        foreach ($cases as [$name, $arguments]) {
            $response = $this->callTool($server, $name, $arguments);

            $this->assertTrue($response['result']['isError'] ?? false, "{$name} should reject unsupported arguments.");
        }

        $malformed = $this->callToolWithRawArguments($server, 'get_dashboard', 'not-an-object');
        $this->assertTrue($malformed['result']['isError'] ?? false);
    }

    public function test_mcp_import_queues_rows_without_silent_posting(): void
    {
        $response = $this->callTool(app(FinancialMcpServer::class), 'import_csv', [
            'file_name' => 'statement.csv',
            'source' => 'bank',
            'csv' => "date,description,amount,currency,type\n2026-08-01,Salary,10000,EGP,income\n",
        ]);

        $this->assertFalse($response['result']['isError'] ?? false);
        $data = $response['result']['structuredContent']['data'];
        $this->assertSame('review', $data['entity']['status']);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('import_rows', 1);
        $this->assertDatabaseHas('import_batches', ['file_name' => 'statement.csv', 'status' => 'review']);
    }

    public function test_mcp_manages_bucket_allocations_and_ledger_actuals(): void
    {
        $account = Account::create(['name' => 'MCP account', 'type' => 'bank', 'currency' => 'EGP']);
        $asset = Asset::create(['name' => 'MCP asset', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 5000, 'liquidity' => 'immediate']);
        $bucket = Bucket::create(['name' => 'MCP purpose', 'color' => '#123456']);

        $allocation = $this->callTool(app(FinancialMcpServer::class), 'set_bucket_allocations', [
            'bucket_id' => $bucket->id,
            'allocations' => [['asset_id' => $asset->id, 'amount_egp' => 2500]],
        ]);
        $this->assertFalse($allocation['result']['isError'] ?? false);
        $this->assertDatabaseHas('asset_bucket_allocations', ['asset_id' => $asset->id, 'bucket_id' => $bucket->id, 'amount_egp' => 2500]);

        $plan = AllocationPlan::create(['month' => now()->startOfMonth(), 'planned_income_egp' => 10000, 'planned_expenses_egp' => 0]);
        $plan->items()->create(['bucket_id' => $bucket->id, 'planned_amount_egp' => 1000]);
        LedgerTransaction::create([
            'account_id' => $account->id,
            'purpose_bucket_id' => $bucket->id,
            'transaction_type' => 'contribution',
            'occurred_on' => now()->startOfMonth(),
            'description' => 'MCP actual',
            'amount' => 800,
            'currency' => 'EGP',
            'amount_egp' => 800,
            'review_state' => 'confirmed',
            'source' => 'mcp-test',
        ]);

        $sync = $this->callTool(app(FinancialMcpServer::class), 'sync_allocation_plan_actuals', ['allocation_plan_id' => $plan->id]);
        $this->assertFalse($sync['result']['isError'] ?? false);
        $this->assertTrue($sync['result']['structuredContent']['data']['sync']['synced']);
        $this->assertDatabaseHas('allocation_plan_items', ['allocation_plan_id' => $plan->id, 'actual_amount_egp' => 800, 'actual_source' => 'confirmed_ledger']);
    }

    public function test_mcp_prepares_next_month_from_a_closed_review_with_provenance(): void
    {
        $bucket = Bucket::create(['name' => 'Long-Term Investing', 'purpose_type' => 'investment', 'color' => '#123456']);
        $review = MonthlyFinancialReview::create([
            'month' => now()->startOfMonth(),
            'income_egp' => 10000,
            'essential_expenses_egp' => 2000,
            'lifestyle_expenses_egp' => 1000,
            'status' => 'closed',
        ]);

        $response = $this->callTool(app(FinancialMcpServer::class), 'prepare_next_month', ['review_id' => $review->id, 'lesson' => 'Keep investing consistent.']);

        $this->assertFalse($response['result']['isError'] ?? false);
        $plan = AllocationPlan::whereDate('month', now()->startOfMonth()->addMonth())->firstOrFail();
        $this->assertSame($review->id, $plan->source_review_id);
        $this->assertSame('prepared_from_review', $plan->generation_method);
        $this->assertSame(5600.0, (float) $plan->items()->sum('planned_amount_egp'));
        $this->assertStringContainsString('Keep investing consistent.', (string) $plan->notes);
        $this->assertSame($bucket->id, $plan->items()->first()?->bucket_id);
    }

    /** @return array<string, mixed> */
    private function callTool(FinancialMcpServer $server, string $name, array $arguments): array
    {
        $handle = new ReflectionMethod($server, 'handle');
        $handle->setAccessible(true);

        /** @var array<string, mixed> $response */
        $response = $handle->invoke($server, [
            'jsonrpc' => '2.0',
            'id' => random_int(10, 1000),
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);

        return $response;
    }

    /** @return array<string, mixed> */
    private function callToolWithRawArguments(FinancialMcpServer $server, string $name, mixed $arguments): array
    {
        $handle = new ReflectionMethod($server, 'handle');
        $handle->setAccessible(true);

        /** @var array<string, mixed> $response */
        $response = $handle->invoke($server, [
            'jsonrpc' => '2.0',
            'id' => random_int(10, 1000),
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);

        return $response;
    }
}
