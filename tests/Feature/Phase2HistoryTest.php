<?php

namespace Tests\Feature;

use App\Mcp\FinancialMcpServer;
use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\ImportRow;
use App\Models\LedgerTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalanceHistory;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class Phase2HistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_ledger_is_one_source_and_transfers_are_not_monthly_spending(): void
    {
        $account = Account::create(['name' => 'Current account', 'type' => 'bank', 'currency' => 'EGP']);
        $other = Account::create(['name' => 'Savings account', 'type' => 'bank', 'currency' => 'EGP']);
        LedgerTransaction::create(['account_id' => $account->id, 'transaction_type' => 'income', 'occurred_on' => '2026-08-01', 'description' => 'Salary', 'amount' => 1000, 'currency' => 'EGP', 'amount_egp' => 1000, 'review_state' => 'confirmed', 'source' => 'manual']);
        LedgerTransaction::create(['account_id' => $account->id, 'transaction_type' => 'expense', 'occurred_on' => '2026-08-02', 'description' => 'Food', 'amount' => 100, 'currency' => 'EGP', 'amount_egp' => 100, 'review_state' => 'confirmed', 'source' => 'manual']);
        LedgerTransaction::create(['account_id' => $account->id, 'counter_account_id' => $other->id, 'transaction_type' => 'transfer', 'occurred_on' => '2026-08-03', 'description' => 'Move to savings', 'amount' => 500, 'currency' => 'EGP', 'amount_egp' => 500, 'review_state' => 'confirmed', 'source' => 'manual']);

        $summary = app(LedgerService::class)->summarizeMonth(Carbon::parse('2026-08-01'));

        $this->assertSame('confirmed_ledger', $summary['source']);
        $this->assertSame(1000.0, $summary['income']);
        $this->assertSame(100.0, $summary['lifestyleExpenses']);
        $this->assertSame(3, $summary['sourceTransactionCount']);
        $this->assertSame(400.0, app(LedgerService::class)->reconciliation(Carbon::parse('2026-08-01'))['accounts'][0]['ledgerBalance']);
    }

    public function test_csv_import_is_deduplicated_and_never_posts_without_review(): void
    {
        $response = $this->post(route('ledger.imports.store'), ['file_name' => 'statement.csv', 'csv' => "date,description,amount,currency,transaction_type\n2026-08-01,Salary,1000,EGP,income\n2026-08-02,Coffee,50,EGP,expense\n"]);
        $response->assertRedirect();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('import_rows', 2);

        $this->post(route('ledger.imports.store'), ['file_name' => 'statement-again.csv', 'csv' => "date,description,amount,currency,transaction_type\n2026-08-01,Salary,1000,EGP,income\n"]);
        $this->assertDatabaseHas('import_rows', ['review_state' => 'duplicate']);
        $this->assertDatabaseCount('transactions', 0);

        $row = ImportRow::where('review_state', 'pending')->firstOrFail();
        $this->post(route('ledger.import-rows.accept', $row));
        $this->assertDatabaseHas('transactions', ['import_row_id' => $row->id, 'review_state' => 'confirmed']);
        $this->assertSame('accepted', $row->fresh()->review_state);
    }

    public function test_dated_valuation_projection_and_historical_mcp_snapshot_are_attributed(): void
    {
        $asset = Asset::create(['name' => 'Fund', 'type' => 'Fixed income', 'currency' => 'EGP', 'current_value_egp' => 1500, 'liquidity' => 'within_3_days']);
        AssetValuation::create(['asset_id' => $asset->id, 'valued_on' => '2026-08-01', 'value_egp' => 1200, 'currency' => 'EGP', 'source' => 'statement', 'valuation_method' => 'closing_mark']);
        LedgerTransaction::create(['transaction_type' => 'contribution', 'occurred_on' => '2026-08-01', 'description' => 'Funding', 'amount' => 200, 'currency' => 'EGP', 'amount_egp' => 200, 'review_state' => 'confirmed', 'source' => 'manual']);

        $projection = app(LedgerService::class)->snapshotAt(Carbon::parse('2026-08-01'));
        $this->assertSame(1200.0, $projection['totalAssets']);
        $this->assertSame(200.0, $projection['attribution']['contributions']);

        $data = $this->callMcp('create_historical_snapshot', ['as_of' => '2026-08-01', 'notes' => 'Baseline']);
        $this->assertSame('dated_ledger', $data['entity']['historical_source']);
        $this->assertSame(1200.0, (float) $data['entity']['net_worth_egp']);
        $this->assertDatabaseHas('snapshots', ['as_of' => '2026-08-01 00:00:00', 'capture_basis' => 'dated_ledger']);
    }

    public function test_new_ledger_mcp_resources_have_validated_crud_and_reflection(): void
    {
        $tools = $this->callMcpRaw('tools/list', [])['result']['tools'];
        foreach (['create_account', 'create_transaction', 'create_asset_valuation', 'create_fx_rate', 'create_import_batch', 'create_liability_balance_history', 'accept_import_row', 'get_reconciliation'] as $tool) {
            $this->assertTrue(collect($tools)->contains('name', $tool), $tool.' is missing');
        }

        $data = $this->callMcp('create_account', ['name' => 'Agent account', 'type' => 'bank', 'currency' => 'EGP']);
        $this->assertNotEmpty($data['audit_id']);
        $this->assertArrayHasKey('summary', $data['dashboard_delta']);
        $this->assertDatabaseHas('accounts', ['name' => 'Agent account']);
    }

    public function test_historical_projection_is_incomplete_when_an_active_asset_lacks_an_as_of_valuation(): void
    {
        $covered = Asset::create(['name' => 'Covered fund', 'type' => 'Fund', 'currency' => 'EGP', 'current_value_egp' => 1000, 'liquidity' => 'within_3_days']);
        $uncovered = Asset::create(['name' => 'Uncovered fund', 'type' => 'Fund', 'currency' => 'EGP', 'current_value_egp' => 900, 'liquidity' => 'within_3_days']);
        AssetValuation::create(['asset_id' => $covered->id, 'valued_on' => '2026-08-01', 'value_egp' => 1000, 'currency' => 'EGP', 'source' => 'statement', 'valuation_method' => 'closing_mark']);

        $projection = app(LedgerService::class)->snapshotAt(Carbon::parse('2026-08-01'));

        $this->assertSame('incomplete', $projection['status']);
        $this->assertContains($uncovered->id, $projection['missingHistoricalSources']['assetsWithoutValuation']);
        $this->assertStringContainsString((string) $uncovered->id, implode(' ', $projection['limitations']));
        $response = $this->callMcpRaw('tools/call', ['name' => 'create_historical_snapshot', 'arguments' => ['as_of' => '2026-08-01', 'notes' => 'Should remain blocked']]);
        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('historical sources', strtolower($response['result']['content'][0]['text']));
        $this->assertDatabaseCount('snapshots', 0);
    }

    public function test_current_only_liability_never_enters_a_historical_snapshot(): void
    {
        $asset = Asset::create(['name' => 'Fund', 'type' => 'Fund', 'currency' => 'EGP', 'current_value_egp' => 1500, 'liquidity' => 'within_3_days']);
        AssetValuation::create(['asset_id' => $asset->id, 'valued_on' => '2026-08-01', 'value_egp' => 1500, 'currency' => 'EGP', 'source' => 'statement', 'valuation_method' => 'closing_mark']);
        $liability = Liability::create(['name' => 'Card', 'type' => 'card', 'balance_egp' => 400, 'monthly_payment_egp' => 50, 'is_active' => true]);

        $projection = app(LedgerService::class)->snapshotAt(Carbon::parse('2026-08-01'));

        $this->assertSame('incomplete', $projection['status']);
        $this->assertSame(0.0, $projection['liabilities']);
        $this->assertContains($liability->id, $projection['missingHistoricalSources']['liabilitiesWithoutBalanceHistory']);
        $this->assertStringContainsString('balance history', strtolower(implode(' ', $projection['limitations'])));
        $response = $this->callMcpRaw('tools/call', ['name' => 'create_historical_snapshot', 'arguments' => ['as_of' => '2026-08-01', 'notes' => 'Should remain blocked']]);
        $this->assertTrue($response['result']['isError']);

        $history = $this->callMcp('create_liability_balance_history', ['liability_id' => $liability->id, 'as_of' => '2026-08-01', 'balance_egp' => 350, 'source' => 'statement']);
        $this->assertNotEmpty($history['audit_id']);
        $historyId = (int) $history['entity']['id'];
        $this->callMcp('archive_liability_balance_history', ['id' => $historyId]);
        $this->assertSoftDeleted('liability_balance_histories', ['id' => $historyId]);
        $this->callMcp('restore_liability_balance_history', ['id' => $historyId]);
        $this->assertDatabaseHas('liability_balance_histories', ['id' => $historyId, 'deleted_at' => null]);
        $projection = app(LedgerService::class)->snapshotAt(Carbon::parse('2026-08-01'));
        $this->assertSame('confirmed', $projection['status']);
        $this->assertSame(350.0, $projection['liabilities']);
        $this->assertSame(1150.0, $projection['netWorth']);
    }

    public function test_liability_history_dashboard_supports_edit_archive_and_restore(): void
    {
        $liability = Liability::create(['name' => 'Loan', 'type' => 'loan', 'balance_egp' => 1000, 'monthly_payment_egp' => 100, 'is_active' => true]);
        $this->post(route('liability-history.store'), ['liability_id' => $liability->id, 'as_of' => '2026-08-01', 'balance_egp' => 900, 'source' => 'statement'])->assertRedirect();
        $history = LiabilityBalanceHistory::query()->firstOrFail();

        $this->put(route('liability-history.update', $history), ['liability_id' => $liability->id, 'as_of' => '2026-08-01', 'balance_egp' => 850, 'source' => 'corrected_statement'])->assertRedirect();
        $this->assertDatabaseHas('liability_balance_histories', ['id' => $history->id, 'balance_egp' => 850, 'source' => 'corrected_statement']);
        $this->delete(route('liability-history.destroy', $history))->assertRedirect();
        $this->assertSoftDeleted('liability_balance_histories', ['id' => $history->id]);
        $this->post(route('liability-history.restore', $history->id))->assertRedirect();
        $this->assertDatabaseHas('liability_balance_histories', ['id' => $history->id, 'deleted_at' => null]);
    }

    /** @param array<string, mixed> $arguments @return array<string, mixed> */
    private function callMcp(string $name, array $arguments): array
    {
        return $this->callMcpRaw('tools/call', ['name' => $name, 'arguments' => $arguments])['result']['structuredContent']['data'];
    }

    /** @param array<string, mixed> $params @return array<string, mixed> */
    private function callMcpRaw(string $method, array $params): array
    {
        $handle = new ReflectionMethod(app(FinancialMcpServer::class), 'handle');
        $handle->setAccessible(true);

        /** @var array<string, mixed> $response */
        $response = $handle->invoke(app(FinancialMcpServer::class), ['jsonrpc' => '2.0', 'id' => random_int(1, 1000), 'method' => $method, 'params' => $params]);

        return $response;
    }
}
