import { router } from '@inertiajs/react';
import {
    CalendarPlus,
    CheckCircle2,
    Link2,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { MonthPicker } from '@/components/date-picker';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { CardContent, CardFooter } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCompactEGP, formatEGP } from '@/types/finance';

type Review = {
    id: number | null;
    month: string;
    income: number;
    essentialExpenses: number;
    lifestyleExpenses: number;
    recurringCommitments: number;
    oneTimeExpenses: number;
    debtPayments: number;
    invested: number;
    manualAdjustment: number;
    notes: string | null;
    status: string;
    source: string;
    linkage: {
        source: string;
        commitments: {
            configuredMonthly: number;
            recorded: number;
            variance: number;
        };
        liabilities: {
            configuredMonthlyPayments: number;
            recorded: number;
            variance: number;
            balance: number;
        };
    };
    obligationChanges: ObligationChanges;
};

type ObligationChangeRecord = {
    before?: {
        name?: string;
        monthlyAmount?: number;
        monthlyPayment?: number;
        balance?: number;
    };
    after?: {
        name?: string;
        monthlyAmount?: number;
        monthlyPayment?: number;
        balance?: number;
    };
    monthlyDelta?: number;
    balanceDelta?: number | null;
    name?: string;
    monthlyAmount?: number;
    monthlyPayment?: number;
};
type ObligationChanges = {
    status: 'no_baseline' | 'unchanged' | 'changed';
    hasChanges: boolean;
    capturedAt: string | null;
    summary: {
        previousMonthly: number | null;
        currentMonthly: number;
        monthlyDelta: number | null;
        freeCashFlowImpact: number | null;
    };
    commitments: {
        added: ObligationChangeRecord[];
        removed: ObligationChangeRecord[];
        changed: ObligationChangeRecord[];
    };
    liabilities: {
        added: ObligationChangeRecord[];
        removed: ObligationChangeRecord[];
        changed: ObligationChangeRecord[];
    };
};

type Plan = {
    templateName?: string | null;
    status?: string;
    income: number;
    expenses: number;
    freeCashFlow: number;
    generationMethod?: string;
    sourceReviewId?: number | null;
    sourceReviewMonth?: string | null;
    generatedAt?: string | null;
    allocations: { label: string; planned: number; actual: number }[];
    expenseItems?: { label: string; planned: number; actual: number }[];
} | null;
type ActualTracking = {
    source: string;
    transactionCount: number;
    summary: {
        income: number;
        essentialExpenses: number;
        lifestyleExpenses: number;
        commitments: number;
        otherExpenses: number;
        debtPayments: number;
        invested: number;
    };
    unmappedPurposeAmount: number;
    unmappedExpenseAmount: number;
} | null;
type NextMonthProposal = {
    nextMonth: string;
    plannedIncome: number;
    plannedExpenses: number;
    available: number;
    currentEmergency: number;
    emergencyTarget: number;
    emergencyGap: number;
    emergencyContribution: number;
    reserveComplete: boolean;
    redirectAmount: number;
    redirectTarget: 'goals' | 'investments' | null;
    templateName?: string | null;
    expenseItems?: {
        categoryId: number;
        categoryName: string;
        planned: number;
    }[];
    lesson: string | null;
    allocations: {
        bucketId: number;
        label: string;
        kind: string;
        amount: number;
    }[];
};

type HistoryItem = {
    month: string;
    income: number;
    expenses: number;
    invested: number;
    status: string;
};

export default function MonthlyReview({
    review,
    plan,
    history,
    actualTracking,
    nextMonthProposal,
}: {
    review: Review;
    plan: Plan;
    history: HistoryItem[];
    actualTracking: ActualTracking;
    nextMonthProposal: NextMonthProposal | null;
}) {
    const [form, setForm] = useState(() => toForm(review));
    const [proposalOpen, setProposalOpen] = useState(false);
    const [redirectTarget, setRedirectTarget] = useState<
        'goals' | 'investments'
    >('investments');
    const [lesson, setLesson] = useState(review.notes ?? '');
    const month = review.month.slice(0, 7);

    const totalExpenses = useMemo(
        () =>
            [
                'essential_expenses',
                'lifestyle_expenses',
                'recurring_commitments',
                'one_time_expenses',
                'debt_payments',
                'manual_adjustment_egp',
            ].reduce(
                (total, key) =>
                    total + Number(form[key as keyof typeof form] || 0),
                0,
            ),
        [form],
    );
    const freeCashFlow = Number(form.income || 0) - totalExpenses;
    const commitmentMismatch =
        Math.abs(review.linkage.commitments.variance) > 0.01;
    const liabilityMismatch =
        Math.abs(review.linkage.liabilities.variance) > 0.01;
    const hasLinkageMismatch = commitmentMismatch || liabilityMismatch;

    const changeMonth = (value: string) => {
        router.get(
            '/monthly-review',
            { month: value },
            { preserveState: false },
        );
    };
    const update = (key: keyof typeof form, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const syncLinkedValues = () => {
        setForm((current) => ({
            ...current,
            recurring_commitments: String(
                review.linkage.commitments.configuredMonthly,
            ),
            debt_payments: String(
                review.linkage.liabilities.configuredMonthlyPayments,
            ),
        }));
    };
    const prepareNextMonth = () => setProposalOpen(true);
    const confirmNextMonth = () => {
        if (review.id === null) {
            return;
        }

        router.post(
            `/monthly-review/${review.id}/prepare-next`,
            {
                redirect_emergency_to: redirectTarget,
                lesson: lesson || null,
            },
            { onSuccess: () => setProposalOpen(false) },
        );
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/monthly-review', form);
    };

    return (
        <AppShell title="Monthly review">
            <PageHeader
                eyebrow="Direct your money"
                title="Monthly review"
                description="A lightweight monthly check-in for your financial direction. Track totals and decisions, not every small transaction."
                action={
                    <div className="flex items-center gap-2">
                        <Field>
                            <FieldLabel
                                className="sr-only"
                                htmlFor="review-month"
                            >
                                Review month
                            </FieldLabel>
                            <MonthPicker
                                id="review-month"
                                value={month}
                                onChange={(value) => changeMonth(value)}
                            />
                        </Field>
                        <Button href="/commitments" variant="ghost">
                            Manage commitments
                        </Button>
                        {review.status === 'closed' && review.id !== null && (
                            <Button type="button" onClick={prepareNextMonth}>
                                <CalendarPlus data-icon="inline-start" />
                                Prepare next month
                            </Button>
                        )}
                    </div>
                }
            />
            <Dialog open={proposalOpen} onOpenChange={setProposalOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Confirm next month’s plan</DialogTitle>
                        <DialogDescription>
                            Review the proposal before anything is saved. You
                            can edit the plan later from Allocations.
                        </DialogDescription>
                    </DialogHeader>
                    {nextMonthProposal ? (
                        <div className="flex flex-col gap-4">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <ProposalMetric
                                    label="Income"
                                    value={nextMonthProposal.plannedIncome}
                                />
                                <ProposalMetric
                                    label="Outflow"
                                    value={nextMonthProposal.plannedExpenses}
                                />
                                <ProposalMetric
                                    label="To direct"
                                    value={nextMonthProposal.available}
                                />
                            </div>
                            <div className="rounded-xl border p-4 text-sm">
                                <p className="font-semibold">
                                    {nextMonthProposal.templateName
                                        ? `Template preview · ${nextMonthProposal.templateName}`
                                        : 'Emergency reserve'}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {nextMonthProposal.templateName
                                        ? 'Income and expense rules are recalculated for next month; allocations use template percentages on the cash left after expenses.'
                                        : nextMonthProposal.reserveComplete
                                          ? `Target complete. Redirect ${formatCompactEGP(nextMonthProposal.redirectAmount)} to a long-term purpose.`
                                          : `Current ${formatCompactEGP(nextMonthProposal.currentEmergency)} of ${formatCompactEGP(nextMonthProposal.emergencyTarget)} target; proposed contribution ${formatCompactEGP(nextMonthProposal.emergencyContribution)}.`}
                                </p>
                                {nextMonthProposal.reserveComplete && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={
                                                redirectTarget === 'investments'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                setRedirectTarget('investments')
                                            }
                                        >
                                            Redirect to investments
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={
                                                redirectTarget === 'goals'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                setRedirectTarget('goals')
                                            }
                                        >
                                            Redirect to goals
                                        </Button>
                                    </div>
                                )}
                            </div>
                            {nextMonthProposal.expenseItems &&
                                nextMonthProposal.expenseItems.length > 0 && (
                                    <div className="rounded-xl border p-4 text-sm">
                                        <p className="font-semibold">
                                            Proposed expenses
                                        </p>
                                        <div className="mt-2 flex flex-col gap-2">
                                            {nextMonthProposal.expenseItems
                                                .filter(
                                                    (item) => item.planned > 0,
                                                )
                                                .map((item) => (
                                                    <div
                                                        key={`expense-${item.categoryId}`}
                                                        className="flex items-center justify-between gap-3"
                                                    >
                                                        <span className="text-muted-foreground">
                                                            {item.categoryName}
                                                        </span>
                                                        <span className="font-medium">
                                                            {formatCompactEGP(
                                                                item.planned,
                                                            )}
                                                        </span>
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                )}
                            <div className="rounded-xl bg-muted p-4 text-sm">
                                <p className="font-semibold">
                                    Lesson to carry forward
                                </p>
                                <Input
                                    className="mt-2 bg-background"
                                    value={lesson}
                                    onChange={(event) =>
                                        setLesson(event.target.value)
                                    }
                                    placeholder="Example: dining out was higher than planned; keep one low-cost weekend next month."
                                />
                                <p className="mt-2 text-xs text-muted-foreground">
                                    This is saved in the next plan so the next
                                    review starts with context.
                                </p>
                            </div>
                            <div className="flex flex-col gap-2 text-sm">
                                <p className="font-semibold">
                                    Proposed allocation
                                </p>
                                {nextMonthProposal.allocations.map((item) => (
                                    <div
                                        key={`${item.bucketId}-${item.kind}`}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <span className="text-muted-foreground">
                                            {item.label}
                                        </span>
                                        <span className="font-medium">
                                            {formatCompactEGP(item.amount)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            A plan for the next month already exists.
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setProposalOpen(false)}
                        >
                            Keep reviewing
                        </Button>
                        <Button
                            type="button"
                            onClick={confirmNextMonth}
                            disabled={!nextMonthProposal}
                        >
                            Confirm and prepare plan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <div className="mb-4 grid gap-4 sm:grid-cols-3">
                <SummaryCard
                    label="Income"
                    value={Number(form.income || 0)}
                    color="green"
                />
                <SummaryCard
                    label="Total outflow"
                    value={totalExpenses}
                    color="amber"
                />
                <SummaryCard
                    label="Available to direct"
                    value={freeCashFlow}
                    color="blue"
                />
            </div>
            <Alert
                variant={hasLinkageMismatch ? 'destructive' : 'default'}
                className="mb-4"
            >
                {hasLinkageMismatch ? <TriangleAlert /> : <CheckCircle2 />}
                <AlertTitle>
                    {hasLinkageMismatch
                        ? 'Review values need synchronising'
                        : 'Review matches your active obligations'}
                </AlertTitle>
                <AlertDescription>
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <span className="inline-flex items-center gap-1.5">
                            <Link2 />
                            Commitments:{' '}
                            {formatCompactEGP(
                                review.linkage.commitments.configuredMonthly,
                            )}{' '}
                            / month
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <Link2 />
                            Debt payments:{' '}
                            {formatCompactEGP(
                                review.linkage.liabilities
                                    .configuredMonthlyPayments,
                            )}{' '}
                            / month
                        </span>
                        {hasLinkageMismatch && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={syncLinkedValues}
                            >
                                <RefreshCw data-icon="inline-start" />
                                Use active records
                            </Button>
                        )}
                    </div>
                </AlertDescription>
            </Alert>
            <ObligationChangesCard changes={review.obligationChanges} />
            <div className="grid items-start gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                <Card>
                    <CardHeader
                        title="This month’s numbers"
                        meta={
                            review.source === 'review'
                                ? 'Saved review — editable'
                                : 'Built from cash-flow entries — save an editable review'
                        }
                    />
                    <form onSubmit={submit} className="flex flex-col gap-0">
                        <CardContent>
                            <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                <Field>
                                    <FieldLabel htmlFor="review-income">
                                        Income (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-income"
                                        type="number"
                                        min="0"
                                        value={form.income}
                                        onChange={(event) =>
                                            update('income', event.target.value)
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-invested">
                                        Invested / allocated (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-invested"
                                        type="number"
                                        min="0"
                                        value={form.invested}
                                        onChange={(event) =>
                                            update(
                                                'invested',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-essential">
                                        Essential living (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-essential"
                                        type="number"
                                        min="0"
                                        value={form.essential_expenses}
                                        onChange={(event) =>
                                            update(
                                                'essential_expenses',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-lifestyle">
                                        Lifestyle (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-lifestyle"
                                        type="number"
                                        min="0"
                                        value={form.lifestyle_expenses}
                                        onChange={(event) =>
                                            update(
                                                'lifestyle_expenses',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-recurring">
                                        Recurring commitments (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-recurring"
                                        type="number"
                                        min="0"
                                        value={form.recurring_commitments}
                                        onChange={(event) =>
                                            update(
                                                'recurring_commitments',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-one-time">
                                        One-time expenses (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-one-time"
                                        type="number"
                                        min="0"
                                        value={form.one_time_expenses}
                                        onChange={(event) =>
                                            update(
                                                'one_time_expenses',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-debt">
                                        Debt payments (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-debt"
                                        type="number"
                                        min="0"
                                        value={form.debt_payments}
                                        onChange={(event) =>
                                            update(
                                                'debt_payments',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-adjustment">
                                        Other adjustment (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="review-adjustment"
                                        type="number"
                                        value={form.manual_adjustment_egp}
                                        onChange={(event) =>
                                            update(
                                                'manual_adjustment_egp',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="review-status">
                                        Review status
                                    </FieldLabel>
                                    <Select
                                        value={form.status}
                                        onValueChange={(value) =>
                                            update(
                                                'status',
                                                String(value ?? ''),
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="review-status"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="open">
                                                    Open
                                                </SelectItem>
                                                <SelectItem value="closed">
                                                    Closed
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field className="sm:col-span-2">
                                    <FieldLabel htmlFor="review-notes">
                                        Notes
                                    </FieldLabel>
                                    <Textarea
                                        id="review-notes"
                                        rows={3}
                                        value={form.notes}
                                        onChange={(event) =>
                                            update('notes', event.target.value)
                                        }
                                        placeholder="What changed, and what will you do next month?"
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                        <CardFooter className="flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-muted-foreground">
                                {review.source === 'review'
                                    ? 'You can revise this later.'
                                    : 'Saving creates a monthly record you can revisit.'}
                            </p>
                            <Button type="submit" className="sm:shrink-0">
                                Save monthly review
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
                <Card>
                    <CardHeader
                        title="How the month moved"
                        meta={
                            plan
                                ? plan.templateName
                                    ? `Template snapshot · ${plan.templateName}`
                                    : plan.generationMethod ===
                                        'prepared_from_review'
                                      ? `Prepared from ${plan.sourceReviewMonth ? new Date(`${plan.sourceReviewMonth}-01`).toLocaleDateString('en-EG', { month: 'long', year: 'numeric' }) : 'a closed'} review`
                                      : 'Manual plan — use the surplus deliberately'
                                : 'Use the surplus deliberately'
                        }
                    />
                    {actualTracking?.source === 'confirmed_ledger' && (
                        <Alert className="mx-5 mb-4">
                            <RefreshCw />
                            <AlertTitle>
                                Actuals are linked automatically
                            </AlertTitle>
                            <AlertDescription>
                                {actualTracking.transactionCount} confirmed
                                ledger transactions are driving this month’s
                                income, expenses, debt payments, and investment
                                totals. Allocation bucket actuals are derived
                                from their purpose when available.
                            </AlertDescription>
                        </Alert>
                    )}
                    <div className="flex flex-col gap-4 p-5">
                        <InsightRow
                            label="Available after outflow"
                            value={freeCashFlow}
                            tone={freeCashFlow >= 0 ? 'green' : 'red'}
                        />
                        <InsightRow
                            label="Invested / allocated"
                            value={Number(form.invested || 0)}
                            tone="blue"
                        />
                        <InsightRow
                            label="Recurring commitments"
                            value={Number(form.recurring_commitments || 0)}
                            tone="amber"
                        />
                        <InsightRow
                            label="Active liability payments"
                            value={
                                review.linkage.liabilities
                                    .configuredMonthlyPayments
                            }
                            tone="amber"
                        />
                        {plan && (
                            <>
                                <InsightRow
                                    label="Planned free cash flow"
                                    value={plan.freeCashFlow}
                                    tone="blue"
                                />
                                <InsightRow
                                    label="Plan vs actual"
                                    value={freeCashFlow - plan.freeCashFlow}
                                    tone={
                                        freeCashFlow >= plan.freeCashFlow
                                            ? 'green'
                                            : 'red'
                                    }
                                />
                                <div className="rounded-xl border p-4">
                                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        Planned vs actual allocations
                                    </p>
                                    <div className="mt-3 flex flex-col gap-2">
                                        {plan.allocations.map((allocation) => (
                                            <div
                                                key={allocation.label}
                                                className="flex items-center justify-between gap-3 text-xs"
                                            >
                                                <span className="truncate text-muted-foreground">
                                                    {allocation.label}
                                                </span>
                                                <span className="shrink-0 font-medium">
                                                    {formatCompactEGP(
                                                        allocation.actual,
                                                    )}{' '}
                                                    /{' '}
                                                    {formatCompactEGP(
                                                        allocation.planned,
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                                {plan.expenseItems &&
                                    plan.expenseItems.length > 0 && (
                                        <div className="rounded-xl border p-4">
                                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                Planned vs actual expenses
                                            </p>
                                            <div className="mt-3 flex flex-col gap-2">
                                                {plan.expenseItems.map(
                                                    (expense) => (
                                                        <div
                                                            key={expense.label}
                                                            className="flex items-center justify-between gap-3 text-xs"
                                                        >
                                                            <span className="truncate text-muted-foreground">
                                                                {expense.label}
                                                            </span>
                                                            <span className="shrink-0 font-medium">
                                                                {formatCompactEGP(
                                                                    expense.actual,
                                                                )}{' '}
                                                                /{' '}
                                                                {formatCompactEGP(
                                                                    expense.planned,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}
                            </>
                        )}
                        <div className="rounded-xl bg-muted p-4 text-xs leading-5 text-muted-foreground">
                            The goal is not perfect categorisation. The goal is
                            to know what your money did and what it should do
                            next.
                        </div>
                    </div>
                </Card>
            </div>
            <Card className="mt-4">
                <CardHeader
                    title="Review history"
                    meta="Your financial rhythm over time"
                />
                <div className="overflow-x-auto">
                    <Table className="min-w-[650px] text-left text-sm">
                        <TableHeader className="bg-muted/50 text-[11px] tracking-wider text-muted-foreground uppercase">
                            <TableRow>
                                <TableHead className="px-5 py-3">
                                    Month
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Income
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Outflow
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Invested
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Status
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-border">
                            {history.map((item) => (
                                <TableRow
                                    key={item.month}
                                    className="cursor-pointer hover:bg-muted/40"
                                    onClick={() => changeMonth(item.month)}
                                >
                                    <TableCell className="px-5 py-4 font-medium text-muted-foreground">
                                        {new Date(
                                            `${item.month}-01`,
                                        ).toLocaleDateString('en-EG', {
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-muted-foreground">
                                        {formatCompactEGP(item.income)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-muted-foreground">
                                        {formatCompactEGP(item.expenses)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-semibold text-emerald-600 dark:text-emerald-400">
                                        {formatCompactEGP(item.invested)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-xs font-semibold text-muted-foreground">
                                        {item.status}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {!history.length && (
                        <p className="p-6 text-sm text-muted-foreground">
                            No saved monthly reviews yet.
                        </p>
                    )}
                </div>
            </Card>
        </AppShell>
    );
}

function toForm(review: Review) {
    return {
        month: review.month.slice(0, 7),
        income: String(review.income),
        essential_expenses: String(review.essentialExpenses),
        lifestyle_expenses: String(review.lifestyleExpenses),
        recurring_commitments: String(review.recurringCommitments),
        one_time_expenses: String(review.oneTimeExpenses),
        debt_payments: String(review.debtPayments),
        invested: String(review.invested),
        manual_adjustment_egp: String(review.manualAdjustment ?? 0),
        status: review.status,
        notes: review.notes ?? '',
    };
}

function ObligationChangesCard({ changes }: { changes: ObligationChanges }) {
    if (changes.status === 'no_baseline') {
        return (
            <Alert className="mb-4">
                <Link2 />
                <AlertTitle>
                    Baseline will be captured when you close this review
                </AlertTitle>
                <AlertDescription>
                    The next review will compare each active commitment and
                    liability with today’s records, so increases and removals
                    are visible.
                </AlertDescription>
            </Alert>
        );
    }

    if (!changes.hasChanges) {
        return (
            <Alert className="mb-4">
                <CheckCircle2 />
                <AlertTitle>Active obligations are unchanged</AlertTitle>
                <AlertDescription>
                    Current commitments and liabilities match the records
                    captured at the last close.
                </AlertDescription>
            </Alert>
        );
    }

    const groups = [
        ['Commitments', changes.commitments],
        ['Liabilities', changes.liabilities],
    ] as const;

    return (
        <Alert variant="destructive" className="mb-4">
            <TriangleAlert />
            <AlertTitle>Obligations changed after the last close</AlertTitle>
            <AlertDescription>
                <p>
                    Monthly capacity changed by{' '}
                    {formatCompactEGP(changes.summary.monthlyDelta ?? 0)}. A
                    positive amount reduces free cash flow; review the exact
                    records below before preparing the next plan.
                </p>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    {groups.map(([label, group]) => (
                        <div
                            key={label}
                            className="rounded-lg border bg-background/60 p-3 text-foreground"
                        >
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                {label}
                            </p>
                            <div className="mt-2 flex flex-col gap-1 text-xs">
                                {group.added.map((item) => (
                                    <span key={`added-${item.name}`}>
                                        <strong>Added:</strong> {item.name}
                                    </span>
                                ))}
                                {group.removed.map((item) => (
                                    <span key={`removed-${item.name}`}>
                                        <strong>Removed:</strong> {item.name}
                                    </span>
                                ))}
                                {group.changed.map((item) => (
                                    <span
                                        key={`changed-${item.after?.name ?? item.before?.name}`}
                                    >
                                        <strong>Changed:</strong>{' '}
                                        {item.after?.name ?? item.before?.name}{' '}
                                        · monthly{' '}
                                        {formatCompactEGP(
                                            item.monthlyDelta ?? 0,
                                        )}
                                        {item.balanceDelta !== null &&
                                        item.balanceDelta !== undefined
                                            ? ` · balance ${formatCompactEGP(item.balanceDelta)}`
                                            : ''}
                                    </span>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </AlertDescription>
        </Alert>
    );
}

function ProposalMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg bg-muted p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-semibold">{formatCompactEGP(value)}</p>
        </div>
    );
}

function SummaryCard({
    label,
    value,
    color,
}: {
    label: string;
    value: number;
    color: string;
}) {
    const text =
        color === 'green'
            ? 'text-emerald-600 dark:text-emerald-400'
            : color === 'amber'
              ? 'text-amber-600 dark:text-amber-400'
              : 'text-primary';

    return (
        <Card className="p-5">
            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                {label}
            </p>
            <p className={`mt-3 text-2xl font-semibold ${text}`}>
                {formatEGP(value)}
            </p>
        </Card>
    );
}

function InsightRow({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: 'green' | 'red' | 'blue' | 'amber';
}) {
    const color =
        tone === 'green'
            ? 'text-emerald-600 dark:text-emerald-400'
            : tone === 'red'
              ? 'text-destructive'
              : tone === 'amber'
                ? 'text-amber-600 dark:text-amber-400'
                : 'text-primary';

    return (
        <div className="flex items-center justify-between border-b border-border pb-3">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className={`font-semibold ${color}`}>{formatEGP(value)}</span>
        </div>
    );
}
