<?php

namespace App\Services;

use App\Models\BudgetCategory;
use App\Models\BudgetRule;
use App\Models\PlanTemplate;
use App\Models\RecurringCommitment;
use Illuminate\Support\Collection;

class BudgetRuleService
{
    public function ensureDefaultTemplate(float $fallbackIncome = 0): PlanTemplate
    {
        $categories = $this->ensureDefaultCategories();
        $template = PlanTemplate::query()->where('is_active', true)->where('is_default', true)->first()
            ?? PlanTemplate::query()->where('is_active', true)->orderBy('id')->first();

        if ($template === null) {
            $template = PlanTemplate::create([
                'name' => 'Normal month',
                'description' => 'The standard income, expense and savings allocation plan.',
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        $this->seedDefaultRules($template, $categories, $fallbackIncome);

        return $template->fresh();
    }

    public function template(int $id, float $fallbackIncome = 0): PlanTemplate
    {
        $template = PlanTemplate::query()->findOrFail($id);
        $this->ensureCommitmentRules($template);
        $this->seedDefaultRules($template, $this->ensureDefaultCategories(), $fallbackIncome);

        return $template->fresh([
            'budgetRules.category',
            'budgetRules.recurringCommitment',
            'allocationRules.asset',
            'allocationRules.bucket.goal',
        ]);
    }

    public function monthlyIncome(float $fallback): float
    {
        return $this->templateIncome($this->ensureDefaultTemplate($fallback), $fallback);
    }

    public function templateIncome(PlanTemplate $template, float $fallback = 0): float
    {
        return round((float) $this->templateIncomeSuggestions($template, $fallback)->sum('amount'), 2);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function templateIncomeSuggestions(PlanTemplate $template, float $fallback = 0): Collection
    {
        $rules = $template->budgetRules()->where('direction', 'income')->where('is_active', true)->get();
        if ($rules->isEmpty() && $fallback > 0) {
            $template->budgetRules()->create([
                'name' => 'Primary monthly income',
                'direction' => 'income',
                'amount_egp' => $fallback,
                'frequency' => 'monthly',
                'is_active' => true,
            ]);
            $rules = $template->budgetRules()->where('direction', 'income')->where('is_active', true)->get();
        }

        return $rules->map(function (BudgetRule $rule) use ($fallback): array {
            $percent = $rule->percent_of_income !== null ? (float) $rule->percent_of_income : null;
            $amount = $rule->amount_egp !== null
                ? (float) $rule->amount_egp
                : $fallback * (float) ($percent ?? 0) / 100;

            return [
                'id' => $rule->id,
                'label' => $rule->name,
                'amount' => round($amount, 2),
                'percent' => $percent,
            ];
        })->values();
    }

    /** @return Collection<int, BudgetCategory> */
    public function ensureDefaultCategories(): Collection
    {
        $defaults = [
            ['name' => 'Essentials', 'color' => '#4db6ac', 'sort_order' => 10],
            ['name' => 'Lifestyle', 'color' => '#f6c453', 'sort_order' => 20],
            ['name' => 'Commitments', 'color' => '#ef8f62', 'sort_order' => 30],
            ['name' => 'Flexible / irregular', 'color' => '#7c8cf8', 'sort_order' => 40],
        ];

        foreach ($defaults as $default) {
            $category = BudgetCategory::withTrashed()->firstOrNew(['name' => $default['name'], 'kind' => 'expense']);
            $category->fill($default + ['is_active' => true, 'is_default' => true])->save();
            if ($category->trashed()) {
                $category->restore();
            }
        }

        return BudgetCategory::query()->where('kind', 'expense')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function monthlyExpenseSuggestions(float $income): Collection
    {
        return $this->templateExpenseSuggestions($this->ensureDefaultTemplate($income), $income);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function templateExpenseSuggestions(PlanTemplate $template, float $income): Collection
    {
        $this->ensureCommitmentRules($template);
        $categories = $this->ensureDefaultCategories();
        $rules = $template->budgetRules()->with('category')->where('direction', 'expense')->where('is_active', true)->whereNotNull('budget_category_id')->get();
        $items = $rules->groupBy('budget_category_id')->map(function (Collection $categoryRules, int|string $categoryId) use ($income): array {
            $category = $categoryRules->first()->category;
            $amount = $categoryRules->sum(function (BudgetRule $rule) use ($income): float {
                if ($rule->amount_egp !== null) {
                    return (float) $rule->amount_egp;
                }

                return $income * (float) ($rule->percent_of_income ?? 0) / 100;
            });

            return [
                'categoryId' => (int) $categoryId,
                'categoryName' => $category?->name ?? 'Uncategorized',
                'planned' => round($amount, 2),
                'ruleIds' => $categoryRules->pluck('id')->values()->all(),
            ];
        });

        return $categories->filter(fn (BudgetCategory $category): bool => $category->is_default || $items->has($category->id))->map(fn (BudgetCategory $category): array => $items->get($category->id, [
            'categoryId' => $category->id,
            'categoryName' => $category->name,
            'planned' => 0,
            'ruleIds' => [],
        ]))->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function templateAllocationSuggestions(PlanTemplate $template, float $income, float $expenses): Collection
    {
        $available = max(0, $income - $expenses);

        return $template->allocationRules()
            ->with(['asset', 'bucket.goal'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($rule) use ($available): array {
                $bucketName = $rule->bucket?->name ?? 'Unassigned bucket';
                $assetName = $rule->asset_target ?: ($rule->asset?->name ?? 'Unlinked asset');

                return [
                    'bucketId' => $rule->bucket_id,
                    'label' => $assetName.' → '.$bucketName,
                    'amount' => round($available * (float) $rule->allocation_percent / 100, 2),
                    'actual' => 0.0,
                    'kind' => $rule->bucket?->goal_id !== null ? 'goal' : ($rule->bucket?->purpose_type ?? 'other'),
                    'assetTarget' => $rule->asset_target,
                    'assetId' => $rule->asset_id,
                    'assetName' => $rule->asset?->name,
                    'allocationPercent' => (float) $rule->allocation_percent,
                ];
            })
            ->values();
    }

    public function syncCommitmentRules(PlanTemplate $template): void
    {
        $this->ensureCommitmentRules($template);
    }

    private function ensureCommitmentRules(PlanTemplate $template): void
    {
        $category = $this->ensureDefaultCategories()->first(fn (BudgetCategory $item): bool => strtolower($item->name) === 'commitments');
        if ($category === null) {
            return;
        }

        $activeIds = [];
        foreach (RecurringCommitment::query()->where('is_active', true)->get() as $commitment) {
            $activeIds[] = $commitment->id;
            $rule = $template->budgetRules()->firstOrNew([
                'recurring_commitment_id' => $commitment->id,
                'direction' => 'expense',
            ]);
            $rule->fill([
                'budget_category_id' => $category->id,
                'name' => 'Commitment: '.$commitment->name,
                'amount_egp' => $commitment->monthlyAmount(),
                'percent_of_income' => null,
                'frequency' => 'monthly',
                'is_active' => true,
            ])->save();
        }

        $template->budgetRules()
            ->where('direction', 'expense')
            ->whereNotNull('recurring_commitment_id')
            ->when($activeIds !== [], fn ($query) => $query->whereNotIn('recurring_commitment_id', $activeIds))
            ->get()->each->update(['is_active' => false]);
    }

    private function seedDefaultRules(PlanTemplate $template, Collection $categories, float $fallbackIncome): void
    {
        if (! $template->budgetRules()->where('direction', 'income')->exists() && $fallbackIncome > 0) {
            $template->budgetRules()->create([
                'name' => 'Primary monthly income',
                'direction' => 'income',
                'amount_egp' => $fallbackIncome,
                'frequency' => 'monthly',
                'is_active' => true,
            ]);
        }

        foreach ($categories as $category) {
            $defaultAmount = match (strtolower($category->name)) {
                'essentials' => 10000,
                'lifestyle' => 5000,
                'flexible / irregular' => 3098.33,
                default => null,
            };
            if ($defaultAmount !== null && ! $template->budgetRules()->where('budget_category_id', $category->id)->where('direction', 'expense')->exists()) {
                $template->budgetRules()->create([
                    'budget_category_id' => $category->id,
                    'name' => 'Default monthly '.$category->name.' budget',
                    'direction' => 'expense',
                    'amount_egp' => $defaultAmount,
                    'frequency' => 'monthly',
                    'is_active' => true,
                ]);
            }
        }

        $this->ensureCommitmentRules($template);
    }
}
