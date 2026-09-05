<?php

namespace App\Http\Controllers;

use App\Models\AllocationPlan;
use App\Models\AllocationRule;
use App\Models\Asset;
use App\Models\BudgetCategory;
use App\Models\BudgetRule;
use App\Models\PlanTemplate;
use App\Services\BudgetRuleService;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BudgetRulesController extends Controller
{
    public function index(Request $request, BudgetRuleService $rules, FinanceService $finance): Response
    {
        $fallbackIncome = (float) $finance->dashboard()['summary']['income'];
        $default = $rules->ensureDefaultTemplate($fallbackIncome);
        $selected = $request->filled('template_id')
            ? PlanTemplate::query()->find((int) $request->integer('template_id'))
            : $default;
        $selected ??= $default;
        $template = $rules->template($selected->id, $fallbackIncome)->load([
            'budgetRules.category',
            'budgetRules.recurringCommitment',
            'allocationRules.asset',
            'allocationRules.bucket.goal',
        ]);
        $categories = $rules->ensureDefaultCategories();

        return Inertia::render('budget-rules', [
            'editing' => $request->boolean('edit'),
            'templates' => PlanTemplate::query()->where('is_active', true)->withCount('allocationPlans')->orderByDesc('is_default')->orderBy('name')->get()->map(fn (PlanTemplate $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'isDefault' => $item->is_default,
                'monthlyPlanCount' => $item->allocation_plans_count,
            ])->values(),
            'selectedTemplateId' => $template->id,
            'templateName' => $template->name,
            'templateDescription' => $template->description,
            'incomeRules' => $template->budgetRules->where('direction', 'income')->where('is_active', true)->map(fn (BudgetRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'amount' => $rule->amount_egp !== null ? (float) $rule->amount_egp : null,
            ])->values(),
            'expenseRules' => $template->budgetRules->where('direction', 'expense')->where('is_active', true)->map(fn (BudgetRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'categoryId' => $rule->budget_category_id,
                'categoryName' => $rule->category?->name,
                'amount' => $rule->amount_egp !== null ? (float) $rule->amount_egp : null,
                'percent' => $rule->percent_of_income !== null ? (float) $rule->percent_of_income : null,
                'generatedFromCommitment' => $rule->recurring_commitment_id !== null,
                'commitmentName' => $rule->recurringCommitment?->name,
            ])->values(),
            'allocationRules' => $template->allocationRules->where('is_active', true)->map(fn (AllocationRule $rule): array => [
                'id' => $rule->id,
                'assetId' => $rule->asset_id,
                'assetName' => $rule->asset?->name,
                'bucketId' => $rule->bucket_id,
                'bucketName' => $rule->bucket->name,
                'goalName' => $rule->bucket->goal?->name,
                'assetTarget' => $rule->asset_target,
                'percent' => (float) $rule->allocation_percent,
                'legacyUnlinked' => $rule->asset_id === null,
            ])->values(),
            'assets' => Asset::with(['buckets.goal', 'assetType'])->orderBy('name')->get()->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->name,
                'type' => $asset->type,
                'assetTypeLabel' => $asset->assetType?->label,
                'assetClass' => $asset->assetType?->class,
                'buckets' => $asset->buckets->map(fn ($bucket): array => ['id' => $bucket->id, 'name' => $bucket->name, 'goalName' => $bucket->goal?->name])->values(),
            ])->values(),
            'categories' => $categories->map(fn (BudgetCategory $category): array => ['id' => $category->id, 'name' => $category->name, 'isDefault' => $category->is_default])->values(),
            'monthlyIncome' => $rules->templateIncome($template, $fallbackIncome),
        ]);
    }

    public function storeTemplate(Request $request, BudgetRuleService $rules): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]);
        $template = PlanTemplate::create($data + ['is_active' => true, 'is_default' => false]);
        $rules->template($template->id);

        return redirect()->route('monthly-rules.index', ['template_id' => $template->id, 'edit' => 1])->with('success', 'Plan template created.');
    }

    public function edit(PlanTemplate $template): RedirectResponse
    {
        return redirect()->route('monthly-rules.index', ['template_id' => $template->id, 'edit' => 1]);
    }

    public function duplicate(Request $request, BudgetRuleService $rules): RedirectResponse
    {
        $data = $request->validate(['template_id' => ['required', 'exists:plan_templates,id'], 'name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]);
        $source = PlanTemplate::query()->with(['budgetRules', 'allocationRules'])->findOrFail($data['template_id']);

        DB::transaction(function () use ($source, $data, &$template): void {
            $template = PlanTemplate::create(['name' => $data['name'], 'description' => $data['description'] ?? $source->description, 'is_active' => true, 'is_default' => false]);
            foreach ($source->budgetRules as $rule) {
                $copy = $rule->replicate(['id', 'created_at', 'updated_at']);
                $copy->plan_template_id = $template->id;
                $copy->save();
            }
            foreach ($source->allocationRules as $rule) {
                $copy = $rule->replicate(['id', 'created_at', 'updated_at']);
                $copy->plan_template_id = $template->id;
                $copy->save();
            }
        });
        $rules->syncCommitmentRules($template);

        return redirect()->route('monthly-rules.index', ['template_id' => $template->id, 'edit' => 1])->with('success', 'Plan template duplicated.');
    }

    public function setDefault(PlanTemplate $template): RedirectResponse
    {
        DB::transaction(function () use ($template): void {
            PlanTemplate::query()->get()->each->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });

        return back()->with('success', 'Default template updated.');
    }

    public function updateTemplate(Request $request, PlanTemplate $template): RedirectResponse
    {
        $template->update($request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]));

        return back()->with('success', 'Template details updated.');
    }

    public function store(Request $request, BudgetRuleService $defaults): RedirectResponse
    {
        $data = $request->validate([
            'template_id' => ['nullable', 'exists:plan_templates,id'],
            'income_rules' => ['array'],
            'income_rules.*.id' => ['nullable', 'exists:budget_rules,id'],
            'income_rules.*.name' => ['required', 'string', 'max:120'],
            'income_rules.*.amount' => ['nullable', 'numeric', 'min:0'],
            'income_rules.*.percent' => ['nullable', 'numeric', 'between:0,100'],
            'expense_rules' => ['array'],
            'expense_rules.*.id' => ['nullable', 'exists:budget_rules,id'],
            'expense_rules.*.name' => ['required', 'string', 'max:120'],
            'expense_rules.*.category_id' => ['required', 'exists:budget_categories,id'],
            'expense_rules.*.amount' => ['nullable', 'numeric', 'min:0'],
            'expense_rules.*.percent' => ['nullable', 'numeric', 'between:0,100'],
            'allocation_rules' => ['array'],
            'allocation_rules.*.id' => ['nullable', 'exists:allocation_rules,id'],
            'allocation_rules.*.asset_id' => ['nullable', 'exists:assets,id'],
            'allocation_rules.*.bucket_id' => ['required', 'exists:buckets,id'],
            'allocation_rules.*.asset_target' => ['nullable', 'string', 'max:160'],
            'allocation_rules.*.percent' => ['required', 'numeric', 'between:0,100'],
        ]);
        foreach ($data['income_rules'] ?? [] as $index => $item) {
            $hasAmount = array_key_exists('amount', $item) && $item['amount'] !== null && $item['amount'] !== '';
            $hasPercent = array_key_exists('percent', $item) && $item['percent'] !== null && $item['percent'] !== '';
            if ($hasAmount === $hasPercent) {
                throw ValidationException::withMessages(["income_rules.{$index}.amount" => 'Choose either a fixed amount or a percentage of income.']);
            }
        }
        $template = isset($data['template_id']) ? $defaults->template((int) $data['template_id']) : $defaults->ensureDefaultTemplate();
        $allocationPercent = round((float) collect($data['allocation_rules'] ?? [])->sum(fn (array $rule): float => (float) $rule['percent']), 3);
        abort_if($allocationPercent > 100.001, 422, 'Allocation rule percentages cannot exceed 100%.');

        DB::transaction(function () use ($data, $template): void {
            $incomeIds = [];
            foreach ($data['income_rules'] ?? [] as $item) {
                $rule = isset($item['id']) ? $template->budgetRules()->findOrFail($item['id']) : $template->budgetRules()->firstOrNew(['name' => $item['name'], 'direction' => 'income']);
                $rule->fill(['name' => $item['name'], 'direction' => 'income', 'amount_egp' => $item['amount'] ?? null, 'percent_of_income' => $item['percent'] ?? null, 'frequency' => 'monthly', 'is_active' => true])->save();
                $incomeIds[] = $rule->id;
            }
            $template->budgetRules()->where('direction', 'income')->whereNotIn('id', $incomeIds ?: [0])->get()->each->update(['is_active' => false]);

            $expenseIds = [];
            foreach ($data['expense_rules'] ?? [] as $item) {
                $rule = isset($item['id']) ? $template->budgetRules()->findOrFail($item['id']) : $template->budgetRules()->firstOrNew(['budget_category_id' => $item['category_id'], 'direction' => 'expense', 'recurring_commitment_id' => null, 'name' => $item['name']]);
                if ($rule->recurring_commitment_id !== null) {
                    $expenseIds[] = $rule->id;

                    continue;
                }
                $rule->fill(['budget_category_id' => $item['category_id'], 'name' => $item['name'], 'direction' => 'expense', 'amount_egp' => $item['amount'] ?? null, 'percent_of_income' => $item['percent'] ?? null, 'frequency' => 'monthly', 'is_active' => true])->save();
                $expenseIds[] = $rule->id;
            }
            $template->budgetRules()->where('direction', 'expense')->whereNull('recurring_commitment_id')->whereNotIn('id', $expenseIds ?: [0])->get()->each->update(['is_active' => false]);

            $allocationIds = [];
            foreach ($data['allocation_rules'] ?? [] as $index => $item) {
                $asset = isset($item['asset_id']) ? Asset::findOrFail($item['asset_id']) : null;
                if ($asset !== null && ! $asset->buckets()->whereKey($item['bucket_id'])->exists()) {
                    abort(422, 'The selected bucket is not assigned to the selected asset.');
                }
                $target = $item['asset_target'] ?? $asset?->name ?? 'Unlinked asset';
                $rule = isset($item['id']) ? $template->allocationRules()->findOrFail($item['id']) : $template->allocationRules()->withTrashed()->firstOrNew(['asset_id' => $item['asset_id'] ?? null, 'bucket_id' => $item['bucket_id'], 'asset_target' => $target]);
                $rule->fill(['asset_id' => $item['asset_id'] ?? null, 'bucket_id' => $item['bucket_id'], 'asset_target' => $target, 'allocation_percent' => $item['percent'], 'sort_order' => $index, 'is_active' => true])->save();
                if ($rule->trashed()) {
                    $rule->restore();
                }
                $allocationIds[] = $rule->id;
            }
            $template->allocationRules()->whereNotIn('id', $allocationIds ?: [0])->get()->each->delete();
        });

        return back()->with('success', 'Template rules saved.');
    }

    public function fromPlan(Request $request, AllocationPlan $allocationPlan): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]);
        $allocationPlan->load(['items.asset', 'items.bucket', 'expenseItems.category']);
        $allocationTotal = max(0, (float) $allocationPlan->items->sum('planned_amount_egp'));

        $template = DB::transaction(function () use ($allocationPlan, $data, $allocationTotal): PlanTemplate {
            $template = PlanTemplate::create(['name' => $data['name'], 'description' => $data['description'] ?? 'Created from monthly plan '.$allocationPlan->month->format('F Y'), 'is_active' => true, 'is_default' => false]);
            $template->budgetRules()->create(['name' => 'Income from '.$allocationPlan->month->format('F Y'), 'direction' => 'income', 'amount_egp' => $allocationPlan->planned_income_egp, 'frequency' => 'monthly', 'is_active' => true]);
            foreach ($allocationPlan->expenseItems as $item) {
                $template->budgetRules()->create(['budget_category_id' => $item->budget_category_id, 'name' => $item->category->name.' from '.$allocationPlan->month->format('F Y'), 'direction' => 'expense', 'amount_egp' => $item->planned_amount_egp, 'frequency' => 'monthly', 'is_active' => true]);
            }
            foreach ($allocationPlan->items as $item) {
                $template->allocationRules()->create(['asset_id' => $item->asset_id, 'bucket_id' => $item->bucket_id, 'asset_target' => $item->asset_target ?? $item->asset?->name ?? 'Unlinked asset', 'allocation_percent' => $item->allocation_percent !== null ? $item->allocation_percent : ($allocationTotal > 0 ? round((float) $item->planned_amount_egp / $allocationTotal * 100, 3) : 0), 'sort_order' => $item->id, 'is_active' => true]);
            }

            return $template;
        });

        return redirect()->route('monthly-rules.index', ['template_id' => $template->id])->with('success', 'Template created from the monthly plan.');
    }

    public function destroy(PlanTemplate $template): RedirectResponse
    {
        abort_if($template->is_default, 422, 'The default template cannot be archived. Choose another default first.');
        $template->delete();

        return redirect()->route('monthly-rules.index')->with('success', 'Plan template archived.');
    }

    public function restore(int $template): RedirectResponse
    {
        PlanTemplate::withTrashed()->findOrFail($template)->restore();

        return back()->with('success', 'Plan template restored.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        BudgetCategory::create($request->validate(['name' => ['required', 'string', 'max:100'], 'color' => ['nullable', 'string', 'max:20'], 'is_default' => ['sometimes', 'boolean']]));

        return back()->with('success', 'Budget category added.');
    }
}
