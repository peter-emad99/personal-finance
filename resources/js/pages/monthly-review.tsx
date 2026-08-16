import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { Field, FieldLabel } from '@/components/ui/field';
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
import { formatCompactEGP, formatEGP } from '@/types/finance';

type Review = {
    month: string;
    income: number;
    essentialExpenses: number;
    lifestyleExpenses: number;
    recurringCommitments: number;
    oneTimeExpenses: number;
    debtPayments: number;
    invested: number;
    notes: string | null;
    status: string;
    source: string;
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
    history,
}: {
    review: Review;
    history: HistoryItem[];
}) {
    const [form, setForm] = useState(() => toForm(review));
    const month = review.month.slice(0, 7);

    const totalExpenses = useMemo(
        () =>
            [
                'essential_expenses',
                'lifestyle_expenses',
                'recurring_commitments',
                'one_time_expenses',
                'debt_payments',
            ].reduce(
                (total, key) =>
                    total + Number(form[key as keyof typeof form] || 0),
                0,
            ),
        [form],
    );
    const freeCashFlow = Number(form.income || 0) - totalExpenses;

    const changeMonth = (value: string) => {
        router.get(
            '/monthly-review',
            { month: value },
            { preserveState: false },
        );
    };
    const update = (key: keyof typeof form, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
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
                            <Input
                                id="review-month"
                                aria-label="Review month"
                                type="month"
                                value={month}
                                onChange={(event) =>
                                    changeMonth(event.target.value)
                                }
                            />
                        </Field>
                        <Button href="/commitments" variant="ghost">
                            Manage commitments
                        </Button>
                    </div>
                }
            />
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
            <div className="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                <Card>
                    <CardHeader
                        title="This month’s numbers"
                        meta={
                            review.source === 'review'
                                ? 'Saved review — editable'
                                : 'Built from cash-flow entries — save an editable review'
                        }
                    />
                    <form
                        onSubmit={submit}
                        className="grid gap-4 p-5 sm:grid-cols-2"
                    >
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
                                    update('invested', event.target.value)
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
                                    update('debt_payments', event.target.value)
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
                                    update('status', String(value ?? ''))
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
                        <div className="sm:col-span-2">
                            <Field>
                                <FieldLabel htmlFor="review-notes">
                                    Notes
                                </FieldLabel>
                                <Input
                                    id="review-notes"
                                    value={form.notes}
                                    onChange={(event) =>
                                        update('notes', event.target.value)
                                    }
                                    placeholder="What changed, and what will you do next month?"
                                />
                            </Field>
                        </div>
                        <div className="flex items-center justify-between sm:col-span-2">
                            <p className="text-xs text-muted-foreground">
                                {review.source === 'review'
                                    ? 'You can revise this later.'
                                    : 'Saving creates a monthly record you can revisit.'}
                            </p>
                            <Button type="submit">Save monthly review</Button>
                        </div>
                    </form>
                </Card>
                <Card>
                    <CardHeader
                        title="How the month moved"
                        meta="Use the surplus deliberately"
                    />
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
        status: review.status,
        notes: review.notes ?? '',
    };
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
