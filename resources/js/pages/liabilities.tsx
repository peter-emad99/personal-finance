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

type Liability = {
    id: number;
    name: string;
    type: string;
    balance: number;
    originalBalance: number | null;
    interestRate: number | null;
    monthlyPayment: number;
    dueDay: number | null;
    payoffOn: string | null;
    isActive: boolean;
    notes: string | null;
};

export default function Liabilities({
    liabilities,
}: {
    liabilities: Liability[];
}) {
    const [editing, setEditing] = useState<Liability | null>(null);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState(emptyForm());
    const total = liabilities
        .filter((item) => item.isActive)
        .reduce((sum, item) => sum + item.balance, 0);
    const monthly = liabilities
        .filter((item) => item.isActive)
        .reduce((sum, item) => sum + item.monthlyPayment, 0);
    const begin = (item?: Liability) => {
        setEditing(item ?? null);
        setForm(
            item
                ? {
                      name: item.name,
                      type: item.type,
                      balance_egp: String(item.balance),
                      original_balance_egp:
                          item.originalBalance === null
                              ? ''
                              : String(item.originalBalance),
                      interest_rate_percent:
                          item.interestRate === null
                              ? ''
                              : String(item.interestRate),
                      monthly_payment_egp: String(item.monthlyPayment),
                      due_day: item.dueDay ? String(item.dueDay) : '',
                      payoff_on: item.payoffOn ?? '',
                      is_active: item.isActive ? '1' : '0',
                      notes: item.notes ?? '',
                  }
                : emptyForm(),
        );
        setOpen(true);
    };
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const url = editing ? `/liabilities/${editing.id}` : '/liabilities';
        router[editing ? 'put' : 'post'](
            url,
            { ...form, is_active: form.is_active === '1' },
            { onSuccess: () => setOpen(false) },
        );
    };

    return (
        <AppShell title="Liabilities">
            <PageHeader
                eyebrow="See the whole picture"
                title="Liabilities"
                description="Track balances and monthly payments so net worth, purchase decisions, and free cash flow reflect reality."
                action={
                    <Button onClick={() => begin()}>+ Add liability</Button>
                }
            />
            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                <Card className="p-5">
                    <p className="text-xs font-semibold tracking-wider text-[#8993a3] uppercase">
                        Outstanding balance
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-[#c65365]">
                        {formatEGP(total)}
                    </p>
                </Card>
                <Card className="p-5">
                    <p className="text-xs font-semibold tracking-wider text-[#8993a3] uppercase">
                        Monthly payments
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-[#273246]">
                        {formatEGP(monthly)}
                    </p>
                </Card>
            </div>
            <Card>
                <CardHeader
                    title="Debt and obligations"
                    meta="Net worth is assets minus active liabilities"
                />
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[820px] text-left text-sm">
                        <thead className="bg-[#fafbfc] text-[11px] tracking-wider text-[#99a2af] uppercase">
                            <tr>
                                <th className="px-5 py-3">Name</th>
                                <th className="px-5 py-3">Type</th>
                                <th className="px-5 py-3">Balance</th>
                                <th className="px-5 py-3">Payment</th>
                                <th className="px-5 py-3">Rate</th>
                                <th className="px-5 py-3">Payoff</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#eef0f4]">
                            {liabilities.map((item) => (
                                <tr
                                    key={item.id}
                                    className={
                                        !item.isActive ? 'opacity-50' : ''
                                    }
                                >
                                    <td className="px-5 py-4">
                                        <p className="font-semibold text-[#273246]">
                                            {item.name}
                                        </p>
                                        <p className="mt-1 text-xs text-[#8993a3]">
                                            {item.isActive
                                                ? 'Active'
                                                : 'Inactive'}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-[#58657a]">
                                        {item.type}
                                    </td>
                                    <td className="px-5 py-4 font-semibold text-[#c65365]">
                                        {formatEGP(item.balance)}
                                    </td>
                                    <td className="px-5 py-4 text-[#58657a]">
                                        {formatEGP(item.monthlyPayment)}
                                    </td>
                                    <td className="px-5 py-4 text-[#58657a]">
                                        {item.interestRate === null
                                            ? '—'
                                            : `${item.interestRate}%`}
                                    </td>
                                    <td className="px-5 py-4 text-xs text-[#8993a3]">
                                        {item.payoffOn ?? '—'}
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        <button
                                            onClick={() => begin(item)}
                                            className="mr-3 text-xs font-semibold text-[#6878d5]"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            onClick={() =>
                                                router.delete(
                                                    `/liabilities/${item.id}`,
                                                )
                                            }
                                            className="text-xs font-semibold text-[#c65365]"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!liabilities.length && (
                        <EmptyState
                            title="No liabilities recorded"
                            description="Add loans, instalments, and credit-card balances to calculate true net worth."
                        />
                    )}
                </div>
            </Card>
            {open && (
                <FormModal
                    title={editing ? 'Edit liability' : 'Add liability'}
                    onClose={() => setOpen(false)}
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Field
                            label="Name"
                            required
                            value={form.name}
                            onChange={(e) => update('name', e.target.value)}
                            placeholder="Car loan, credit card..."
                        />
                        <Field
                            label="Type"
                            required
                            value={form.type}
                            onChange={(e) => update('type', e.target.value)}
                            placeholder="loan, credit_card..."
                        />
                        <Field
                            label="Current balance (EGP)"
                            required
                            type="number"
                            min="0"
                            value={form.balance_egp}
                            onChange={(e) =>
                                update('balance_egp', e.target.value)
                            }
                        />
                        <Field
                            label="Original balance (EGP)"
                            type="number"
                            min="0"
                            value={form.original_balance_egp}
                            onChange={(e) =>
                                update('original_balance_egp', e.target.value)
                            }
                        />
                        <Field
                            label="Interest rate (%)"
                            type="number"
                            min="0"
                            step="0.01"
                            value={form.interest_rate_percent}
                            onChange={(e) =>
                                update('interest_rate_percent', e.target.value)
                            }
                        />
                        <Field
                            label="Monthly payment (EGP)"
                            required
                            type="number"
                            min="0"
                            value={form.monthly_payment_egp}
                            onChange={(e) =>
                                update('monthly_payment_egp', e.target.value)
                            }
                        />
                        <Field
                            label="Due day"
                            type="number"
                            min="1"
                            max="31"
                            value={form.due_day}
                            onChange={(e) => update('due_day', e.target.value)}
                        />
                        <Field
                            label="Expected payoff"
                            type="date"
                            value={form.payoff_on}
                            onChange={(e) =>
                                update('payoff_on', e.target.value)
                            }
                        />
                        <SelectField
                            label="Status"
                            value={form.is_active}
                            onChange={(e) =>
                                update('is_active', e.target.value)
                            }
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive / paid off</option>
                        </SelectField>
                        <div className="sm:col-span-2">
                            <Field
                                label="Notes"
                                value={form.notes}
                                onChange={(e) =>
                                    update('notes', e.target.value)
                                }
                            />
                        </div>
                        <div className="flex justify-end gap-2 sm:col-span-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Save liability</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}

function emptyForm() {
    return {
        name: '',
        type: 'loan',
        balance_egp: '',
        original_balance_egp: '',
        interest_rate_percent: '',
        monthly_payment_egp: '',
        due_day: '',
        payoff_on: '',
        is_active: '1',
        notes: '',
    };
}
