<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\MarketDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MarketRateController extends Controller
{
    public function index(MarketDataService $marketData): JsonResponse
    {
        return response()->json(['data' => $marketData->dashboardPayload()]);
    }

    public function sync(MarketDataService $marketData): RedirectResponse
    {
        $result = $marketData->syncForOwner();
        $updatedMarkets = [];
        if (($result['fxUpdated'] ?? false) === true) {
            $updatedMarkets[] = 'USD/EGP';
        }
        if (($result['goldUpdated'] ?? false) === true) {
            $updatedMarkets[] = 'gold';
        }
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        AuditLogger::recordEvent(
            'market_sync',
            'market_rates',
            null,
            null,
            [
                'fxUpdated' => (bool) ($result['fxUpdated'] ?? false),
                'goldUpdated' => (bool) ($result['goldUpdated'] ?? false),
                'assetsUpdated' => (int) ($result['assetsUpdated'] ?? 0),
                'errors' => $errors,
            ],
            'dashboard_refresh',
            'web',
        );

        if ($errors !== []) {
            $updated = $updatedMarkets !== []
                ? implode(' and ', $updatedMarkets).' updated. '
                : 'No market rates were refreshed. ';
            $details = [];
            foreach ($errors as $market => $message) {
                $details[] = strtoupper((string) $market).': '.(string) $message;
            }

            return back()->with('error', $updated.'Existing successful values and asset/bucket totals were kept. '.implode(' ', $details));
        }

        $markets = $updatedMarkets !== [] ? implode(' and ', $updatedMarkets) : 'no market rates';

        return back()->with(
            'success',
            "Market rates refreshed: {$markets}. {$result['assetsUpdated']} asset value(s) and their bucket totals were revalued.",
        );
    }
}
