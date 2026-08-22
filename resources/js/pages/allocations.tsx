import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { formatCompactEGP, formatEGP } from '@/types/finance';
import type { Bucket } from '@/types/finance';

type Item = {
    bucketId: number;
    bucketName: string;
    planned: number;
    actual: number;
};
type Plan = {
    id: number;
    income: number;
    expenses: number;
    items: Item[];
} | null;
type Defaults = {
    income: number;
    expenses: number;
    source: 'saved_plan' | 'starter_template';
    items: {
        bucketId?: number | null;
        label: string;
        amount: number;
        actual: number;
    }[];
};

export default function Allocations({
    month,
    plan,
    buckets,
    defaults,
}: {
    month: string;
    plan: Plan;
    buckets: Bucket[];
    defaults: Defaults;
}) {
    const suggestedByBucket = new Map(
        defaults.items
            .filter((item) => item.bucketId)
            .map((item) => [item.bucketId, item]),
    );
    const initialItems =
        plan?.items ??
        buckets.map((bucket) => {
            const suggestion = suggestedByBucket.get(bucket.id);

            return {
                bucketId: bucket.id,
                bucketName: bucket.name,
                planned: suggestion?.amount ?? 0,
                actual: suggestion?.actual ?? 0,
            };
        });
    const [income, setIncome] = useState(
        String(plan?.income ?? defaults.income),
    );
    const [expenses, setExpenses] = useState(
        String(plan?.expenses ?? defaults.expenses),
    );
    const [items, setItems] = useState(initialItems);
    const plannedTotal = useMemo(
        () => items.reduce((sum, item) => sum + Number(item.planned || 0), 0),
        [items],
    );
    const available = Number(income || 0) - Number(expenses || 0);
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/allocations', {
            month,
            planned_income_egp: income,
            planned_expenses_egp: expenses,
            items: items.map((item) => ({
                bucket_id: item.bucketId,
                planned_amount_egp: item.planned,
                actual_amount_egp: item.actual,
            })),
        });
    };

    return (
        <AppShell title="Allocations">
            <PageHeader
                eyebrow="Give every pound a job"
                title="Monthly allocation"
                description="Plan the month before it happens, then compare the plan with what actually moved into each bucket."
            />
            <form onSubmit={submit}>
                <div className="grid gap-4 xl:grid-cols-[1fr_1.35fr]">
                    <Card>
                        <CardHeader
                            title="Monthly inputs"
                            meta={`${new Date(month).toLocaleDateString(
                                'en-EG',
                                {
                                    month: 'long',
                                    year: 'numeric',
                                },
                            )} · ${plan ? 'Saved plan' : 'Starter template'}`}
                        />
                        <div className="flex flex-col gap-4 p-5">
                            <Field>
                                <FieldLabel htmlFor="planned-income">
                                    Planned income (EGP)
                                </FieldLabel>
                                <Input
                                    id="planned-income"
                                    type="number"
                                    value={income}
                                    onChange={(e) => setIncome(e.target.value)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="planned-expenses">
                                    Planned expenses (EGP)
                                </FieldLabel>
                                <Input
                                    id="planned-expenses"
                                    type="number"
                                    value={expenses}
                                    onChange={(e) =>
                                        setExpenses(e.target.value)
                                    }
                                />
                            </Field>
                            <div className="rounded-2xl bg-sidebar p-5 text-sidebar-foreground">
                                <p className="text-xs text-sidebar-foreground/70">
                                    Available to allocate
                                </p>
                                <p className="mt-2 text-3xl font-semibold">
                                    {formatEGP(available)}
                                </p>
                                <p className="mt-2 text-xs leading-5 text-sidebar-foreground/70">
                                    Income − expenses − obligations. This is the
                                    ceiling for your monthly plan.
                                </p>
                            </div>
                            <div className="rounded-xl bg-muted p-4">
                                <div className="flex justify-between text-xs">
                                    <span className="font-semibold text-muted-foreground">
                                        Plan coverage
                                    </span>
                                    <span className="font-semibold text-muted-foreground">
                                        {formatCompactEGP(plannedTotal)} /{' '}
                                        {formatCompactEGP(available)}
                                    </span>
                                </div>
                                <div className="mt-3">
                                    <Progress
                                        value={
                                            available > 0
                                                ? (plannedTotal / available) *
                                                  100
                                                : 0
                                        }
                                        color={
                                            plannedTotal > available
                                                ? 'var(--chart-3)'
                                                : 'var(--chart-2)'
                                        }
                                    />
                                </div>
                                <p className="mt-2 text-[11px] text-muted-foreground">
                                    {plannedTotal > available
                                        ? `${formatCompactEGP(plannedTotal - available)} over your available cash flow`
                                        : `${formatCompactEGP(Math.max(0, available - plannedTotal))} still unassigned`}
                                </p>
                            </div>
                        </div>
                    </Card>
                    <Card>
                        <CardHeader
                            title="Planned vs actual"
                            meta="Use buckets instead of vague savings categories"
                        />
                        {!plan && (
                            <div className="px-5">
                                <Badge variant="secondary">
                                    Suggested 20% safety · 30% goals · remainder
                                    investing
                                </Badge>
                            </div>
                        )}
                        <div className="divide-y divide-border">
                            {items.map((item, index) => (
                                <div
                                    key={item.bucketId}
                                    className="grid items-center gap-3 p-4 sm:grid-cols-[1fr_150px_150px]"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-muted-foreground">
                                            {item.bucketName}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {item.actual
                                                ? `${Math.round((item.actual / Math.max(1, item.planned)) * 100)}% of plan moved`
                                                : `${available > 0 ? Math.round((item.planned / available) * 100) : 0}% of available cash · no actual entered yet`}
                                        </p>
                                    </div>
                                    <Field>
                                        <FieldLabel
                                            htmlFor={`planned-${item.bucketId}`}
                                        >
                                            Planned
                                        </FieldLabel>
                                        <Input
                                            id={`planned-${item.bucketId}`}
                                            type="number"
                                            value={String(item.planned)}
                                            onChange={(e) =>
                                                setItems((current) =>
                                                    current.map(
                                                        (row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      planned:
                                                                          Number(
                                                                              e
                                                                                  .target
                                                                                  .value,
                                                                          ),
                                                                  }
                                                                : row,
                                                    ),
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel
                                            htmlFor={`actual-${item.bucketId}`}
                                        >
                                            Actual
                                        </FieldLabel>
                                        <Input
                                            id={`actual-${item.bucketId}`}
                                            type="number"
                                            value={String(item.actual)}
                                            onChange={(e) =>
                                                setItems((current) =>
                                                    current.map(
                                                        (row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      actual: Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      ),
                                                                  }
                                                                : row,
                                                    ),
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                            ))}
                        </div>
                        <div className="flex justify-end border-t border-border p-5">
                            <Button type="submit">Save monthly plan</Button>
                        </div>
                    </Card>
                </div>
            </form>
        </AppShell>
    );
}
