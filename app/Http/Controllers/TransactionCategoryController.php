<?php

namespace App\Http\Controllers;

use App\Models\BudgetCategory;
use App\Models\TransactionCategory;
use App\Services\TransactionCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCategoryController extends Controller
{
    public function index(TransactionCategoryService $categoryDefaults): Response
    {
        $categoryDefaults->ensureDefaults();

        return Inertia::render('transaction-categories', [
            'categories' => TransactionCategory::query()->with('budgetCategory')->orderBy('kind')->orderBy('name')->get()->map(fn (TransactionCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'kind' => $category->kind,
                'budgetCategoryId' => $category->budget_category_id,
                'budgetCategoryName' => $category->budgetCategory?->name,
                'isSystem' => (bool) $category->is_system,
            ])->values(),
            'budgetCategories' => BudgetCategory::query()->where('kind', 'expense')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]);
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'in:income,expense,transfer,investment,adjustment'],
            'budget_category_id' => ['nullable', 'exists:budget_categories,id'],
            'is_system' => ['sometimes', 'boolean'],
        ]);
        $budgetCategory = isset($data['budget_category_id']) ? BudgetCategory::findOrFail($data['budget_category_id']) : null;
        if ($data['kind'] === 'expense' && $budgetCategory === null) {
            abort(422, 'An expense transaction category must belong to an expense category.');
        }
        if ($budgetCategory !== null && $data['kind'] !== 'expense') {
            abort(422, 'Only expense transaction categories can belong to an expense category.');
        }

        return $data + ['parent_name' => $budgetCategory?->name];
    }
}
