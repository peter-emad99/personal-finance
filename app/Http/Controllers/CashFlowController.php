<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function index(): Response
    {
        $month = now()->startOfMonth();
        $flows = CashFlow::where('occurred_on', '>=', $month)->orderByDesc('occurred_on')->get();

        return Inertia::render('cash-flow', [
            'flows' => $flows, 'month' => $month->toDateString(),
            'summary' => ['income' => $flows->where('type', 'income')->sum('amount_egp'), 'expenses' => $flows->whereIn('type', ['expense', 'obligation'])->sum('amount_egp')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CashFlow::create($request->validate([
            'type' => ['required', 'in:income,expense,obligation'], 'category' => ['required', 'string', 'max:80'],
            'amount_egp' => ['required', 'numeric', 'min:0'], 'occurred_on' => ['required', 'date'], 'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Cash flow entry added.');
    }

    public function destroy(CashFlow $cashFlow): RedirectResponse
    {
        $cashFlow->delete();

        return back()->with('success', 'Cash flow entry removed.');
    }
}
