<?php

namespace App\Http\Controllers;

use App\Models\RecurringCommitment;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommitmentController extends Controller
{
    public function index(FinanceService $finance): Response
    {
        $commitments = RecurringCommitment::orderByDesc('is_active')->orderBy('name')->get();

        return Inertia::render('commitments', [
            'commitments' => $commitments->map(fn (RecurringCommitment $commitment) => $finance->commitmentPayloadForAgent($commitment))->values(),
            'summary' => [
                'monthly' => round((float) $commitments->where('is_active', true)->sum(fn (RecurringCommitment $item) => $item->monthlyAmount()), 2),
                'annual' => round((float) $commitments->where('is_active', true)->sum(fn (RecurringCommitment $item) => $item->annualAmount()), 2),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        RecurringCommitment::create($this->validated($request));

        return back()->with('success', 'Recurring commitment added.');
    }

    public function update(Request $request, RecurringCommitment $commitment): RedirectResponse
    {
        $commitment->update($this->validated($request));

        return back()->with('success', 'Recurring commitment updated.');
    }

    public function destroy(RecurringCommitment $commitment): RedirectResponse
    {
        $commitment->delete();

        return back()->with('success', 'Recurring commitment removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'amount_egp' => ['required', 'numeric', 'min:0'],
            'frequency' => ['required', 'in:weekly,monthly,quarterly,yearly'],
            'next_due_on' => ['nullable', 'date'],
            'renewal_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
