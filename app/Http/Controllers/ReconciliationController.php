<?php

namespace App\Http\Controllers;

use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __invoke(Request $request, LedgerService $ledger): Response
    {
        $month = $request->string('month')->toString();
        $date = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? Carbon::createFromFormat('Y-m', $month)->startOfMonth() : now()->startOfMonth();

        return Inertia::render('reconciliation', ['reconciliation' => $ledger->reconciliation($date)]);
    }
}
