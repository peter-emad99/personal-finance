<?php

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Services\FinanceService;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        $input = $request->validate(['as_of' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $requestedDate = $input['as_of'] ?? null;
        $asOf = $requestedDate ? Carbon::parse($requestedDate) : Carbon::today();
        if (! $asOf->isToday()) {
            throw ValidationException::withMessages([
                'as_of' => 'Manual snapshots are current-date checkpoints. Historical values require dated valuation records.',
            ]);
        }
        $data = $finance->dashboard();
        Snapshot::updateOrCreate(['as_of' => $asOf->toDateString()], [
            'net_worth_egp' => $data['summary']['netWorth'], 'liquid_assets_egp' => $data['summary']['liquidAssets'],
            'investable_net_worth_egp' => $data['summary']['investableNetWorth'], 'income_egp' => $data['summary']['income'],
            'expenses_egp' => $data['summary']['expenses'], 'free_cash_flow_egp' => $data['summary']['freeCashFlow'],
            'emergency_coverage_months' => $data['summary']['emergencyCoverageMonths'], 'asset_breakdown' => [
                'byClass' => $data['assetAllocation'],
                'byType' => $data['assetTypeAllocation'],
                'assets' => $data['assets']->map(fn (array $asset): array => [
                    'assetId' => $asset['id'],
                    'type' => $asset['type'],
                    'typeKey' => $asset['assetTypeKey'] ?? null,
                    'class' => $asset['assetClass'] ?? $asset['classification'] ?? 'other',
                    'value' => $asset['currentValue'],
                ])->values()->all(),
            ],
            'notes' => $input['notes'] ?? null,
            'capture_basis' => 'manual_current_state',
            'captured_at' => now(),
        ]);

        return back()->with('success', 'Snapshot saved.');
    }

    public function historical(Request $request, LedgerService $ledger): RedirectResponse
    {
        $input = $request->validate(['as_of' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $projection = $ledger->snapshotAt(Carbon::parse($input['as_of'])->startOfDay());
        if ($projection['status'] !== 'confirmed') {
            throw ValidationException::withMessages(['as_of' => 'Historical snapshot is incomplete because required historical sources are missing. '.implode(' ', $projection['limitations'] ?? [])]);
        }
        Snapshot::create([
            'as_of' => $projection['asOf'], 'net_worth_egp' => $projection['netWorth'], 'liquid_assets_egp' => 0,
            'investable_net_worth_egp' => $projection['netWorth'], 'income_egp' => 0, 'expenses_egp' => 0, 'free_cash_flow_egp' => 0,
            'emergency_coverage_months' => 0, 'asset_breakdown' => $projection['assetBreakdown'], 'change_attribution' => $projection['attribution'],
            'notes' => $input['notes'] ?? null, 'capture_basis' => 'dated_ledger', 'historical_source' => 'dated_ledger', 'captured_at' => now(),
        ]);

        return back()->with('success', 'Historical snapshot saved from dated records.');
    }

    public function destroy(Snapshot $snapshot): RedirectResponse
    {
        $snapshot->delete();

        return back()->with('success', 'Snapshot archived.');
    }

    public function update(Request $request, Snapshot $snapshot): RedirectResponse
    {
        $snapshot->update($request->validate(['notes' => ['nullable', 'string', 'max:2000']]));

        return back()->with('success', 'Snapshot notes updated.');
    }

    public function restore(int $snapshot): RedirectResponse
    {
        Snapshot::withTrashed()->findOrFail($snapshot)->restore();

        return back()->with('success', 'Snapshot restored.');
    }
}
