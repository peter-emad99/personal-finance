<?php

namespace App\Http\Controllers;

use App\Models\AllocationPlan;
use App\Models\AllocationPlanExpense;
use App\Models\AllocationPlanIncome;
use App\Models\AllocationPlanItem;
use App\Models\Asset;
use App\Models\Bucket;
use App\Models\BudgetCategory;
use App\Models\BudgetRule;
use App\Models\PlanTemplate;
use App\Services\AllocationActualService;
use App\Services\BudgetRuleService;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AllocationController extends Controller
{
    public function reconciliation(FinanceService $finance): Response
    {
        $dashboard = $finance->dashboard();

        return Inertia::render('allocation-reconciliation', [
            'summary' => [
                'totalAssets' => $dashboard['summary']['totalAssets'],
                'fullyAssignedPurposeBalance' => $dashboard['summary']['allocatedToGoals'] + $dashboard['summary']['allocatedToNonGoals'],
                'allocatedToGoals' => $dashboard['summary']['allocatedToGoals'],
                'allocatedToNonGoals' => $dashboard['summary']['allocatedToNonGoals'],
                'trulyUnallocated' => $dashboard['summary']['unallocated'],
            ],
            'assets' => $dashboard['assets'],
            'buckets' => $dashboard['buckets'],
            'dataFreshness' => $dashboard['dataFreshness'],
        ]);
    }

    public function index(Request $request, FinanceService $finance, AllocationActualService $actuals, BudgetRuleService $budgetRules): Response
    {
        $month = $request->input('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->input('month'))
            ? Carbon::createFromFormat('Y-m', (string) $request->input('month'))->startOfMonth()->toDateString()
            : now()->startOfMonth()->toDateString();
        $plan = AllocationPlan::with(['template', 'items.asset', 'items.bucket.goal', 'expenseItems.category', 'incomeItems.budgetRule'])->whereDate('month', $month)->first();
        $actualTracking = $plan ? $actuals->preview($plan) : null;
        $dashboard = $finance->dashboard();
        $fallbackIncome = (float) $dashboard['summary']['income'];
        $defaultTemplate = $budgetRules->ensureDefaultTemplate($fallbackIncome);
        $requestedTemplate = $request->filled('template_id')
            ? PlanTemplate::query()->find((int) $request->integer('template_id'))
            : null;
        $selectedTemplate = $plan?->template
            ?? $requestedTemplate
            ?? $defaultTemplate;
        $selectedTemplate = $budgetRules->template($selectedTemplate->id, $fallbackIncome);
        $plannedIncome = $budgetRules->templateIncome($selectedTemplate, $fallbackIncome);
        $templateExpenses = $budgetRules->templateExpenseSuggestions($selectedTemplate, $plannedIncome);
        $plannedExpenses = round((float) $templateExpenses->sum('planned'), 2);
        $templateItems = $budgetRules->templateAllocationSuggestions($selectedTemplate, $plannedIncome, $plannedExpenses);

        return Inertia::render('allocations', [
            'month' => $month,
            'plan' => $plan ? ['id' => $plan->id, 'templateId' => $plan->plan_template_id, 'templateName' => $plan->template?->name, 'status' => $plan->status, 'closedAt' => $plan->closed_at?->toIso8601String(), 'income' => (float) $plan->planned_income_egp, 'expenses' => (float) $plan->planned_expenses_egp, 'sourceReviewId' => $plan->source_review_id, 'generationMethod' => $plan->generation_method, 'incomeItems' => $plan->incomeItems->map(fn (AllocationPlanIncome $item) => ['id' => $item->id, 'budgetRuleId' => $item->budget_rule_id, 'name' => $item->name, 'planned' => (float) $item->planned_amount_egp, 'actual' => (float) $item->actual_amount_egp])->values(), 'items' => $plan->items->map(fn (AllocationPlanItem $item) => ['bucketId' => $item->bucket_id, 'bucketName' => $item->bucket?->name ?? 'Unassigned bucket', 'assetId' => $item->asset_id, 'assetName' => $item->asset?->name, 'planned' => (float) $item->planned_amount_egp, 'actual' => $actualTracking['source'] === 'confirmed_ledger' ? (float) ($actualTracking['itemActuals'][$item->id] ?? $actualTracking['actuals'][$item->bucket_id] ?? 0) : (float) $item->actual_amount_egp, 'actualSource' => $actualTracking['source'] === 'confirmed_ledger' ? 'confirmed_ledger' : ($item->actual_source ?? 'manual'), 'assetTarget' => $item->asset_target, 'allocationPercent' => $item->allocation_percent !== null ? (float) $item->allocation_percent : null]), 'expenseItems' => $plan->expenseItems->map(fn (AllocationPlanExpense $item) => ['categoryId' => $item->budget_category_id, 'categoryName' => $item->category?->name ?? 'Uncategorized', 'planned' => (float) $item->planned_amount_egp, 'actual' => $actualTracking['source'] === 'confirmed_ledger' ? (float) ($actualTracking['expenseActuals'][$item->budget_category_id] ?? 0) : (float) $item->actual_amount_egp, 'actualSource' => $actualTracking['source'] === 'confirmed_ledger' ? 'confirmed_ledger' : $item->actual_source])->values()] : null,
            'actualTracking' => $actualTracking,
            'buckets' => Bucket::orderBy('name')->get(['id', 'name']),
            'assets' => Asset::with(['buckets.goal'])->orderBy('name')->get()->map(fn (Asset $asset): array => ['id' => $asset->id, 'name' => $asset->name, 'type' => $asset->type, 'buckets' => $asset->buckets->map(fn ($bucket): array => ['id' => $bucket->id, 'name' => $bucket->name, 'goalName' => $bucket->goal?->name])->values()])->values(),
            'defaults' => [
                'income' => $plannedIncome,
                'incomeItems' => $budgetRules->templateIncomeSuggestions($selectedTemplate, $fallbackIncome)->map(fn (array $item): array => ['budgetRuleId' => $item['id'], 'name' => $item['label'], 'planned' => (float) $item['amount'], 'actual' => 0.0])->values(),
                'expenses' => $plannedExpenses,
                'items' => $templateItems,
                'source' => 'plan_template',
                'expenseItems' => $templateExpenses,
            ],
            'templates' => PlanTemplate::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default'])->map(fn (PlanTemplate $item): array => ['id' => $item->id, 'name' => $item->name, 'isDefault' => $item->is_default])->values(),
            'selectedTemplateId' => $selectedTemplate->id,
            'canChooseTemplate' => $plan === null,
            'expenseCategories' => $budgetRules->ensureDefaultCategories()->map(fn (BudgetCategory $category): array => ['id' => $category->id, 'name' => $category->name])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date'], 'planned_income_egp' => ['required', 'numeric', 'min:0'], 'planned_expenses_egp' => ['required', 'numeric', 'min:0'],
            'plan_template_id' => ['nullable', 'exists:plan_templates,id'], 'income_items' => ['sometimes', 'array'], 'income_items.*.budget_rule_id' => ['nullable', 'exists:budget_rules,id'], 'income_items.*.name' => ['required', 'string', 'max:120'], 'income_items.*.planned_amount_egp' => ['required', 'numeric', 'min:0'], 'income_items.*.actual_amount_egp' => ['nullable', 'numeric', 'min:0'], 'items' => ['array'], 'items.*.bucket_id' => ['required', 'exists:buckets,id'], 'items.*.asset_id' => ['nullable', 'exists:assets,id'], 'items.*.planned_amount_egp' => ['required', 'numeric', 'min:0'], 'items.*.actual_amount_egp' => ['nullable', 'numeric', 'min:0'], 'items.*.asset_target' => ['nullable', 'string', 'max:160'], 'items.*.allocation_percent' => ['nullable', 'numeric', 'between:0,100'],
            'expenses' => ['sometimes', 'array'], 'expenses.*.category_id' => ['required', 'exists:budget_categories,id'], 'expenses.*.planned_amount_egp' => ['required', 'numeric', 'min:0'], 'expenses.*.actual_amount_egp' => ['nullable', 'numeric', 'min:0'],
        ]);
        if (array_key_exists('income_items', $data)) {
            $incomeTotal = round((float) collect($data['income_items'])->sum(fn (array $item): float => (float) $item['planned_amount_egp']), 2);
            if (abs($incomeTotal - (float) $data['planned_income_egp']) > 0.01) {
                throw ValidationException::withMessages(['income_items' => 'Income sources must add up to the planned monthly income.']);
            }
        }
        $available = (float) $data['planned_income_egp'] - (float) $data['planned_expenses_egp'];
        $plannedTotal = 0.0;
        $percentTotal = 0.0;
        $hasPercentage = false;
        $hasFixed = false;
        foreach ($data['items'] ?? [] as $item) {
            if (array_key_exists('allocation_percent', $item) && $item['allocation_percent'] !== null && $item['allocation_percent'] !== '') {
                $hasPercentage = true;
                $percentTotal += (float) $item['allocation_percent'];
                $plannedTotal += round(max(0, $available) * (float) $item['allocation_percent'] / 100, 2);
            } else {
                $hasFixed = true;
                $plannedTotal += (float) ($item['planned_amount_egp'] ?? 0);
            }
        }
        if ($hasPercentage && $hasFixed) {
            throw ValidationException::withMessages(['items' => 'Use percentages for every savings allocation row, or use fixed amounts for every row.']);
        }
        if ($percentTotal > 100.01) {
            throw ValidationException::withMessages(['items' => 'Savings allocation percentages cannot exceed 100%.']);
        }
        $expenseCategoryIds = collect($data['expenses'] ?? [])->pluck('category_id');
        if ($expenseCategoryIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['expenses' => 'Each expense category can only appear once in a monthly plan.']);
        }
        if ($plannedTotal > max(0, $available) + 0.01) {
            throw ValidationException::withMessages([
                'items' => 'Monthly allocations cannot exceed the income left after planned expenses.',
            ]);
        }
        $allocationKeys = collect($data['items'] ?? [])->map(function (array $item): string {
            return (string) ($item['asset_id'] ?? 'none').':'.$item['bucket_id'];
        });
        if ($allocationKeys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['items' => 'The same asset and purpose bucket can only appear once in a monthly plan.']);
        }
        $template = isset($data['plan_template_id']) ? app(BudgetRuleService::class)->template((int) $data['plan_template_id'], (float) $data['planned_income_egp']) : app(BudgetRuleService::class)->ensureDefaultTemplate((float) $data['planned_income_egp']);
        $expenseItems = array_key_exists('expenses', $data)
            ? $data['expenses']
            : app(BudgetRuleService::class)->templateExpenseSuggestions($template, (float) $data['planned_income_egp'])->map(fn (array $item): array => ['category_id' => $item['categoryId'], 'planned_amount_egp' => $item['planned'], 'actual_amount_egp' => 0])->all();
        $month = Carbon::parse($data['month'])->startOfMonth();

        DB::transaction(function () use ($data, $month, $expenseItems, $template): void {
            $plan = AllocationPlan::whereDate('month', $month->toDateString())->first() ?? new AllocationPlan;
            abort_if($plan->exists && $plan->status === 'closed', 422, 'This monthly plan is closed and cannot be edited.');
            $effectiveTemplate = $plan->exists && $plan->plan_template_id !== null
                ? PlanTemplate::findOrFail($plan->plan_template_id)
                : $template;
            $plan->fill(['month' => $month, 'plan_template_id' => $effectiveTemplate->id, 'planned_income_egp' => $data['planned_income_egp'], 'planned_expenses_egp' => $data['planned_expenses_egp'], 'generation_method' => $plan->exists ? $plan->generation_method : 'from_template', 'status' => $plan->exists ? $plan->status : 'open'])->save();
            if (array_key_exists('income_items', $data)) {
                $plan->incomeItems()->get()->each->delete();
                foreach ($data['income_items'] as $item) {
                    $budgetRule = isset($item['budget_rule_id']) ? BudgetRule::findOrFail($item['budget_rule_id']) : null;
                    $plan->incomeItems()->create([
                        'budget_rule_id' => $budgetRule?->id,
                        'name' => $item['name'],
                        'planned_amount_egp' => $item['planned_amount_egp'],
                        'actual_amount_egp' => $item['actual_amount_egp'] ?? 0,
                    ]);
                }
            } elseif (! $plan->incomeItems()->exists()) {
                $plan->incomeItems()->create(['name' => 'Monthly income', 'planned_amount_egp' => $data['planned_income_egp']]);
            }
            $plan->items()->get()->each->delete();
            foreach ($data['items'] ?? [] as $item) {
                $asset = isset($item['asset_id']) ? Asset::findOrFail($item['asset_id']) : null;
                if ($asset !== null && ! $asset->buckets()->whereKey($item['bucket_id'])->exists()) {
                    abort(422, 'The selected bucket is not assigned to the selected asset.');
                }
                $amount = array_key_exists('allocation_percent', $item) && $item['allocation_percent'] !== null && $item['allocation_percent'] !== ''
                    ? round(max(0, (float) $data['planned_income_egp'] - (float) $data['planned_expenses_egp']) * (float) $item['allocation_percent'] / 100, 2)
                    : (float) $item['planned_amount_egp'];
                $plan->items()->create(['bucket_id' => $item['bucket_id'], 'asset_id' => $item['asset_id'] ?? null, 'planned_amount_egp' => $amount, 'actual_amount_egp' => $item['actual_amount_egp'] ?? 0, 'asset_target' => $item['asset_target'] ?? $asset?->name, 'allocation_percent' => $item['allocation_percent'] ?? null]);
            }
            $plan->expenseItems()->get()->each->delete();
            foreach ($expenseItems as $item) {
                $plan->expenseItems()->create(['budget_category_id' => $item['category_id'], 'planned_amount_egp' => $item['planned_amount_egp'], 'actual_amount_egp' => $item['actual_amount_egp'] ?? 0]);
            }
        });

        return back()->with('success', 'Monthly allocation plan saved.');
    }

    public function syncActuals(AllocationPlan $allocationPlan, AllocationActualService $actuals): RedirectResponse
    {
        $result = $actuals->sync($allocationPlan);

        return back()->with('success', $result['synced'] ? 'Actual amounts synced from confirmed ledger transactions.' : 'No confirmed ledger transactions were available for this month.');
    }

    public function refreshFromTemplate(AllocationPlan $allocationPlan, BudgetRuleService $budgetRules, AllocationActualService $actuals): RedirectResponse
    {
        abort_if($allocationPlan->status === 'closed', 422, 'This monthly plan is closed and cannot be refreshed from its template.');
        abort_if($allocationPlan->plan_template_id === null, 422, 'This monthly plan has no template to refresh from.');

        $existingIncomeActuals = $allocationPlan->incomeItems()->get()->mapWithKeys(function (AllocationPlanIncome $item): array {
            $key = $item->budget_rule_id !== null
                ? 'rule:'.$item->budget_rule_id
                : 'name:'.$item->name;

            return [$key => [
                'amount' => (float) $item->actual_amount_egp,
                'source' => $item->actual_source,
                'syncedAt' => $item->actual_synced_at,
            ]];
        })->all();

        $template = $budgetRules->template((int) $allocationPlan->plan_template_id, (float) $allocationPlan->planned_income_egp);
        $plannedIncome = $budgetRules->templateIncome($template, (float) $allocationPlan->planned_income_egp);
        $incomeItems = $budgetRules->templateIncomeSuggestions($template, (float) $allocationPlan->planned_income_egp);
        $expenseItems = $budgetRules->templateExpenseSuggestions($template, $plannedIncome);
        $allocationItems = $budgetRules->templateAllocationSuggestions(
            $template,
            $plannedIncome,
            (float) $expenseItems->sum('planned'),
        );

        DB::transaction(function () use ($allocationPlan, $plannedIncome, $expenseItems, $incomeItems, $allocationItems, $existingIncomeActuals): void {
            $allocationPlan->update([
                'planned_income_egp' => $plannedIncome,
                'planned_expenses_egp' => round((float) $expenseItems->sum('planned'), 2),
                'generation_method' => 'from_template',
                'generated_at' => now(),
            ]);

            $allocationPlan->incomeItems()->get()->each->delete();
            foreach ($incomeItems as $item) {
                $existingActual = $existingIncomeActuals['rule:'.$item['id']]
                    ?? $existingIncomeActuals['name:'.$item['label']]
                    ?? ['amount' => 0.0, 'source' => 'manual', 'syncedAt' => null];
                $allocationPlan->incomeItems()->create([
                    'budget_rule_id' => $item['id'],
                    'name' => $item['label'],
                    'planned_amount_egp' => $item['amount'],
                    'actual_amount_egp' => $existingActual['amount'],
                    'actual_source' => $existingActual['source'],
                    'actual_synced_at' => $existingActual['syncedAt'],
                ]);
            }

            $allocationPlan->expenseItems()->get()->each->delete();
            foreach ($expenseItems as $item) {
                $allocationPlan->expenseItems()->create([
                    'budget_category_id' => $item['categoryId'],
                    'planned_amount_egp' => $item['planned'],
                    'actual_amount_egp' => 0,
                ]);
            }

            $allocationPlan->items()->get()->each->delete();
            foreach ($allocationItems as $item) {
                $allocationPlan->items()->create([
                    'bucket_id' => $item['bucketId'],
                    'asset_id' => $item['assetId'],
                    'asset_target' => $item['assetTarget'],
                    'allocation_percent' => $item['allocationPercent'],
                    'planned_amount_egp' => $item['amount'],
                    'actual_amount_egp' => 0,
                ]);
            }
        });

        $actuals->sync($allocationPlan->fresh());

        return back()->with('success', 'The current monthly plan was refreshed from its template and confirmed actuals were re-synced.');
    }

    public function close(AllocationPlan $allocationPlan): RedirectResponse
    {
        abort_if($allocationPlan->status === 'closed', 422, 'This monthly plan is already closed.');
        $allocationPlan->update(['status' => 'closed', 'closed_at' => now()]);

        return back()->with('success', 'Monthly plan closed. Its snapshot is now protected from template changes.');
    }

    public function destroy(AllocationPlan $allocationPlan): RedirectResponse
    {
        $allocationPlan->delete();

        return back()->with('success', 'Allocation plan archived.');
    }

    public function restore(int $allocationPlan): RedirectResponse
    {
        AllocationPlan::withTrashed()->findOrFail($allocationPlan)->restore();

        return back()->with('success', 'Allocation plan restored.');
    }
}
