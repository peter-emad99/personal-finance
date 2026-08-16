import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { Field } from '@/components/form';
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

export default function Allocations({
    month,
    plan,
    buckets,
}: {
    month: string;
    plan: Plan;
    buckets: Bucket[];
}) {
    const initialItems =
        plan?.items ??
        buckets.map((bucket) => ({
            bucketId: bucket.id,
            bucketName: bucket.name,
            planned: 0,
            actual: 0,
        }));
    const [income, setIncome] = useState(String(plan?.income ?? 140000));
    const [expenses, setExpenses] = useState(String(plan?.expenses ?? 15000));
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
                            meta={new Date(month).toLocaleDateString('en-EG', {
                                month: 'long',
                                year: 'numeric',
                            })}
                        />
                        <div className="space-y-4 p-5">
                            <Field
                                label="Planned income (EGP)"
                                type="number"
                                value={income}
                                onChange={(e) => setIncome(e.target.value)}
                            />
                            <Field
                                label="Planned expenses (EGP)"
                                type="number"
                                value={expenses}
                                onChange={(e) => setExpenses(e.target.value)}
                            />
                            <div className="rounded-2xl bg-[#1c2a45] p-5 text-white">
                                <p className="text-xs text-[#aeb9d1]">
                                    Available to allocate
                                </p>
                                <p className="mt-2 text-3xl font-semibold">
                                    {formatEGP(available)}
                                </p>
                                <p className="mt-2 text-xs leading-5 text-[#aeb9d1]">
                                    Income − expenses − obligations. This is the
                                    ceiling for your monthly plan.
                                </p>
                            </div>
                            <div className="rounded-xl bg-[#f8f9fb] p-4">
                                <div className="flex justify-between text-xs">
                                    <span className="font-semibold text-[#58657a]">
                                        Plan coverage
                                    </span>
                                    <span className="font-semibold text-[#4d5a6d]">
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
                                                ? '#e39b53'
                                                : '#63bf85'
                                        }
                                    />
                                </div>
                                <p className="mt-2 text-[11px] text-[#8993a3]">
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
                        <div className="divide-y divide-[#eef0f4]">
                            {items.map((item, index) => (
                                <div
                                    key={item.bucketId}
                                    className="grid items-center gap-3 p-4 sm:grid-cols-[1fr_150px_150px]"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-[#4d5a6d]">
                                            {item.bucketName}
                                        </p>
                                        <p className="mt-1 text-xs text-[#9aa3b1]">
                                            {item.actual
                                                ? `${Math.round((item.actual / Math.max(1, item.planned)) * 100)}% of plan moved`
                                                : 'No actual entered yet'}
                                        </p>
                                    </div>
                                    <Field
                                        label="Planned"
                                        type="number"
                                        value={String(item.planned)}
                                        onChange={(e) =>
                                            setItems((current) =>
                                                current.map((row, rowIndex) =>
                                                    rowIndex === index
                                                        ? {
                                                              ...row,
                                                              planned: Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                          }
                                                        : row,
                                                ),
                                            )
                                        }
                                    />
                                    <Field
                                        label="Actual"
                                        type="number"
                                        value={String(item.actual)}
                                        onChange={(e) =>
                                            setItems((current) =>
                                                current.map((row, rowIndex) =>
                                                    rowIndex === index
                                                        ? {
                                                              ...row,
                                                              actual: Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                          }
                                                        : row,
                                                ),
                                            )
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="flex justify-end border-t border-[#eef0f4] p-5">
                            <Button type="submit">Save monthly plan</Button>
                        </div>
                    </Card>
                </div>
            </form>
        </AppShell>
    );
}
