<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        CashFlow::create($this->validated($request));

        return back()->with('success', 'Cash flow entry added.');
    }

    public function update(Request $request, CashFlow $cashFlow): RedirectResponse
    {
        $cashFlow->update($this->validated($request));

        return back()->with('success', 'Cash flow entry updated.');
    }

    public function restore(int $cashFlow): RedirectResponse
    {
        CashFlow::withTrashed()->findOrFail($cashFlow)->restore();

        return back()->with('success', 'Cash flow entry restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:income,expense,obligation'], 'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'in:EGP,USD'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
            'occurred_on' => ['required', 'date'], 'notes' => ['nullable', 'string'],
        ]);

        $rate = $data['currency'] === 'EGP' ? 1.0 : (float) ($data['exchange_rate'] ?? 0);
        if ($data['currency'] === 'USD' && $rate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'A USD entry needs its EGP exchange rate.',
            ]);
        }

        $data['exchange_rate'] = $rate;
        $data['amount_egp'] = round((float) $data['amount'] * $rate, 2);

        return $data;
    }

    public function destroy(CashFlow $cashFlow): RedirectResponse
    {
        $cashFlow->delete();

        return back()->with('success', 'Cash flow entry removed.');
    }
}
