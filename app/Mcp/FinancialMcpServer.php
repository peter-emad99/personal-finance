<?php

namespace App\Mcp;

use App\Models\Asset;
use App\Models\Goal;
use App\Models\Liability;
use App\Models\RecurringCommitment;
use App\Services\FinanceService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

class FinancialMcpServer
{
    public function __construct(private readonly FinanceService $finance) {}

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
                $this->write([
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => ['code' => -32700, 'message' => 'Invalid JSON-RPC message.'],
                ]);
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
            'initialize' => $this->success($id, [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'personal-finance-os', 'version' => '0.1.0'],
                'instructions' => 'Read-only financial context for planning conversations. Never treat outputs as regulated financial advice.',
            ]),
            'ping' => $this->success($id, new \stdClass),
            'tools/list' => $this->success($id, ['tools' => $this->tools()]),
            'tools/call' => $this->callTool($id, data_get($request, 'params.name'), data_get($request, 'params.arguments', [])),
            default => $this->error($id, -32601, "Method '{$method}' not found."),
        };
    }

    /** @return list<array<string, mixed>> */
    private function tools(): array
    {
        return [
            $this->tool('get_financial_overview', 'Get the current financial position, wealth metrics, goals, liquidity, commitments, liabilities, and rules-based signals.', []),
            $this->tool('get_monthly_review', 'Get an editable monthly financial review by month. Use YYYY-MM.', ['month' => ['type' => 'string', 'description' => 'Month in YYYY-MM format. Defaults to the current month.']]),
            $this->tool('list_assets', 'List assets with current values, liquidity, currency, and purpose allocations.', []),
            $this->tool('list_goals', 'List active goals with funding progress, deadlines, and required monthly contribution.', []),
            $this->tool('list_recurring_commitments', 'List active recurring commitments with monthly and annual equivalents.', []),
            $this->tool('list_liabilities', 'List active liabilities with balances, rates, and monthly payments.', []),
            $this->tool('evaluate_purchase', 'Evaluate a planned purchase against liquidity, emergency coverage, and free cash flow. This is planning math, not financial advice.', [
                'price' => ['type' => 'number', 'minimum' => 0, 'description' => 'Purchase price in EGP.'],
                'mode' => ['type' => 'string', 'enum' => ['cash', 'finance'], 'description' => 'Cash purchase or financed purchase.'],
                'down_payment' => ['type' => 'number', 'minimum' => 0],
                'interest_rate' => ['type' => 'number', 'minimum' => 0, 'description' => 'Annual interest rate percentage.'],
                'tenure' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Financing tenure in months.'],
            ], ['price', 'mode']),
            $this->tool('get_decision_context', 'Get a complete exportable context pack for discussing a financial decision.', []),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, array $properties, array $required = []): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'inputSchema' => ['type' => 'object', 'properties' => $properties ?: new \stdClass, 'required' => $required, 'additionalProperties' => false],
            'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function callTool(mixed $id, mixed $name, mixed $arguments): array
    {
        $arguments = is_array($arguments) ? $arguments : [];
        $name = is_string($name) ? $name : '';

        try {
            $data = match ($name) {
                'get_financial_overview' => $this->finance->dashboard($this->asOf($arguments['as_of'] ?? null)),
                'get_monthly_review' => $this->finance->monthlyReview($this->month($arguments['month'] ?? null)),
                'list_assets' => Asset::with('buckets')->get()->map(fn (Asset $asset) => $this->finance->assetPayload($asset))->values()->all(),
                'list_goals' => Goal::with('buckets')->where('status', 'active')->get()->map(fn (Goal $goal) => $this->finance->dashboard()['goals']->firstWhere('id', $goal->id))->values()->all(),
                'list_recurring_commitments' => RecurringCommitment::where('is_active', true)->orderBy('name')->get()->map(fn (RecurringCommitment $commitment) => $this->finance->commitmentPayloadForAgent($commitment))->values()->all(),
                'list_liabilities' => Liability::where('is_active', true)->orderByDesc('balance_egp')->get()->map(fn (Liability $liability) => $this->finance->liabilityPayloadForAgent($liability))->values()->all(),
                'evaluate_purchase' => $this->finance->purchaseAnalysis($arguments, $this->asOf($arguments['as_of'] ?? null)),
                'get_decision_context' => $this->finance->exportContext($this->asOf($arguments['as_of'] ?? null)),
                default => throw new \InvalidArgumentException("Tool '{$name}' not found."),
            };

            return $this->success($id, ['content' => [['type' => 'text', 'text' => $this->encode($data)]], 'structuredContent' => ['data' => $data]]);
        } catch (Throwable $exception) {
            return $this->success($id, ['isError' => true, 'content' => [['type' => 'text', 'text' => $exception->getMessage()]]]);
        }
    }

    private function asOf(mixed $value): ?CarbonInterface
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    private function month(mixed $value): CarbonInterface
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}$/', $value)
            ? Carbon::createFromFormat('Y-m', $value)->startOfMonth()
            : now()->startOfMonth();
    }

    /**
     * @return array<string, mixed>
     */
    private function success(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
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
