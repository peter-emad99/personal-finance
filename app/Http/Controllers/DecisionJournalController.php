<?php

namespace App\Http\Controllers;

use App\Models\DecisionJournalEntry;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DecisionJournalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('decision-journal', [
            'entries' => DecisionJournalEntry::query()->latest('review_date')->latest('id')->get()->map(fn (DecisionJournalEntry $entry): array => $this->payload($entry))->values(),
            'archivedEntries' => DecisionJournalEntry::onlyTrashed()->latest()->get()->map(fn (DecisionJournalEntry $entry): array => $this->payload($entry))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DecisionJournalEntry::create($this->validated($request));

        return back()->with('success', 'Decision journal entry saved.');
    }

    public function update(Request $request, DecisionJournalEntry $decisionJournalEntry): RedirectResponse
    {
        $decisionJournalEntry->update($this->validated($request));

        return back()->with('success', 'Decision journal entry updated.');
    }

    public function destroy(DecisionJournalEntry $decisionJournalEntry): RedirectResponse
    {
        $decisionJournalEntry->delete();

        return back()->with('success', 'Decision journal entry archived.');
    }

    public function restore(int $decisionJournalEntry): RedirectResponse
    {
        DecisionJournalEntry::withTrashed()->findOrFail($decisionJournalEntry)->restore();

        return back()->with('success', 'Decision journal entry restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'decision' => ['required', 'string', 'max:240'],
            'assumptions' => ['nullable', 'array'],
            'alternatives' => ['nullable', 'array'],
            'rule_result' => ['nullable', 'array'],
            'chosen_action' => ['nullable', 'string', 'max:240'],
            'review_date' => ['nullable', 'date'],
            'outcome' => ['nullable', 'string'],
            'status' => ['required', 'in:open,reviewed,closed'],
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(DecisionJournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'decision' => $entry->decision,
            'assumptions' => $entry->assumptions ?? [],
            'alternatives' => $entry->alternatives ?? [],
            'ruleResult' => $entry->rule_result ?? [],
            'chosenAction' => $entry->chosen_action,
            'reviewDate' => $entry->review_date ? Carbon::parse($entry->review_date)->toDateString() : null,
            'outcome' => $entry->outcome,
            'status' => $entry->status,
            'archived' => $entry->trashed(),
        ];
    }
}
