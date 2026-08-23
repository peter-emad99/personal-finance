<?php

namespace App\Mcp;

use App\Models\Account;
use App\Models\AllocationPlan;
use App\Models\AllocationRule;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Bucket;
use App\Models\BudgetCategory;
use App\Models\BudgetRule;
use App\Models\CashFlow;
use App\Models\DecisionJournalEntry;
use App\Models\FinancialSetting;
use App\Models\FxRate;
use App\Models\Goal;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\LedgerTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalanceHistory;
use App\Models\LiabilityPaymentRecord;
use App\Models\MonthlyFinancialReview;
use App\Models\PlanTemplate;
use App\Models\RecurringCommitment;
use App\Models\Snapshot;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\AllocationActualService;
use App\Services\AuditLogger;
use App\Services\BackupService;
use App\Services\BudgetRuleService;
use App\Services\FinanceService;
use App\Services\IntegrityService;
use App\Services\LedgerService;
use App\Services\MonthlyReviewActualService;
use App\Services\MonthlyReviewGuard;
use App\Support\OwnerContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/** Local owner-agent control plane with explicit, validated domain tools. */
class FinancialMcpServer
{
    public function __construct(private readonly FinanceService $finance, private readonly LedgerService $ledger, private readonly BackupService $backups, private readonly IntegrityService $integrity, private readonly AllocationActualService $allocationActuals, private readonly MonthlyReviewGuard $reviewGuard, private readonly MonthlyReviewActualService $monthlyReviewActuals)
    {
        // Direct in-process protocol tests and local artisan invocations still
        // use the explicitly bootstrapped owner. HTTP never receives this
        // fallback because MCP has no web route/transport.
        if (OwnerContext::id() === null && app()->runningInConsole()) {
            $owner = User::query()->where('email', config('finance.owner_email'))->first();
            if ($owner !== null) {
                OwnerContext::set($owner);
            }
        }
    }

    public function run(): void
    {
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            try {
                $request = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $response = $this->handle(is_array($request) ? $request : []);
                if ($response !== null) {
                    $this->write($response);
                }
            } catch (Throwable $exception) {
                $this->write(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Invalid JSON-RPC message.']]);
                fwrite(STDERR, $exception->getMessage().PHP_EOL);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>|null
     */
    private function handle(array $request): ?array
    {
        $method = (string) ($request['method'] ?? '');
        $id = $request['id'] ?? null;
        if ($id === null && str_starts_with($method, 'notifications/')) {
            return null;
        }

        return match ($method) {
            'initialize' => $this->success($id, ['protocolVersion' => '2025-06-18', 'capabilities' => ['tools' => ['listChanged' => false]], 'serverInfo' => ['name' => 'personal-finance-os', 'version' => '0.3.0'], 'instructions' => 'Local owner-agent financial control plane. Outputs are explainable planning data, not regulated financial advice. All financial records are owner-scoped, validated, audited, and soft-archived by default.']),
            'ping' => $this->success($id, new \stdClass),
            'tools/list' => $this->success($id, ['tools' => $this->tools()]),
            'tools/call' => $this->callTool($id, data_get($request, 'params.name'), data_get($request, 'params.arguments', [])),
            default => $this->error($id, -32601, "Method '{$method}' not found."),
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function tools(): array
    {
        $tools = [
            $this->tool('get_dashboard', 'Get the current dashboard projection, policy, freshness, and limitations.', []),
            $this->tool('get_financial_overview', 'Alias for get_dashboard.', []),
            $this->tool('get_dashboard_delta', 'Get current dashboard summary values and projection version.', []),
            $this->tool('get_full_financial_context', 'Get versioned JSON context for the local owner agent.', ['scope' => ['type' => 'string', 'enum' => ['dashboard_summary', 'full_financial_context', 'decision_context', 'redacted_context']]]),
            $this->tool('export_financial_context', 'Export versioned JSON context for local use.', ['scope' => ['type' => 'string', 'enum' => ['dashboard_summary', 'full_financial_context', 'decision_context', 'redacted_context']]]),
            $this->tool('get_decision_context', 'Get complete exportable decision context.', ['scope' => ['type' => 'string', 'enum' => ['dashboard_summary', 'full_financial_context', 'decision_context', 'redacted_context']]]),
            $this->tool('get_redacted_context', 'Get redacted context with exact balances, account names, and notes removed.', []),
            $this->tool('get_monthly_review', 'Get a monthly review by month (YYYY-MM).', ['month' => ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}$']]),
            $this->tool('evaluate_purchase', 'Evaluate a purchase against the configured liquidity reserve policy.', ['price' => ['type' => 'number', 'minimum' => 0], 'mode' => ['type' => 'string', 'enum' => ['cash', 'finance']], 'down_payment' => ['type' => 'number', 'minimum' => 0], 'interest_rate' => ['type' => 'number', 'minimum' => 0], 'tenure' => ['type' => 'integer', 'minimum' => 1], 'monthly_savings_after_purchase' => ['type' => 'number'], 'monthly_savings' => ['type' => 'number']], ['price', 'mode']),
            $this->tool('list_assets', 'List assets, optionally including archived records.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_buckets', 'List purpose buckets, optionally including archived records.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_goals', 'List goals with portfolio-wide funding feasibility.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_cash_flow', 'List cash-flow entries.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_monthly_reviews', 'List monthly reviews.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_recurring_commitments', 'List recurring commitments.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_liabilities', 'List liabilities.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_allocation_plans', 'List allocation plans.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_plan_templates', 'List reusable income, expense, and savings allocation templates.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_snapshots', 'List current-state manual snapshots.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('get_financial_settings', 'Get the active financial policy.', []),
            $this->tool('get_audit_log', 'List recent mutation audit records.', ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200]]),
            $this->tool('list_backups', 'List owner-scoped backup metadata.', []),
            $this->tool('create_backup', 'Create an encrypted local database backup.', [], [], false),
            $this->tool('verify_backup', 'Decrypt and verify one backup checksum.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false),
            $this->tool('archive_backup', 'Soft-archive one backup metadata record.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false),
            $this->tool('restore_backup', 'Restore one archived backup metadata record.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false),
            $this->tool('purge_expired_backups', 'Permanently purge backups past their retention date after explicit confirmation.', ['confirm' => ['type' => 'boolean']], ['confirm'], false),
            $this->tool('validate_data_integrity', 'Run owner-scoped data-integrity checks.', [], [], false),
            $this->tool('get_allocation_reconciliation', 'Show goal, non-goal, and genuinely unallocated asset balances.', []),
            $this->tool('get_reconciliation', 'Compare account balances, expected/received income, due/paid commitments, and review completeness.', ['month' => ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}$']]),
            $this->tool('derive_monthly_review', 'Prepare open monthly actuals from confirmed ledger transactions. Review and close the month separately.', ['month' => ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}$']], ['month'], false),
            $this->tool('close_month', 'Close a derived or reviewed month and preserve its source provenance.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false),
            $this->tool('reopen_month', 'Reopen a closed month for a recorded revision.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false),
            $this->tool('prepare_next_month', 'Prepare the next month allocation plan from a closed monthly review.', ['review_id' => ['type' => 'integer', 'minimum' => 1], 'redirect_emergency_to' => ['type' => 'string', 'enum' => ['goals', 'investments']], 'lesson' => ['type' => 'string']], ['review_id'], false),
            $this->tool('sync_allocation_plan_actuals', 'Synchronize one allocation plan from confirmed ledger transactions.', ['allocation_plan_id' => ['type' => 'integer', 'minimum' => 1]], ['allocation_plan_id'], false),
            $this->tool('close_allocation_plan', 'Close a monthly allocation snapshot and protect it from future template edits.', ['allocation_plan_id' => ['type' => 'integer', 'minimum' => 1]], ['allocation_plan_id'], false),
            $this->tool('import_csv', 'Queue CSV text or parsed rows for review without silently posting transactions.', ['file_name' => ['type' => 'string'], 'source' => ['type' => 'string'], 'csv' => ['type' => 'string'], 'rows' => ['type' => 'array']], [], false),
            $this->tool('accept_import_row', 'Explicitly post one pending, non-duplicate CSV row as a confirmed transaction; optional fields override the queued row.', ['id' => ['type' => 'integer', 'minimum' => 1], 'account_id' => ['type' => 'integer', 'minimum' => 1], 'category_id' => ['type' => 'integer', 'minimum' => 1], 'transaction_type' => ['type' => 'string'], 'occurred_on' => ['type' => 'string', 'format' => 'date'], 'description' => ['type' => 'string'], 'amount' => ['type' => 'number', 'exclusiveMinimum' => 0], 'currency' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 3]], ['id'], false),
            $this->tool('reject_import_row', 'Reject one pending CSV row without posting it.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false),
            $this->tool('create_historical_snapshot', 'Create a dated snapshot only from dated valuation and confirmed ledger records.', ['as_of' => ['type' => 'string', 'format' => 'date'], 'notes' => ['type' => 'string']], ['as_of'], false),
            $this->tool('list_decision_journal_entries', 'List decision-journal entries, optionally including archived records.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_accounts', 'List ledger accounts.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_transaction_categories', 'List transaction categories.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_transactions', 'List canonical ledger transactions.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_asset_valuations', 'List dated asset valuations.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_fx_rates', 'List dated FX rates.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_import_batches', 'List CSV import batches and review status.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_import_rows', 'List reviewable CSV import rows.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_liability_balance_histories', 'List dated liability balances used by historical snapshots.', ['include_archived' => ['type' => 'boolean']]),
            $this->tool('list_liability_payment_records', 'List lender-style debt payment records with principal and interest.', ['include_archived' => ['type' => 'boolean']]),
        ];
        foreach (array_keys($this->rules()) as $resource) {
            $this->appendResourceTools($tools, $resource);
        }
        $tools[] = $this->tool('set_asset_allocations', 'Atomically replace one asset’s purpose allocations.', ['asset_id' => ['type' => 'integer', 'minimum' => 1], 'allocations' => ['type' => 'array']], ['asset_id', 'allocations'], false);
        $tools[] = $this->tool('reallocate_asset_balance', 'Change one asset’s allocation to a bucket while preserving the other allocations.', ['asset_id' => ['type' => 'integer', 'minimum' => 1], 'bucket_id' => ['type' => 'integer', 'minimum' => 1], 'amount_egp' => ['type' => 'number', 'minimum' => 0]], ['asset_id', 'bucket_id', 'amount_egp'], false);
        $tools[] = $this->tool('get_bucket_allocations', 'Get one bucket and all asset amounts assigned to it.', ['bucket_id' => ['type' => 'integer', 'minimum' => 1]], ['bucket_id']);
        $tools[] = $this->tool('set_bucket_allocations', 'Atomically replace one bucket’s asset funding allocations while respecting asset value and bucket target limits.', ['bucket_id' => ['type' => 'integer', 'minimum' => 1], 'allocations' => ['type' => 'array']], ['bucket_id', 'allocations'], false);

        return $tools;
    }

    /** @param array<int, array<string, mixed>> $tools */
    private function appendResourceTools(array &$tools, string $resource): void
    {
        $properties = [];
        $required = [];
        foreach ($this->rules($resource) as $key => $rule) {
            $properties[$key] = $this->propertySchema($resource, $key);
            if (is_string($rule) && str_contains($rule, 'required')) {
                $required[] = $key;
            }
        }
        $tools[] = $this->tool('get_'.$resource, 'Get a '.$resource.'.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id']);
        $tools[] = $this->tool('create_'.$resource, 'Create a '.$resource.'.', $properties, $required, false);
        $tools[] = $this->tool('update_'.$resource, 'Update a '.$resource.'.', $properties + ['id' => ['type' => 'integer', 'minimum' => 1]], ['id', ...$required], false);
        $tools[] = $this->tool('archive_'.$resource, 'Archive a '.$resource.'.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false);
        $tools[] = $this->tool('restore_'.$resource, 'Restore an archived '.$resource.'.', ['id' => ['type' => 'integer', 'minimum' => 1]], ['id'], false);
    }

    /** @return array<string, mixed> */
    private function propertySchema(string $resource, string $key): array
    {
        if ($key === 'policy' || $key === 'asset_class_targets' || $key === 'rule_result' || $key === 'metadata' || $key === 'raw_data') {
            return ['type' => 'object'];
        }
        if ($key === 'items' || $key === 'splits' || in_array($key, ['assumptions', 'alternatives'], true)) {
            return ['type' => 'array'];
        }
        if (in_array($key, ['is_active'], true)) {
            return ['type' => 'boolean'];
        }
        if (in_array($key, ['quantity', 'cost_basis_egp', 'current_value_egp', 'unit_price_egp', 'target_amount_egp', 'amount_egp', 'amount', 'opening_balance_egp', 'reported_balance_egp', 'value_egp', 'rate', 'exchange_rate', 'balance_egp', 'original_balance_egp', 'interest_rate_percent', 'monthly_payment_egp', 'planned_income_egp', 'planned_expenses_egp', 'income', 'essential_expenses', 'lifestyle_expenses', 'recurring_commitments', 'one_time_expenses', 'debt_payments', 'invested', 'manual_adjustment_egp', 'emergency_reserve_months', 'monthly_contribution_egp', 'rebalancing_tolerance_percent', 'minimum_cash_after_purchase_egp', 'maximum_monthly_payment_egp', 'maximum_debt_burden_percent', 'valuation_freshness_days'], true)) {
            return ['type' => 'number'];
        }
        if ($key === 'priority' || $key === 'due_day' || str_ends_with($key, '_id')) {
            return ['type' => 'integer'];
        }
        if (in_array($key, ['as_of', 'month', 'deadline', 'occurred_on', 'posted_on', 'next_due_on', 'renewal_on', 'payoff_on', 'review_date', 'valued_on', 'rate_date', 'reported_balance_as_of'], true)) {
            return ['type' => 'string', 'format' => $key === 'month' ? 'YYYY-MM' : 'date'];
        }

        return ['type' => 'string'];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, array $properties, array $required = [], bool $readOnly = true): array
    {
        return ['name' => $name, 'description' => $description, 'inputSchema' => ['type' => 'object', 'properties' => $properties ?: new \stdClass, 'required' => $required, 'additionalProperties' => false], 'annotations' => ['readOnlyHint' => $readOnly, 'destructiveHint' => ! $readOnly, 'openWorldHint' => false]];
    }

    /**
     * @return array<string, mixed>
     */
    private function callTool(mixed $id, mixed $name, mixed $arguments): array
    {
        $name = is_string($name) ? $name : '';
        try {
            if (! is_array($arguments)) {
                throw new \InvalidArgumentException('Tool arguments must be an object.');
            }
            $data = $this->dispatch($name, $arguments);

            return $this->success($id, ['content' => [['type' => 'text', 'text' => $this->encode($data)]], 'structuredContent' => ['data' => $data]]);
        } catch (Throwable $exception) {
            return $this->success($id, ['isError' => true, 'content' => [['type' => 'text', 'text' => $exception->getMessage()]]]);
        }
    }

    /** @param array<string, mixed> $arguments */
    private function dispatch(string $name, array $arguments): mixed
    {
        return match ($name) {
            'get_dashboard', 'get_financial_overview' => $this->dashboardTool($arguments),
            'get_dashboard_delta' => $this->dashboardDeltaTool($arguments),
            'get_full_financial_context', 'export_financial_context', 'get_decision_context' => $this->contextTool($arguments),
            'get_redacted_context' => $this->redactedTool($arguments),
            'get_monthly_review' => $this->monthlyReviewPayload($arguments),
            'evaluate_purchase' => $this->purchasePayload($arguments),
            'list_assets' => $this->listAssets($this->listFlag($arguments)),
            'list_buckets' => $this->listBuckets($this->listFlag($arguments)),
            'list_goals' => $this->listGoals($this->listFlag($arguments)),
            'list_cash_flow' => $this->listModel(CashFlow::class, $this->listFlag($arguments)),
            'list_monthly_reviews' => $this->listModel(MonthlyFinancialReview::class, $this->listFlag($arguments)),
            'list_recurring_commitments' => $this->listModel(RecurringCommitment::class, $this->listFlag($arguments)),
            'list_liabilities' => $this->listModel(Liability::class, $this->listFlag($arguments)),
            'list_allocation_plans' => $this->listModel(AllocationPlan::class, $this->listFlag($arguments)),
            'list_plan_templates' => $this->listModel(PlanTemplate::class, $this->listFlag($arguments)),
            'list_snapshots' => $this->listModel(Snapshot::class, $this->listFlag($arguments)),
            'get_financial_settings' => $this->financialSettingsTool($arguments),
            'get_audit_log' => $this->auditTool($arguments),
            'list_backups' => $this->listBackups($arguments),
            'create_backup' => $this->createBackupTool($arguments),
            'verify_backup' => $this->verifyBackupTool($arguments),
            'archive_backup' => $this->archiveBackupTool($arguments),
            'restore_backup' => $this->restoreBackupTool($arguments),
            'purge_expired_backups' => $this->purgeBackupsTool($arguments),
            'validate_data_integrity' => $this->integrityTool($arguments),
            'get_allocation_reconciliation' => $this->allocationReconciliation($arguments),
            'get_reconciliation' => $this->reconciliationTool($arguments),
            'derive_monthly_review' => $this->deriveReviewTool($arguments),
            'close_month' => $this->closeMonthTool($arguments),
            'reopen_month' => $this->reopenMonthTool($arguments),
            'prepare_next_month' => $this->prepareNextMonthTool($arguments),
            'sync_allocation_plan_actuals' => $this->syncAllocationPlanActualsTool($arguments),
            'close_allocation_plan' => $this->closeAllocationPlanTool($arguments),
            'import_csv' => $this->importCsvTool($arguments),
            'accept_import_row' => $this->acceptImportRowTool($arguments),
            'reject_import_row' => $this->rejectImportRowTool($arguments),
            'create_historical_snapshot' => $this->createHistoricalSnapshotTool($arguments),
            'list_decision_journal_entries', 'list_decision_journal' => $this->listModel(DecisionJournalEntry::class, $this->listFlag($arguments)),
            'list_accounts' => $this->listModel(Account::class, $this->listFlag($arguments)),
            'list_transaction_categories' => $this->listModel(TransactionCategory::class, $this->listFlag($arguments)),
            'list_transactions' => $this->listModel(LedgerTransaction::class, $this->listFlag($arguments)),
            'list_asset_valuations' => $this->listModel(AssetValuation::class, $this->listFlag($arguments)),
            'list_fx_rates' => $this->listModel(FxRate::class, $this->listFlag($arguments)),
            'list_import_batches' => $this->listModel(ImportBatch::class, $this->listFlag($arguments)),
            'list_import_rows' => $this->listModel(ImportRow::class, $this->listFlag($arguments)),
            'list_liability_balance_histories' => $this->listModel(LiabilityBalanceHistory::class, $this->listFlag($arguments)),
            'list_liability_payment_records' => $this->listModel(LiabilityPaymentRecord::class, $this->listFlag($arguments)),
            'set_asset_allocations' => $this->setAssetAllocations($arguments),
            'reallocate_asset_balance' => $this->reallocateAssetBalance($arguments),
            'get_bucket_allocations' => $this->getBucketAllocations($arguments),
            'set_bucket_allocations' => $this->setBucketAllocations($arguments),
            default => $this->dispatchResource($name, $arguments),
        };
    }

    /** @param array<string, mixed> $arguments */
    private function dispatchResource(string $name, array $arguments): mixed
    {
        foreach (array_keys($this->rules()) as $resource) {
            if ($name === 'get_'.$resource) {
                return $this->getResource($resource, $arguments);
            }
            if ($name === 'create_'.$resource) {
                return $this->create($resource, $arguments);
            }
            if ($name === 'update_'.$resource) {
                return $this->update($resource, $arguments);
            }
            if ($name === 'archive_'.$resource) {
                return $this->archive($resource, $arguments);
            }
            if ($name === 'restore_'.$resource) {
                return $this->restore($resource, $arguments);
            }
        }
        throw new \InvalidArgumentException("Tool '{$name}' not found.");
    }

    /** @return array<string, mixed> */
    private function rules(?string $resource = null): array
    {
        $all = [
            'asset' => ['name' => 'required|string|max:120', 'type' => 'required|string|max:60', 'quantity' => 'nullable|numeric|min:0', 'currency' => 'required|string|max:8', 'cost_basis_egp' => 'nullable|numeric|min:0', 'current_value_egp' => 'required|numeric|min:0', 'unit_price_egp' => 'nullable|numeric|min:0', 'acquired_on' => 'nullable|date', 'account_name' => 'nullable|string|max:120', 'account_id' => 'nullable|exists:accounts,id', 'liquidity' => 'required|in:immediate,within_3_days,longer_term,illiquid', 'notes' => 'nullable|string'],
            'bucket' => ['name' => 'required|string|max:120', 'purpose' => 'nullable|string|max:200', 'purpose_type' => 'required|in:emergency,goal,investment,other', 'target_amount_egp' => 'nullable|numeric|min:0', 'color' => 'required|string|max:20', 'goal_id' => 'nullable|exists:goals,id'],
            'goal' => ['name' => 'required|string|max:120', 'target_amount_egp' => 'required|numeric|min:0', 'deadline' => 'nullable|date', 'status' => 'nullable|in:active,completed,paused', 'priority' => 'required|integer|min:1|max:99', 'monthly_contribution_egp' => 'nullable|numeric|min:0', 'notes' => 'nullable|string'],
            'cash_flow' => ['type' => 'required|in:income,expense,obligation', 'category' => 'required|string|max:80', 'amount_egp' => 'required|numeric|min:0', 'occurred_on' => 'required|date', 'notes' => 'nullable|string'],
            'monthly_review' => ['month' => 'required|date_format:Y-m', 'income' => 'required|numeric|min:0', 'essential_expenses' => 'required|numeric|min:0', 'lifestyle_expenses' => 'required|numeric|min:0', 'recurring_commitments' => 'required|numeric|min:0', 'one_time_expenses' => 'required|numeric|min:0', 'debt_payments' => 'required|numeric|min:0', 'invested' => 'required|numeric|min:0', 'manual_adjustment_egp' => 'nullable|numeric', 'status' => 'sometimes|in:open', 'notes' => 'nullable|string'],
            'commitment' => ['name' => 'required|string|max:120', 'category' => 'required|string|max:80', 'amount_egp' => 'required|numeric|min:0', 'frequency' => 'required|in:weekly,monthly,quarterly,yearly', 'next_due_on' => 'nullable|date', 'renewal_on' => 'nullable|date', 'is_active' => 'nullable|boolean', 'notes' => 'nullable|string'],
            'liability' => ['name' => 'required|string|max:120', 'type' => 'required|string|max:80', 'balance_egp' => 'required|numeric|min:0', 'original_balance_egp' => 'nullable|numeric|min:0', 'interest_rate_percent' => 'nullable|numeric|min:0', 'monthly_payment_egp' => 'required|numeric|min:0', 'due_day' => 'nullable|integer|between:1,31', 'payoff_on' => 'nullable|date', 'is_active' => 'nullable|boolean', 'notes' => 'nullable|string'],
            'plan_template' => ['name' => 'required|string|max:120', 'description' => 'nullable|string|max:500', 'is_default' => 'nullable|boolean', 'is_active' => 'nullable|boolean'],
            'allocation_plan' => ['month' => 'required|date', 'plan_template_id' => 'nullable|exists:plan_templates,id', 'planned_income_egp' => 'required|numeric|min:0', 'planned_expenses_egp' => 'required|numeric|min:0', 'source_review_id' => 'nullable|exists:monthly_financial_reviews,id', 'generation_method' => 'nullable|in:manual,prepared_from_review,from_template', 'status' => 'nullable|in:open,closed', 'closed_at' => 'nullable|date', 'generated_at' => 'nullable|date', 'notes' => 'nullable|string', 'income_items' => 'nullable|array', 'items' => 'nullable|array', 'expenses' => 'nullable|array'],
            'budget_category' => ['name' => 'required|string|max:100', 'kind' => 'required|in:expense,income', 'color' => 'nullable|string|max:20', 'is_active' => 'nullable|boolean', 'is_default' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0'],
            'budget_rule' => ['name' => 'required|string|max:120', 'direction' => 'required|in:income,expense', 'budget_category_id' => 'nullable|exists:budget_categories,id', 'amount_egp' => 'nullable|numeric|min:0', 'percent_of_income' => 'nullable|numeric|between:0,100', 'frequency' => 'required|in:monthly,weekly,quarterly,yearly', 'is_active' => 'nullable|boolean'],
            'allocation_rule' => ['plan_template_id' => 'required|exists:plan_templates,id', 'asset_id' => 'required|exists:assets,id', 'bucket_id' => 'required|exists:buckets,id', 'asset_target' => 'nullable|string|max:160', 'allocation_percent' => 'required|numeric|between:0,100', 'is_active' => 'nullable|boolean', 'sort_order' => 'nullable|integer|min:0'],
            'snapshot' => ['as_of' => 'nullable|date', 'notes' => 'nullable|string'],
            'financial_settings' => ['name' => 'required|string|max:120', 'base_currency' => 'required|string|max:8', 'emergency_reserve_months' => 'required|integer|between:1,36', 'emergency_eligible_liquidity' => 'required|in:immediate,within_3_days', 'policy' => 'nullable|array', 'asset_class_targets' => 'nullable|array', 'rebalancing_tolerance_percent' => 'nullable|numeric|between:0,100', 'goal_funding_policy' => 'nullable|in:priority_order,manual_contributions', 'minimum_cash_after_purchase_egp' => 'nullable|numeric|min:0', 'maximum_monthly_payment_egp' => 'nullable|numeric|min:0', 'maximum_debt_burden_percent' => 'nullable|numeric|between:0,100', 'valuation_freshness_days' => 'nullable|integer|between:1,3650', 'is_active' => 'nullable|boolean'],
            'decision_journal_entry' => ['decision' => 'required|string|max:240', 'assumptions' => 'nullable|array', 'alternatives' => 'nullable|array', 'rule_result' => 'nullable|array', 'chosen_action' => 'nullable|string|max:240', 'review_date' => 'nullable|date', 'outcome' => 'nullable|string', 'status' => 'nullable|in:open,reviewed,closed'],
            'account' => ['name' => 'required|string|max:120', 'institution' => 'nullable|string|max:120', 'type' => 'required|in:bank,cash,brokerage,card,investment,other', 'currency' => 'required|string|size:3', 'opening_balance_egp' => 'nullable|numeric', 'reported_balance_egp' => 'nullable|numeric', 'reported_balance_as_of' => 'nullable|date', 'is_active' => 'nullable|boolean', 'notes' => 'nullable|string'],
            'transaction_category' => ['name' => 'required|string|max:100', 'kind' => 'required|in:income,expense,transfer,investment,adjustment', 'budget_category_id' => 'nullable|exists:budget_categories,id', 'parent_name' => 'nullable|string|max:100', 'is_system' => 'nullable|boolean'],
            'transaction' => ['account_id' => 'nullable|exists:accounts,id', 'counter_account_id' => 'nullable|exists:accounts,id|different:account_id', 'category_id' => 'nullable|exists:transaction_categories,id', 'purpose_bucket_id' => 'nullable|exists:buckets,id', 'import_batch_id' => 'nullable|exists:import_batches,id', 'import_row_id' => 'nullable|exists:import_rows,id', 'transaction_type' => 'required|in:income,expense,transfer,contribution,withdrawal,dividend,interest,fee,tax,debt_payment,obligation,correction', 'occurred_on' => 'required|date', 'posted_on' => 'nullable|date', 'description' => 'nullable|string|max:240', 'amount' => 'required|numeric|gt:0', 'currency' => 'required|string|size:3', 'exchange_rate' => 'nullable|numeric|gt:0', 'amount_egp' => 'nullable|numeric|gt:0', 'review_state' => 'nullable|in:pending,confirmed,rejected,void', 'source' => 'nullable|string|max:80', 'external_id' => 'nullable|string|max:180', 'fingerprint' => 'nullable|string|max:64', 'metadata' => 'nullable|array', 'splits' => 'nullable|array', 'notes' => 'nullable|string'],
            'asset_valuation' => ['asset_id' => 'required|exists:assets,id', 'valued_on' => 'required|date', 'value_egp' => 'required|numeric|min:0', 'quantity' => 'nullable|numeric|min:0', 'currency' => 'required|string|size:3', 'source' => 'required|string|max:120', 'valuation_method' => 'required|string|max:80', 'notes' => 'nullable|string'],
            'fx_rate' => ['base_currency' => 'required|string|size:3', 'quote_currency' => 'required|string|size:3|different:base_currency', 'rate_date' => 'required|date', 'rate' => 'required|numeric|gt:0', 'source' => 'required|string|max:120', 'method' => 'required|string|max:80', 'notes' => 'nullable|string'],
            'import_batch' => ['file_name' => 'nullable|string|max:240', 'source' => 'required|string|max:80', 'status' => 'nullable|in:review,partially_posted,posted,rejected,archived', 'total_rows' => 'nullable|integer|min:0', 'duplicate_rows' => 'nullable|integer|min:0', 'metadata' => 'nullable|array', 'notes' => 'nullable|string'],
            'import_row' => ['import_batch_id' => 'required|exists:import_batches,id', 'row_number' => 'required|integer|min:1', 'raw_data' => 'required|array', 'fingerprint' => 'required|string|max:64', 'duplicate_of_id' => 'nullable|exists:import_rows,id', 'account_id' => 'nullable|exists:accounts,id', 'category_id' => 'nullable|exists:transaction_categories,id', 'occurred_on' => 'nullable|date', 'description' => 'nullable|string|max:240', 'amount' => 'nullable|numeric|gt:0', 'exchange_rate' => 'nullable|numeric|gt:0', 'amount_egp' => 'nullable|numeric|gt:0', 'currency' => 'required|string|size:3', 'transaction_type' => 'required|in:income,expense,transfer,contribution,withdrawal,dividend,interest,fee,tax,debt_payment,obligation,correction', 'review_state' => 'nullable|in:pending,accepted,rejected,duplicate,rejected_duplicate', 'review_notes' => 'nullable|string'],
            'liability_balance_history' => ['liability_id' => 'required|exists:liabilities,id', 'as_of' => 'required|date', 'balance_egp' => 'required|numeric|min:0', 'source' => 'required|string|max:120', 'notes' => 'nullable|string'],
            'liability_payment_record' => ['liability_id' => 'required|exists:liabilities,id', 'paid_on' => 'required|date', 'payment_egp' => 'required|numeric|gt:0', 'principal_egp' => 'required|numeric|min:0', 'interest_egp' => 'required|numeric|min:0', 'fees_egp' => 'nullable|numeric|min:0', 'balance_after_egp' => 'nullable|numeric|min:0', 'source' => 'required|string|max:120', 'notes' => 'nullable|string'],
        ];

        if ($resource === null) {
            return $all;
        }

        return $all[$resource] ?? [];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function validated(string $operation, array $input, array $required = []): array
    {
        foreach ($required as $field) {
            if (! array_key_exists($field, $input)) {
                throw new \InvalidArgumentException("Missing required argument '{$field}'.");
            }
        }
        $rules = $operation === 'evaluate_purchase' ? ['price' => 'required|numeric|min:0', 'mode' => 'required|in:cash,finance', 'down_payment' => 'nullable|numeric|min:0', 'interest_rate' => 'nullable|numeric|min:0', 'tenure' => 'nullable|integer|min:1', 'monthly_savings_after_purchase' => 'nullable|numeric', 'monthly_savings' => 'nullable|numeric'] : $this->rules($operation);
        // Resource create/update tools expose only their declared fields. The
        // list and allocation tools validate their own dedicated arguments;
        // accepting those fields here would silently ignore typos or misplaced
        // arguments instead of returning a contract-level validation error.
        $allowedExtras = in_array('id', $required, true) ? ['id'] : [];
        $unknown = array_diff(array_keys($input), array_keys($rules), $allowedExtras);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unsupported arguments: '.implode(', ', $unknown));
        }

        $validated = Validator::make($input, $rules)->validate();
        if ($operation === 'financial_settings' && array_key_exists('asset_class_targets', $validated) && $validated['asset_class_targets'] !== null) {
            $this->validatePolicyTargets($validated['asset_class_targets']);
        }

        return $validated;
    }

    /** @param array<string, mixed> $targets */
    private function validatePolicyTargets(array $targets): void
    {
        $minimum = 0.0;
        $maximum = 0.0;
        foreach ($targets as $label => $range) {
            if (! is_array($range) || ! isset($range['min'], $range['target'], $range['max'])) {
                throw new \InvalidArgumentException("Allocation target '{$label}' needs min, target, and max.");
            }
            if ((float) $range['min'] > (float) $range['target'] || (float) $range['target'] > (float) $range['max']) {
                throw new \InvalidArgumentException("Allocation target '{$label}' must be between its minimum and maximum.");
            }
            $minimum += (float) $range['min'];
            $maximum += (float) $range['max'];
        }
        if ($minimum > 100 || $maximum < 100) {
            throw new \InvalidArgumentException('Allocation ranges must be able to contain 100% of the portfolio.');
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(string $resource, array $arguments): array
    {
        $data = $this->validated($resource, $arguments);
        if ($resource === 'cash_flow') {
            $this->reviewGuard->assertEditable($data['occurred_on']);
        }
        if ($resource === 'budget_rule') {
            $data = $this->normalizeBudgetRuleData($data);
        }

        return $this->mutate('create_'.$resource, 'create', null, function () use ($resource, $data): Model {
            return match ($resource) {
                'asset' => Asset::create($this->assetData($data)),
                'bucket' => Bucket::create($data),
                'goal' => $this->createGoal($data),
                'cash_flow' => CashFlow::create($data),
                'monthly_review' => $this->saveReview($data),
                'commitment' => $this->createCommitment($data),
                'liability' => Liability::create($data),
                'allocation_plan' => $this->saveAllocationPlan($data),
                'plan_template' => $this->createPlanTemplate($data),
                'budget_category' => BudgetCategory::create($data),
                'budget_rule' => BudgetRule::create($data),
                'allocation_rule' => $this->createAllocationRule($data),
                'snapshot' => $this->createSnapshot($data),
                'financial_settings' => $this->createSettings($data),
                'decision_journal_entry' => DecisionJournalEntry::create($data),
                'account' => Account::create($data),
                'transaction_category' => TransactionCategory::create($data),
                'transaction' => $this->createTransaction($data),
                'asset_valuation' => AssetValuation::create($data),
                'fx_rate' => FxRate::create($data),
                'import_batch' => ImportBatch::create($data),
                'import_row' => $this->createImportRow($data),
                'liability_balance_history' => LiabilityBalanceHistory::create($data),
                'liability_payment_record' => LiabilityPaymentRecord::create($data),
                default => throw new \InvalidArgumentException("Resource '{$resource}' is not supported."),
            };
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getResource(string $resource, array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $model = $this->find($resource, (int) ($arguments['id'] ?? 0));

        return $this->payload($model);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(string $resource, array $arguments): array
    {
        $id = (int) ($arguments['id'] ?? 0);
        $data = $this->validated($resource, $arguments, ['id']);
        unset($data['id']);
        $before = $this->find($resource, $id);
        if ($resource === 'budget_rule' && $before instanceof BudgetRule) {
            $data = $this->normalizeBudgetRuleData($data, $before);
        }
        if ($resource === 'financial_settings' && $before instanceof FinancialSetting && array_key_exists('is_active', $data) && ! $data['is_active'] && $before->is_active) {
            throw new \InvalidArgumentException('The active financial policy cannot be disabled.');
        }
        if ($resource === 'cash_flow' || $resource === 'transaction') {
            $this->reviewGuard->assertEditable($before->occurred_on);
            if (isset($data['occurred_on'])) {
                $this->reviewGuard->assertEditable($data['occurred_on']);
            }
        }

        return $this->mutate('update_'.$resource, 'update', $before, function () use ($resource, $id, $data): Model {
            return match ($resource) {
                'goal' => $this->updateGoal($id, $data),
                'monthly_review' => $this->saveReview($data, $id),
                'allocation_plan' => $this->saveAllocationPlan($data, $id),
                'budget_category' => tap(BudgetCategory::findOrFail($id), fn (BudgetCategory $model) => $model->update($data)),
                'budget_rule' => tap(BudgetRule::findOrFail($id), fn (BudgetRule $model) => $model->update($data)),
                'allocation_rule' => $this->updateAllocationRule($id, $data),
                'plan_template' => tap(PlanTemplate::findOrFail($id), fn (PlanTemplate $model) => $model->update($data)),
                'commitment' => $this->updateCommitment($id, $data),
                'snapshot' => $this->updateSnapshot($id, $data),
                'decision_journal_entry' => tap(DecisionJournalEntry::findOrFail($id), fn (DecisionJournalEntry $model) => $model->update($data)),
                'transaction' => $this->updateTransaction($id, $data),
                'import_batch' => tap(ImportBatch::findOrFail($id), function (ImportBatch $model) use ($data): void {
                    $model->update($data);
                    $model->refreshCounts();
                }),
                default => tap($this->find($resource, $id), fn (Model $model) => $model->update($resource === 'asset' ? $this->assetData($data) : $data)),
            };
        });
    }

    /** @param array<string, mixed> $data */
    private function normalizeBudgetRuleData(array $data, ?BudgetRule $existing = null): array
    {
        $direction = (string) ($data['direction'] ?? $existing?->direction ?? 'expense');
        if ($direction !== 'income') {
            return $data;
        }

        if ($existing === null && (! array_key_exists('amount_egp', $data) || $data['amount_egp'] === null)) {
            throw new \InvalidArgumentException('Income sources require a fixed amount_egp.');
        }
        if (array_key_exists('percent_of_income', $data) && $data['percent_of_income'] !== null) {
            throw new \InvalidArgumentException('Income sources use a fixed amount; percent_of_income is only for expense rules.');
        }
        $data['percent_of_income'] = null;

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function updateGoal(int $id, array $data): Goal
    {
        $goal = Goal::findOrFail($id);
        $goal->update($data);
        $goal->buckets()->update([
            'name' => $goal->name.' Fund',
            'purpose' => 'Reserved for '.$goal->name,
            'target_amount_egp' => $goal->target_amount_egp,
        ]);

        return $goal;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function archive(string $resource, array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $id = (int) ($arguments['id'] ?? 0);
        $before = $this->find($resource, $id);
        if ($resource === 'cash_flow' || $resource === 'transaction') {
            $this->reviewGuard->assertEditable($before->occurred_on);
        }

        $envelope = $this->mutate('archive_'.$resource, 'archive', $before, function () use ($resource, $id): Model {
            $model = $this->find($resource, $id);
            if ($resource === 'financial_settings' && $model instanceof FinancialSetting) {
                if ($model->is_active && FinancialSetting::query()->where('is_active', true)->count() <= 1) {
                    throw new \InvalidArgumentException('The active financial policy cannot be archived.');
                }
                $wasActive = $model->is_active;
                $model->delete();
                if ($wasActive) {
                    FinancialSetting::query()->latest('id')->first()?->update(['is_active' => true]);
                }

                return $model;
            }
            if ($resource === 'bucket' && $model instanceof Bucket && $model->goal_id !== null) {
                throw new \InvalidArgumentException('Goal buckets are managed by archiving or restoring the goal.');
            }
            if ($resource === 'goal') {
                Goal::query()->findOrFail($id)->buckets()->delete();
            }
            $model->delete();
            if ($resource === 'commitment') {
                $this->syncCommitmentRulesAcrossTemplates();
            }

            return $model;
        });
        if ($resource === 'transaction' && $before instanceof LedgerTransaction && $before->review_state === 'confirmed') {
            $this->allocationActuals->syncMonth(Carbon::parse($before->occurred_on));
        }

        return $envelope;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restore(string $resource, array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $id = (int) ($arguments['id'] ?? 0);
        $before = $this->find($resource, $id, true);
        if (($resource === 'cash_flow' || $resource === 'transaction') && isset($before->occurred_on)) {
            $this->reviewGuard->assertEditable($before->occurred_on);
        }

        $envelope = $this->mutate('restore_'.$resource, 'restore', $before, function () use ($resource, $id): Model {
            $model = $this->restoreModel($resource, $id);
            if ($resource === 'commitment') {
                $this->syncCommitmentRulesAcrossTemplates();
            }

            return $model;
        });
        if ($resource === 'transaction' && $before instanceof LedgerTransaction && $before->review_state === 'confirmed') {
            $this->allocationActuals->syncMonth(Carbon::parse($before->occurred_on));
        }

        return $envelope;
    }

    /**
     * @param  callable(): Model  $callback
     * @return array<string, mixed>
     */
    private function mutate(string $tool, string $action, ?Model $before, callable $callback): array
    {
        $beforeDashboard = $this->finance->dashboard();
        // Eloquent relations are mutable and allocation callbacks intentionally
        // reuse the loaded model instance. Capture a plain typed array before
        // running the callback so the audit's before_state cannot be rewritten
        // by sync/update operations performed during the mutation.
        /** @var array<string, mixed>|null $beforeState */
        $beforeState = $before?->toArray();
        [$model, $audit] = DB::transaction(function () use ($callback, $tool, $action, $beforeState): array {
            $model = AuditLogger::muteAutomaticLogging($callback);
            $audit = AuditLog::create(['action' => $action, 'entity_type' => $model::class, 'entity_id' => $model->getKey(), 'tool_name' => $tool, 'agent_id' => 'local-owner-agent', 'request_id' => $this->requestId(), 'before_state' => $beforeState, 'after_state' => $model->toArray()]);

            return [$model, $audit];
        });
        $afterDashboard = $this->finance->dashboard();
        $version = hash('sha256', $this->encode($afterDashboard));
        AuditLog::allowMaintenanceChanges(fn (): bool => (bool) $audit->update(['dashboard_version' => $version]));

        return ['entity' => $this->payload($model), 'dashboard_delta' => $this->dashboardDelta($beforeDashboard, $afterDashboard), 'affected_entities' => [['type' => $model::class, 'id' => $model->getKey()]], 'warnings' => [], 'audit_id' => $audit->getKey(), 'dashboard_version' => $version, 'undo_available' => in_array($action, ['create', 'update', 'archive'], true), 'data_freshness' => $afterDashboard['dataFreshness']];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>|null  $after
     * @return array<string, mixed>
     */
    private function dashboardDelta(array $before, ?array $after): array
    {
        if ($after === null) {
            return ['summary' => $before['summary'] ?? [], 'changed' => []];
        }
        $changed = [];
        foreach (($before['summary'] ?? []) as $key => $value) {
            if (($after['summary'][$key] ?? null) !== $value) {
                $changed[$key] = ['before' => $value, 'after' => $after['summary'][$key] ?? null];
            }
        }

        return ['summary' => $after['summary'] ?? [], 'changed' => $changed];
    }

    /** @return array<string, mixed> */
    private function dashboardDeltaPayload(): array
    {
        $dashboard = $this->finance->dashboard();

        return $this->dashboardDelta($dashboard, null) + [
            'dashboard_version' => hash('sha256', $this->encode($dashboard)),
            'data_freshness' => $dashboard['dataFreshness'],
            'limitations' => $dashboard['dataFreshness']['limitations'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dashboardTool(array $arguments): array
    {
        $this->assertArguments($arguments, []);
        $dashboard = $this->finance->dashboard();

        return $dashboard + ['data_freshness' => $dashboard['dataFreshness'], 'limitations' => $dashboard['dataFreshness']['limitations']];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dashboardDeltaTool(array $arguments): array
    {
        $this->assertArguments($arguments, []);

        return $this->dashboardDeltaPayload();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function contextTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['scope']);
        $scope = Validator::make($arguments, ['scope' => 'sometimes|in:dashboard_summary,full_financial_context,decision_context,redacted_context'])->validate()['scope'] ?? 'full_financial_context';
        AuditLog::create([
            'action' => 'export',
            'entity_type' => 'financial_context',
            'tool_name' => 'get_full_financial_context',
            'agent_id' => 'local-owner-agent',
            'request_id' => $this->requestId(),
            'after_state' => ['scope' => $scope],
        ]);
        if ($scope === 'redacted_context') {
            return $this->redactedContext();
        }
        $full = $this->finance->exportContext(null, $scope);
        if ($scope === 'dashboard_summary') {
            return collect($full)->only(['schema_version', 'generated_at', 'base_currency', 'dashboard_version', 'data_freshness', 'limitations', 'policy', 'summary', 'source_status', 'attention_queue', 'allocation_policy', 'debt_summary'])->all();
        }
        if ($scope === 'decision_context') {
            return collect($full)->only(['schema_version', 'generated_at', 'base_currency', 'dashboard_version', 'data_freshness', 'limitations', 'policy', 'summary', 'goals', 'monthly_review', 'allocation_plans', 'snapshots', 'decision_journal', 'attention_queue', 'audit_references'])->all();
        }

        return $full;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function redactedTool(array $arguments): array
    {
        $this->assertArguments($arguments, []);
        AuditLog::create([
            'action' => 'export',
            'entity_type' => 'financial_context',
            'tool_name' => 'get_redacted_context',
            'agent_id' => 'local-owner-agent',
            'request_id' => $this->requestId(),
            'after_state' => ['scope' => 'redacted_context'],
        ]);

        return $this->redactedContext();
    }

    /** @param array<string, mixed> $arguments */
    private function listFlag(array $arguments): bool
    {
        $this->assertArguments($arguments, ['include_archived']);
        $validated = Validator::make($arguments, ['include_archived' => 'sometimes|boolean'])->validate();

        return (bool) ($validated['include_archived'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    private function auditTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['limit']);
        $validated = Validator::make($arguments, ['limit' => 'sometimes|integer|min:1|max:200'])->validate();

        return AuditLog::latest()->limit((int) ($validated['limit'] ?? 50))->get()->toArray();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    private function listBackups(array $arguments): array
    {
        $this->assertArguments($arguments, []);

        return Backup::withTrashed()->latest()->limit(100)->get()->map(fn (Backup $backup): array => [
            'id' => $backup->id,
            'file_name' => $backup->file_name,
            'status' => $backup->status,
            'encrypted' => $backup->encrypted,
            'checksum' => $backup->checksum,
            'size_bytes' => $backup->size_bytes,
            'verified_at' => $backup->verified_at ? (string) $backup->verified_at : null,
            'retention_until' => $backup->retention_until ? (string) $backup->retention_until : null,
            'archived' => $backup->trashed(),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createBackupTool(array $arguments): array
    {
        $this->assertArguments($arguments, []);
        $backup = $this->backups->create();
        $audit = AuditLog::query()->where('entity_type', Backup::class)->where('entity_id', $backup->id)->latest()->first();

        return ['entity' => $backup->toArray(), 'dashboard_delta' => $this->dashboardDeltaPayload(), 'affected_entities' => [['type' => Backup::class, 'id' => $backup->id]], 'warnings' => [], 'audit_id' => $audit?->id, 'dashboard_version' => hash('sha256', $this->encode($this->finance->dashboard())), 'undo_available' => false, 'data_freshness' => $this->finance->dashboard()['dataFreshness']];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function verifyBackupTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $id = (int) Validator::make($arguments, ['id' => 'required|integer|min:1'])->validate()['id'];
        $backup = Backup::withTrashed()->findOrFail($id);
        $result = $this->backups->verify($backup);

        return ['backup' => $backup->fresh()->toArray(), 'verification' => $result, 'audit_id' => AuditLog::query()->where('entity_type', Backup::class)->where('entity_id', $id)->latest()->value('id')];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function archiveBackupTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $id = (int) Validator::make($arguments, ['id' => 'required|integer|min:1'])->validate()['id'];
        $backup = Backup::query()->findOrFail($id);
        $before = $backup->toArray();
        $backup->delete();
        $audit = AuditLog::query()->where('entity_type', Backup::class)->where('entity_id', $id)->latest()->first();

        return $this->backupMutationEnvelope($backup, $audit?->id, true) + ['before' => $before];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function restoreBackupTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $id = (int) Validator::make($arguments, ['id' => 'required|integer|min:1'])->validate()['id'];
        $backup = Backup::withTrashed()->findOrFail($id);
        $backup->restore();

        $backup = $backup->fresh();

        return $this->backupMutationEnvelope($backup, AuditLog::query()->where('entity_type', Backup::class)->where('entity_id', $id)->latest()->value('id'), true);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function purgeBackupsTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['confirm']);
        $confirmed = Validator::make($arguments, ['confirm' => 'required|boolean'])->validate()['confirm'];
        if ($confirmed !== true) {
            throw new \InvalidArgumentException('Explicit confirm=true is required before purge.');
        }

        $dashboard = $this->finance->dashboard();

        return ['purged' => $this->backups->purgeExpired(), 'dashboard_delta' => $this->dashboardDelta($dashboard, $dashboard), 'affected_entities' => [], 'warnings' => [], 'audit_id' => null, 'dashboard_version' => hash('sha256', $this->encode($dashboard)), 'undo_available' => false, 'data_freshness' => $dashboard['dataFreshness']];
    }

    /** @return array<string, mixed> */
    private function backupMutationEnvelope(Backup $backup, ?int $auditId, bool $undoAvailable): array
    {
        $dashboard = $this->finance->dashboard();

        return ['entity' => $backup->toArray(), 'dashboard_delta' => $this->dashboardDelta($dashboard, $dashboard), 'affected_entities' => [['type' => Backup::class, 'id' => $backup->id]], 'warnings' => [], 'audit_id' => $auditId, 'dashboard_version' => hash('sha256', $this->encode($dashboard)), 'undo_available' => $undoAvailable, 'data_freshness' => $dashboard['dataFreshness']];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function integrityTool(array $arguments): array
    {
        $this->assertArguments($arguments, []);

        return $this->integrity->run();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function financialSettingsTool(array $arguments): array
    {
        $this->assertArguments($arguments, []);
        $setting = FinancialSetting::active();
        $payload = $setting->toArray();
        $payload['asset_class_targets'] ??= FinancialSetting::defaultAssetClassTargets();
        $payload['rebalancing_tolerance_percent'] ??= 5;
        $payload['goal_funding_policy'] ??= 'priority_order';
        $payload['minimum_cash_after_purchase_egp'] ??= 0;
        $payload['valuation_freshness_days'] ??= 30;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  list<string>  $allowed
     */
    private function assertArguments(array $arguments, array $allowed): void
    {
        $unknown = array_diff(array_keys($arguments), $allowed);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unsupported arguments: '.implode(', ', $unknown));
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function purchasePayload(array $arguments): array
    {
        $inputs = $this->validated('evaluate_purchase', $arguments, ['price', 'mode']);
        $dashboard = $this->finance->dashboard();

        return $this->finance->purchaseAnalysis($inputs) + [
            'dataFreshness' => $dashboard['dataFreshness'],
            'data_freshness' => $dashboard['dataFreshness'],
            'limitations' => ['Planning math is not regulated financial advice.', ...$dashboard['dataFreshness']['limitations']],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function monthlyReviewPayload(array $arguments): array
    {
        $this->assertArguments($arguments, ['month']);
        $dashboard = $this->finance->dashboard();

        return $this->finance->monthlyReview($this->month($arguments['month'] ?? null)) + [
            'dataFreshness' => $dashboard['dataFreshness'],
            'data_freshness' => $dashboard['dataFreshness'],
            'limitations' => $dashboard['dataFreshness']['limitations'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function listAssets(bool $includeArchived): array
    {
        $query = $includeArchived ? Asset::withTrashed() : Asset::query();

        return $query->with('buckets')->get()->map(fn (Asset $asset): array => $this->finance->assetPayload($asset))->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function listBuckets(bool $includeArchived): array
    {
        $query = $includeArchived ? Bucket::withTrashed() : Bucket::query();

        return $query->with(['goal', 'assets'])->get()->map(fn (Bucket $bucket): array => [
            'id' => $bucket->id,
            'name' => $bucket->name,
            'purpose' => $bucket->purpose,
            'purposeType' => $bucket->goal_id !== null ? 'goal' : ($bucket->purpose_type ?? 'other'),
            'goalId' => $bucket->goal_id,
            'targetAmount' => (float) $bucket->target_amount_egp,
            'currentAmount' => $this->finance->bucketValue($bucket),
            'assetAllocations' => $bucket->assets->map(fn (Asset $asset): array => [
                'assetId' => $asset->id,
                'assetName' => $asset->name,
                'amountEgp' => (float) data_get($asset, 'pivot.amount_egp', 0),
            ])->values()->all(),
            'archived' => $bucket->trashed(),
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function listGoals(bool $includeArchived): array
    {
        $query = $includeArchived ? Goal::withTrashed() : Goal::query();
        $dashboard = $this->finance->dashboard();

        return $query->with('buckets')->get()->map(fn (Goal $goal): array => ($dashboard['goals']->firstWhere('id', $goal->id) ?? ['id' => $goal->id, 'name' => $goal->name, 'status' => $goal->status, 'archived' => $goal->trashed()]))->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function listModel(string $class, bool $includeArchived): array
    {
        $query = $includeArchived ? $class::withTrashed() : $class::query();

        return $query->latest()->get()->map(fn (Model $model): array => $this->payload($model))->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function assetData(array $data): array
    {
        $data['is_liquid'] = in_array($data['liquidity'], ['immediate', 'within_3_days'], true);
        unset($data['id']);

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function createGoal(array $data): Goal
    {
        return DB::transaction(function () use ($data): Goal {
            $goal = Goal::create($data + ['status' => 'active']);
            Bucket::create(['goal_id' => $goal->id, 'purpose_type' => 'goal', 'name' => $goal->name.' Fund', 'purpose' => 'Reserved for '.$goal->name, 'target_amount_egp' => $goal->target_amount_egp, 'color' => '#7c8cf8']);

            return $goal;
        });
    }

    /** @param array<string, mixed> $data */
    private function createCommitment(array $data): RecurringCommitment
    {
        $commitment = RecurringCommitment::create($data);
        $this->syncCommitmentRulesAcrossTemplates();

        return $commitment;
    }

    /** @param array<string, mixed> $data */
    private function createAllocationRule(array $data): AllocationRule
    {
        $asset = Asset::findOrFail($data['asset_id']);
        if (! $asset->buckets()->whereKey($data['bucket_id'])->exists()) {
            throw new \InvalidArgumentException('The selected bucket is not assigned to the selected asset.');
        }
        $data['asset_target'] ??= $asset->name;

        return AllocationRule::create($data);
    }

    /** @param array<string, mixed> $data */
    private function updateAllocationRule(int $id, array $data): AllocationRule
    {
        $rule = AllocationRule::findOrFail($id);
        $asset = Asset::findOrFail($data['asset_id']);
        if (! $asset->buckets()->whereKey($data['bucket_id'])->exists()) {
            throw new \InvalidArgumentException('The selected bucket is not assigned to the selected asset.');
        }
        $data['asset_target'] ??= $asset->name;
        $rule->update($data);

        return $rule;
    }

    /** @param array<string, mixed> $data */
    private function createPlanTemplate(array $data): PlanTemplate
    {
        $template = PlanTemplate::create($data + ['is_active' => true, 'is_default' => false]);
        app(BudgetRuleService::class)->template($template->id);

        return $template;
    }

    /** @param array<string, mixed> $data */
    private function updateCommitment(int $id, array $data): RecurringCommitment
    {
        $commitment = RecurringCommitment::findOrFail($id);
        $commitment->update($data);
        $this->syncCommitmentRulesAcrossTemplates();

        return $commitment;
    }

    private function syncCommitmentRulesAcrossTemplates(): void
    {
        $service = app(BudgetRuleService::class);
        foreach (PlanTemplate::query()->where('is_active', true)->get() as $template) {
            $service->syncCommitmentRules($template);
        }
    }

    /** @param array<string, mixed> $data */
    private function saveReview(array $data, ?int $id = null): MonthlyFinancialReview
    {
        $month = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()->toDateString();
        $review = $id ? MonthlyFinancialReview::findOrFail($id) : MonthlyFinancialReview::firstOrNew(['month' => $month]);
        if ($review->exists && $review->status === 'closed') {
            throw new \InvalidArgumentException('This month is closed. Reopen it before editing.');
        }
        $review->fill(['income_egp' => $data['income'], 'essential_expenses_egp' => $data['essential_expenses'], 'lifestyle_expenses_egp' => $data['lifestyle_expenses'], 'recurring_commitments_egp' => $data['recurring_commitments'], 'one_time_expenses_egp' => $data['one_time_expenses'], 'debt_payments_egp' => $data['debt_payments'], 'invested_egp' => $data['invested'], 'manual_adjustment_egp' => $data['manual_adjustment_egp'] ?? 0, 'status' => 'open', 'reconciliation_status' => 'pending', 'reconciled_at' => null, 'notes' => ($data['notes'] ?? null) ?: null])->save();
        $this->monthlyReviewActuals->syncManualPlanExpenseActuals(Carbon::parse($month), $data);

        return $review;
    }

    /** @param array<string, mixed> $data */
    private function saveAllocationPlan(array $data, ?int $id = null): AllocationPlan
    {
        if (array_key_exists('income_items', $data)) {
            $incomeTotal = round((float) collect($data['income_items'])->sum(fn (array $item): float => (float) ($item['planned_amount_egp'] ?? 0)), 2);
            if (abs($incomeTotal - (float) $data['planned_income_egp']) > 0.01) {
                throw new \InvalidArgumentException('Income sources must add up to the planned monthly income.');
            }
        }
        $plan = $id ? AllocationPlan::findOrFail($id) : AllocationPlan::firstOrNew(['month' => Carbon::parse($data['month'])->startOfMonth()->toDateString()]);
        if ($plan->exists && $plan->status === 'closed') {
            throw new \InvalidArgumentException('This monthly plan is closed and cannot be edited.');
        }
        $plan->fill([
            'month' => Carbon::parse($data['month'])->startOfMonth()->toDateString(),
            'plan_template_id' => $data['plan_template_id'] ?? $plan->plan_template_id,
            'planned_income_egp' => $data['planned_income_egp'],
            'planned_expenses_egp' => $data['planned_expenses_egp'],
            'source_review_id' => $data['source_review_id'] ?? $plan->source_review_id,
            'generation_method' => $data['generation_method'] ?? ($plan->generation_method ?: 'manual'),
            'status' => $data['status'] ?? ($plan->status ?: 'open'),
            'closed_at' => $data['closed_at'] ?? $plan->closed_at,
            'generated_at' => $data['generated_at'] ?? $plan->generated_at,
            'notes' => $data['notes'] ?? $plan->notes,
        ])->save();
        if (array_key_exists('income_items', $data)) {
            $plan->incomeItems()->delete();
            foreach ($data['income_items'] ?? [] as $item) {
                Validator::make($item, ['budget_rule_id' => 'nullable|exists:budget_rules,id', 'name' => 'required|string|max:120', 'planned_amount_egp' => 'required|numeric|min:0', 'actual_amount_egp' => 'nullable|numeric|min:0'])->validate();
                $plan->incomeItems()->create([
                    'budget_rule_id' => $item['budget_rule_id'] ?? null,
                    'name' => $item['name'],
                    'planned_amount_egp' => $item['planned_amount_egp'],
                    'actual_amount_egp' => $item['actual_amount_egp'] ?? 0,
                ]);
            }
        }
        if (array_key_exists('items', $data)) {
            $plan->items()->delete();
            $allocationKeys = [];
            $available = max(0, (float) $data['planned_income_egp'] - (float) $data['planned_expenses_egp']);
            $percentTotal = 0.0;
            $hasPercentage = false;
            $hasFixed = false;
            foreach ($data['items'] ?? [] as $item) {
                Validator::make($item, ['bucket_id' => 'required|exists:buckets,id', 'asset_id' => 'nullable|exists:assets,id', 'planned_amount_egp' => 'required|numeric|min:0', 'actual_amount_egp' => 'nullable|numeric|min:0', 'asset_target' => 'nullable|string|max:160', 'allocation_percent' => 'nullable|numeric|between:0,100'])->validate();
                $asset = isset($item['asset_id']) ? Asset::findOrFail($item['asset_id']) : null;
                if ($asset !== null && ! $asset->buckets()->whereKey($item['bucket_id'])->exists()) {
                    throw new \InvalidArgumentException('The selected bucket is not assigned to the selected asset.');
                }
                $allocationKey = (string) ($item['asset_id'] ?? 'none').':'.$item['bucket_id'];
                if (in_array($allocationKey, $allocationKeys, true)) {
                    throw new \InvalidArgumentException('The same asset and purpose bucket can only appear once in a monthly plan.');
                }
                $allocationKeys[] = $allocationKey;
                $usesPercentage = array_key_exists('allocation_percent', $item) && $item['allocation_percent'] !== null && $item['allocation_percent'] !== '';
                $hasPercentage = $hasPercentage || $usesPercentage;
                $hasFixed = $hasFixed || ! $usesPercentage;
                $percent = $usesPercentage ? (float) $item['allocation_percent'] : null;
                $percentTotal += $percent ?? 0;
                $amount = $usesPercentage ? round($available * $percent / 100, 2) : (float) $item['planned_amount_egp'];
                $plan->items()->create(['bucket_id' => $item['bucket_id'], 'asset_id' => $item['asset_id'] ?? null, 'asset_target' => $item['asset_target'] ?? $asset?->name, 'allocation_percent' => $percent, 'planned_amount_egp' => $amount, 'actual_amount_egp' => $item['actual_amount_egp'] ?? 0]);
            }
            if ($hasPercentage && $hasFixed) {
                throw new \InvalidArgumentException('Use percentages for every savings allocation row, or fixed amounts for every row.');
            }
            if ($percentTotal > 100.01) {
                throw new \InvalidArgumentException('Savings allocation percentages cannot exceed 100%.');
            }
        }
        if (array_key_exists('expenses', $data)) {
            $plan->expenseItems()->delete();
            foreach ($data['expenses'] ?? [] as $item) {
                Validator::make($item, ['category_id' => 'required|exists:budget_categories,id', 'planned_amount_egp' => 'required|numeric|min:0', 'actual_amount_egp' => 'nullable|numeric|min:0'])->validate();
                $plan->expenseItems()->create(['budget_category_id' => $item['category_id'], 'planned_amount_egp' => $item['planned_amount_egp'], 'actual_amount_egp' => $item['actual_amount_egp'] ?? 0]);
            }
        }

        return $plan;
    }

    /** @param array<string, mixed> $data */
    private function createSnapshot(array $data): Snapshot
    {
        $asOf = isset($data['as_of']) ? Carbon::parse($data['as_of']) : Carbon::today();
        if (! $asOf->isToday()) {
            throw new \InvalidArgumentException('Manual snapshots must use today’s date.');
        }
        $dashboard = $this->finance->dashboard();

        return Snapshot::updateOrCreate(['as_of' => $asOf->toDateString()], ['net_worth_egp' => $dashboard['summary']['netWorth'], 'liquid_assets_egp' => $dashboard['summary']['liquidAssets'], 'investable_net_worth_egp' => $dashboard['summary']['investableNetWorth'], 'income_egp' => $dashboard['summary']['income'], 'expenses_egp' => $dashboard['summary']['expenses'], 'free_cash_flow_egp' => $dashboard['summary']['freeCashFlow'], 'emergency_coverage_months' => $dashboard['summary']['emergencyCoverageMonths'], 'asset_breakdown' => $dashboard['assetAllocation'], 'notes' => $data['notes'] ?? null, 'capture_basis' => 'manual_current_state', 'captured_at' => now()]);
    }

    /** @param array<string, mixed> $data */
    private function updateSnapshot(int $id, array $data): Snapshot
    {
        $snapshot = Snapshot::findOrFail($id);
        $snapshot->update(['notes' => $data['notes'] ?? $snapshot->notes]);

        return $snapshot;
    }

    /** @param array<string, mixed> $data */
    private function createSettings(array $data): FinancialSetting
    {
        FinancialSetting::query()->update(['is_active' => false]);

        return FinancialSetting::create(array_replace([
            'asset_class_targets' => FinancialSetting::defaultAssetClassTargets(),
            'rebalancing_tolerance_percent' => 5,
            'goal_funding_policy' => 'priority_order',
            'minimum_cash_after_purchase_egp' => 0,
            'valuation_freshness_days' => 30,
        ], $data, ['is_active' => true]));
    }

    /** @param array<string, mixed> $data */
    private function createTransaction(array $data): LedgerTransaction
    {
        $this->reviewGuard->assertEditable($data['occurred_on']);
        if (strtoupper((string) $data['currency']) !== 'EGP' && ! isset($data['exchange_rate']) && ! isset($data['amount_egp'])) {
            throw new \InvalidArgumentException('A non-EGP transaction needs an explicit exchange rate or EGP amount.');
        }
        $splits = $data['splits'] ?? [];
        unset($data['splits']);
        $data['amount_egp'] ??= round((float) $data['amount'] * (float) ($data['exchange_rate'] ?? 1), 2);
        $data['review_state'] ??= 'confirmed';
        $data['source'] ??= 'mcp';
        $data['fingerprint'] ??= LedgerTransaction::fingerprintFor($data);
        $data['reviewed_at'] ??= $data['review_state'] === 'confirmed' ? now() : null;

        $transaction = LedgerTransaction::create($data);
        $this->syncTransactionSplits($transaction, $splits);
        if ($transaction->review_state === 'confirmed') {
            $this->allocationActuals->syncMonth(Carbon::parse($transaction->occurred_on));
        }

        return $transaction;
    }

    /** @param array<string, mixed> $data */
    private function updateTransaction(int $id, array $data): LedgerTransaction
    {
        $transaction = LedgerTransaction::findOrFail($id);
        $this->reviewGuard->assertEditable($transaction->occurred_on);
        $this->reviewGuard->assertEditable($data['occurred_on'] ?? $transaction->occurred_on);
        if (strtoupper((string) ($data['currency'] ?? $transaction->currency)) !== 'EGP' && ! isset($data['exchange_rate']) && ! isset($data['amount_egp']) && ($transaction->exchange_rate === null || strtoupper((string) $transaction->currency) === 'EGP')) {
            throw new \InvalidArgumentException('A non-EGP transaction needs an explicit exchange rate or EGP amount.');
        }
        $splits = $data['splits'] ?? null;
        unset($data['splits']);
        $previousMonth = Carbon::parse($transaction->occurred_on);
        $wasConfirmed = $transaction->review_state === 'confirmed';
        $data['amount_egp'] ??= round((float) ($data['amount'] ?? $transaction->amount) * (float) ($data['exchange_rate'] ?? $transaction->exchange_rate ?? 1), 2);
        $data['fingerprint'] = LedgerTransaction::fingerprintFor($data + ['account_id' => $transaction->account_id, 'occurred_on' => $transaction->occurred_on, 'description' => $transaction->description, 'currency' => $transaction->currency]);
        if (($data['review_state'] ?? $transaction->review_state) === 'confirmed') {
            $data['reviewed_at'] = now();
        }
        $transaction->update($data);
        if ($splits !== null) {
            $this->syncTransactionSplits($transaction, $splits);
        }
        if ($wasConfirmed) {
            $this->allocationActuals->syncMonth($previousMonth);
        }
        if ($transaction->review_state === 'confirmed') {
            $this->allocationActuals->syncMonth(Carbon::parse($transaction->occurred_on));
        }

        return $transaction;
    }

    /** @param array<int, mixed> $splits */
    private function syncTransactionSplits(LedgerTransaction $transaction, array $splits): void
    {
        if ($splits === []) {
            $transaction->splits()->delete();

            return;
        }
        $total = 0.0;
        $rows = [];
        foreach ($splits as $split) {
            if (! is_array($split)) {
                throw new \InvalidArgumentException('Every transaction split must be an object.');
            }
            $row = Validator::make($split, ['category_id' => 'nullable|exists:transaction_categories,id', 'purpose_bucket_id' => 'nullable|exists:buckets,id', 'amount_egp' => 'required|numeric|gt:0', 'transaction_type' => 'required|in:income,expense,contribution,withdrawal,dividend,interest,fee,tax,debt_payment,obligation,correction', 'notes' => 'nullable|string'])->validate();
            $total = round($total + (float) $row['amount_egp'], 2);
            $rows[] = $row;
        }
        if (abs($total - (float) $transaction->amount_egp) > 0.005) {
            throw new \InvalidArgumentException('Transaction splits must add up to the parent EGP amount.');
        }
        $transaction->splits()->delete();
        foreach ($rows as $row) {
            $transaction->splits()->create($row);
        }
    }

    /** @param array<string, mixed> $data */
    private function createImportRow(array $data): ImportRow
    {
        $existing = ImportRow::query()->where('fingerprint', $data['fingerprint'])->first();
        $data['duplicate_of_id'] ??= $existing?->id;
        $data['review_state'] ??= $existing ? 'duplicate' : 'pending';

        return ImportRow::create($data);
    }

    private function find(string $resource, int $id, bool $withTrashed = false): Model
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('A positive id is required.');
        }
        $class = match ($resource) {
            'asset' => Asset::class, 'bucket' => Bucket::class, 'budget_category' => BudgetCategory::class, 'budget_rule' => BudgetRule::class, 'allocation_rule' => AllocationRule::class, 'plan_template' => PlanTemplate::class, 'goal' => Goal::class, 'cash_flow' => CashFlow::class, 'monthly_review' => MonthlyFinancialReview::class, 'commitment' => RecurringCommitment::class, 'liability' => Liability::class, 'allocation_plan' => AllocationPlan::class, 'snapshot' => Snapshot::class, 'financial_settings' => FinancialSetting::class, 'decision_journal_entry' => DecisionJournalEntry::class,
            'account' => Account::class, 'transaction_category' => TransactionCategory::class, 'transaction' => LedgerTransaction::class, 'asset_valuation' => AssetValuation::class, 'fx_rate' => FxRate::class, 'import_batch' => ImportBatch::class, 'import_row' => ImportRow::class, 'liability_balance_history' => LiabilityBalanceHistory::class, 'liability_payment_record' => LiabilityPaymentRecord::class,
            default => throw new \InvalidArgumentException("Resource '{$resource}' is not supported."),
        };

        return ($withTrashed ? $class::withTrashed() : $class::query())->findOrFail($id);
    }

    private function restoreModel(string $resource, int $id): Model
    {
        return match ($resource) {
            'asset' => tap(Asset::withTrashed()->findOrFail($id), fn (Asset $model) => $model->restore()),
            'bucket' => tap(Bucket::withTrashed()->findOrFail($id), fn (Bucket $model) => $model->restore()),
            'budget_category' => tap(BudgetCategory::withTrashed()->findOrFail($id), fn (BudgetCategory $model) => $model->restore()),
            'budget_rule' => tap(BudgetRule::withTrashed()->findOrFail($id), fn (BudgetRule $model) => $model->restore()),
            'allocation_rule' => tap(AllocationRule::withTrashed()->findOrFail($id), fn (AllocationRule $model) => $model->restore()),
            'goal' => tap(Goal::withTrashed()->findOrFail($id), function (Goal $model): void {
                $model->restore();
                Bucket::withTrashed()->where('goal_id', $model->id)->restore();
            }),
            'cash_flow' => tap(CashFlow::withTrashed()->findOrFail($id), fn (CashFlow $model) => $model->restore()),
            'monthly_review' => tap(MonthlyFinancialReview::withTrashed()->findOrFail($id), fn (MonthlyFinancialReview $model) => $model->restore()),
            'commitment' => tap(RecurringCommitment::withTrashed()->findOrFail($id), fn (RecurringCommitment $model) => $model->restore()),
            'liability' => tap(Liability::withTrashed()->findOrFail($id), fn (Liability $model) => $model->restore()),
            'allocation_plan' => tap(AllocationPlan::withTrashed()->findOrFail($id), fn (AllocationPlan $model) => $model->restore()),
            'plan_template' => tap(PlanTemplate::withTrashed()->findOrFail($id), fn (PlanTemplate $model) => $model->restore()),
            'snapshot' => tap(Snapshot::withTrashed()->findOrFail($id), fn (Snapshot $model) => $model->restore()),
            'decision_journal_entry' => tap(DecisionJournalEntry::withTrashed()->findOrFail($id), fn (DecisionJournalEntry $model) => $model->restore()),
            'financial_settings' => tap(FinancialSetting::withTrashed()->findOrFail($id), function (FinancialSetting $model): void {
                FinancialSetting::query()->update(['is_active' => false]);
                $model->restore();
                $model->update(['is_active' => true]);
            }),
            'account' => tap(Account::withTrashed()->findOrFail($id), fn (Account $model) => $model->restore()),
            'transaction_category' => tap(TransactionCategory::withTrashed()->findOrFail($id), fn (TransactionCategory $model) => $model->restore()),
            'transaction' => tap(LedgerTransaction::withTrashed()->findOrFail($id), fn (LedgerTransaction $model) => $model->restore()),
            'asset_valuation' => tap(AssetValuation::withTrashed()->findOrFail($id), fn (AssetValuation $model) => $model->restore()),
            'fx_rate' => tap(FxRate::withTrashed()->findOrFail($id), fn (FxRate $model) => $model->restore()),
            'import_batch' => tap(ImportBatch::withTrashed()->findOrFail($id), fn (ImportBatch $model) => $model->restore()),
            'import_row' => tap(ImportRow::withTrashed()->findOrFail($id), fn (ImportRow $model) => $model->restore()),
            'liability_balance_history' => tap(LiabilityBalanceHistory::withTrashed()->findOrFail($id), fn (LiabilityBalanceHistory $model) => $model->restore()),
            'liability_payment_record' => tap(LiabilityPaymentRecord::withTrashed()->findOrFail($id), fn (LiabilityPaymentRecord $model) => $model->restore()),
            default => throw new \InvalidArgumentException("Resource '{$resource}' is not supported."),
        };
    }

    /** @return array<string, mixed> */
    private function payload(Model $model): array
    {
        if ($model instanceof Asset) {
            return $this->finance->assetPayload($model->loadMissing('buckets'));
        }
        if ($model instanceof Bucket) {
            return $model->loadMissing(['goal', 'assets'])->toArray() + [
                'current_amount_egp' => $this->finance->bucketValue($model),
                'archived' => $model->trashed(),
            ];
        }
        if ($model instanceof Account) {
            return $model->toArray() + ['ledger_balance_egp' => $model->ledgerBalance(), 'archived' => $model->trashed()];
        }
        if ($model instanceof LedgerTransaction) {
            return $model->loadMissing(['account', 'counterAccount', 'category', 'splits.category'])->toArray() + ['archived' => $model->trashed()];
        }
        if ($model instanceof ImportBatch) {
            return $model->loadMissing('rows')->toArray() + ['archived' => $model->trashed()];
        }
        if ($model instanceof AllocationPlan) {
            return $model->loadMissing(['incomeItems.budgetRule', 'items.bucket.goal', 'expenseItems.category', 'sourceReview', 'template'])->toArray() + ['archived' => $model->trashed()];
        }
        if ($model instanceof PlanTemplate) {
            return $model->loadMissing(['budgetRules.category', 'allocationRules.bucket.goal', 'allocationPlans'])->toArray() + ['archived' => $model->trashed()];
        }
        if ($model instanceof RecurringCommitment) {
            return $this->finance->commitmentPayloadForAgent($model);
        }
        if ($model instanceof Liability) {
            return $this->finance->liabilityPayloadForAgent($model);
        }
        if ($model instanceof LiabilityBalanceHistory) {
            return $model->loadMissing('liability')->toArray() + ['archived' => $model->trashed()];
        }
        if ($model instanceof LiabilityPaymentRecord) {
            return $model->loadMissing('liability')->toArray() + ['archived' => $model->trashed()];
        }
        if ($model instanceof DecisionJournalEntry) {
            return [
                'id' => $model->id,
                'decision' => $model->decision,
                'assumptions' => $model->assumptions ?? [],
                'alternatives' => $model->alternatives ?? [],
                'ruleResult' => $model->rule_result ?? [],
                'chosenAction' => $model->chosen_action,
                'reviewDate' => $model->review_date ? Carbon::parse($model->review_date)->toDateString() : null,
                'outcome' => $model->outcome,
                'status' => $model->status,
                'archived' => $model->trashed(),
            ];
        }

        return $model->toArray();
    }

    /** @return array<string, mixed> */
    private function redactedContext(): array
    {
        $full = $this->finance->exportContext();

        return ['schema_version' => $full['schema_version'], 'generated_at' => $full['generated_at'], 'base_currency' => $full['base_currency'], 'data_freshness' => $full['data_freshness'], 'limitations' => $full['limitations'], 'summary' => ['record_counts' => ['assets' => count($full['assets']), 'goals' => count($full['goals']), 'buckets' => count($full['buckets'])]], 'asset_allocation' => $full['asset_allocation'], 'currency_exposure' => $full['currency_exposure'], 'liquidity' => array_map(fn (array $row): array => ['label' => $row['label']], $full['liquidity']), 'insights' => []];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reconciliationTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['month']);
        $month = $this->month($arguments['month'] ?? null);
        $data = $this->ledger->reconciliation($month);
        $data['data_freshness'] = $this->finance->dashboard()['dataFreshness'];
        $data['limitations'] = ['Expected income is not configured as a recurring schedule yet.', ...$data['data_freshness']['limitations']];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function deriveReviewTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['month']);
        if (($arguments['closed'] ?? false) === true) {
            throw new \InvalidArgumentException('Deriving actuals leaves the review open. Use close_month after review.');
        }
        $data = Validator::make($arguments, ['month' => 'required|date_format:Y-m'])->validate();
        $month = $this->month($data['month']);
        $before = MonthlyFinancialReview::whereDate('month', $month->toDateString())->first();

        return $this->mutate('derive_monthly_review', 'update', $before, function () use ($month): Model {
            return $this->ledger->deriveMonthlyReview($month, false)->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function closeMonthTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $review = MonthlyFinancialReview::findOrFail((int) $arguments['id']);
        if ($review->status === 'closed') {
            throw new \InvalidArgumentException('This monthly review is already closed. Reopen it before making a revision.');
        }
        $nextMonthPlanId = null;
        $summary = $this->ledger->summarizeMonth(Carbon::parse($review->month), true);
        $hasConfirmedLedger = $summary['source'] === 'confirmed_ledger';

        $envelope = $this->mutate('close_month', 'update', $review, function () use ($review, &$nextMonthPlanId, $summary, $hasConfirmedLedger): Model {
            $review->update([
                'status' => 'closed',
                'closed_at' => now(),
                'obligation_snapshot' => $this->obligationSnapshot(),
                'reconciliation_status' => $hasConfirmedLedger ? 'matched' : 'reviewed',
                'reconciled_at' => $hasConfirmedLedger ? now() : null,
                'source_transaction_count' => (int) ($summary['sourceTransactionCount'] ?? 0),
            ]);
            if ((bool) data_get(FinancialSetting::active()->policy, 'auto_prepare_next_month', false)) {
                $proposal = $this->nextMonthProposal($review);
                $nextMonth = Carbon::parse($proposal['nextMonth'])->startOfMonth();
                if (! AllocationPlan::whereDate('month', $nextMonth)->exists()) {
                    $plan = AllocationPlan::create([
                        'month' => $proposal['nextMonth'],
                        'planned_income_egp' => $proposal['plannedIncome'],
                        'planned_expenses_egp' => $proposal['plannedExpenses'],
                        'source_review_id' => $review->id,
                        'generation_method' => 'prepared_from_review',
                        'generated_at' => now(),
                        'notes' => 'Prepared from the closed review and current obligations.',
                    ]);
                    foreach ($proposal['allocations'] as $item) {
                        if ($item['amount'] > 0) {
                            $plan->items()->create(['bucket_id' => $item['bucketId'], 'planned_amount_egp' => $item['amount'], 'actual_amount_egp' => 0]);
                        }
                    }
                    $nextMonthPlanId = $plan->id;
                }
            }

            return $review->refresh();
        });

        if ($nextMonthPlanId !== null) {
            $envelope['next_month_plan_id'] = $nextMonthPlanId;
        }

        return $envelope;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reopenMonthTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $review = MonthlyFinancialReview::findOrFail((int) $arguments['id']);

        return $this->mutate('reopen_month', 'update', $review, function () use ($review): Model {
            $review->update(['status' => 'open', 'reopened_at' => now(), 'reconciliation_status' => 'pending', 'reconciled_at' => null]);

            return $review->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function prepareNextMonthTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['review_id', 'redirect_emergency_to', 'lesson']);
        $data = Validator::make($arguments, [
            'review_id' => 'required|integer|min:1',
            'redirect_emergency_to' => 'sometimes|in:goals,investments',
            'lesson' => 'nullable|string|max:2000',
        ])->validate();
        $review = MonthlyFinancialReview::findOrFail((int) $data['review_id']);
        if ($review->status !== 'closed') {
            throw new \InvalidArgumentException('Close this month before preparing the next month.');
        }
        $nextMonth = Carbon::parse($review->month)->startOfMonth()->addMonth();
        if (AllocationPlan::whereDate('month', $nextMonth)->exists()) {
            return ['already_exists' => true, 'next_month' => $nextMonth->format('Y-m'), 'plan' => AllocationPlan::whereDate('month', $nextMonth)->first()?->toArray()];
        }

        $proposal = $this->nextMonthProposal($review, (string) ($data['redirect_emergency_to'] ?? 'investments'));

        return $this->mutate('prepare_next_month', 'create', null, function () use ($review, $proposal, $data): Model {
            $plan = AllocationPlan::create([
                'month' => $proposal['nextMonth'],
                'planned_income_egp' => $proposal['plannedIncome'],
                'planned_expenses_egp' => $proposal['plannedExpenses'],
                'source_review_id' => $review->id,
                'generation_method' => 'prepared_from_review',
                'generated_at' => now(),
                'notes' => ! empty($data['lesson']) ? 'Lesson carried forward: '.$data['lesson'] : 'Prepared from the closed review and current obligations.',
            ]);
            foreach ($proposal['allocations'] as $item) {
                if ($item['amount'] > 0) {
                    $plan->items()->create(['bucket_id' => $item['bucketId'], 'planned_amount_egp' => $item['amount'], 'actual_amount_egp' => 0]);
                }
            }

            return $plan->refresh();
        });
    }

    /** @return array<string, mixed> */
    private function nextMonthProposal(MonthlyFinancialReview $review, string $redirectTarget = 'investments'): array
    {
        $nextMonth = Carbon::parse($review->month)->startOfMonth()->addMonth();
        $commitments = RecurringCommitment::where('is_active', true)->get();
        $liabilities = Liability::where('is_active', true)->get();
        $monthlyCommitments = round((float) $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2);
        $monthlyDebtPayments = round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp), 2);
        $plannedIncome = (float) $review->income_egp;
        $plannedExpenses = round((float) $review->essential_expenses_egp + (float) $review->lifestyle_expenses_egp + $monthlyCommitments + $monthlyDebtPayments, 2);
        $available = max(0, $plannedIncome - $plannedExpenses);
        $settings = FinancialSetting::active();
        $emergencyBucket = Bucket::where('purpose_type', 'emergency')->with('assets')->first();
        $currentEmergency = $emergencyBucket ? $this->finance->bucketValue($emergencyBucket) : 0;
        $monthlyBase = (float) $review->essential_expenses_egp + $monthlyCommitments + $monthlyDebtPayments;
        $emergencyTarget = round($monthlyBase * (int) ($settings->emergency_reserve_months ?: 6), 2);
        $emergencyGap = max(0, round($emergencyTarget - $currentEmergency, 2));
        $emergencyContribution = min($emergencyGap, round($available * 0.2, 2));
        $reserveComplete = $emergencyGap <= 0.01;
        $redirectAmount = $reserveComplete ? round(min($available, $available * 0.2), 2) : 0;
        $remaining = max(0, $available - $emergencyContribution);
        $goalAllocations = [];
        foreach (Goal::with('buckets')->where('status', 'active')->orderBy('priority')->get() as $goal) {
            $amount = min($remaining, max(0, (float) $goal->monthly_contribution_egp));
            $bucket = $goal->buckets->first();
            if ($bucket !== null && $amount > 0) {
                $goalAllocations[] = ['bucketId' => $bucket->id, 'label' => $bucket->name, 'kind' => 'goal', 'amount' => round($amount, 2)];
                $remaining = max(0, $remaining - $amount);
            }
        }
        $investmentContribution = $remaining;
        if ($reserveComplete && $redirectTarget === 'goals' && $redirectAmount > 0 && count($goalAllocations) > 0) {
            $goalAllocations[0]['amount'] = round($goalAllocations[0]['amount'] + min($redirectAmount, $investmentContribution), 2);
            $investmentContribution = max(0, $investmentContribution - $redirectAmount);
        }
        $investmentBucket = Bucket::where('purpose_type', 'investment')->orderBy('name')->first();
        $allocations = collect();
        if ($emergencyBucket !== null && $emergencyContribution > 0) {
            $allocations->push(['bucketId' => $emergencyBucket->id, 'label' => $emergencyBucket->name, 'kind' => 'emergency', 'amount' => round($emergencyContribution, 2)]);
        }
        foreach ($goalAllocations as $item) {
            $allocations->push($item);
        }
        if ($investmentBucket !== null && $investmentContribution > 0) {
            $allocations->push(['bucketId' => $investmentBucket->id, 'label' => $investmentBucket->name, 'kind' => 'investment', 'amount' => round($investmentContribution, 2)]);
        }

        return [
            'nextMonth' => $nextMonth->toDateString(),
            'plannedIncome' => round($plannedIncome, 2),
            'plannedExpenses' => $plannedExpenses,
            'available' => round($available, 2),
            'currentEmergency' => round($currentEmergency, 2),
            'emergencyTarget' => $emergencyTarget,
            'emergencyGap' => round($emergencyGap, 2),
            'emergencyContribution' => round($emergencyContribution, 2),
            'reserveComplete' => $reserveComplete,
            'redirectAmount' => $redirectAmount,
            'redirectTarget' => $reserveComplete ? $redirectTarget : null,
            'allocations' => $allocations->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function syncAllocationPlanActualsTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['allocation_plan_id']);
        $plan = AllocationPlan::findOrFail((int) $arguments['allocation_plan_id']);
        $before = $plan->load('items');
        $result = [];

        $envelope = $this->mutate('sync_allocation_plan_actuals', 'update', $before, function () use ($plan, &$result): Model {
            $result = $this->allocationActuals->sync($plan);

            return $plan->fresh(['items']) ?? $plan;
        });

        return $envelope + ['sync' => $result];
    }

    /** @param array<string, mixed> $arguments */
    private function closeAllocationPlanTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['allocation_plan_id']);
        $plan = AllocationPlan::findOrFail((int) $arguments['allocation_plan_id']);
        if ($plan->status === 'closed') {
            throw new \InvalidArgumentException('This monthly plan is already closed.');
        }

        return $this->mutate('close_allocation_plan', 'update', $plan, function () use ($plan): Model {
            $plan->update(['status' => 'closed', 'closed_at' => now()]);

            return $plan;
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function importCsvTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['file_name', 'source', 'csv', 'rows']);
        $data = Validator::make($arguments, [
            'file_name' => 'nullable|string|max:240',
            'source' => 'nullable|string|max:80',
            'csv' => 'nullable|string',
            'rows' => 'nullable|array',
        ])->validate();
        $rows = $data['rows'] ?? $this->parseCsv((string) ($data['csv'] ?? ''));
        if ($rows === []) {
            throw new \InvalidArgumentException('Provide CSV data or at least one parsed row.');
        }

        return $this->mutate('import_csv', 'create', null, function () use ($data, $rows): Model {
            $batch = ImportBatch::create(['file_name' => $data['file_name'] ?? null, 'source' => $data['source'] ?? 'csv', 'status' => 'review', 'metadata' => ['no_silent_posting' => true]]);
            foreach ($rows as $index => $raw) {
                if (! is_array($raw)) {
                    throw new \InvalidArgumentException('Every imported row must be an object.');
                }
                $normalized = $this->normalizeImportRow($raw);
                if ($normalized['category_id'] === null && isset($raw['category']) && trim((string) $raw['category']) !== '') {
                    $normalized['category_id'] = TransactionCategory::firstOrCreate([
                        'name' => trim((string) $raw['category']),
                        'kind' => in_array($normalized['transaction_type'], ['income', 'expense'], true) ? $normalized['transaction_type'] : 'adjustment',
                    ])->id;
                }
                $fingerprint = LedgerTransaction::fingerprintFor($normalized);
                $duplicate = ImportRow::query()->where('fingerprint', $fingerprint)->first();
                ImportRow::create($normalized + ['import_batch_id' => $batch->id, 'row_number' => $index + 1, 'raw_data' => $raw, 'fingerprint' => $fingerprint, 'duplicate_of_id' => $duplicate?->id, 'review_state' => $duplicate ? 'duplicate' : 'pending']);
            }
            $batch->refreshCounts();

            return $batch->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function acceptImportRowTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['id', 'account_id', 'category_id', 'transaction_type', 'occurred_on', 'description', 'amount', 'currency']);
        $data = Validator::make($arguments, [
            'id' => 'required|integer|min:1',
            'account_id' => 'nullable|exists:accounts,id',
            'category_id' => 'nullable|exists:transaction_categories,id',
            'transaction_type' => 'nullable|in:income,expense,transfer,contribution,withdrawal,dividend,interest,fee,tax,debt_payment,obligation,correction',
            'occurred_on' => 'nullable|date',
            'description' => 'nullable|string|max:240',
            'amount' => 'nullable|numeric|gt:0',
            'currency' => 'nullable|string|size:3',
        ])->validate();
        $row = ImportRow::findOrFail((int) $data['id']);
        $overrides = array_filter(Arr::only($data, ['account_id', 'category_id', 'transaction_type', 'occurred_on', 'description', 'amount', 'currency']), fn ($value): bool => $value !== null);

        return $this->mutate('accept_import_row', 'update', $row, function () use ($row, $overrides): Model {
            $transaction = $this->ledger->acceptImportRow($row, $overrides);
            $this->allocationActuals->syncMonth(Carbon::parse($transaction->occurred_on));

            return $row->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function rejectImportRowTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['id']);
        $row = ImportRow::findOrFail((int) ($arguments['id'] ?? 0));

        return $this->mutate('reject_import_row', 'update', $row, function () use ($row): Model {
            $row->update(['review_state' => $row->duplicate_of_id ? 'rejected_duplicate' : 'rejected', 'reviewed_at' => now()]);
            $row->batch?->refreshCounts();

            return $row->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createHistoricalSnapshotTool(array $arguments): array
    {
        $this->assertArguments($arguments, ['as_of', 'notes']);
        $data = Validator::make($arguments, ['as_of' => 'required|date', 'notes' => 'nullable|string|max:2000'])->validate();
        $asOf = Carbon::parse($data['as_of'])->startOfDay();
        $projection = $this->ledger->snapshotAt($asOf);
        if ($projection['status'] !== 'confirmed') {
            throw new \InvalidArgumentException('Historical snapshot is incomplete because required historical sources are missing. '.implode(' ', $projection['limitations'] ?? []));
        }

        return $this->mutate('create_historical_snapshot', 'create', null, function () use ($asOf, $data, $projection): Model {
            return Snapshot::create([
                'as_of' => $asOf->toDateString(), 'net_worth_egp' => $projection['netWorth'], 'liquid_assets_egp' => 0,
                'investable_net_worth_egp' => $projection['netWorth'], 'income_egp' => 0, 'expenses_egp' => 0, 'free_cash_flow_egp' => 0,
                'emergency_coverage_months' => 0, 'asset_breakdown' => $projection['assetBreakdown'], 'change_attribution' => $projection['attribution'],
                'notes' => $data['notes'] ?? null, 'capture_basis' => 'dated_ledger', 'historical_source' => 'dated_ledger', 'captured_at' => now(),
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function obligationSnapshot(): array
    {
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $liabilities = Liability::where('is_active', true)->orderBy('name')->get();

        return [
            'capturedAt' => now()->toIso8601String(),
            'commitments' => [
                'configuredMonthly' => round((float) $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2),
                'items' => $commitments->map(fn (RecurringCommitment $commitment): array => ['id' => $commitment->id, 'name' => $commitment->name, 'monthlyAmount' => $commitment->monthlyAmount()])->values()->all(),
            ],
            'liabilities' => [
                'configuredMonthlyPayments' => round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp), 2),
                'items' => $liabilities->map(fn (Liability $liability): array => ['id' => $liability->id, 'name' => $liability->name, 'balance' => (float) $liability->balance_egp, 'monthlyPayment' => (float) $liability->monthly_payment_egp])->values()->all(),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function parseCsv(string $csv): array
    {
        if (trim($csv) === '') {
            return [];
        }
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $csv);
        rewind($handle);
        $headers = fgetcsv($handle) ?: [];
        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($headers, array_pad($values, count($headers), null)) ?: [];
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeImportRow(array $row): array
    {
        return [
            'account_id' => isset($row['account_id']) && $row['account_id'] !== '' ? (int) $row['account_id'] : null,
            'category_id' => isset($row['category_id']) && $row['category_id'] !== '' ? (int) $row['category_id'] : null,
            'occurred_on' => $row['occurred_on'] ?? $row['date'] ?? null,
            'description' => $row['description'] ?? $row['memo'] ?? $row['name'] ?? null,
            'amount' => isset($row['amount']) ? abs((float) $row['amount']) : null,
            'exchange_rate' => isset($row['exchange_rate']) && $row['exchange_rate'] !== '' ? (float) $row['exchange_rate'] : null,
            'amount_egp' => isset($row['amount_egp']) && $row['amount_egp'] !== '' ? abs((float) $row['amount_egp']) : null,
            'currency' => strtoupper((string) ($row['currency'] ?? 'EGP')),
            'transaction_type' => $row['transaction_type'] ?? $row['type'] ?? ((isset($row['amount']) && (float) $row['amount'] < 0) ? 'expense' : 'income'),
        ];
    }

    private function month(mixed $value): CarbonInterface
    {
        if ($value === null || $value === '') {
            return now()->startOfMonth();
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException('month must use YYYY-MM format.');
        }

        return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setAssetAllocations(array $arguments): array
    {
        $this->assertArguments($arguments, ['asset_id', 'allocations']);
        $assetId = (int) ($arguments['asset_id'] ?? 0);
        $allocations = $arguments['allocations'] ?? null;
        if ($assetId < 1 || ! is_array($allocations)) {
            throw new \InvalidArgumentException('asset_id and allocations are required.');
        }
        $asset = Asset::findOrFail($assetId);
        $before = $asset->load('buckets');
        $data = [];
        $total = 0.0;
        foreach ($allocations as $allocation) {
            if (! is_array($allocation) || ! isset($allocation['bucket_id'], $allocation['amount_egp'])) {
                throw new \InvalidArgumentException('Each allocation needs bucket_id and amount_egp.');
            }
            $row = Validator::make($allocation, ['bucket_id' => 'required|integer|exists:buckets,id', 'amount_egp' => 'required|numeric|min:0'])->validate();
            $bucketId = (int) $row['bucket_id'];
            if (array_key_exists($bucketId, $data)) {
                throw new \InvalidArgumentException('Bucket allocations cannot repeat a bucket.');
            }
            $amount = round((float) $row['amount_egp'], 2);
            $data[$bucketId] = ['amount_egp' => $amount];
            $total = round($total + $amount, 2);
        }
        if ($total > (float) $asset->current_value_egp) {
            throw new \InvalidArgumentException('Bucket allocations cannot exceed the asset value.');
        }

        return $this->mutate('set_asset_allocations', 'update', $before, function () use ($asset, $data): Model {
            $asset->buckets()->sync($data);

            return $asset->load('buckets');
        });
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function allocationReconciliation(array $arguments): array
    {
        $this->assertArguments($arguments, []);
        $dashboard = $this->finance->dashboard();

        return [
            'totalAssets' => $dashboard['summary']['totalAssets'],
            'fullyAssignedPurposeBalance' => $dashboard['summary']['allocatedToGoals'] + $dashboard['summary']['allocatedToNonGoals'],
            'allocatedToGoals' => $dashboard['summary']['allocatedToGoals'],
            'allocatedToNonGoals' => $dashboard['summary']['allocatedToNonGoals'],
            'unallocated' => $dashboard['summary']['unallocated'],
            'trulyUnallocated' => $dashboard['summary']['unallocated'],
            'warnings' => $dashboard['summary']['unallocated'] > 0.01 ? ['Some asset value has no purpose allocation.'] : [],
            'data_freshness' => $dashboard['dataFreshness'],
            'limitations' => $dashboard['dataFreshness']['limitations'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function reallocateAssetBalance(array $arguments): array
    {
        $this->assertArguments($arguments, ['asset_id', 'bucket_id', 'amount_egp']);
        $validated = Validator::make($arguments, ['asset_id' => 'required|integer|exists:assets,id', 'bucket_id' => 'required|integer|exists:buckets,id', 'amount_egp' => 'required|numeric|min:0'])->validate();
        $asset = Asset::query()->whereKey($validated['asset_id'])->firstOrFail();
        $asset->load('buckets');
        $allocations = $asset->buckets->mapWithKeys(fn (Bucket $bucket): array => [$bucket->id => ['bucket_id' => $bucket->id, 'amount_egp' => (float) data_get($bucket, 'pivot.amount_egp', 0)]])->values()->all();
        $found = false;
        foreach ($allocations as &$allocation) {
            if ($allocation['bucket_id'] === (int) $validated['bucket_id']) {
                $allocation['amount_egp'] = $validated['amount_egp'];
                $found = true;
            }
        }
        unset($allocation);
        if (! $found) {
            $allocations[] = ['bucket_id' => (int) $validated['bucket_id'], 'amount_egp' => $validated['amount_egp']];
        }

        return $this->setAssetAllocations(['asset_id' => $asset->id, 'allocations' => $allocations]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function getBucketAllocations(array $arguments): array
    {
        $this->assertArguments($arguments, ['bucket_id']);
        $bucket = Bucket::withTrashed()->with(['goal', 'assets'])->findOrFail((int) $arguments['bucket_id']);

        return [
            'id' => $bucket->id,
            'name' => $bucket->name,
            'purpose' => $bucket->purpose,
            'purpose_type' => $bucket->goal_id !== null ? 'goal' : ($bucket->purpose_type ?? 'other'),
            'goal_id' => $bucket->goal_id,
            'target_amount_egp' => (float) $bucket->target_amount_egp,
            'current_amount_egp' => $this->finance->bucketValue($bucket),
            'asset_allocations' => $bucket->assets->map(fn (Asset $asset): array => ['asset_id' => $asset->id, 'asset_name' => $asset->name, 'amount_egp' => (float) data_get($asset, 'pivot.amount_egp', 0)])->values()->all(),
            'archived' => $bucket->trashed(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setBucketAllocations(array $arguments): array
    {
        $this->assertArguments($arguments, ['bucket_id', 'allocations']);
        $data = Validator::make($arguments, ['bucket_id' => 'required|integer|min:1', 'allocations' => 'required|array'])->validate();
        $bucket = Bucket::query()->whereKey($data['bucket_id'])->firstOrFail();
        $before = Bucket::query()->with('assets')->whereKey($data['bucket_id'])->firstOrFail();
        $requested = [];
        foreach ($data['allocations'] as $allocation) {
            if (! is_array($allocation)) {
                throw new \InvalidArgumentException('Every allocation must be an object.');
            }
            $row = Validator::make($allocation, ['asset_id' => 'required|integer|exists:assets,id', 'amount_egp' => 'required|numeric|min:0'])->validate();
            if (array_key_exists((int) $row['asset_id'], $requested)) {
                throw new \InvalidArgumentException('Bucket allocations cannot repeat an asset.');
            }
            $requested[(int) $row['asset_id']] = round((float) $row['amount_egp'], 2);
        }
        $assets = Asset::with('buckets')->whereIn('id', array_keys($requested))->get()->keyBy('id');
        foreach ($requested as $assetId => $amount) {
            $asset = $assets->get($assetId);
            if (! $asset instanceof Asset) {
                throw new \InvalidArgumentException("Asset {$assetId} is not available to the current owner.");
            }
            $assignedElsewhere = (float) $asset->buckets->where('id', '!=', $bucket->id)->sum(fn (Bucket $item): float => (float) data_get($item, 'pivot.amount_egp', 0));
            if ($assignedElsewhere + $amount > (float) $asset->current_value_egp + 0.005) {
                throw new \InvalidArgumentException("{$asset->name} does not have enough unassigned value for this bucket.");
            }
        }
        if ($bucket->target_amount_egp !== null && array_sum($requested) > (float) $bucket->target_amount_egp + 0.005) {
            throw new \InvalidArgumentException('This bucket cannot exceed its target amount.');
        }

        return $this->mutate('set_bucket_allocations', 'update', $before, function () use ($bucket, $requested): Model {
            $bucket->assets()->sync(collect($requested)->map(fn (float $amount): array => ['amount_egp' => $amount])->all());

            return $bucket->fresh(['assets']) ?? $bucket;
        });
    }

    private function requestId(): string
    {
        return (string) (request()->header('X-Request-Id') ?: request()->attributes->get('request_id') ?: Str::uuid());
    }

    /** @return array<string, mixed> */
    private function success(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /** @return array<string, mixed> */
    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    /** @param array<string, mixed> $message */
    private function write(array $message): void
    {
        fwrite(STDOUT, $this->encode($message).PHP_EOL);
        fflush(STDOUT);
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
