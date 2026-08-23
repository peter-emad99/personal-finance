<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AllocationPlan;
use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\Bucket;
use App\Models\BudgetCategory;
use App\Models\CashFlow;
use App\Models\DecisionJournalEntry;
use App\Models\FinancialSetting;
use App\Models\FxRate;
use App\Models\Goal;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\LedgerTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalanceHistory;
use App\Models\LiabilityPaymentRecord;
use App\Models\MonthlyFinancialReview;
use App\Models\PlanTemplate;
use App\Models\RecurringCommitment;
use App\Models\Snapshot;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Services\AllocationActualService;
use App\Services\BudgetRuleService;
use App\Support\OwnerContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoWorkspaceSeeder extends Seeder
{
    private const DEMO_MONTHLY_SALARY = 40000;

    private const DEMO_PLANNED_EXPENSES = 20000;

    public function reset(): void
    {
        if (! config('finance.demo_enabled')) {
            return;
        }

        $demo = User::query()->where('email', config('finance.demo_email'))->first();
        if ($demo) {
            DB::transaction(function () use ($demo): void {
                $this->purgeDemoData((int) $demo->id);
            });
        }

        $this->run();
    }

    public function run(): void
    {
        if (! config('finance.demo_enabled')) {
            return;
        }

        $demo = User::query()->firstOrNew(['email' => config('finance.demo_email')]);
        $demo->name = config('finance.demo_name');
        $demo->password = Hash::make((string) config('finance.demo_password'));
        $demo->email_verified_at ??= now();
        $demo->save();

        OwnerContext::set($demo);
        $this->seedPolicy();

        $homeOffice = Goal::updateOrCreate(['name' => 'Home office upgrade'], [
            'target_amount_egp' => 250000,
            'monthly_contribution_egp' => 8000,
            'deadline' => now()->addMonths(24)->endOfMonth()->toDateString(),
            'priority' => 1,
            'status' => 'active',
            'notes' => 'Demo goal: a medium-term purchase funded without emptying the safety reserve.',
        ]);

        $familyCar = Goal::updateOrCreate(['name' => 'Family car'], [
            'target_amount_egp' => 1500000,
            'monthly_contribution_egp' => 4000,
            'deadline' => now()->addMonths(48)->endOfMonth()->toDateString(),
            'priority' => 2,
            'status' => 'active',
            'notes' => 'Demo stretch goal: compare its required pace with investing and the emergency reserve before committing to the purchase.',
        ]);

        $homeRenovation = Goal::updateOrCreate(['name' => 'Home renovation fund'], [
            'target_amount_egp' => 180000,
            'monthly_contribution_egp' => 2000,
            'deadline' => now()->addMonths(18)->endOfMonth()->toDateString(),
            'priority' => 3,
            'status' => 'active',
            'notes' => 'Demo shorter-term goal used to show how multiple goals compete for the same monthly surplus.',
        ]);

        $buckets = [
            'Emergency Reserve' => Bucket::updateOrCreate(['name' => 'Emergency Reserve'], [
                'purpose_type' => 'emergency',
                'purpose' => 'Six months of essential expenses and required payments',
                'target_amount_egp' => 102000,
                'color' => '#4ade80',
            ]),
            'Home Office' => Bucket::updateOrCreate(['name' => 'Home Office'], [
                'purpose_type' => 'goal',
                'goal_id' => $homeOffice->id,
                'purpose' => 'A planned purchase with a fixed target date',
                'target_amount_egp' => 250000,
                'color' => '#f6c453',
            ]),
            'Family Car' => Bucket::updateOrCreate(['name' => 'Family Car'], [
                'purpose_type' => 'goal',
                'goal_id' => $familyCar->id,
                'purpose' => 'A high-value stretch purchase that must be tested against long-term investing',
                'target_amount_egp' => 1500000,
                'color' => '#f08da1',
            ]),
            'Home Renovation' => Bucket::updateOrCreate(['name' => 'Home Renovation'], [
                'purpose_type' => 'goal',
                'goal_id' => $homeRenovation->id,
                'purpose' => 'A nearer-term improvement funded without using emergency cash',
                'target_amount_egp' => 180000,
                'color' => '#79c2d0',
            ]),
            'Long-Term Investing' => Bucket::updateOrCreate(['name' => 'Long-Term Investing'], [
                'purpose_type' => 'investment',
                'purpose' => 'Capital intended to compound for the long term',
                'color' => '#7c8cf8',
            ]),
            'Opportunity Fund' => Bucket::updateOrCreate(['name' => 'Opportunity Fund'], [
                'purpose_type' => 'investment',
                'purpose' => 'Flexible capital kept outside the monthly budget',
                'target_amount_egp' => 100000,
                'color' => '#f08da1',
            ]),
            'Monthly Spending' => Bucket::updateOrCreate(['name' => 'Monthly Spending'], [
                'purpose_type' => 'other',
                'purpose' => 'Near-term cash buffer for normal life',
                'target_amount_egp' => 30000,
                'color' => '#79c2d0',
            ]),
        ];

        $assets = [
            'Current account reserve' => [
                'type' => 'Cash', 'quantity' => 40000, 'currency' => 'EGP',
                'cost_basis_egp' => 40000, 'current_value_egp' => 40000,
                'liquidity' => 'immediate', 'is_liquid' => true, 'account_name' => 'Demo current account',
            ],
            'Money market fund' => [
                'type' => 'Fixed income', 'quantity' => 80000, 'currency' => 'EGP',
                'cost_basis_egp' => 80000, 'current_value_egp' => 80000,
                'liquidity' => 'within_3_days', 'is_liquid' => true, 'account_name' => 'Demo money market fund',
            ],
            'USD reserve' => [
                'type' => 'USD', 'quantity' => 2000, 'currency' => 'USD',
                'cost_basis_egp' => 90000, 'current_value_egp' => 100000, 'unit_price_egp' => 50,
                'liquidity' => 'immediate', 'is_liquid' => true, 'account_name' => 'Demo USD account',
            ],
            'Gold holdings' => [
                'type' => 'Gold', 'quantity' => 24, 'currency' => 'Gold',
                'cost_basis_egp' => 100000, 'current_value_egp' => 120000, 'unit_price_egp' => 5000,
                'liquidity' => 'longer_term', 'is_liquid' => true, 'account_name' => 'Demo physical holdings',
            ],
            'Egyptian equity fund' => [
                'type' => 'Egyptian equities', 'quantity' => null, 'currency' => 'EGP',
                'cost_basis_egp' => 105000, 'current_value_egp' => 120000,
                'liquidity' => 'longer_term', 'is_liquid' => true, 'account_name' => 'Demo brokerage',
            ],
            'Long-term fixed income' => [
                'type' => 'Fixed income', 'quantity' => 180000, 'currency' => 'EGP',
                'cost_basis_egp' => 180000, 'current_value_egp' => 180000,
                'liquidity' => 'longer_term', 'is_liquid' => true, 'account_name' => 'Demo investment account',
            ],
        ];

        foreach ($assets as $name => $attributes) {
            $asset = Asset::updateOrCreate(['name' => $name], $attributes + [
                'notes' => 'Demo workspace value. Replace with your own asset data before making decisions.',
            ]);
            $allocations = match ($name) {
                'Current account reserve' => [
                    ['bucket' => 'Monthly Spending', 'amount' => 10000],
                    ['bucket' => 'Emergency Reserve', 'amount' => 30000],
                ],
                'Money market fund' => [
                    ['bucket' => 'Emergency Reserve', 'amount' => 72000],
                    ['bucket' => 'Opportunity Fund', 'amount' => 8000],
                ],
                'USD reserve' => [['bucket' => 'Long-Term Investing', 'amount' => 100000]],
                'Gold holdings' => [
                    ['bucket' => 'Home Office', 'amount' => 100000],
                    ['bucket' => 'Long-Term Investing', 'amount' => 20000],
                ],
                'Egyptian equity fund' => [['bucket' => 'Long-Term Investing', 'amount' => 120000]],
                default => [
                    ['bucket' => 'Home Office', 'amount' => 20000],
                    ['bucket' => 'Family Car', 'amount' => 10000],
                    ['bucket' => 'Long-Term Investing', 'amount' => 120000],
                    ['bucket' => 'Opportunity Fund', 'amount' => 30000],
                ],
            };

            foreach ($allocations as $allocation) {
                DB::table('asset_bucket_allocations')->updateOrInsert(
                    ['user_id' => $demo->id, 'asset_id' => $asset->id, 'bucket_id' => $buckets[$allocation['bucket']]->id],
                    ['amount_egp' => $allocation['amount'], 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        $educationLoan = Liability::updateOrCreate(['name' => 'Education loan'], [
            'type' => 'loan',
            'original_balance_egp' => 100000,
            'interest_rate_percent' => 14,
            'balance_egp' => 32000,
            'monthly_payment_egp' => 3000,
            'due_day' => 25,
            'payoff_on' => now()->addMonths(13)->endOfMonth()->toDateString(),
            'is_active' => true,
            'notes' => 'Demo liability showing how required payments affect the monthly plan.',
        ]);

        $commitments = [
            ['name' => 'Connectivity bundle', 'category' => 'utilities', 'amount_egp' => 800, 'frequency' => 'monthly', 'next_due_on' => now()->addDays(5)->toDateString()],
            ['name' => 'Digital subscriptions', 'category' => 'subscription', 'amount_egp' => 600, 'frequency' => 'monthly', 'next_due_on' => now()->addDays(12)->toDateString()],
            ['name' => 'Annual insurance reserve', 'category' => 'insurance', 'amount_egp' => 7200, 'frequency' => 'yearly', 'next_due_on' => now()->addMonths(4)->startOfMonth()->toDateString()],
        ];
        foreach ($commitments as $commitment) {
            RecurringCommitment::updateOrCreate(['name' => $commitment['name']], $commitment + [
                'is_active' => true,
                'notes' => 'Demo recurring commitment.',
            ]);
        }

        $templates = $this->seedPlanTemplates();
        $this->seedTwelveMonths($buckets, $educationLoan, $templates['normal']);

        DecisionJournalEntry::updateOrCreate(['decision' => 'Keep the home office goal funded without using the emergency reserve'], [
            'assumptions' => ['Emergency reserve stays above six months', 'Monthly investment pace stays above 10,000 EGP', 'The family car goal is reviewed against the freedom target before increasing its contribution'],
            'alternatives' => ['Buy sooner with a smaller reserve', 'Delay the purchase and invest the difference'],
            'rule_result' => ['status' => 'passes_configured_checks'],
            'chosen_action' => 'Continue the current monthly split and review in three months.',
            'review_date' => now()->addMonths(3)->toDateString(),
            'outcome' => 'Demo decision for the decision journal.',
            'status' => 'open',
        ]);

        OwnerContext::clear();
    }

    private function purgeDemoData(int $userId): void
    {
        $planIds = DB::table('allocation_plans')->where('user_id', $userId)->pluck('id');
        if ($planIds->isNotEmpty()) {
            DB::table('allocation_plan_items')->whereIn('allocation_plan_id', $planIds)->delete();
            DB::table('allocation_plan_expenses')->whereIn('allocation_plan_id', $planIds)->delete();
        }

        $assetIds = DB::table('assets')->where('user_id', $userId)->pluck('id');
        $bucketIds = DB::table('buckets')->where('user_id', $userId)->pluck('id');
        if ($assetIds->isNotEmpty() || $bucketIds->isNotEmpty()) {
            DB::table('asset_bucket_allocations')
                ->where(function ($query) use ($assetIds, $bucketIds): void {
                    if ($assetIds->isNotEmpty()) {
                        $query->whereIn('asset_id', $assetIds);
                    }
                    if ($bucketIds->isNotEmpty()) {
                        $query->orWhereIn('bucket_id', $bucketIds);
                    }
                })
                ->delete();
        }

        $transactionIds = DB::table('transactions')->where('user_id', $userId)->pluck('id');
        if ($transactionIds->isNotEmpty()) {
            DB::table('transaction_splits')->whereIn('transaction_id', $transactionIds)->delete();
        }

        DB::table('liability_payment_records')->where('user_id', $userId)->delete();
        DB::table('liability_balance_histories')->where('user_id', $userId)->delete();
        DB::table('asset_valuations')->where('user_id', $userId)->delete();
        DB::table('transactions')->where('user_id', $userId)->delete();
        DB::table('import_rows')->where('user_id', $userId)->delete();
        DB::table('import_batches')->where('user_id', $userId)->delete();
        DB::table('monthly_financial_reviews')->where('user_id', $userId)->delete();
        DB::table('snapshots')->where('user_id', $userId)->delete();
        DB::table('cash_flows')->where('user_id', $userId)->delete();
        DB::table('recurring_commitments')->where('user_id', $userId)->delete();
        DB::table('liabilities')->where('user_id', $userId)->delete();
        DB::table('allocation_plans')->where('user_id', $userId)->delete();
        DB::table('allocation_rules')->where('user_id', $userId)->delete();
        DB::table('budget_rules')->where('user_id', $userId)->delete();
        DB::table('plan_templates')->where('user_id', $userId)->delete();
        DB::table('budget_categories')->where('user_id', $userId)->delete();
        DB::table('goals')->where('user_id', $userId)->delete();
        DB::table('buckets')->where('user_id', $userId)->delete();
        DB::table('decision_journal_entries')->where('user_id', $userId)->delete();
        DB::table('financial_settings')->where('user_id', $userId)->delete();
        DB::table('transaction_categories')->where('user_id', $userId)->delete();
        DB::table('fx_rates')->where('user_id', $userId)->delete();
        DB::table('gold_prices')->where('user_id', $userId)->delete();
        DB::table('accounts')->where('user_id', $userId)->delete();
        DB::table('assets')->where('user_id', $userId)->delete();
        DB::table('integrity_checks')->where('user_id', $userId)->delete();
        DB::table('audit_logs')->where('user_id', $userId)->delete();
    }

    private function seedPolicy(): void
    {
        FinancialSetting::updateOrCreate(['name' => 'Demo policy'], [
            'base_currency' => 'EGP',
            'emergency_reserve_months' => 6,
            'emergency_eligible_liquidity' => 'within_3_days',
            'asset_class_targets' => FinancialSetting::defaultAssetClassTargets(),
            'rebalancing_tolerance_percent' => 5,
            'goal_funding_policy' => 'manual_contributions',
            'minimum_cash_after_purchase_egp' => 30000,
            'maximum_monthly_payment_egp' => 12000,
            'maximum_debt_burden_percent' => 20,
            'valuation_freshness_days' => 30,
            'policy' => [
                'monthly_allocation_targets' => [
                    'essentials' => 35,
                    'lifestyle' => 15,
                    'debt' => 10,
                    'emergency' => 10,
                    'goals' => 15,
                    'investing' => 15,
                ],
                'financial_freedom' => [
                    'withdrawal_rate_percent' => 4,
                    'annual_spending_override_egp' => null,
                ],
                'variance_thresholds' => [
                    'income_percent' => 10,
                    'expenses_percent' => 10,
                    'investment_minimum_percent' => 80,
                ],
                'auto_prepare_next_month' => false,
            ],
            'is_active' => true,
        ]);
        FinancialSetting::query()->where('name', '!=', 'Demo policy')->update(['is_active' => false]);
    }

    /** @return array<string, PlanTemplate> */
    private function seedPlanTemplates(): array
    {
        $rules = app(BudgetRuleService::class);
        $normal = $rules->ensureDefaultTemplate(self::DEMO_MONTHLY_SALARY);
        $normal->update(['description' => 'Standard month: protect the reserve, cover life, and invest the remaining cash.']);
        $normal->budgetRules()->where('direction', 'income')->withTrashed()->forceDelete();
        $normal->budgetRules()->create([
            'name' => 'Primary monthly income',
            'direction' => 'income',
            'amount_egp' => self::DEMO_MONTHLY_SALARY,
            'frequency' => 'monthly',
            'is_active' => true,
        ]);
        $normal->allocationRules()->withTrashed()->forceDelete();
        $assets = Asset::query()->get()->keyBy('name');
        $buckets = Bucket::query()->get()->keyBy('name');
        foreach ([
            ['asset' => 'Money market fund', 'bucket' => 'Emergency Reserve', 'percent' => 30],
            ['asset' => 'Gold holdings', 'bucket' => 'Home Office', 'percent' => 20],
            ['asset' => 'Long-term fixed income', 'bucket' => 'Family Car', 'percent' => 10],
            ['asset' => 'Long-term fixed income', 'bucket' => 'Home Renovation', 'percent' => 10],
            ['asset' => 'USD reserve', 'bucket' => 'Long-Term Investing', 'percent' => 30],
        ] as $index => $row) {
            $normal->allocationRules()->create(['asset_id' => $assets[$row['asset']]->id, 'bucket_id' => $buckets[$row['bucket']]->id, 'asset_target' => $assets[$row['asset']]->name, 'allocation_percent' => $row['percent'], 'sort_order' => $index, 'is_active' => true]);
        }

        PlanTemplate::withTrashed()
            ->where('name', 'like', '% priority')
            ->where('description', 'like', '%car%')
            ->where('name', '!=', 'Family car priority')
            ->get()->each(function (PlanTemplate $legacy): void {
                AllocationPlan::query()->where('plan_template_id', $legacy->id)->update(['plan_template_id' => null]);
                $legacy->allocationRules()->withTrashed()->forceDelete();
                $legacy->budgetRules()->withTrashed()->forceDelete();
                $legacy->forceDelete();
            });

        $priority = $this->freshDemoTemplate('Family car priority', 'A medium-term family-car template that keeps the reserve intact while directing more surplus to the car bucket.');
        foreach ($normal->budgetRules()->get() as $rule) {
            $copy = $rule->replicate(['id', 'created_at', 'updated_at']);
            $copy->plan_template_id = $priority->id;
            $copy->save();
        }
        foreach ($normal->allocationRules()->get() as $rule) {
            $copy = $rule->replicate(['id', 'created_at', 'updated_at']);
            $copy->plan_template_id = $priority->id;
            $copy->save();
        }
        $priority->allocationRules()->where('bucket_id', $buckets['Home Office']->id)->update(['allocation_percent' => 15]);
        $priority->allocationRules()->where('bucket_id', $buckets['Family Car']->id)->update(['allocation_percent' => 25]);
        $priority->allocationRules()->where('bucket_id', $buckets['Long-Term Investing']->id)->update(['allocation_percent' => 20]);

        $investment = $this->freshDemoTemplate('Investment-heavy', 'A long-term investing template for months without a near-term purchase priority.');
        foreach ($normal->budgetRules()->get() as $rule) {
            $copy = $rule->replicate(['id', 'created_at', 'updated_at']);
            $copy->plan_template_id = $investment->id;
            $copy->save();
        }
        $investment->allocationRules()->create(['asset_id' => $assets['USD reserve']->id, 'bucket_id' => $buckets['Long-Term Investing']->id, 'asset_target' => $assets['USD reserve']->name, 'allocation_percent' => 70, 'sort_order' => 0, 'is_active' => true]);
        $investment->allocationRules()->create(['asset_id' => $assets['Gold holdings']->id, 'bucket_id' => $buckets['Long-Term Investing']->id, 'asset_target' => $assets['Gold holdings']->name, 'allocation_percent' => 30, 'sort_order' => 1, 'is_active' => true]);

        return ['normal' => $normal, 'priority' => $priority, 'investment' => $investment];
    }

    private function freshDemoTemplate(string $name, string $description): PlanTemplate
    {
        $templates = PlanTemplate::withTrashed()->where('name', $name)->orderBy('id')->get();
        $template = $templates->first() ?? new PlanTemplate;

        foreach ($templates->skip(1) as $duplicate) {
            AllocationPlan::query()->where('plan_template_id', $duplicate->id)->update(['plan_template_id' => $template->id]);
            $duplicate->allocationRules()->withTrashed()->forceDelete();
            $duplicate->budgetRules()->withTrashed()->forceDelete();
            $duplicate->forceDelete();
        }

        $template->fill([
            'name' => $name,
            'description' => $description,
            'is_active' => true,
            'is_default' => false,
        ])->save();
        $template->restore();
        $template->budgetRules()->withTrashed()->forceDelete();
        $template->allocationRules()->withTrashed()->forceDelete();

        return $template->fresh();
    }

    /** @param array<string, Bucket> $buckets */
    private function seedTwelveMonths(array $buckets, Liability $educationLoan, PlanTemplate $template): void
    {
        $start = now()->startOfMonth()->subMonths(11);
        $salaryByMonth = array_fill(0, 12, self::DEMO_MONTHLY_SALARY);
        $salaryByMonth[8] = 48000;
        $essentialByMonth = array_fill(0, 12, 12000);
        $essentialByMonth[3] = 20000;
        $lifestyleByMonth = array_fill(0, 12, 3000);
        $lifestyleByMonth[6] = 5000;
        $oneTimeByMonth = array_fill(0, 12, 0);
        $oneTimeByMonth[3] = 8000;
        $investedByMonth = [7000, 7500, 8000, 0, 7000, 8000, 8000, 8000, 12000, 7000, 7000, 7000];
        $allocationByMonth = array_fill(0, 12, [
            'emergency' => 5000,
            'homeOffice' => 4000,
            'familyCar' => 2000,
            'homeRenovation' => 2000,
        ]);
        $allocationByMonth[3] = ['emergency' => 2000, 'homeOffice' => 1000, 'familyCar' => 500, 'homeRenovation' => 500];
        $allocationByMonth[8] = ['emergency' => 5000, 'homeOffice' => 5000, 'familyCar' => 4000, 'homeRenovation' => 2000];
        $netWorthByMonth = array_map(fn (int $index): int => 330000 + ($index * 22000), range(0, 11));
        $valuationAssets = Asset::query()->get()->keyBy('name');
        $valuationStarts = [
            'Current account reserve' => 30000,
            'Money market fund' => 70000,
            'USD reserve' => 85000,
            'Gold holdings' => 105000,
            'Egyptian equity fund' => 105000,
            'Long-term fixed income' => 160000,
        ];
        $account = Account::updateOrCreate(['name' => 'Demo current account'], [
            'type' => 'bank',
            'currency' => 'EGP',
            'opening_balance_egp' => 0,
            'is_active' => true,
            'notes' => 'Demo account used to demonstrate confirmed-ledger actual tracking.',
        ]);
        $categories = [];
        foreach (['salary' => 'income', 'essential' => 'expense', 'lifestyle' => 'expense', 'recurring commitments' => 'expense', 'one_time' => 'expense', 'fees' => 'expense'] as $name => $kind) {
            $categories[$name] = TransactionCategory::updateOrCreate(['name' => $name, 'kind' => $kind], ['is_system' => true]);
        }
        // Re-seeding replaces canonical demo rows and clears stale salary rows
        // from earlier demo versions, which prevents the dashboard from
        // counting an old salary alongside the current one.
        LedgerTransaction::forUser((int) $account->user_id)->withTrashed()
            ->whereBetween('occurred_on', [$start->toDateString(), now()->endOfMonth()->toDateString()])
            ->where(function ($query) use ($categories): void {
                $query->where('source', 'demo_seed')
                    ->orWhere(function ($query) use ($categories): void {
                        $query->where('transaction_type', 'income')->where('category_id', $categories['salary']->id);
                    });
            })
            ->forceDelete();
        CashFlow::withTrashed()->whereIn('notes', ['Demo salary income.', 'Demo monthly spending.'])->forceDelete();

        $loanBalance = 61370.00;
        LiabilityPaymentRecord::query()->where('liability_id', $educationLoan->id)->where('source', 'demo_statement')->forceDelete();
        LiabilityBalanceHistory::query()->where('liability_id', $educationLoan->id)->where('source', 'demo_statement')->forceDelete();
        AssetValuation::query()->where('source', 'demo_statement')->forceDelete();
        FxRate::query()->where('source', 'demo_market_mark')->forceDelete();

        for ($index = 0; $index < 12; $index++) {
            $month = $start->copy()->addMonths($index);
            $monthDate = $month->toDateString();
            $salaryAmount = $salaryByMonth[$index];
            $essentialAmount = $essentialByMonth[$index];
            $lifestyleAmount = $lifestyleByMonth[$index];
            $oneTimeAmount = $oneTimeByMonth[$index];
            $commitmentAmount = 2000;
            $debtAmount = 3000;
            $invested = $investedByMonth[$index];
            $allocation = $allocationByMonth[$index];
            $actualExpenses = $essentialAmount + $lifestyleAmount + $commitmentAmount + $debtAmount + $oneTimeAmount;

            $salary = CashFlow::withTrashed()->where('type', 'income')->where('category', 'salary')->whereDate('occurred_on', $monthDate)->first() ?? new CashFlow;
            $salary->fill([
                'type' => 'income', 'category' => 'salary', 'occurred_on' => $monthDate,
                'amount' => $salaryAmount,
                'amount_egp' => $salaryAmount,
                'currency' => 'EGP',
                'notes' => 'Demo salary income.',
            ]);
            $salary->save();
            $salary->restore();
            $cashFlowExpenses = [
                ['category' => 'essential', 'amount' => $essentialAmount, 'offset' => 3],
                ['category' => 'lifestyle', 'amount' => $lifestyleAmount, 'offset' => 8],
                ['category' => 'obligation', 'amount' => $commitmentAmount, 'offset' => 12],
                ['category' => 'debt', 'amount' => $debtAmount, 'offset' => 18],
            ];
            if ($oneTimeAmount > 0) {
                $cashFlowExpenses[] = ['category' => 'one_time', 'amount' => $oneTimeAmount, 'offset' => 22];
            }
            foreach ($cashFlowExpenses as $expense) {
                $occurredOn = $month->copy()->addDays($expense['offset'])->toDateString();
                $cashFlow = CashFlow::withTrashed()->where('type', 'expense')->where('category', $expense['category'])->whereDate('occurred_on', $occurredOn)->first() ?? new CashFlow;
                $cashFlow->fill([
                    'type' => 'expense',
                    'category' => $expense['category'],
                    'occurred_on' => $occurredOn,
                    'amount' => $expense['amount'],
                    'amount_egp' => $expense['amount'],
                    'currency' => 'EGP',
                    'notes' => 'Demo monthly spending.',
                ]);
                $cashFlow->save();
                $cashFlow->restore();
            }

            $review = MonthlyFinancialReview::withTrashed()->whereDate('month', $monthDate)->first() ?? new MonthlyFinancialReview;
            $review->month = $monthDate;
            $review->fill([
                'income_egp' => $salaryAmount,
                'essential_expenses_egp' => $essentialAmount,
                'lifestyle_expenses_egp' => $lifestyleAmount,
                'recurring_commitments_egp' => $commitmentAmount,
                'one_time_expenses_egp' => $oneTimeAmount,
                'debt_payments_egp' => $debtAmount,
                'invested_egp' => $invested,
                'status' => 'closed',
                'source_type' => 'confirmed_ledger',
                'source_transaction_count' => 10 + ($oneTimeAmount > 0 ? 1 : 0),
                'closed_at' => $month->isSameMonth(now()) ? now() : $month->copy()->endOfMonth(),
                'notes' => 'Demo twelve-month review. Replace with your actual month before relying on decisions.',
            ]);
            if ($index === 3) {
                $review->notes = 'Lesson: an 8,000 EGP one-time repair pushed expenses above plan, so investing was reduced temporarily instead of using the emergency reserve.';
            }
            if ($index === 8) {
                $review->notes = 'Lesson: a salary bonus created room to restore investing and keep the family-car goal moving without touching the emergency reserve.';
            }
            if ($month->isSameMonth(now())) {
                $review->notes = 'Lesson: subscriptions rose by 200 EGP after the last close; keep the emergency reserve complete and direct the next reserve-sized contribution to the home-office goal, family-car goal, or long-term investing.';
            }
            $review->save();
            $review->restore();
            $obligationSnapshot = $this->obligationSnapshot();
            if ($month->isSameMonth(now())) {
                // Keep one realistic lesson visible in the demo: the current
                // subscription price rose after the last review was closed.
                $obligationSnapshot['capturedAt'] = $month->copy()->subDays(3)->toIso8601String();
                foreach ($obligationSnapshot['commitments']['items'] as &$item) {
                    if ($item['name'] === 'Digital subscriptions') {
                        $item['monthlyAmount'] = 400;
                    }
                }
                unset($item);
                $obligationSnapshot['commitments']['configuredMonthly'] = round((float) $obligationSnapshot['commitments']['configuredMonthly'] - 200, 2);
            }
            $review->update([
                'obligation_snapshot' => $obligationSnapshot,
                'reconciliation_status' => 'matched',
                'reconciled_at' => now(),
            ]);

            $plan = AllocationPlan::withTrashed()->whereDate('month', $monthDate)->first() ?? new AllocationPlan;
            $plan->month = $monthDate;
            $plan->fill([
                'plan_template_id' => $template->id,
                'planned_income_egp' => self::DEMO_MONTHLY_SALARY,
                'planned_expenses_egp' => self::DEMO_PLANNED_EXPENSES,
                'source_review_id' => $index > 0 ? MonthlyFinancialReview::whereDate('month', $month->copy()->subMonth())->value('id') : null,
                'generation_method' => $index > 0 ? 'prepared_from_review' : 'manual',
                'generated_at' => $index > 0 ? $month->copy()->startOfMonth() : null,
                'status' => $month->isSameMonth(now()) ? 'open' : 'closed',
                'closed_at' => $month->isSameMonth(now()) ? null : $month->copy()->endOfMonth(),
                'notes' => 'Demo monthly allocation plan.',
            ]);
            $plan->save();
            $plan->restore();
            $incomeRule = $template->budgetRules()->where('direction', 'income')->where('is_active', true)->first();
            $plan->incomeItems()->updateOrCreate(['name' => 'Demo salary'], [
                'budget_rule_id' => $incomeRule?->id,
                'planned_amount_egp' => self::DEMO_MONTHLY_SALARY,
                'actual_amount_egp' => $salaryAmount,
                'actual_source' => 'demo_seed',
            ]);
            foreach ([
                ['bucket' => 'Emergency Reserve', 'asset' => 'Money market fund', 'amount' => $allocation['emergency']],
                ['bucket' => 'Home Office', 'asset' => 'Gold holdings', 'amount' => $allocation['homeOffice']],
                ['bucket' => 'Family Car', 'asset' => 'Long-term fixed income', 'amount' => $allocation['familyCar']],
                ['bucket' => 'Home Renovation', 'asset' => 'Long-term fixed income', 'amount' => $allocation['homeRenovation']],
                ['bucket' => 'Long-Term Investing', 'asset' => 'USD reserve', 'amount' => $invested],
            ] as $item) {
                $asset = Asset::query()->where('name', $item['asset'])->first();
                $plan->items()->updateOrCreate(['bucket_id' => $buckets[$item['bucket']]->id], [
                    'asset_id' => $asset?->id,
                    'asset_target' => $asset?->name,
                    'planned_amount_egp' => $item['amount'],
                    'actual_amount_egp' => $item['amount'],
                ]);
            }

            foreach (BudgetCategory::query()->where('is_active', true)->where('is_default', true)->get() as $category) {
                $planned = match (strtolower($category->name)) {
                    'essentials' => $essentialAmount,
                    'lifestyle' => $lifestyleAmount,
                    'commitments' => $commitmentAmount,
                    default => 0,
                };
                $plan->expenseItems()->updateOrCreate(['budget_category_id' => $category->id], ['planned_amount_egp' => $planned, 'actual_amount_egp' => $planned]);
            }

            foreach ([
                ['description' => 'Demo salary', 'type' => 'income', 'amount' => $salaryAmount, 'category' => 'salary'],
                ['description' => 'Demo essential spending', 'type' => 'expense', 'amount' => $essentialAmount, 'category' => 'essential'],
                ['description' => 'Demo lifestyle spending', 'type' => 'expense', 'amount' => $lifestyleAmount, 'category' => 'lifestyle'],
                ['description' => 'Demo recurring commitments', 'type' => 'expense', 'amount' => $commitmentAmount, 'category' => 'recurring commitments'],
                ['description' => 'Demo debt payment', 'type' => 'debt_payment', 'amount' => $debtAmount, 'category' => null],
                ...($oneTimeAmount > 0 ? [['description' => 'Demo one-time repair', 'type' => 'expense', 'amount' => $oneTimeAmount, 'category' => 'one_time']] : []),
                ['description' => 'Emergency reserve contribution', 'type' => 'contribution', 'amount' => $allocation['emergency'], 'category' => null, 'bucket' => 'Emergency Reserve'],
                ['description' => 'Home office goal contribution', 'type' => 'contribution', 'amount' => $allocation['homeOffice'], 'category' => null, 'bucket' => 'Home Office'],
                ['description' => 'Family car goal contribution', 'type' => 'contribution', 'amount' => $allocation['familyCar'], 'category' => null, 'bucket' => 'Family Car'],
                ['description' => 'Home renovation contribution', 'type' => 'contribution', 'amount' => $allocation['homeRenovation'], 'category' => null, 'bucket' => 'Home Renovation'],
                ['description' => 'Long-term investment contribution', 'type' => 'contribution', 'amount' => $invested, 'category' => null, 'bucket' => 'Long-Term Investing'],
            ] as $transaction) {
                $ledgerTransaction = LedgerTransaction::updateOrCreate([
                    'account_id' => $account->id,
                    'occurred_on' => $monthDate,
                    'description' => $transaction['description'],
                ], [
                    'category_id' => $transaction['category'] ? $categories[$transaction['category']]->id : null,
                    'purpose_bucket_id' => isset($transaction['bucket']) ? $buckets[$transaction['bucket']]->id : null,
                    'transaction_type' => $transaction['type'],
                    'amount' => $transaction['amount'],
                    'currency' => 'EGP',
                    'amount_egp' => $transaction['amount'],
                    'review_state' => 'confirmed',
                    'source' => 'demo_seed',
                    'reviewed_at' => now(),
                ]);
                $ledgerTransaction->restore();
            }
            app(AllocationActualService::class)->sync($plan->fresh());

            $balanceBeforePayment = $loanBalance;
            $interest = round($balanceBeforePayment * 14 / 100 / 12, 2);
            $principal = round(min($balanceBeforePayment, max(0, $debtAmount - $interest)), 2);
            $balanceAfterPayment = max(0, round($balanceBeforePayment - $principal, 2));
            LiabilityPaymentRecord::updateOrCreate([
                'liability_id' => $educationLoan->id,
                'paid_on' => $monthDate,
            ], [
                'payment_egp' => $debtAmount,
                'principal_egp' => $principal,
                'interest_egp' => $interest,
                'fees_egp' => 0,
                'balance_after_egp' => $balanceAfterPayment,
                'source' => 'demo_statement',
                'notes' => 'Demo lender statement allocation.',
            ]);
            LiabilityBalanceHistory::updateOrCreate([
                'liability_id' => $educationLoan->id,
                'as_of' => $monthDate,
            ], [
                'balance_egp' => $balanceAfterPayment,
                'source' => 'demo_statement',
                'notes' => 'Demo balance history after the scheduled payment.',
            ]);
            $loanBalance = $balanceAfterPayment;

            $snapshotDate = $month->isSameMonth(now())
                ? now()->toDateString()
                : $month->copy()->endOfMonth()->toDateString();
            $snapshot = Snapshot::withTrashed()->whereDate('as_of', $snapshotDate)->first() ?? new Snapshot;
            $snapshot->as_of = $snapshotDate;
            $snapshot->fill([
                'net_worth_egp' => $netWorthByMonth[$index],
                'liquid_assets_egp' => 120000,
                'investable_net_worth_egp' => max(0, $netWorthByMonth[$index] - 120000),
                'income_egp' => $salaryAmount,
                'expenses_egp' => $actualExpenses,
                'free_cash_flow_egp' => $salaryAmount - $actualExpenses,
                'emergency_coverage_months' => 6,
                'asset_breakdown' => [],
                'notes' => 'Demo history checkpoint for the twelve-month learning workspace.',
                'capture_basis' => 'demo_history',
                'historical_source' => 'demo_seed',
                'captured_at' => $month->isSameMonth(now()) ? now() : $month->copy()->endOfMonth(),
            ]);
            $snapshot->save();
            $snapshot->restore();

            foreach ($valuationStarts as $assetName => $startingValue) {
                $asset = $valuationAssets->get($assetName);
                if (! $asset) {
                    continue;
                }
                $value = round($startingValue + (((float) $asset->current_value_egp - $startingValue) * ($index / 11)), 2);
                AssetValuation::updateOrCreate([
                    'asset_id' => $asset->id,
                    'valued_on' => $snapshotDate,
                ], [
                    'value_egp' => $value,
                    'quantity' => $asset->quantity,
                    'currency' => $asset->currency === 'Gold' ? 'EGP' : $asset->currency,
                    'source' => 'demo_statement',
                    'valuation_method' => 'monthly_mark',
                    'notes' => 'Demo dated valuation for learning how net-worth history is sourced.',
                ]);
            }
            FxRate::updateOrCreate([
                'base_currency' => 'USD',
                'quote_currency' => 'EGP',
                'rate_date' => $snapshotDate,
                'source' => 'demo_market_mark',
            ], [
                'rate' => round(45 + ($index * 5 / 11), 4),
                'method' => 'closing',
                'notes' => 'Demo FX rate used to explain currency conversion; replace with a trusted source.',
            ]);
        }

        $educationLoan->update(['balance_egp' => $loanBalance]);
        $account->update(['reported_balance_egp' => $account->fresh()->ledgerBalance(), 'reported_balance_as_of' => now()->toDateString()]);
        $this->seedImportExample($account, $categories);
    }

    /** @param array<string, TransactionCategory> $categories */
    private function seedImportExample(Account $account, array $categories): void
    {
        $batch = ImportBatch::withTrashed()->where('file_name', 'demo-bank-statement.csv')->first() ?? new ImportBatch;
        $batch->fill([
            'file_name' => 'demo-bank-statement.csv',
            'source' => 'demo_seed',
            'status' => 'review',
            'metadata' => ['no_silent_posting' => true, 'learning_example' => true],
            'notes' => 'Demo import kept in review so the user can learn why imported rows are not posted automatically.',
        ]);
        $batch->save();
        $batch->restore();
        $batch->rows()->withTrashed()->forceDelete();

        $pending = ImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'raw_data' => ['date' => now()->startOfMonth()->addDays(4)->toDateString(), 'description' => 'Demo bank fee', 'amount' => '250.00'],
            'fingerprint' => hash('sha256', 'demo-bank-fee'),
            'account_id' => $account->id,
            'category_id' => $categories['fees']->id,
            'occurred_on' => now()->startOfMonth()->addDays(4)->toDateString(),
            'description' => 'Demo bank fee',
            'amount' => 250,
            'amount_egp' => 250,
            'currency' => 'EGP',
            'transaction_type' => 'expense',
            'review_state' => 'pending',
            'review_notes' => 'Decide whether this is a real fee before accepting it into actual spending.',
        ]);

        ImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => 2,
            'raw_data' => ['date' => now()->startOfMonth()->addDays(4)->toDateString(), 'description' => 'Demo bank fee duplicate', 'amount' => '250.00'],
            'fingerprint' => hash('sha256', 'demo-bank-fee-duplicate'),
            'duplicate_of_id' => $pending->id,
            'account_id' => $account->id,
            'category_id' => $categories['fees']->id,
            'occurred_on' => now()->startOfMonth()->addDays(4)->toDateString(),
            'description' => 'Demo bank fee duplicate',
            'amount' => 250,
            'amount_egp' => 250,
            'currency' => 'EGP',
            'transaction_type' => 'expense',
            'review_state' => 'duplicate',
            'review_notes' => 'Demo duplicate row: reject it instead of posting twice.',
        ]);

        $batch->refreshCounts();
    }

    /** @return array<string, mixed> */
    private function obligationSnapshot(): array
    {
        $commitments = RecurringCommitment::where('is_active', true)->orderBy('name')->get();
        $liabilities = Liability::where('is_active', true)->orderBy('name')->get();

        return [
            'capturedAt' => now()->toIso8601String(),
            'commitments' => [
                'configuredMonthly' => round((float) $commitments->sum(fn (RecurringCommitment $commitment): float => $commitment->monthlyAmount()), 2),
                'items' => $commitments->map(fn (RecurringCommitment $commitment): array => [
                    'id' => $commitment->id,
                    'name' => $commitment->name,
                    'monthlyAmount' => $commitment->monthlyAmount(),
                ])->values()->all(),
            ],
            'liabilities' => [
                'configuredMonthlyPayments' => round((float) $liabilities->sum(fn (Liability $liability): float => (float) $liability->monthly_payment_egp), 2),
                'items' => $liabilities->map(fn (Liability $liability): array => [
                    'id' => $liability->id,
                    'name' => $liability->name,
                    'balance' => (float) $liability->balance_egp,
                    'monthlyPayment' => (float) $liability->monthly_payment_egp,
                ])->values()->all(),
            ],
        ];
    }
}
