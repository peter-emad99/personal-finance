<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('budget-categories', [
            'categories' => BudgetCategory::withTrashed()->orderBy('sort_order')->orderBy('name')->get()->map(fn (BudgetCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'isDefault' => $category->is_default,
                'isActive' => $category->is_active && ! $category->trashed(),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        BudgetCategory::create($this->validated($request));

        return back()->with('success', 'Expense category created.');
    }

    public function update(Request $request, BudgetCategory $budgetCategory): RedirectResponse
    {
        $budgetCategory->update($this->validated($request));

        return back()->with('success', 'Expense category updated.');
    }

    public function destroy(BudgetCategory $budgetCategory): RedirectResponse
    {
        $budgetCategory->delete();

        return back()->with('success', 'Expense category archived. Existing history was preserved.');
    }

    public function restore(int $budgetCategory): RedirectResponse
    {
        BudgetCategory::withTrashed()->findOrFail($budgetCategory)->restore();

        return back()->with('success', 'Expense category restored.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
