import { router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
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
    payoffProjection: {
        monthlyPayment: number;
        estimatedMonthlyInterest: number;
        estimatedMonthlyPrincipal: number;
        estimatedTotalInterest: number;
        estimatedRemainingMonths: number | null;
        estimatedPayoffOn: string | null;
        extraPaymentScenarios: {
            extraMonthlyPayment: number;
            estimatedTotalInterest: number;
            estimatedRemainingMonths: number | null;
            estimatedPayoffOn: string | null;
        }[];
    };
    recordedPaymentSummary: {
        count: number;
        totalPayments: number;
        principalPaid: number;
        interestPaid: number;
        feesPaid: number;
        lastPaidOn: string | null;
    };
    paymentRecords: PaymentRecord[];
};

type PaymentRecord = {
    id: number;
    paidOn: string | null;
    payment: number;
    principal: number;
    interest: number;
    fees: number;
    balanceAfter: number | null;
    source: string;
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
    const [paymentLiability, setPaymentLiability] = useState<Liability | null>(
        null,
    );
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [paymentForm, setPaymentForm] = useState(emptyPaymentForm());
    const [expandedId, setExpandedId] = useState<number | null>(null);
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
    const updatePayment = (key: string, value: string) =>
        setPaymentForm((current) => ({ ...current, [key]: value }));
    const beginPayment = (item: Liability) => {
        setPaymentLiability(item);
        setPaymentForm(emptyPaymentForm());
        setPaymentOpen(true);
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const url = editing ? `/liabilities/${editing.id}` : '/liabilities';
        router[editing ? 'put' : 'post'](
            url,
            { ...form, is_active: form.is_active === '1' },
            { onSuccess: () => setOpen(false) },
        );
    };
    const submitPayment = (event: React.FormEvent) => {
        event.preventDefault();

        if (!paymentLiability) {
            return;
        }

        router.post(
            `/liabilities/${paymentLiability.id}/payments`,
            paymentForm,
            { onSuccess: () => setPaymentOpen(false) },
        );
    };

    return (
        <AppShell title="Liabilities">
            <PageHeader
                eyebrow="See the whole picture"
                title="Liabilities"
                description="Track balances and monthly payments, record lender statements, and compare extra-payment scenarios so net worth and free cash flow reflect reality."
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
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-border">
                            {liabilities.map((item) => (
                                <Fragment key={item.id}>
                                    <TableRow
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
                                            <p>
                                                {item.payoffProjection
                                                    .estimatedPayoffOn ??
                                                    item.payoffOn ??
                                                    '—'}
                                            </p>
                                            <p className="mt-1">
                                                {item.payoffProjection
                                                    .estimatedRemainingMonths ===
                                                null
                                                    ? 'Needs payment detail'
                                                    : `${item.payoffProjection.estimatedRemainingMonths} months estimated`}
                                            </p>
                                        </TableCell>
                                        <TableCell className="px-5 py-4 text-right">
                                            <Button
                                                variant="ghost"
                                                className="mr-1 h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                                onClick={() =>
                                                    setExpandedId(
                                                        expandedId === item.id
                                                            ? null
                                                            : item.id,
                                                    )
                                                }
                                            >
                                                {expandedId === item.id
                                                    ? 'Hide details'
                                                    : 'Details'}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                className="mr-1 h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                                onClick={() =>
                                                    beginPayment(item)
                                                }
                                            >
                                                Record payment
                                            </Button>
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
                                    {expandedId === item.id && (
                                        <TableRow key={`${item.id}-details`}>
                                            <TableCell
                                                colSpan={8}
                                                className="bg-muted/30 px-5 py-5"
                                            >
                                                <DebtDetails liability={item} />
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
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
                            <DatePicker
                                id="liability-payoff"
                                value={form.payoff_on}
                                onChange={(payoff_on) =>
                                    update('payoff_on', payoff_on)
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
                                <Textarea
                                    id="liability-notes"
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
                            <Button type="submit">Save liability</Button>
                        </div>
                    </form>
                </FormModal>
            )}
            {paymentOpen && paymentLiability && (
                <FormModal
                    title={`Record payment · ${paymentLiability.name}`}
                    description="Use the lender statement when possible. Principal and interest stay separate so the payoff picture improves over time."
                    onClose={() => setPaymentOpen(false)}
                >
                    <form
                        onSubmit={submitPayment}
                        className="flex flex-col gap-4"
                    >
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="payment-paid-on">
                                    Payment date
                                </FieldLabel>
                                <DatePicker
                                    id="payment-paid-on"
                                    value={paymentForm.paid_on}
                                    onChange={(value) =>
                                        updatePayment('paid_on', value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="payment-total">
                                    Total payment (EGP)
                                </FieldLabel>
                                <Input
                                    id="payment-total"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={paymentForm.payment_egp}
                                    onChange={(event) =>
                                        updatePayment(
                                            'payment_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="payment-principal">
                                    Principal paid (EGP)
                                </FieldLabel>
                                <Input
                                    id="payment-principal"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={paymentForm.principal_egp}
                                    onChange={(event) =>
                                        updatePayment(
                                            'principal_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="payment-interest">
                                    Interest paid (EGP)
                                </FieldLabel>
                                <Input
                                    id="payment-interest"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={paymentForm.interest_egp}
                                    onChange={(event) =>
                                        updatePayment(
                                            'interest_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="payment-fees">
                                    Fees (EGP)
                                </FieldLabel>
                                <Input
                                    id="payment-fees"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={paymentForm.fees_egp}
                                    onChange={(event) =>
                                        updatePayment(
                                            'fees_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="payment-balance">
                                    Balance after payment (EGP)
                                </FieldLabel>
                                <Input
                                    id="payment-balance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={paymentForm.balance_after_egp}
                                    onChange={(event) =>
                                        updatePayment(
                                            'balance_after_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="payment-source">
                                    Source
                                </FieldLabel>
                                <Input
                                    id="payment-source"
                                    required
                                    value={paymentForm.source}
                                    onChange={(event) =>
                                        updatePayment(
                                            'source',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="payment-notes">
                                    Notes
                                </FieldLabel>
                                <Textarea
                                    id="payment-notes"
                                    rows={3}
                                    value={paymentForm.notes}
                                    onChange={(event) =>
                                        updatePayment(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Statement reference or correction note"
                                />
                            </Field>
                        </FieldGroup>
                        <div className="flex justify-end gap-2">
                            <FormModalClose>
                                <Button type="button" variant="ghost">
                                    Cancel
                                </Button>
                            </FormModalClose>
                            <Button type="submit">Save payment record</Button>
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

function emptyPaymentForm() {
    return {
        paid_on: new Date().toISOString().slice(0, 10),
        payment_egp: '',
        principal_egp: '',
        interest_egp: '',
        fees_egp: '',
        balance_after_egp: '',
        source: 'statement',
        notes: '',
    };
}

function DebtDetails({ liability }: { liability: Liability }) {
    const summary = liability.recordedPaymentSummary;
    const baseInterest = liability.payoffProjection.estimatedTotalInterest;

    return (
        <div className="flex flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-4">
                <DebtMetric
                    label="Recorded principal"
                    value={summary.principalPaid}
                />
                <DebtMetric
                    label="Recorded interest"
                    value={summary.interestPaid}
                />
                <DebtMetric
                    label="Monthly interest estimate"
                    value={liability.payoffProjection.estimatedMonthlyInterest}
                />
                <DebtMetric
                    label="Monthly principal estimate"
                    value={liability.payoffProjection.estimatedMonthlyPrincipal}
                />
            </div>
            <div>
                <p className="text-sm font-semibold">
                    Extra monthly payment scenarios
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                    These are planning scenarios. They do not change the
                    liability until you update the actual payment or record a
                    statement.
                </p>
                <div className="mt-3 grid gap-2 sm:grid-cols-3">
                    {liability.payoffProjection.extraPaymentScenarios.map(
                        (scenario) => (
                            <div
                                key={scenario.extraMonthlyPayment}
                                className="rounded-lg border bg-background p-3 text-xs"
                            >
                                <p className="font-semibold">
                                    +{formatEGP(scenario.extraMonthlyPayment)} /
                                    month
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    Payoff:{' '}
                                    {scenario.estimatedPayoffOn ??
                                        'not projected'}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    Interest saved:{' '}
                                    {formatEGP(
                                        Math.max(
                                            0,
                                            baseInterest -
                                                scenario.estimatedTotalInterest,
                                        ),
                                    )}
                                </p>
                            </div>
                        ),
                    )}
                </div>
            </div>
            {liability.paymentRecords.length > 0 && (
                <div>
                    <p className="text-sm font-semibold">
                        Recent lender records
                    </p>
                    <div className="mt-2 flex flex-col gap-1 text-xs text-muted-foreground">
                        {liability.paymentRecords.map((record) => (
                            <div
                                key={record.id}
                                className="flex flex-wrap justify-between gap-2"
                            >
                                <span>
                                    {record.paidOn} · {record.source}
                                </span>
                                <span>
                                    {formatEGP(record.payment)} · principal{' '}
                                    {formatEGP(record.principal)} · interest{' '}
                                    {formatEGP(record.interest)}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function DebtMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg bg-background p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-semibold">{formatEGP(value)}</p>
        </div>
    );
}
