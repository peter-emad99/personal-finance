import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    EmptyState,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { Field, FormModal } from '@/components/form';
import { formatCompactEGP, formatEGP } from '@/types/finance';
import type { Bucket, Goal } from '@/types/finance';

export default function Goals({
    goals,
    buckets,
}: {
    goals: Goal[];
    buckets: Bucket[];
}) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        name: '',
        target_amount_egp: '',
        deadline: '2026-12-31',
        priority: '1',
        notes: '',
    });
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/goals', form, {
            onSuccess: () => {
                setOpen(false);
                setForm({
                    name: '',
                    target_amount_egp: '',
                    deadline: '2026-12-31',
                    priority: '1',
                    notes: '',
                });
            },
        });
    };

    return (
        <AppShell title="Goals">
            <PageHeader
                eyebrow="Money with a job"
                title="Goals"
                description="Give near-term money a clear destination so it does not get mistaken for long-term investment capital."
                action={
                    <Button onClick={() => setOpen(true)}>+ Add goal</Button>
                }
            />
            <div className="grid gap-4 xl:grid-cols-[1.4fr_0.9fr]">
                <Card>
                    <CardHeader
                        title="Active goals"
                        meta="Funding progress and required pace"
                    />
                    {goals.length ? (
                        <div className="divide-y divide-[#eef0f4]">
                            {goals.map((goal) => (
                                <div key={goal.id} className="p-5">
                                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h2 className="text-base font-semibold text-[#273246]">
                                                    {goal.name}
                                                </h2>
                                                <span
                                                    className={`rounded-full px-2 py-1 text-[10px] font-bold ${goal.onTrack ? 'bg-[#eaf8ef] text-[#328654]' : 'bg-[#fff1e8] text-[#b76a2b]'}`}
                                                >
                                                    {goal.onTrack
                                                        ? 'ON TRACK'
                                                        : 'OFF TRACK'}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-xs text-[#8993a3]">
                                                {goal.deadline
                                                    ? `Deadline ${new Date(goal.deadline).toLocaleDateString('en-EG', { day: 'numeric', month: 'short', year: 'numeric' })}`
                                                    : 'No deadline'}{' '}
                                                · {goal.monthsRemaining ?? '—'}{' '}
                                                months remaining
                                            </p>
                                        </div>
                                        <p className="text-lg font-semibold text-[#273246]">
                                            {formatEGP(goal.allocatedAmount)}{' '}
                                            <span className="text-xs font-normal text-[#98a1ae]">
                                                of{' '}
                                                {formatEGP(goal.targetAmount)}
                                            </span>
                                        </p>
                                    </div>
                                    <div className="mt-5 flex items-center gap-4">
                                        <div className="flex-1">
                                            <Progress
                                                value={goal.fundingPercent}
                                                color={
                                                    goal.onTrack
                                                        ? '#63bf85'
                                                        : '#f0ad59'
                                                }
                                            />
                                        </div>
                                        <span className="w-12 text-right text-sm font-semibold text-[#4d5a6d]">
                                            {goal.fundingPercent}%
                                        </span>
                                    </div>
                                    <div className="mt-4 grid gap-3 text-xs sm:grid-cols-3">
                                        <div className="rounded-xl bg-[#f8f9fb] p-3">
                                            <p className="text-[#8993a3]">
                                                Remaining
                                            </p>
                                            <p className="mt-1 font-semibold text-[#4d5a6d]">
                                                {formatCompactEGP(
                                                    goal.remainingAmount,
                                                )}
                                            </p>
                                        </div>
                                        <div className="rounded-xl bg-[#f8f9fb] p-3">
                                            <p className="text-[#8993a3]">
                                                Required / month
                                            </p>
                                            <p className="mt-1 font-semibold text-[#4d5a6d]">
                                                {formatCompactEGP(
                                                    goal.requiredMonthlyContribution,
                                                )}
                                            </p>
                                        </div>
                                        <div className="rounded-xl bg-[#f8f9fb] p-3">
                                            <p className="text-[#8993a3]">
                                                Funding sources
                                            </p>
                                            <p className="mt-1 font-semibold text-[#4d5a6d]">
                                                {buckets
                                                    .filter(
                                                        (bucket) =>
                                                            bucket.goalId ===
                                                            goal.id,
                                                    )
                                                    .map(
                                                        (bucket) => bucket.name,
                                                    )
                                                    .join(', ') || 'No bucket'}
                                            </p>
                                        </div>
                                    </div>
                                    {!goal.onTrack && (
                                        <p className="mt-4 text-xs text-[#b76a2b]">
                                            This goal is off track by{' '}
                                            {formatCompactEGP(goal.gapPerMonth)}
                                            /month compared with current free
                                            cash flow.
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            title="No goals yet"
                            description="Create a goal such as a car, a home, or a future opportunity."
                        />
                    )}
                </Card>
                <Card>
                    <CardHeader
                        title="Goal buckets"
                        meta="A bucket can hold parts of many assets"
                    />
                    <div className="space-y-3 p-5">
                        {buckets
                            .filter((bucket) => bucket.goalId)
                            .map((bucket) => (
                                <div
                                    key={bucket.id}
                                    className="rounded-xl border border-[#edf0f4] p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-semibold text-[#4d5a6d]">
                                            {bucket.name}
                                        </p>
                                        <span
                                            className="h-2.5 w-2.5 rounded-full"
                                            style={{
                                                backgroundColor: bucket.color,
                                            }}
                                        />
                                    </div>
                                    <p className="mt-2 text-xl font-semibold text-[#273246]">
                                        {formatEGP(bucket.currentAmount)}
                                    </p>
                                    <p className="mt-1 text-xs text-[#8993a3]">
                                        {bucket.targetAmount
                                            ? `${Math.round((bucket.currentAmount / bucket.targetAmount) * 100)}% of bucket target`
                                            : 'No bucket target'}
                                    </p>
                                </div>
                            ))}
                        {!buckets.some((bucket) => bucket.goalId) && (
                            <p className="text-sm text-[#8993a3]">
                                Goals will appear here with their dedicated
                                funding buckets.
                            </p>
                        )}
                    </div>
                </Card>
            </div>
            {open && (
                <FormModal
                    title="Add a financial goal"
                    onClose={() => setOpen(false)}
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Field
                            label="Goal name"
                            required
                            value={form.name}
                            onChange={(e) => update('name', e.target.value)}
                            placeholder="e.g. Car"
                        />
                        <Field
                            label="Target amount (EGP)"
                            type="number"
                            required
                            value={form.target_amount_egp}
                            onChange={(e) =>
                                update('target_amount_egp', e.target.value)
                            }
                        />
                        <Field
                            label="Deadline"
                            type="date"
                            value={form.deadline}
                            onChange={(e) => update('deadline', e.target.value)}
                        />
                        <Field
                            label="Priority"
                            type="number"
                            min="1"
                            value={form.priority}
                            onChange={(e) => update('priority', e.target.value)}
                        />
                        <div className="flex justify-end gap-2 sm:col-span-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Create goal</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
