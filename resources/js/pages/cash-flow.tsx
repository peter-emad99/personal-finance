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
import { DatePicker, MonthPicker } from '@/components/date-picker';
import { FormModal, FormModalClose } from '@/components/form';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
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

type Flow = {
    id: number;
    type: string;
    category: string;
    description?: string | null;
    transaction_category_id?: number | null;
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
    categories,
    budgetCategories,
}: {
    flows: Flow[];
    summary: { income: number; expenses: number };
    month: string;
    categories: {
        id: number;
        name: string;
        kind: string;
        budgetCategoryId?: number | null;
        budgetCategoryName?: string | null;
    }[];
    budgetCategories: { id: number; name: string }[];
}) {
    const firstBudgetCategoryId = budgetCategories[0]
        ? String(budgetCategories[0].id)
        : '';
    const firstExpenseCategory = categories.find(
        (category) =>
            category.kind === 'expense' &&
            String(category.budgetCategoryId ?? '') === firstBudgetCategoryId,
    );
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Flow | null>(null);
    const [form, setForm] = useState({
        type: 'expense',
        category: firstExpenseCategory?.name ?? '',
        transaction_category_id: firstExpenseCategory
            ? String(firstExpenseCategory.id)
            : '',
        budget_category_id: firstBudgetCategoryId,
        description: '',
        amount: '',
        currency: 'EGP',
        exchange_rate: '1',
        occurred_on: month,
        notes: '',
    });
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const openEditor = (flow?: Flow) => {
        setEditing(flow ?? null);
        setForm(
            flow
                ? {
                      type: flow.type === 'income' ? 'income' : 'expense',
                      category: flow.category,
                      transaction_category_id: flow.transaction_category_id
                          ? String(flow.transaction_category_id)
                          : '',
                      budget_category_id: flow.transaction_category_id
                          ? String(
                                categories.find(
                                    (category) =>
                                        category.id ===
                                        flow.transaction_category_id,
                                )?.budgetCategoryId ?? '',
                            )
                          : '',
                      description: flow.description ?? '',
                      amount: String(flow.amount ?? flow.amount_egp),
                      currency: flow.currency,
                      exchange_rate: String(flow.exchange_rate ?? 1),
                      occurred_on: flow.occurred_on,
                      notes: flow.notes ?? '',
                  }
                : {
                      type: 'expense',
                      category: firstExpenseCategory?.name ?? '',
                      transaction_category_id: firstExpenseCategory
                          ? String(firstExpenseCategory.id)
                          : '',
                      budget_category_id: firstBudgetCategoryId,
                      description: '',
                      amount: '',
                      currency: 'EGP',
                      exchange_rate: '1',
                      occurred_on: month,
                      notes: '',
                  },
        );
        setOpen(true);
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const selectedCategory = categories.find(
            (category) => category.id === Number(form.transaction_category_id),
        );
        const payload = {
            ...form,
            category: selectedCategory?.name ?? form.category,
        };
        const options = {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                setForm({ ...form, amount: '', notes: '' });
            },
        };

        if (editing) {
            router.put(`/cash-flow/${editing.id}`, payload, options);
        } else {
            router.post('/cash-flow', payload, options);
        }
    };
    const detailCategories =
        form.type === 'income'
            ? categories.filter((category) => category.kind === 'income')
            : categories.filter(
                  (category) =>
                      category.kind === 'expense' &&
                      (!form.budget_category_id ||
                          String(category.budgetCategoryId ?? '') ===
                              form.budget_category_id),
              );
    const changeType = (type: string) => {
        const isIncome = type === 'income';
        const parentId = isIncome
            ? ''
            : form.budget_category_id ||
              (budgetCategories[0] ? String(budgetCategories[0].id) : '');
        const nextCategory = (
            isIncome
                ? categories.filter((category) => category.kind === 'income')
                : categories.filter(
                      (category) =>
                          category.kind === 'expense' &&
                          String(category.budgetCategoryId ?? '') === parentId,
                  )
        )[0];
        setForm((current) => ({
            ...current,
            type,
            budget_category_id: parentId,
            transaction_category_id: nextCategory
                ? String(nextCategory.id)
                : '',
            category: nextCategory?.name ?? '',
        }));
    };

    return (
        <AppShell title="Income & expenses">
            <PageHeader
                eyebrow="Monthly rhythm"
                title="Income & expenses"
                description="Record actual money received and spent. These entries update the selected month’s plan actuals and dashboard totals."
                action={
                    <div className="flex items-center gap-2">
                        <MonthPicker
                            ariaLabel="Choose month"
                            className="w-36"
                            value={month.slice(0, 7)}
                            onChange={(value) =>
                                router.get('/cash-flow', { month: value })
                            }
                        />
                        <Button onClick={() => openEditor()}>
                            + Add entry
                        </Button>
                    </div>
                }
            />
            <div className="mb-4 grid gap-4 sm:grid-cols-3">
                <SummaryCard
                    label="Income"
                    value={summary.income}
                    color="green"
                />
                <SummaryCard
                    label="Expenses"
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
                    meta={`${new Date(month).toLocaleDateString('en-EG', { month: 'long', year: 'numeric' })} · actual entries only`}
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
                                            {flow.type === 'income'
                                                ? 'Income'
                                                : 'Expense'}
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
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                className="h-7 px-2 text-xs"
                                                onClick={() => openEditor(flow)}
                                            >
                                                Edit
                                            </Button>
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
                                        </div>
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
                    title={editing ? 'Edit actual entry' : 'Add actual entry'}
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
                                        changeType(String(value ?? ''))
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
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            {form.type !== 'income' && (
                                <Field>
                                    <FieldLabel htmlFor="cash-flow-budget-category">
                                        Expense category
                                    </FieldLabel>
                                    <Select
                                        value={form.budget_category_id}
                                        onValueChange={(value) => {
                                            const parentId = String(
                                                value ?? '',
                                            );
                                            const nextCategory =
                                                categories.find(
                                                    (category) =>
                                                        category.kind ===
                                                            'expense' &&
                                                        String(
                                                            category.budgetCategoryId ??
                                                                '',
                                                        ) === parentId,
                                                );
                                            setForm((current) => ({
                                                ...current,
                                                budget_category_id: parentId,
                                                transaction_category_id:
                                                    nextCategory
                                                        ? String(
                                                              nextCategory.id,
                                                          )
                                                        : '',
                                                category:
                                                    nextCategory?.name ?? '',
                                            }));
                                        }}
                                    >
                                        <SelectTrigger
                                            id="cash-flow-budget-category"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Choose expense category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectLabel>
                                                    Planning expense categories
                                                </SelectLabel>
                                                {budgetCategories.map(
                                                    (category) => (
                                                        <SelectItem
                                                            key={category.id}
                                                            value={String(
                                                                category.id,
                                                            )}
                                                        >
                                                            {category.name}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </Field>
                            )}
                            <Field>
                                <FieldLabel htmlFor="cash-flow-transaction-category">
                                    Detailed category
                                </FieldLabel>
                                <Select
                                    required
                                    value={form.transaction_category_id}
                                    onValueChange={(value) => {
                                        const selected = detailCategories.find(
                                            (category) =>
                                                category.id === Number(value),
                                        );
                                        setForm((current) => ({
                                            ...current,
                                            transaction_category_id: String(
                                                value ?? '',
                                            ),
                                            category:
                                                selected?.name ??
                                                current.category,
                                        }));
                                    }}
                                >
                                    <SelectTrigger
                                        id="cash-flow-transaction-category"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Choose detailed category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>
                                                {form.type === 'income'
                                                    ? 'Income categories'
                                                    : 'Categories under selected expense category'}
                                            </SelectLabel>
                                            {detailCategories.map(
                                                (category) => (
                                                    <SelectItem
                                                        key={category.id}
                                                        value={String(
                                                            category.id,
                                                        )}
                                                    >
                                                        {category.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                {!detailCategories.length && (
                                    <p className="text-xs text-muted-foreground">
                                        Create a detailed category first from
                                        Transaction categories.
                                    </p>
                                )}
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="cash-flow-description">
                                    Description / merchant
                                </FieldLabel>
                                <Input
                                    id="cash-flow-description"
                                    value={form.description}
                                    onChange={(e) =>
                                        update('description', e.target.value)
                                    }
                                    placeholder="Vodafone, electricity bill, client payment..."
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
                                <Textarea
                                    id="cash-flow-notes"
                                    rows={3}
                                    value={form.notes}
                                    onChange={(e) =>
                                        update('notes', e.target.value)
                                    }
                                    placeholder="Optional context"
                                />
                            </Field>
                        </FieldGroup>
                        <div className="mt-6 flex justify-end gap-2">
                            <FormModalClose>
                                <Button type="button" variant="ghost">
                                    Cancel
                                </Button>
                            </FormModalClose>
                            <Button type="submit">
                                {editing ? 'Save changes' : 'Save entry'}
                            </Button>
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
