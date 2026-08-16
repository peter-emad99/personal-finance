import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    EmptyState,
    PageHeader,
} from '@/components/app-shell';
import { Field, FormModal, SelectField } from '@/components/form';
import { formatEGP } from '@/types/finance';

type Flow = {
    id: number;
    type: string;
    category: string;
    amount_egp: number;
    occurred_on: string;
    notes?: string | null;
};

export default function CashFlow({
    flows,
    summary,
    month,
}: {
    flows: Flow[];
    summary: { income: number; expenses: number };
    month: string;
}) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        type: 'expense',
        category: 'essential',
        amount_egp: '',
        occurred_on: month,
        notes: '',
    });
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/cash-flow', form, {
            onSuccess: () => {
                setOpen(false);
                setForm({ ...form, amount_egp: '', notes: '' });
            },
        });
    };

    return (
        <AppShell title="Cash flow">
            <PageHeader
                eyebrow="Monthly rhythm"
                title="Cash flow"
                description="Keep this light: capture monthly income, expenses, and obligations so your allocation decisions use real free cash flow."
                action={
                    <Button onClick={() => setOpen(true)}>+ Add entry</Button>
                }
            />
            <div className="mb-4 grid gap-4 sm:grid-cols-3">
                <SummaryCard
                    label="Income"
                    value={summary.income}
                    color="green"
                />
                <SummaryCard
                    label="Expenses + obligations"
                    value={summary.expenses}
                    color="amber"
                />
                <SummaryCard
                    label="Available to allocate"
                    value={summary.income - summary.expenses}
                    color="blue"
                />
            </div>
            <Card>
                <CardHeader
                    title="This month's activity"
                    meta={`Since ${new Date(month).toLocaleDateString('en-EG', { month: 'long', year: 'numeric' })}`}
                />
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[650px] text-left text-sm">
                        <thead className="bg-[#fafbfc] text-[11px] tracking-wider text-[#99a2af] uppercase">
                            <tr>
                                <th className="px-5 py-3">Date</th>
                                <th className="px-5 py-3">Type</th>
                                <th className="px-5 py-3">Category</th>
                                <th className="px-5 py-3">Amount</th>
                                <th className="px-5 py-3">Notes</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#eef0f4]">
                            {flows.map((flow) => (
                                <tr key={flow.id}>
                                    <td className="px-5 py-4 text-xs text-[#8993a3]">
                                        {new Date(
                                            flow.occurred_on,
                                        ).toLocaleDateString('en-EG', {
                                            day: 'numeric',
                                            month: 'short',
                                        })}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span
                                            className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${flow.type === 'income' ? 'bg-[#eaf8ef] text-[#328654]' : flow.type === 'obligation' ? 'bg-[#fff1e8] text-[#b76a2b]' : 'bg-[#eef1ff] text-[#6878d5]'}`}
                                        >
                                            {flow.type}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 font-medium text-[#4d5a6d]">
                                        {flow.category}
                                    </td>
                                    <td
                                        className={`px-5 py-4 font-semibold ${flow.type === 'income' ? 'text-[#328654]' : 'text-[#4d5a6d]'}`}
                                    >
                                        {flow.type === 'income' ? '+' : '-'}
                                        {formatEGP(flow.amount_egp)}
                                    </td>
                                    <td className="px-5 py-4 text-xs text-[#8993a3]">
                                        {flow.notes || '—'}
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        <button
                                            onClick={() =>
                                                router.delete(
                                                    `/cash-flow/${flow.id}`,
                                                )
                                            }
                                            className="text-xs font-semibold text-[#a4acb9] hover:text-[#c65365]"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!flows.length && (
                        <EmptyState
                            title="No monthly entries"
                            description="Add your recurring income and normal expenses to calculate free cash flow."
                        />
                    )}
                </div>
            </Card>
            {open && (
                <FormModal
                    title="Add cash flow entry"
                    onClose={() => setOpen(false)}
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <SelectField
                            label="Type"
                            value={form.type}
                            onChange={(e) => update('type', e.target.value)}
                        >
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                            <option value="obligation">Obligation</option>
                        </SelectField>
                        <Field
                            label="Category"
                            required
                            value={form.category}
                            onChange={(e) => update('category', e.target.value)}
                            placeholder="essential, lifestyle, salary..."
                        />
                        <Field
                            label="Amount (EGP)"
                            type="number"
                            required
                            value={form.amount_egp}
                            onChange={(e) =>
                                update('amount_egp', e.target.value)
                            }
                        />
                        <Field
                            label="Date"
                            type="date"
                            required
                            value={form.occurred_on}
                            onChange={(e) =>
                                update('occurred_on', e.target.value)
                            }
                        />
                        <div className="sm:col-span-2">
                            <Field
                                label="Notes"
                                value={form.notes}
                                onChange={(e) =>
                                    update('notes', e.target.value)
                                }
                                placeholder="Optional context"
                            />
                        </div>
                        <div className="flex justify-end gap-2 sm:col-span-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Save entry</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
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
