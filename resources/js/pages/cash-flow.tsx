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
} from '@/components/app-shell';
import { DatePicker } from '@/components/date-picker';
import { FormModal } from '@/components/form';
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
import { formatEGP } from '@/types/finance';

type Flow = {
    id: number;
    type: string;
    category: string;
    amount: number | null;
    currency: 'EGP' | 'USD';
    exchange_rate: number | null;
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
        amount: '',
        currency: 'EGP',
        exchange_rate: '1',
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
                setForm({ ...form, amount: '', notes: '' });
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
                    <Table className="min-w-[650px] text-left text-sm">
                        <TableHeader className="bg-muted/50 text-[11px] tracking-wider text-muted-foreground uppercase">
                            <TableRow>
                                <TableHead className="px-5 py-3">
                                    Date
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Type
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Category
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Amount
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Notes
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-border">
                            {flows.map((flow) => (
                                <TableRow key={flow.id}>
                                    <TableCell className="px-5 py-4 text-xs text-muted-foreground">
                                        {new Date(
                                            flow.occurred_on,
                                        ).toLocaleDateString('en-EG', {
                                            day: 'numeric',
                                            month: 'short',
                                        })}
                                    </TableCell>
                                    <TableCell className="px-5 py-4">
                                        <Badge
                                            className={`rounded-full border-0 px-2.5 py-1 text-[11px] font-semibold ${flow.type === 'income' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : flow.type === 'obligation' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-secondary text-secondary-foreground'}`}
                                        >
                                            {flow.type}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-medium text-muted-foreground">
                                        {flow.category}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-semibold">
                                        {flow.type === 'income' ? '+' : '-'}
                                        {flow.currency === 'USD'
                                            ? `${Number(flow.amount ?? 0).toLocaleString('en-EG')} USD`
                                            : formatEGP(flow.amount_egp)}
                                        {flow.currency === 'USD' && (
                                            <span className="mt-0.5 block text-[11px] font-normal text-muted-foreground">
                                                {formatEGP(flow.amount_egp)} at{' '}
                                                {Number(
                                                    flow.exchange_rate ?? 0,
                                                ).toLocaleString('en-EG')}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-xs text-muted-foreground">
                                        {flow.notes || '—'}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-right">
                                        <Button
                                            variant="danger"
                                            className="h-7 border-0 bg-transparent px-2 text-xs text-muted-foreground hover:bg-transparent hover:text-destructive"
                                            onClick={() =>
                                                router.delete(
                                                    `/cash-flow/${flow.id}`,
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
                    <form onSubmit={submit}>
                        <FieldGroup className="sm:grid sm:grid-cols-2">
                            <Field>
                                <FieldLabel htmlFor="cash-flow-type">
                                    Type
                                </FieldLabel>
                                <Select
                                    value={form.type}
                                    onValueChange={(value) =>
                                        update('type', String(value ?? ''))
                                    }
                                >
                                    <SelectTrigger
                                        id="cash-flow-type"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="income">
                                                Income
                                            </SelectItem>
                                            <SelectItem value="expense">
                                                Expense
                                            </SelectItem>
                                            <SelectItem value="obligation">
                                                Obligation
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="cash-flow-category">
                                    Category
                                </FieldLabel>
                                <Input
                                    id="cash-flow-category"
                                    required
                                    value={form.category}
                                    onChange={(e) =>
                                        update('category', e.target.value)
                                    }
                                    placeholder="essential, lifestyle, salary..."
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="cash-flow-amount">
                                    Amount
                                </FieldLabel>
                                <Input
                                    id="cash-flow-amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={form.amount}
                                    onChange={(e) =>
                                        update('amount', e.target.value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="cash-flow-currency">
                                    Currency
                                </FieldLabel>
                                <Select
                                    value={form.currency}
                                    onValueChange={(value) => {
                                        const currency = String(value ?? 'EGP');
                                        setForm((current) => ({
                                            ...current,
                                            currency,
                                            exchange_rate:
                                                currency === 'EGP'
                                                    ? '1'
                                                    : current.exchange_rate ===
                                                        '1'
                                                      ? ''
                                                      : current.exchange_rate,
                                        }));
                                    }}
                                >
                                    <SelectTrigger
                                        id="cash-flow-currency"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select currency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="EGP">
                                                EGP — Egyptian pound
                                            </SelectItem>
                                            <SelectItem value="USD">
                                                USD — US dollar
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            {form.currency === 'USD' && (
                                <Field>
                                    <FieldLabel htmlFor="cash-flow-rate">
                                        EGP per USD
                                    </FieldLabel>
                                    <Input
                                        id="cash-flow-rate"
                                        type="number"
                                        min="0.00000001"
                                        step="0.00000001"
                                        required
                                        value={form.exchange_rate}
                                        onChange={(e) =>
                                            update(
                                                'exchange_rate',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Current conversion rate"
                                    />
                                </Field>
                            )}
                            <Field>
                                <FieldLabel htmlFor="cash-flow-date">
                                    Date
                                </FieldLabel>
                                <DatePicker
                                    id="cash-flow-date"
                                    value={form.occurred_on}
                                    onChange={(occurred_on) =>
                                        update('occurred_on', occurred_on)
                                    }
                                />
                            </Field>
                            <Field className="sm:col-span-2">
                                <FieldLabel htmlFor="cash-flow-notes">
                                    Notes
                                </FieldLabel>
                                <Input
                                    id="cash-flow-notes"
                                    value={form.notes}
                                    onChange={(e) =>
                                        update('notes', e.target.value)
                                    }
                                    placeholder="Optional context"
                                />
                            </Field>
                        </FieldGroup>
                        <div className="mt-6 flex justify-end gap-2">
                            <Button
                                type="button"
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
