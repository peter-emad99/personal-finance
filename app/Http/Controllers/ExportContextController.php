<?php

namespace App\Http\Controllers;

use App\Services\FinanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ExportContextController extends Controller
{
    public function __invoke(Request $request, FinanceService $finance): SymfonyResponse
    {
        $context = $finance->exportContext();
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
