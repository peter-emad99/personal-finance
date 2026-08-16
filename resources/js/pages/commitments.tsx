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

type Commitment = {
    id: number;
    name: string;
    category: string;
    amount: number;
    frequency: string;
    monthlyAmount: number;
    annualAmount: number;
    nextDueOn: string | null;
    renewalOn: string | null;
    isActive: boolean;
    notes: string | null;
};

export default function Commitments({
    commitments,
    summary,
}: {
    commitments: Commitment[];
    summary: { monthly: number; annual: number };
}) {
    const [editing, setEditing] = useState<Commitment | null>(null);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState(emptyForm());
    const begin = (commitment?: Commitment) => {
        setEditing(commitment ?? null);
        setForm(
            commitment
                ? {
                      name: commitment.name,
                      category: commitment.category,
                      amount_egp: String(commitment.amount),
                      frequency: commitment.frequency,
                      next_due_on: commitment.nextDueOn ?? '',
                      renewal_on: commitment.renewalOn ?? '',
                      is_active: commitment.isActive ? '1' : '0',
                      notes: commitment.notes ?? '',
                  }
                : emptyForm(),
        );
        setOpen(true);
    };
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const url = editing ? `/commitments/${editing.id}` : '/commitments';
        router[editing ? 'put' : 'post'](
            url,
            { ...form, is_active: form.is_active === '1' },
            { onSuccess: () => setOpen(false) },
        );
    };

    return (
        <AppShell title="Commitments">
            <PageHeader
                eyebrow="Know what is already spoken for"
                title="Recurring commitments"
                description="Subscriptions, renewals, family support, rent, insurance, and other predictable obligations. Keep the list strategic and let it inform your monthly plan."
                action={
                    <Button onClick={() => begin()}>+ Add commitment</Button>
                }
            />
            <div className="mb-4 grid gap-4 sm:grid-cols-2">
                <Card className="p-5">
                    <p className="text-xs font-semibold tracking-wider text-[#8993a3] uppercase">
                        Monthly commitments
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-[#a46e14]">
                        {formatEGP(summary.monthly)}
                    </p>
                </Card>
                <Card className="p-5">
                    <p className="text-xs font-semibold tracking-wider text-[#8993a3] uppercase">
                        Annual commitment
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-[#273246]">
                        {formatEGP(summary.annual)}
                    </p>
                </Card>
            </div>
            <Card>
                <CardHeader
                    title="Your commitments"
                    meta="Inactive items stay in history without affecting the monthly total"
                />
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[780px] text-left text-sm">
                        <thead className="bg-[#fafbfc] text-[11px] tracking-wider text-[#99a2af] uppercase">
                            <tr>
                                <th className="px-5 py-3">Name</th>
                                <th className="px-5 py-3">Category</th>
                                <th className="px-5 py-3">Amount</th>
                                <th className="px-5 py-3">
                                    Monthly equivalent
                                </th>
                                <th className="px-5 py-3">Next due</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#eef0f4]">
                            {commitments.map((item) => (
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
                                        {item.category}
                                    </td>
                                    <td className="px-5 py-4 text-[#58657a]">
                                        {formatEGP(item.amount)} /{' '}
                                        {item.frequency}
                                    </td>
                                    <td className="px-5 py-4 font-semibold text-[#a46e14]">
                                        {formatEGP(item.monthlyAmount)}
                                    </td>
                                    <td className="px-5 py-4 text-xs text-[#8993a3]">
                                        {item.nextDueOn ?? '—'}
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
                                                    `/commitments/${item.id}`,
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
                    {!commitments.length && (
                        <EmptyState
                            title="No commitments yet"
                            description="Add subscriptions and predictable obligations so your monthly plan reflects reality."
                        />
                    )}
                </div>
            </Card>
            {open && (
                <FormModal
                    title={editing ? 'Edit commitment' : 'Add commitment'}
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
                            placeholder="Insurance, Netflix, rent..."
                        />
                        <Field
                            label="Category"
                            required
                            value={form.category}
                            onChange={(e) => update('category', e.target.value)}
                            placeholder="subscription, housing..."
                        />
                        <Field
                            label="Amount (EGP)"
                            required
                            type="number"
                            min="0"
                            value={form.amount_egp}
                            onChange={(e) =>
                                update('amount_egp', e.target.value)
                            }
                        />
                        <SelectField
                            label="Frequency"
                            value={form.frequency}
                            onChange={(e) =>
                                update('frequency', e.target.value)
                            }
                        >
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </SelectField>
                        <Field
                            label="Next due"
                            type="date"
                            value={form.next_due_on}
                            onChange={(e) =>
                                update('next_due_on', e.target.value)
                            }
                        />
                        <Field
                            label="Renewal date"
                            type="date"
                            value={form.renewal_on}
                            onChange={(e) =>
                                update('renewal_on', e.target.value)
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
                            <option value="0">Inactive</option>
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
                            <Button type="submit">Save commitment</Button>
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
        category: 'subscription',
        amount_egp: '',
        frequency: 'monthly',
        next_due_on: '',
        renewal_on: '',
        is_active: '1',
        notes: '',
    };
}
