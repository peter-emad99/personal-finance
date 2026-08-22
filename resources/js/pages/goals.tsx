import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    EmptyState,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { DatePicker } from '@/components/date-picker';
import { FormModal } from '@/components/form';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { formatCompactEGP, formatEGP } from '@/types/finance';
import type { Bucket, Goal } from '@/types/finance';

export default function Goals({
    goals,
    buckets,
    archivedGoals = [],
}: {
    goals: Goal[];
    buckets: Bucket[];
    archivedGoals?: Pick<Goal, 'id' | 'name' | 'targetAmount' | 'deadline'>[];
}) {
    const [editing, setEditing] = useState<Goal | null>(null);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        name: '',
        target_amount_egp: '',
        deadline: '2026-12-31',
        priority: '1',
        monthly_contribution_egp: '',
        notes: '',
    });
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const begin = (goal?: Goal) => {
        setEditing(goal ?? null);
        setForm(
            goal
                ? {
                      name: goal.name,
                      target_amount_egp: String(goal.targetAmount),
                      deadline: goal.deadline ?? '',
                      priority: String(goal.priority ?? 1),
                      monthly_contribution_egp:
                          goal.plannedMonthlyContribution !== null &&
                          goal.plannedMonthlyContribution !== undefined
                              ? String(goal.plannedMonthlyContribution)
                              : '',
                      notes: goal.notes ?? '',
                  }
                : {
                      name: '',
                      target_amount_egp: '',
                      deadline: '2026-12-31',
                      priority: '1',
                      monthly_contribution_egp: '',
                      notes: '',
                  },
        );
        setOpen(true);
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const url = editing ? `/goals/${editing.id}` : '/goals';
        router[editing ? 'put' : 'post'](
            url,
            { ...form, status: editing?.status ?? 'active' },
            {
                onSuccess: () => {
                    setOpen(false);
                    setEditing(null);
                },
            },
        );
    };

    return (
        <AppShell title="Goals">
            <PageHeader
                eyebrow="Money with a job"
                title="Goals"
                description="Give near-term money a clear destination so it does not get mistaken for long-term investment capital."
                action={<Button onClick={() => begin()}>+ Add goal</Button>}
            />
            <div className="grid gap-4 xl:grid-cols-[1.4fr_0.9fr]">
                <Card>
                    <CardHeader
                        title="Active goals"
                        meta="Funding progress and required pace"
                    />
                    {goals.length ? (
                        <div className="divide-y divide-border">
                            {goals.map((goal) => (
                                <div key={goal.id} className="p-5">
                                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h2 className="text-base font-semibold text-foreground">
                                                    {goal.name}
                                                </h2>
                                                <Badge
                                                    variant={
                                                        goal.onTrack
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {goal.onTrack
                                                        ? 'ON TRACK'
                                                        : 'OFF TRACK'}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {goal.deadline
                                                    ? `Deadline ${new Date(goal.deadline).toLocaleDateString('en-EG', { day: 'numeric', month: 'short', year: 'numeric' })}`
                                                    : 'No deadline'}{' '}
                                                · {goal.monthsRemaining ?? '—'}{' '}
                                                months remaining
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                className="h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                                onClick={() => begin(goal)}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="danger"
                                                className="h-7 border-0 bg-transparent px-2 text-xs text-destructive hover:bg-transparent"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            'Archive this goal?',
                                                        )
                                                    ) {
                                                        router.delete(
                                                            `/goals/${goal.id}`,
                                                        );
                                                    }
                                                }}
                                            >
                                                Archive
                                            </Button>
                                        </div>
                                        <p className="text-lg font-semibold text-foreground">
                                            {formatEGP(goal.allocatedAmount)}{' '}
                                            <span className="text-xs font-normal text-muted-foreground">
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
                                                        ? 'var(--chart-2)'
                                                        : 'var(--chart-3)'
                                                }
                                            />
                                        </div>
                                        <span className="w-12 text-right text-sm font-semibold text-muted-foreground">
                                            {goal.fundingPercent}%
                                        </span>
                                    </div>
                                    <div className="mt-4 grid gap-3 text-xs sm:grid-cols-3">
                                        <div className="rounded-xl bg-muted p-3">
                                            <p className="text-muted-foreground">
                                                Remaining
                                            </p>
                                            <p className="mt-1 font-semibold text-muted-foreground">
                                                {formatCompactEGP(
                                                    goal.remainingAmount,
                                                )}
                                            </p>
                                        </div>
                                        <div className="rounded-xl bg-muted p-3">
                                            <p className="text-muted-foreground">
                                                Required / month
                                            </p>
                                            <p className="mt-1 font-semibold text-muted-foreground">
                                                {formatCompactEGP(
                                                    goal.requiredMonthlyContribution,
                                                )}
                                            </p>
                                        </div>
                                        <div className="rounded-xl bg-muted p-3">
                                            <p className="text-muted-foreground">
                                                Planned / month
                                            </p>
                                            <p className="mt-1 font-semibold text-muted-foreground">
                                                {formatCompactEGP(
                                                    goal.plannedMonthlyContribution ??
                                                        goal.requiredMonthlyContribution,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4 rounded-xl border p-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Backed by real assets
                                            </p>
                                            <span className="text-[11px] text-muted-foreground">
                                                {buckets
                                                    .filter(
                                                        (bucket) =>
                                                            bucket.goalId ===
                                                            goal.id,
                                                    )
                                                    .map(
                                                        (bucket) => bucket.name,
                                                    )
                                                    .join(', ') ||
                                                    'Goal bucket'}
                                            </span>
                                        </div>
                                        <div className="mt-3 flex flex-col gap-2">
                                            {(goal.fundingSources ?? []).map(
                                                (source) => (
                                                    <div
                                                        key={source.assetId}
                                                        className="flex items-center justify-between gap-3 rounded-lg bg-muted px-3 py-2 text-xs"
                                                    >
                                                        <span className="min-w-0 truncate">
                                                            {source.assetName}{' '}
                                                            <span className="text-muted-foreground">
                                                                ·{' '}
                                                                {
                                                                    source.assetType
                                                                }
                                                            </span>
                                                        </span>
                                                        <span className="shrink-0 font-semibold">
                                                            {formatCompactEGP(
                                                                source.amount,
                                                            )}
                                                        </span>
                                                    </div>
                                                ),
                                            )}
                                            {!(goal.fundingSources ?? [])
                                                .length && (
                                                <p className="text-xs text-muted-foreground">
                                                    Link cash, gold, or an
                                                    investment allocation to
                                                    this goal's bucket.
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    {!goal.onTrack && (
                                        <p className="mt-4 text-xs text-muted-foreground">
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
                    {archivedGoals.length > 0 && (
                        <div className="border-t border-border p-5">
                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Archived goals
                            </p>
                            <div className="mt-3 space-y-2">
                                {archivedGoals.map((goal) => (
                                    <div
                                        key={goal.id}
                                        className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-sm opacity-70"
                                    >
                                        <span>{goal.name}</span>
                                        <Button
                                            variant="ghost"
                                            className="h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                            onClick={() =>
                                                router.post(
                                                    `/goals/${goal.id}/restore`,
                                                )
                                            }
                                        >
                                            Restore
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </Card>
                <Card>
                    <CardHeader
                        title="Goal buckets"
                        meta="A bucket can hold parts of many assets"
                    />
                    <div className="flex flex-col gap-3 p-5">
                        {buckets
                            .filter((bucket) => bucket.goalId)
                            .map((bucket) => (
                                <div
                                    key={bucket.id}
                                    className="rounded-xl border border-border p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm font-semibold text-muted-foreground">
                                            {bucket.name}
                                        </p>
                                        <span
                                            className="h-2.5 w-2.5 rounded-full"
                                            style={{
                                                backgroundColor: bucket.color,
                                            }}
                                        />
                                    </div>
                                    <p className="mt-2 text-xl font-semibold text-foreground">
                                        {formatEGP(bucket.currentAmount)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {bucket.targetAmount
                                            ? `${Math.round((bucket.currentAmount / bucket.targetAmount) * 100)}% of bucket target`
                                            : 'No bucket target'}
                                    </p>
                                </div>
                            ))}
                        {!buckets.some((bucket) => bucket.goalId) && (
                            <p className="text-sm text-muted-foreground">
                                Goals will appear here with their dedicated
                                funding buckets.
                            </p>
                        )}
                    </div>
                </Card>
            </div>
            {open && (
                <FormModal
                    title={
                        editing ? 'Edit financial goal' : 'Add a financial goal'
                    }
                    onClose={() => {
                        setOpen(false);
                        setEditing(null);
                    }}
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Field>
                            <FieldLabel htmlFor="goal-name">
                                Goal name
                            </FieldLabel>
                            <Input
                                id="goal-name"
                                required
                                value={form.name}
                                onChange={(e) => update('name', e.target.value)}
                                placeholder="e.g. Car"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="goal-contribution">
                                Planned contribution / month (EGP)
                            </FieldLabel>
                            <Input
                                id="goal-contribution"
                                type="number"
                                min="0"
                                value={form.monthly_contribution_egp}
                                onChange={(e) =>
                                    update(
                                        'monthly_contribution_egp',
                                        e.target.value,
                                    )
                                }
                                placeholder="Used when policy is manual"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="goal-target">
                                Target amount (EGP)
                            </FieldLabel>
                            <Input
                                id="goal-target"
                                type="number"
                                required
                                value={form.target_amount_egp}
                                onChange={(e) =>
                                    update('target_amount_egp', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="goal-deadline">
                                Deadline
                            </FieldLabel>
                            <DatePicker
                                id="goal-deadline"
                                value={form.deadline}
                                onChange={(deadline) =>
                                    update('deadline', deadline)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="goal-priority">
                                Priority
                            </FieldLabel>
                            <Input
                                id="goal-priority"
                                type="number"
                                min="1"
                                value={form.priority}
                                onChange={(e) =>
                                    update('priority', e.target.value)
                                }
                            />
                        </Field>
                        <div className="flex justify-end gap-2 sm:col-span-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">
                                {editing ? 'Save changes' : 'Create goal'}
                            </Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
