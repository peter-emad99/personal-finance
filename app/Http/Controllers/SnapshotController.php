<?php

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SnapshotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('snapshots', ['snapshots' => Snapshot::orderByDesc('as_of')->get()]);
    }

    public function store(Request $request, FinanceService $finance): RedirectResponse
    {
        $data = $finance->dashboard();
        $asOf = $request->date('as_of') ?: now();
        Snapshot::updateOrCreate(['as_of' => $asOf->toDateString()], [
            'net_worth_egp' => $data['summary']['netWorth'], 'liquid_assets_egp' => $data['summary']['liquidAssets'],
            'investable_net_worth_egp' => $data['summary']['investableNetWorth'], 'income_egp' => $data['summary']['income'],
            'expenses_egp' => $data['summary']['expenses'], 'free_cash_flow_egp' => $data['summary']['freeCashFlow'],
            'emergency_coverage_months' => $data['summary']['emergencyCoverageMonths'], 'asset_breakdown' => $data['assetAllocation'],
            'notes' => $request->string('notes')->toString() ?: null,
        ]);

        return back()->with('success', 'Snapshot saved.');
    }
}
