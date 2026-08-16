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
import { FormModal } from '@/components/form';
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
                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Outstanding balance
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-destructive">
                        {formatEGP(total)}
                    </p>
                </Card>
                <Card className="p-5">
                    <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Monthly payments
                    </p>
                    <p className="mt-3 text-2xl font-semibold text-foreground">
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
                    <Table className="min-w-[820px] text-left text-sm">
                        <TableHeader className="bg-muted/50 text-[11px] tracking-wider text-muted-foreground uppercase">
                            <TableRow>
                                <TableHead className="px-5 py-3">
                                    Name
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Type
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Balance
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Payment
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Rate
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Payoff
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-border">
                            {liabilities.map((item) => (
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
                                        {item.type}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-semibold text-destructive">
                                        {formatEGP(item.balance)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-muted-foreground">
                                        {formatEGP(item.monthlyPayment)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-muted-foreground">
                                        {item.interestRate === null
                                            ? '—'
                                            : `${item.interestRate}%`}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-xs text-muted-foreground">
                                        {item.payoffOn ?? '—'}
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
                                                    `/liabilities/${item.id}`,
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
                        <Field>
                            <FieldLabel htmlFor="liability-name">
                                Name
                            </FieldLabel>
                            <Input
                                id="liability-name"
                                required
                                value={form.name}
                                onChange={(e) => update('name', e.target.value)}
                                placeholder="Car loan, credit card..."
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-type">
                                Type
                            </FieldLabel>
                            <Input
                                id="liability-type"
                                required
                                value={form.type}
                                onChange={(e) => update('type', e.target.value)}
                                placeholder="loan, credit_card..."
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-balance">
                                Current balance (EGP)
                            </FieldLabel>
                            <Input
                                id="liability-balance"
                                required
                                type="number"
                                min="0"
                                value={form.balance_egp}
                                onChange={(e) =>
                                    update('balance_egp', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-original-balance">
                                Original balance (EGP)
                            </FieldLabel>
                            <Input
                                id="liability-original-balance"
                                type="number"
                                min="0"
                                value={form.original_balance_egp}
                                onChange={(e) =>
                                    update(
                                        'original_balance_egp',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-interest">
                                Interest rate (%)
                            </FieldLabel>
                            <Input
                                id="liability-interest"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.interest_rate_percent}
                                onChange={(e) =>
                                    update(
                                        'interest_rate_percent',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-payment">
                                Monthly payment (EGP)
                            </FieldLabel>
                            <Input
                                id="liability-payment"
                                required
                                type="number"
                                min="0"
                                value={form.monthly_payment_egp}
                                onChange={(e) =>
                                    update(
                                        'monthly_payment_egp',
                                        e.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-due-day">
                                Due day
                            </FieldLabel>
                            <Input
                                id="liability-due-day"
                                type="number"
                                min="1"
                                max="31"
                                value={form.due_day}
                                onChange={(e) =>
                                    update('due_day', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-payoff">
                                Expected payoff
                            </FieldLabel>
                            <Input
                                id="liability-payoff"
                                type="date"
                                value={form.payoff_on}
                                onChange={(e) =>
                                    update('payoff_on', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="liability-status">
                                Status
                            </FieldLabel>
                            <Select
                                value={form.is_active}
                                onValueChange={(value) =>
                                    update('is_active', String(value ?? ''))
                                }
                            >
                                <SelectTrigger
                                    id="liability-status"
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
                                            Inactive / paid off
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <div className="sm:col-span-2">
                            <Field>
                                <FieldLabel htmlFor="liability-notes">
                                    Notes
                                </FieldLabel>
                                <Input
                                    id="liability-notes"
                                    value={form.notes}
                                    onChange={(e) =>
                                        update('notes', e.target.value)
                                    }
                                />
                            </Field>
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
