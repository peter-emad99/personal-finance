import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { Field, SelectField } from '@/components/form';
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
                        <Field
                            aria-label="Review month"
                            label=""
                            type="month"
                            value={month}
                            onChange={(event) =>
                                changeMonth(event.target.value)
                            }
                        />
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
                        <Field
                            label="Income (EGP)"
                            type="number"
                            min="0"
                            value={form.income}
                            onChange={(event) =>
                                update('income', event.target.value)
                            }
                        />
                        <Field
                            label="Invested / allocated (EGP)"
                            type="number"
                            min="0"
                            value={form.invested}
                            onChange={(event) =>
                                update('invested', event.target.value)
                            }
                        />
                        <Field
                            label="Essential living (EGP)"
                            type="number"
                            min="0"
                            value={form.essential_expenses}
                            onChange={(event) =>
                                update('essential_expenses', event.target.value)
                            }
                        />
                        <Field
                            label="Lifestyle (EGP)"
                            type="number"
                            min="0"
                            value={form.lifestyle_expenses}
                            onChange={(event) =>
                                update('lifestyle_expenses', event.target.value)
                            }
                        />
                        <Field
                            label="Recurring commitments (EGP)"
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
                        <Field
                            label="One-time expenses (EGP)"
                            type="number"
                            min="0"
                            value={form.one_time_expenses}
                            onChange={(event) =>
                                update('one_time_expenses', event.target.value)
                            }
                        />
                        <Field
                            label="Debt payments (EGP)"
                            type="number"
                            min="0"
                            value={form.debt_payments}
                            onChange={(event) =>
                                update('debt_payments', event.target.value)
                            }
                        />
                        <SelectField
                            label="Review status"
                            value={form.status}
                            onChange={(event) =>
                                update('status', event.target.value)
                            }
                        >
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </SelectField>
                        <div className="sm:col-span-2">
                            <Field
                                label="Notes"
                                value={form.notes}
                                onChange={(event) =>
                                    update('notes', event.target.value)
                                }
                                placeholder="What changed, and what will you do next month?"
                            />
                        </div>
                        <div className="flex items-center justify-between sm:col-span-2">
                            <p className="text-xs text-[#8993a3]">
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
                    <div className="space-y-4 p-5">
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
                        <div className="rounded-xl bg-[#f8f9fb] p-4 text-xs leading-5 text-[#697589]">
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
                    <table className="w-full min-w-[650px] text-left text-sm">
                        <thead className="bg-[#fafbfc] text-[11px] tracking-wider text-[#99a2af] uppercase">
                            <tr>
                                <th className="px-5 py-3">Month</th>
                                <th className="px-5 py-3">Income</th>
                                <th className="px-5 py-3">Outflow</th>
                                <th className="px-5 py-3">Invested</th>
                                <th className="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#eef0f4]">
                            {history.map((item) => (
                                <tr
                                    key={item.month}
                                    className="cursor-pointer hover:bg-[#fafbfc]"
                                    onClick={() => changeMonth(item.month)}
                                >
                                    <td className="px-5 py-4 font-medium text-[#4d5a6d]">
                                        {new Date(
                                            `${item.month}-01`,
                                        ).toLocaleDateString('en-EG', {
                                            month: 'long',
                                            year: 'numeric',
                                        })}
                                    </td>
                                    <td className="px-5 py-4 text-[#58657a]">
                                        {formatCompactEGP(item.income)}
                                    </td>
                                    <td className="px-5 py-4 text-[#58657a]">
                                        {formatCompactEGP(item.expenses)}
                                    </td>
                                    <td className="px-5 py-4 font-semibold text-[#328654]">
                                        {formatCompactEGP(item.invested)}
                                    </td>
                                    <td className="px-5 py-4 text-xs font-semibold text-[#8993a3]">
                                        {item.status}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!history.length && (
                        <p className="p-6 text-sm text-[#8993a3]">
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
            ? 'text-[#328654]'
            : color === 'amber'
              ? 'text-[#a46e14]'
              : 'text-[#6878d5]';

    return (
        <Card className="p-5">
            <p className="text-xs font-semibold tracking-wider text-[#8993a3] uppercase">
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
            ? 'text-[#328654]'
            : tone === 'red'
              ? 'text-[#c65365]'
              : tone === 'amber'
                ? 'text-[#a46e14]'
                : 'text-[#6878d5]';

    return (
        <div className="flex items-center justify-between border-b border-[#eef0f4] pb-3">
            <span className="text-sm text-[#697589]">{label}</span>
            <span className={`font-semibold ${color}`}>{formatEGP(value)}</span>
        </div>
    );
}
