<?php

namespace App\Http\Controllers;

use App\Models\TransactionCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('transaction-categories', ['categories' => TransactionCategory::query()->orderBy('kind')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        TransactionCategory::create($this->validated($request));

        return back()->with('success', 'Transaction category added.');
    }

    public function update(Request $request, TransactionCategory $category): RedirectResponse
    {
        $category->update($this->validated($request));

        return back()->with('success', 'Transaction category updated.');
    }

    public function destroy(TransactionCategory $category): RedirectResponse
    {
        if ($category->is_system) {
            return back()->with('error', 'System categories cannot be archived.');
        }
        $category->delete();

        return back()->with('success', 'Transaction category archived.');
    }

    public function restore(int $category): RedirectResponse
    {
        TransactionCategory::withTrashed()->findOrFail($category)->restore();

        return back()->with('success', 'Transaction category restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100'], 'kind' => ['required', 'in:income,expense,transfer,investment,adjustment'], 'parent_name' => ['nullable', 'string', 'max:100'], 'is_system' => ['sometimes', 'boolean']]);
    }
}
