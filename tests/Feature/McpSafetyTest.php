<?php

namespace Tests\Feature;

use App\Mcp\FinancialMcpServer;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Bucket;
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
        $this->assertFalse($tools->firstWhere('name', 'create_asset')['annotations']['readOnlyHint']);
        $createAsset = $tools->firstWhere('name', 'create_asset');
        $this->assertArrayNotHasKey('id', $createAsset['inputSchema']['properties']);
        $this->assertContains('name', $createAsset['inputSchema']['required']);
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
