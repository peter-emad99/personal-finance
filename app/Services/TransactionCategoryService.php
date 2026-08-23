<?php

namespace App\Services;

use App\Models\BudgetCategory;
use App\Models\TransactionCategory;

class TransactionCategoryService
{
    /** Create the starter detail categories for the current owner. */
    public function ensureDefaults(): void
    {
        $parents = app(BudgetRuleService::class)
            ->ensureDefaultCategories()
            ->keyBy(fn (BudgetCategory $category): string => strtolower($category->name));

        $defaults = [
            'expense' => [
                'Essentials' => ['Housing', 'Electricity', 'Water', 'Gas', 'Internet', 'Mobile phone / airtime', 'Groceries', 'Healthcare', 'Transport', 'Fuel'],
                'Lifestyle' => ['Dining out', 'Coffee', 'Entertainment', 'Shopping', 'Personal care', 'Hobbies', 'Travel'],
                'Commitments' => ['Rent', 'Insurance', 'Subscriptions', 'Family support', 'Debt payment'],
                'flexible / irregular' => ['Gifts', 'Repairs', 'Fees', 'Taxes', 'Other expense'],
            ],
            'income' => [
                null => ['Salary', 'Freelance', 'Business income', 'Bonus', 'Interest', 'Dividend', 'Other income'],
            ],
        ];

        foreach ($defaults as $kind => $groups) {
            foreach ($groups as $parentName => $names) {
                $parent = $parentName === null ? null : $parents->get(strtolower($parentName));
                if ($parentName !== null && $parent === null) {
                    continue;
                }

                foreach ($names as $name) {
                    $category = TransactionCategory::withTrashed()->firstOrNew(['name' => $name, 'kind' => $kind]);
                    $category->fill([
                        'budget_category_id' => $parent?->id,
                        'parent_name' => $parent?->name,
                        'is_system' => true,
                    ])->save();
                    if ($category->trashed()) {
                        $category->restore();
                    }
                }
            }
        }
    }
}
