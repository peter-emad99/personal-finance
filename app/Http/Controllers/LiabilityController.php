<?php

namespace App\Http\Controllers;

use App\Models\Liability;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiabilityController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        return Inertia::render('liabilities', [
            'liabilities' => Liability::with('paymentRecords')->orderByDesc('is_active')->orderByDesc('balance_egp')->get()->map(fn (Liability $liability) => $finance->liabilityPayloadForAgent($liability))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Liability::create($this->validated($request));

        return back()->with('success', 'Liability added.');
    }

    public function update(Request $request, Liability $liability): RedirectResponse
    {
        $liability->update($this->validated($request));

        return back()->with('success', 'Liability updated.');
    }

    public function destroy(Liability $liability): RedirectResponse
    {
        $liability->delete();

        return back()->with('success', 'Liability removed.');
    }

    public function restore(int $liability): RedirectResponse
    {
        Liability::withTrashed()->findOrFail($liability)->restore();

        return back()->with('success', 'Liability restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:80'],
            'balance_egp' => ['required', 'numeric', 'min:0'],
            'original_balance_egp' => ['nullable', 'numeric', 'min:0'],
            'interest_rate_percent' => ['nullable', 'numeric', 'min:0'],
            'monthly_payment_egp' => ['required', 'numeric', 'min:0'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'payoff_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
