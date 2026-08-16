<?php

namespace App\Http\Controllers;

use App\Models\Liability;
use App\Models\LiabilityBalanceHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiabilityBalanceHistoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('liability-history', [
            'liabilities' => Liability::query()->orderBy('name')->get(),
            'histories' => LiabilityBalanceHistory::withTrashed()->with('liability')->latest('as_of')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LiabilityBalanceHistory::create($this->rules($request));

        return back()->with('success', 'Dated liability balance saved without changing the current balance.');
    }

    public function update(Request $request, LiabilityBalanceHistory $history): RedirectResponse
    {
        $history->update($this->rules($request));

        return back()->with('success', 'Liability balance history updated.');
    }

    public function destroy(LiabilityBalanceHistory $history): RedirectResponse
    {
        $history->delete();

        return back()->with('success', 'Liability balance history archived.');
    }

    public function restore(int $history): RedirectResponse
    {
        LiabilityBalanceHistory::withTrashed()->findOrFail($history)->restore();

        return back()->with('success', 'Liability balance history restored.');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request): array
    {
        return $request->validate([
            'liability_id' => ['required', 'exists:liabilities,id'],
            'as_of' => ['required', 'date'],
            'balance_egp' => ['required', 'numeric', 'min:0'],
            'source' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
