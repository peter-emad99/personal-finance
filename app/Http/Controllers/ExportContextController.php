<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExportContextController extends Controller
{
    public function __invoke(Request $request, FinanceService $finance): SymfonyResponse
    {
        $scope = $request->string('scope')->toString() ?: 'full_financial_context';
        abort_unless(in_array($scope, ['dashboard_summary', 'full_financial_context', 'decision_context', 'redacted_context'], true), 422, 'Unsupported context scope.');
        $context = $finance->exportContext(null, $scope);
        if ($scope === 'dashboard_summary') {
            $context = collect($context)->only(['schema_version', 'generated_at', 'base_currency', 'dashboard_version', 'data_freshness', 'limitations', 'policy', 'summary', 'source_status', 'attention_queue', 'allocation_policy', 'debt_summary'])->all();
        } elseif ($scope === 'decision_context') {
            $context = collect($context)->only(['schema_version', 'generated_at', 'base_currency', 'dashboard_version', 'data_freshness', 'limitations', 'policy', 'summary', 'goals', 'monthly_review', 'allocation_plans', 'snapshots', 'decision_journal', 'attention_queue', 'audit_references'])->all();
        } elseif ($scope === 'redacted_context') {
            $context = [
                'schema_version' => $context['schema_version'],
                'generated_at' => $context['generated_at'],
                'base_currency' => $context['base_currency'],
                'dashboard_version' => $context['dashboard_version'],
                'data_freshness' => $context['data_freshness'],
                'limitations' => $context['limitations'],
                'summary' => ['record_counts' => ['assets' => count($context['assets']), 'goals' => count($context['goals']), 'buckets' => count($context['buckets'])]],
                'asset_allocation' => $context['asset_allocation'],
                'currency_exposure' => $context['currency_exposure'],
                'liquidity' => array_map(fn (array $row): array => ['label' => $row['label']], $context['liquidity']),
            ];
        }
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'export',
            'entity_type' => 'financial_context',
            'tool_name' => 'web_export_context',
            'agent_id' => 'web-session',
            'request_id' => $request->attributes->get('request_id'),
            'after_state' => ['scope' => $scope, 'format' => $request->string('format')->toString() ?: 'json'],
            'dashboard_version' => $context['dashboard_version'] ?? null,
        ]);
        if ($request->string('format')->toString() === 'markdown') {
            return response($this->markdown($context), 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="decision-context.md"']);
        }

        return response()->json($context, 200, ['Content-Disposition' => 'attachment; filename="financial-context.json"']);
    }

    /** @param array<string, mixed> $context */
    private function markdown(array $context): string
    {
        $summary = $context['summary'];
        $lines = [
            '# Personal Financial Snapshot', '', 'Date: '.$context['as_of'], '',
            '## Summary', '', '- Net worth: '.number_format($summary['netWorth']).' EGP', '- Liquid assets: '.number_format($summary['liquidAssets']).' EGP', '- Investable net worth: '.number_format($summary['investableNetWorth']).' EGP', '- Income: '.number_format($summary['income']).' EGP/month', '- Expenses: '.number_format($summary['expenses']).' EGP/month', '- Free cash flow: '.number_format($summary['freeCashFlow']).' EGP/month', '- Emergency coverage: '.$summary['emergencyCoverageMonths'].' months', '',
            '## Assets', '',
        ];
        foreach ($context['assets'] as $asset) {
            $lines[] = '- '.$asset['name'].' ('.$asset['type'].'): '.number_format($asset['currentValue']).' EGP';
        }
        $lines[] = '';
        $lines[] = '## Goals';
        $lines[] = '';
        foreach ($context['goals'] as $goal) {
            $lines[] = '- '.$goal['name'].': '.number_format($goal['allocatedAmount']).' / '.number_format($goal['targetAmount']).' EGP; remaining '.number_format($goal['remainingAmount']).' EGP; deadline '.($goal['deadline'] ?: 'not set');
        }
        $lines[] = '';
        $lines[] = '## Insights';
        $lines[] = '';
        foreach ($context['insights'] as $insight) {
            $lines[] = '- '.$insight;
        }

        return implode("\n", $lines)."\n";
    }
}
