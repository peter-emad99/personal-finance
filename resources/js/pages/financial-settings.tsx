import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Target = { min: number; max: number; target: number };
type MonthlyAllocationTargets = Record<string, number>;
type FinancialFreedom = {
    withdrawal_rate_percent?: number | string;
    annual_spending_override_egp?: number | string | null;
};
type VarianceThresholds = {
    income_percent?: number | string;
    expenses_percent?: number | string;
    investment_minimum_percent?: number | string;
};
type Setting = {
    id: number;
    name: string;
    base_currency: string;
    emergency_reserve_months: number;
    emergency_eligible_liquidity: 'immediate' | 'within_3_days';
    asset_class_targets?: Record<string, Target> | null;
    rebalancing_tolerance_percent?: number | string;
    goal_funding_policy?: 'priority_order' | 'manual_contributions';
    minimum_cash_after_purchase_egp?: number | string;
    maximum_monthly_payment_egp?: number | string | null;
    maximum_debt_burden_percent?: number | string | null;
    valuation_freshness_days?: number;
    policy?: {
        monthly_allocation_targets?: MonthlyAllocationTargets;
        financial_freedom?: FinancialFreedom;
        variance_thresholds?: VarianceThresholds;
        auto_prepare_next_month?: boolean;
    } | null;
};

const defaultMonthlyAllocationTargets: MonthlyAllocationTargets = {
    essentials: 35,
    lifestyle: 15,
    debt: 10,
    emergency: 10,
    goals: 15,
    investing: 15,
};

const monthlyAllocationLabels: Record<string, string> = {
    essentials: 'Essentials & commitments',
    lifestyle: 'Lifestyle & one-time',
    debt: 'Debt payments',
    emergency: 'Emergency fund',
    goals: 'Goals',
    investing: 'Investments',
};

export default function FinancialSettings({
    settings,
    defaults,
}: {
    settings: Setting[];
    defaults: Record<string, Target>;
}) {
    const active = settings[0];
    const targets = active?.asset_class_targets ?? defaults;
    const configuredPolicy = active?.policy ?? {};
    const [form, setForm] = useState({
        name: active?.name ?? 'Default policy',
        base_currency: active?.base_currency ?? 'EGP',
        emergency_reserve_months: String(active?.emergency_reserve_months ?? 6),
        emergency_eligible_liquidity:
            active?.emergency_eligible_liquidity ?? 'within_3_days',
        rebalancing_tolerance_percent: String(
            active?.rebalancing_tolerance_percent ?? 5,
        ),
        goal_funding_policy: active?.goal_funding_policy ?? 'priority_order',
        minimum_cash_after_purchase_egp: String(
            active?.minimum_cash_after_purchase_egp ?? 0,
        ),
        maximum_monthly_payment_egp: String(
            active?.maximum_monthly_payment_egp ?? '',
        ),
        maximum_debt_burden_percent: String(
            active?.maximum_debt_burden_percent ?? '',
        ),
        valuation_freshness_days: String(
            active?.valuation_freshness_days ?? 30,
        ),
        asset_class_targets: targets,
        policy: {
            monthly_allocation_targets: {
                ...defaultMonthlyAllocationTargets,
                ...(configuredPolicy.monthly_allocation_targets ?? {}),
            },
            financial_freedom: {
                withdrawal_rate_percent:
                    configuredPolicy.financial_freedom
                        ?.withdrawal_rate_percent ?? 4,
                annual_spending_override_egp:
                    configuredPolicy.financial_freedom
                        ?.annual_spending_override_egp ?? '',
            },
            variance_thresholds: {
                income_percent:
                    configuredPolicy.variance_thresholds?.income_percent ?? 10,
                expenses_percent:
                    configuredPolicy.variance_thresholds?.expenses_percent ?? 10,
                investment_minimum_percent:
                    configuredPolicy.variance_thresholds?.investment_minimum_percent ?? 80,
            },
            auto_prepare_next_month:
                configuredPolicy.auto_prepare_next_month ?? false,
        },
    });
    const [feedback, setFeedback] = useState<string | null>(null);
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const updateTarget = (name: string, key: keyof Target, value: string) =>
        setForm((current) => ({
            ...current,
            asset_class_targets: {
                ...current.asset_class_targets,
                [name]: {
                    ...current.asset_class_targets[name],
                    [key]: Number(value || 0),
                },
            },
        }));
    const updateAutoPrepare = (checked: boolean) =>
        setForm((current) => ({
            ...current,
            policy: { ...current.policy, auto_prepare_next_month: checked },
        }));
    const updateMonthlyTarget = (key: string, value: string) =>
        setForm((current) => ({
            ...current,
            policy: {
                ...current.policy,
                monthly_allocation_targets: {
                    ...current.policy.monthly_allocation_targets,
                    [key]: Number(value || 0),
                },
            },
        }));
    const updateFreedom = (key: keyof FinancialFreedom, value: string) =>
        setForm((current) => ({
            ...current,
            policy: {
                ...current.policy,
                financial_freedom: {
                    ...current.policy.financial_freedom,
                    [key]: value,
                },
            },
        }));
    const updateVarianceThreshold = (
        key: keyof VarianceThresholds,
        value: string,
    ) =>
        setForm((current) => ({
            ...current,
            policy: {
                ...current.policy,
                variance_thresholds: {
                    ...current.policy.variance_thresholds,
                    [key]: value,
                },
            },
        }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const data = {
            ...form,
            emergency_reserve_months: Number(form.emergency_reserve_months),
            rebalancing_tolerance_percent: Number(
                form.rebalancing_tolerance_percent,
            ),
            minimum_cash_after_purchase_egp: Number(
                form.minimum_cash_after_purchase_egp,
            ),
            maximum_monthly_payment_egp: form.maximum_monthly_payment_egp
                ? Number(form.maximum_monthly_payment_egp)
                : null,
            maximum_debt_burden_percent: form.maximum_debt_burden_percent
                ? Number(form.maximum_debt_burden_percent)
                : null,
            valuation_freshness_days: Number(form.valuation_freshness_days),
            policy: {
                monthly_allocation_targets:
                    form.policy.monthly_allocation_targets,
                financial_freedom: {
                    withdrawal_rate_percent: Number(
                        form.policy.financial_freedom
                            .withdrawal_rate_percent || 4,
                    ),
                    annual_spending_override_egp:
                        form.policy.financial_freedom
                            .annual_spending_override_egp
                            ? Number(
                                  form.policy.financial_freedom
                                      .annual_spending_override_egp,
                              )
                            : null,
                },
                variance_thresholds: {
                    income_percent: Number(
                        form.policy.variance_thresholds.income_percent || 10,
                    ),
                    expenses_percent: Number(
                        form.policy.variance_thresholds.expenses_percent || 10,
                    ),
                    investment_minimum_percent: Number(
                        form.policy.variance_thresholds
                        .investment_minimum_percent || 80,
                    ),
                },
                auto_prepare_next_month:
                    form.policy.auto_prepare_next_month,
            },
        };
        const url = active
            ? `/settings/financial/${active.id}`
            : '/settings/financial';
        router[active ? 'put' : 'post'](url, data, {
            onSuccess: () => setFeedback('Financial policy saved.'),
            onError: () =>
                setFeedback(
                    'The policy could not be saved. Check the ranges and thresholds.',
                ),
        });
    };

    return (
        <AppShell title="Financial policy">
            <PageHeader
                eyebrow="Calculation rules"
                title="Financial policy"
                description="Configure the reserve, allocation, affordability, and freshness rules shown beside every dashboard decision."
            />
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Card>
                    <CardHeader
                        title="Liquidity and emergency reserve"
                        meta="Used for safe-to-use and emergency calculations"
                    />
                    <div className="p-5">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="policy-name">
                                    Policy name
                                </FieldLabel>
                                <Input
                                    id="policy-name"
                                    value={form.name}
                                    onChange={(event) =>
                                        update('name', event.target.value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="policy-currency">
                                    Base currency
                                </FieldLabel>
                                <Input
                                    id="policy-currency"
                                    value={form.base_currency}
                                    onChange={(event) =>
                                        update(
                                            'base_currency',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    maxLength={8}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="policy-months">
                                    Emergency reserve (months)
                                </FieldLabel>
                                <Input
                                    id="policy-months"
                                    type="number"
                                    min={1}
                                    max={36}
                                    value={form.emergency_reserve_months}
                                    onChange={(event) =>
                                        update(
                                            'emergency_reserve_months',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="policy-liquidity">
                                    Emergency-eligible liquidity
                                </FieldLabel>
                                <Select
                                    value={form.emergency_eligible_liquidity}
                                    onValueChange={(value) =>
                                        update(
                                            'emergency_eligible_liquidity',
                                            String(value ?? 'within_3_days'),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="policy-liquidity"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="immediate">
                                                Immediate only
                                            </SelectItem>
                                            <SelectItem value="within_3_days">
                                                Immediate + within 3 days
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        </FieldGroup>
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Monthly close automation"
                        meta="Optional safe shortcut"
                    />
                    <div className="p-5">
                        <FieldGroup>
                            <Field orientation="horizontal">
                                <Checkbox
                                    id="auto-prepare-next-month"
                                    checked={form.policy.auto_prepare_next_month}
                                    onCheckedChange={(checked) =>
                                        updateAutoPrepare(checked === true)
                                    }
                                />
                                <FieldLabel htmlFor="auto-prepare-next-month">
                                    Prepare the next month automatically when I close a review
                                </FieldLabel>
                            </Field>
                        </FieldGroup>
                        <p className="mt-3 text-xs text-muted-foreground">
                            This only creates a plan when the next month has no plan. It never replaces an existing plan. Keep it off if you prefer to review the proposal first.
                        </p>
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Dashboard warning thresholds"
                        meta="Personal prompts, not universal financial rules"
                    />
                    <div className="p-5">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="income-variance-threshold">
                                    Warn when income differs by at least (%)
                                </FieldLabel>
                                <Input
                                    id="income-variance-threshold"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.1"
                                    value={form.policy.variance_thresholds.income_percent}
                                    onChange={(event) =>
                                        updateVarianceThreshold(
                                            'income_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="expense-variance-threshold">
                                    Warn when outflow differs by at least (%)
                                </FieldLabel>
                                <Input
                                    id="expense-variance-threshold"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.1"
                                    value={form.policy.variance_thresholds.expenses_percent}
                                    onChange={(event) =>
                                        updateVarianceThreshold(
                                            'expenses_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="investment-minimum-threshold">
                                    Warn below this share of target (%)
                                </FieldLabel>
                                <Input
                                    id="investment-minimum-threshold"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.1"
                                    value={form.policy.variance_thresholds.investment_minimum_percent}
                                    onChange={(event) =>
                                        updateVarianceThreshold(
                                            'investment_minimum_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </FieldGroup>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Example: 80% means the dashboard warns when your actual investment pace is below 80% of the configured monthly investing target.
                        </p>
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Monthly allocation rules"
                        meta="Your starting targets for every 100 EGP of income"
                    />
                    <div className="p-5">
                        <FieldGroup>
                            {Object.entries(
                                form.policy.monthly_allocation_targets,
                            ).map(([key, value]) => (
                                <Field key={key}>
                                    <FieldLabel htmlFor={`monthly-${key}`}>
                                        {monthlyAllocationLabels[key] ?? key}
                                    </FieldLabel>
                                    <Input
                                        id={`monthly-${key}`}
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.1"
                                        value={value}
                                        onChange={(event) =>
                                            updateMonthlyTarget(
                                                key,
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            ))}
                        </FieldGroup>
                        <p className="mt-3 text-xs text-muted-foreground">
                            These are personal rules, not universal advice. The
                            six values must add up to 100%.
                        </p>
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Financial freedom assumptions"
                        meta="Used for the planning estimate on the dashboard"
                    />
                    <div className="p-5">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="withdrawal-rate">
                                    Withdrawal-rate assumption (%)
                                </FieldLabel>
                                <Input
                                    id="withdrawal-rate"
                                    type="number"
                                    min={1}
                                    max={10}
                                    step="0.1"
                                    value={
                                        form.policy.financial_freedom
                                            .withdrawal_rate_percent
                                    }
                                    onChange={(event) =>
                                        updateFreedom(
                                            'withdrawal_rate_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="annual-spending">
                                    Annual spending override (EGP)
                                </FieldLabel>
                                <Input
                                    id="annual-spending"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={
                                        form.policy.financial_freedom
                                            .annual_spending_override_egp ?? ''
                                    }
                                    onChange={(event) =>
                                        updateFreedom(
                                            'annual_spending_override_egp',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Use current monthly spending"
                                />
                            </Field>
                        </FieldGroup>
                        <p className="mt-3 text-xs text-muted-foreground">
                            The dashboard uses annual spending divided by this
                            assumption. It is a scenario, not a guarantee.
                        </p>
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Asset-class allocation ranges"
                        meta="Minimum ≤ target ≤ maximum; ranges must contain 100%"
                    />
                    <div className="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                        {Object.entries(form.asset_class_targets).map(
                            ([name, target]) => (
                                <div
                                    key={name}
                                    className="rounded-xl border border-border p-4"
                                >
                                    <p className="mb-3 text-sm font-semibold text-foreground">
                                        {name}
                                    </p>
                                    <div className="grid grid-cols-3 gap-2">
                                        {(
                                            ['min', 'target', 'max'] as const
                                        ).map((key) => (
                                            <Field key={key}>
                                                <FieldLabel
                                                    htmlFor={`${name}-${key}`}
                                                >
                                                    {key}
                                                </FieldLabel>
                                                <Input
                                                    id={`${name}-${key}`}
                                                    type="number"
                                                    min={0}
                                                    max={100}
                                                    step="0.1"
                                                    value={target[key]}
                                                    onChange={(event) =>
                                                        updateTarget(
                                                            name,
                                                            key,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </Field>
                                        ))}
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Decision guardrails"
                        meta="Rules surfaced in purchase results and goal planning"
                    />
                    <div className="p-5">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="tolerance">
                                    Rebalancing tolerance (%)
                                </FieldLabel>
                                <Input
                                    id="tolerance"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.1"
                                    value={form.rebalancing_tolerance_percent}
                                    onChange={(event) =>
                                        update(
                                            'rebalancing_tolerance_percent',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="goal-policy">
                                    Goal funding policy
                                </FieldLabel>
                                <Select
                                    value={form.goal_funding_policy}
                                    onValueChange={(value) =>
                                        update(
                                            'goal_funding_policy',
                                            String(value ?? 'priority_order'),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="goal-policy"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="priority_order">
                                                Fund goals by priority
                                            </SelectItem>
                                            <SelectItem value="manual_contributions">
                                                Use assigned contributions
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="minimum-cash">
                                    Minimum cash after purchase (EGP)
                                </FieldLabel>
                                <Input
                                    id="minimum-cash"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.minimum_cash_after_purchase_egp}
                                    onChange={(event) =>
                                        update(
                                            'minimum_cash_after_purchase_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="maximum-payment">
                                    Maximum monthly payment (EGP)
                                </FieldLabel>
                                <Input
                                    id="maximum-payment"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.maximum_monthly_payment_egp}
                                    onChange={(event) =>
                                        update(
                                            'maximum_monthly_payment_egp',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="No limit"
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="maximum-debt">
                                    Maximum debt burden (%)
                                </FieldLabel>
                                <Input
                                    id="maximum-debt"
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.1"
                                    value={form.maximum_debt_burden_percent}
                                    onChange={(event) =>
                                        update(
                                            'maximum_debt_burden_percent',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="No limit"
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="valuation-freshness">
                                    Manual valuation freshness (days)
                                </FieldLabel>
                                <Input
                                    id="valuation-freshness"
                                    type="number"
                                    min={1}
                                    value={form.valuation_freshness_days}
                                    onChange={(event) =>
                                        update(
                                            'valuation_freshness_days',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </FieldGroup>
                    </div>
                </Card>
                {feedback && (
                    <p role="status" className="text-sm text-muted-foreground">
                        {feedback}
                    </p>
                )}
                <div className="flex justify-end">
                    <Button type="submit">Save policy</Button>
                </div>
            </form>
        </AppShell>
    );
}
