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
import { DatePicker } from '@/components/date-picker';
import { FormModal, FormModalClose } from '@/components/form';
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
import { Textarea } from '@/components/ui/textarea';
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
                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Monthly commitments
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-amber-600 dark:text-amber-400">
                        {formatEGP(summary.monthly)}
                    </p>
                </Card>
                <Card className="p-5">
                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Annual commitment
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-foreground">
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
                    <Table className="min-w-[780px] text-left text-sm">
                        <TableHeader className="bg-muted/50 text-[11px] tracking-wider text-muted-foreground uppercase">
                            <TableRow>
                                <TableHead className="px-5 py-3">
                                    Name
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Category
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Amount
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Monthly equivalent
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Next due
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-border">
                            {commitments.map((item) => (
                                <TableRow
                                    key={item.id}
                                    className={
                                        !item.isActive ? 'opacity-50' : ''
                                    }
                                >
                                    <TableCell className="px-5 py-4">
                                        <p className="font-semibold text-foreground">
                                            {item.name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {item.isActive
                                                ? 'Active'
                                                : 'Inactive'}
                                        </p>
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-muted-foreground">
                                        {item.category}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-muted-foreground">
                                        {formatEGP(item.amount)} /{' '}
                                        {item.frequency}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-semibold text-amber-600 dark:text-amber-400">
                                        {formatEGP(item.monthlyAmount)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-xs text-muted-foreground">
                                        {item.nextDueOn ?? '—'}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-right">
                                        <Button
                                            variant="ghost"
                                            className="mr-1 h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                            onClick={() => begin(item)}
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="danger"
                                            className="h-7 border-0 bg-transparent px-2 text-xs text-destructive hover:bg-transparent"
                                            onClick={() =>
                                                router.delete(
                                                    `/commitments/${item.id}`,
                                                )
                                            }
                                        >
                                            Remove
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
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
                        <Field>
                            <FieldLabel htmlFor="commitment-name">
                                Name
                            </FieldLabel>
                            <Input
                                id="commitment-name"
                                required
                                value={form.name}
                                onChange={(e) => update('name', e.target.value)}
                                placeholder="Insurance, Netflix, rent..."
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="commitment-category">
                                Category
                            </FieldLabel>
                            <Input
                                id="commitment-category"
                                required
                                value={form.category}
                                onChange={(e) =>
                                    update('category', e.target.value)
                                }
                                placeholder="subscription, housing..."
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="commitment-amount">
                                Amount (EGP)
                            </FieldLabel>
                            <Input
                                id="commitment-amount"
                                required
                                type="number"
                                min="0"
                                value={form.amount_egp}
                                onChange={(e) =>
                                    update('amount_egp', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="commitment-frequency">
                                Frequency
                            </FieldLabel>
                            <Select
                                value={form.frequency}
                                onValueChange={(value) =>
                                    update('frequency', String(value ?? ''))
                                }
                            >
                                <SelectTrigger
                                    id="commitment-frequency"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select frequency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="monthly">
                                            Monthly
                                        </SelectItem>
                                        <SelectItem value="weekly">
                                            Weekly
                                        </SelectItem>
                                        <SelectItem value="quarterly">
                                            Quarterly
                                        </SelectItem>
                                        <SelectItem value="yearly">
                                            Yearly
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="commitment-next-due">
                                Next due
                            </FieldLabel>
                            <DatePicker
                                id="commitment-next-due"
                                value={form.next_due_on}
                                onChange={(next_due_on) =>
                                    update('next_due_on', next_due_on)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="commitment-renewal">
                                Renewal date
                            </FieldLabel>
                            <DatePicker
                                id="commitment-renewal"
                                value={form.renewal_on}
                                onChange={(renewal_on) =>
                                    update('renewal_on', renewal_on)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="commitment-status">
                                Status
                            </FieldLabel>
                            <Select
                                value={form.is_active}
                                onValueChange={(value) =>
                                    update('is_active', String(value ?? ''))
                                }
                            >
                                <SelectTrigger
                                    id="commitment-status"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="1">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="0">
                                            Inactive
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <div className="sm:col-span-2">
                            <Field>
                                <FieldLabel htmlFor="commitment-notes">
                                    Notes
                                </FieldLabel>
                                <Textarea
                                    id="commitment-notes"
                                    rows={3}
                                    value={form.notes}
                                    onChange={(e) =>
                                        update('notes', e.target.value)
                                    }
                                />
                            </Field>
                        </div>
                        <div className="flex justify-end gap-2 sm:col-span-2">
                            <FormModalClose>
                                <Button type="button" variant="ghost">
                                    Cancel
                                </Button>
                            </FormModalClose>
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
