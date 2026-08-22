<?php

namespace App\Http\Controllers;

use App\Services\MarketDataService;
use Illuminate\Http\JsonResponse;

class MarketRateController extends Controller
{
    public function index(MarketDataService $marketData): JsonResponse
    {
        return response()->json(['data' => $marketData->dashboardPayload()]);
    }
}
