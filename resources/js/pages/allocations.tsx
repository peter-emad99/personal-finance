import { router } from '@inertiajs/react';
import { Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Field, FieldLabel } from '@/components/ui/field';
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
import { formatCompactEGP, formatEGP } from '@/types/finance';

type Item = {
    bucketId: number;
    bucketName: string;
    assetId?: number | null;
    assetName?: string | null;
    planned: number;
    actual: number;
    assetTarget?: string | null;
    allocationPercent?: number | null;
    actualSource?: string;
    actualSyncedAt?: string | null;
};
type IncomeItem = {
    id?: number;
    budgetRuleId?: number | null;
    name: string;
    planned: number;
    actual: number;
};
type ExpenseItem = {
    categoryId: number;
    categoryName: string;
    planned: number;
    actual: number;
    actualSource?: string;
};
type Plan = {
    id: number;
    templateId?: number | null;
    templateName?: string | null;
    status?: 'open' | 'closed' | string;
    closedAt?: string | null;
    income: number;
    expenses: number;
    incomeItems?: IncomeItem[];
    items: Item[];
    expenseItems: ExpenseItem[];
} | null;
type Defaults = {
    income: number;
    incomeItems: IncomeItem[];
    expenses: number;
    source: 'saved_plan' | 'plan_template';
    items: {
        bucketId?: number | null;
        label: string;
        amount: number;
        actual: number;
        assetId?: number | null;
        assetName?: string | null;
        assetTarget?: string | null;
        allocationPercent?: number | null;
    }[];
    expenseItems: ExpenseItem[];
};
type ActualTracking = {
    source: string;
    transactionCount: number;
    actuals: Record<string, number>;
    itemActuals: Record<string, number>;
    expenseActuals: Record<string, number>;
    summary: {
        income: number;
        essentialExpenses: number;
        lifestyleExpenses: number;
        commitments: number;
        otherExpenses: number;
        debtPayments: number;
        invested: number;
    };
    unmappedPurposeAmount: number;
    unmappedExpenseAmount: number;
} | null;

const plannedAmount = (item: Item, available: number) =>
    item.allocationPercent !== null && item.allocationPercent !== undefined
        ? Math.round((Math.max(0, available) * item.allocationPercent) / 100 * 100) / 100
        : Number(item.planned || 0);

export default function Allocations({
    month,
    plan,
    defaults,
    actualTracking,
    expenseCategories,
    assets,
    templates,
    selectedTemplateId,
    canChooseTemplate,
}: {
    month: string;
    plan: Plan;
    defaults: Defaults;
    actualTracking: ActualTracking;
    expenseCategories: { id: number; name: string }[];
    assets: { id: number; name: string; type: string; buckets: { id: number; name: string; goalName?: string | null }[] }[];
    templates: { id: number; name: string; isDefault: boolean }[];
    selectedTemplateId: number;
    canChooseTemplate: boolean;
}) {
    const initialItems: Item[] =
        plan?.items && plan.items.length > 0
            ? plan.items
            : defaults.items.filter((item) => item.bucketId).map((item) => ({
                  bucketId: item.bucketId as number,
                  bucketName: item.label.split(' → ').pop() ?? item.label,
                  assetId: item.assetId ?? null,
                  assetName: item.assetName ?? null,
                  planned: item.amount ?? 0,
                  actual: item.actual ?? 0,
                  assetTarget: item.assetTarget ?? item.assetName ?? null,
                  allocationPercent: item.allocationPercent ?? null,
              }));
    const initialIncomeItems: IncomeItem[] = plan?.incomeItems?.length
        ? plan.incomeItems
        : defaults.incomeItems.length
          ? defaults.incomeItems
          : [{ name: 'Monthly income', planned: defaults.income, actual: 0 }];
    const [incomeItems, setIncomeItems] = useState(initialIncomeItems);
    const income = useMemo(
        () => incomeItems.reduce((sum, item) => sum + Number(item.planned || 0), 0),
        [incomeItems],
    );
    const [items, setItems] = useState(initialItems);
    const [expenseItems, setExpenseItems] = useState<ExpenseItem[]>(() =>
        plan?.expenseItems?.length
            ? plan.expenseItems
            : defaults.expenseItems.length
              ? defaults.expenseItems
              : expenseCategories.map((category) => ({
                    categoryId: category.id,
                    categoryName: category.name,
                    planned: 0,
                    actual: 0,
                })),
    );
    const expensePlannedTotal = useMemo(
        () => expenseItems.reduce((sum, item) => sum + Number(item.planned || 0), 0),
        [expenseItems],
    );
    const available = Number(income || 0) - expensePlannedTotal;
    const availableToAllocate = Math.max(0, available);
    const plannedTotal = useMemo(
        () => items.reduce((sum, item) => sum + plannedAmount(item, availableToAllocate), 0),
        [items, availableToAllocate],
    );
    const percentageTotal = useMemo(
        () => items.reduce((sum, item) => sum + Number(item.allocationPercent ?? 0), 0),
        [items],
    );
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/allocations', {
            month,
            plan_template_id: selectedTemplateId,
            planned_income_egp: income,
            planned_expenses_egp: expensePlannedTotal,
            income_items: incomeItems.map((item) => ({
                budget_rule_id: item.budgetRuleId ?? null,
                name: item.name,
                planned_amount_egp: item.planned,
                actual_amount_egp: item.actual,
            })),
            items: items.map((item) => ({
                bucket_id: item.bucketId,
                asset_id: item.assetId ?? null,
                planned_amount_egp: plannedAmount(item, availableToAllocate),
                actual_amount_egp: item.actual,
                asset_target: item.assetTarget || null,
                allocation_percent: item.allocationPercent ?? null,
            })),
            expenses: expenseItems.map((item) => ({
                category_id: item.categoryId,
                planned_amount_egp: item.planned,
                actual_amount_egp: item.actual,
            })),
        });
    };

    const setAsset = (index: number, value: string | null) => {
        const asset = assets.find((item) => item.id === Number(value));
        setItems((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, assetId: asset?.id ?? null, assetName: asset?.name ?? null, assetTarget: asset?.name ?? null, bucketId: asset?.buckets[0]?.id ?? row.bucketId, bucketName: asset?.buckets[0]?.name ?? row.bucketName } : row));
    };

    const setExpenseCategory = (index: number, value: string | null) => {
        if (!value) {
            return;
        }

        const category = expenseCategories.find((item) => item.id === Number(value));

        if (!category) {
            return;
        }

        setExpenseItems((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, categoryId: category.id, categoryName: category.name } : row));
    };

    const addExpenseLine = () => {
        const usedCategoryIds = new Set(expenseItems.map((item) => item.categoryId));
        const category = expenseCategories.find((item) => !usedCategoryIds.has(item.id));

        if (category) {
            setExpenseItems((current) => [...current, { categoryId: category.id, categoryName: category.name, planned: 0, actual: 0 }]);
        }
    };

    return (
        <AppShell title="Allocations">
            <PageHeader
                eyebrow="Give every pound a job"
                title="Monthly allocation"
                description="Plan the month before it happens, then compare the plan with what actually moved into each bucket."
            />
            <form onSubmit={submit}>
                <fieldset disabled={plan?.status === 'closed'} className="contents">
                <div className="grid gap-4 xl:grid-cols-[1fr_1.35fr]">
                    <Card>
                        <CardHeader
                            title="Monthly inputs"
                            meta={`${new Date(month).toLocaleDateString(
                                'en-EG',
                                {
                                    month: 'long',
                                    year: 'numeric',
                                },
                            )} · ${plan ? 'Saved plan' : 'Monthly rules'}`}
                        />
                        <div className="flex flex-col gap-4 p-5">
                            <Field>
                                <FieldLabel>Plan template</FieldLabel>
                                <Select disabled={!canChooseTemplate} value={String(selectedTemplateId)} onValueChange={(value) => router.get('/allocations', { month, template_id: value })}>
                                    <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectGroup><SelectLabel>Plan templates</SelectLabel>{templates.map((template) => <SelectItem key={template.id} value={String(template.id)}>{template.name}{template.isDefault ? ' · default' : ''}</SelectItem>)}</SelectGroup></SelectContent>
                                </Select>
                                <p className="text-xs text-muted-foreground">{canChooseTemplate ? 'A new monthly snapshot starts from this template. Changes here stay in this month.' : 'This saved plan is a protected snapshot of the template selected when the month was created.'}</p>
                            </Field>
                            <Field>
                                <FieldLabel>Income sources</FieldLabel>
                                <div className="flex flex-col gap-2 rounded-xl border p-3">
                                    {incomeItems.map((item, index) => (
                                        <div key={item.id ?? `income-${index}`} className="grid items-end gap-2 sm:grid-cols-[1fr_160px_40px]">
                                            <Field><FieldLabel>Source</FieldLabel><Input value={item.name} onChange={(event) => setIncomeItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, name: event.target.value } : row))} /></Field>
                                            <Field><FieldLabel>Planned (EGP)</FieldLabel><Input type="number" min="0" value={String(item.planned)} onChange={(event) => setIncomeItems((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, planned: Number(event.target.value) } : row))} /></Field>
                                            <Button type="button" variant="ghost" size="icon" aria-label="Remove income source" onClick={() => setIncomeItems((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 /></Button>
                                        </div>
                                    ))}
                                    <Button type="button" variant="outline" className="self-start" onClick={() => setIncomeItems((rows) => [...rows, { name: 'New income source', planned: 0, actual: 0 }])}><Plus data-icon="inline-start" />Add income source</Button>
                                </div>
                                <p className="text-xs text-muted-foreground">Planned income is the sum of these sources. The monthly snapshot is the source of truth for this month.</p>
                                {actualTracking?.source === 'confirmed_ledger' && (
                                    <Badge className="mt-2" variant="outline">Actual income: {formatCompactEGP(actualTracking.summary.income)} · synced from ledger</Badge>
                                )}
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="planned-income-total">Planned income total (EGP)</FieldLabel>
                                <Input id="planned-income-total" type="number" value={String(income)} readOnly />
                            </Field>
                            <div className="rounded-2xl bg-sidebar p-5 text-sidebar-foreground">
                                <p className="text-xs text-sidebar-foreground/70">
                                    Available to allocate
                                </p>
                                <p className="mt-2 text-3xl font-semibold">
                                    {formatEGP(available)}
                                </p>
                                <p className="mt-2 text-xs leading-5 text-sidebar-foreground/70">
                                    Income − expenses − obligations. This is the
                                    ceiling for your monthly plan.
                                </p>
                            </div>
                            <div className="rounded-xl bg-muted p-4">
                                <div className="flex justify-between text-xs">
                                    <span className="font-semibold text-muted-foreground">
                                        Plan coverage
                                    </span>
                                    <span className="font-semibold text-muted-foreground">
                                        {formatCompactEGP(plannedTotal)} /{' '}
                                        {formatCompactEGP(available)}
                                    </span>
                                </div>
                                <div className="mt-3">
                                    <Progress
                                        value={
                                            availableToAllocate > 0
                                                ? (plannedTotal / availableToAllocate) *
                                                  100
                                                : 0
                                        }
                                        color={
                                            plannedTotal > availableToAllocate
                                                ? 'var(--chart-3)'
                                                : 'var(--chart-2)'
                                        }
                                    />
                                </div>
                                <p className="mt-2 text-[11px] text-muted-foreground">
                                    {plannedTotal > availableToAllocate
                                        ? `${formatCompactEGP(plannedTotal - availableToAllocate)} over your available cash flow`
                                        : `${formatCompactEGP(Math.max(0, availableToAllocate - plannedTotal))} still unassigned`}
                                </p>
                            </div>
                            <div className="rounded-xl border p-4">
                                <div className="flex justify-between text-xs">
                                    <span className="font-semibold text-muted-foreground">
                                        Monthly expense snapshot
                                    </span>
                                    <span className="font-semibold text-muted-foreground">
                                        {formatCompactEGP(expensePlannedTotal)}
                                    </span>
                                </div>
                                <p className="mt-2 text-xs text-muted-foreground">
                                    The selected template provides the starting point. You can customize categories and planned amounts for this month without changing future months.
                                </p>
                            </div>
                        </div>
                    </Card>
                    <Card>
                            <CardHeader
                            title="This month's plan"
                            meta="Review expenses and assign the remaining cash"
                            action={
                                plan && actualTracking?.source === 'confirmed_ledger' ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/allocations/${plan.id}/sync-actuals`,
                                            )
                                        }
                                    >
                                        <RefreshCw data-icon="inline-start" />
                                        Sync actuals
                                    </Button>
                                ) : undefined
                            }
                        />
                        {actualTracking?.source === 'confirmed_ledger' && (
                            <Alert className="mx-5 mb-4">
                                <RefreshCw />
                                <AlertTitle>
                                    Actuals linked to confirmed ledger
                                </AlertTitle>
                                <AlertDescription>
                                    {actualTracking.transactionCount} confirmed transactions update this month’s plan where their category or purpose bucket is explicitly linked.
                                    {actualTracking.unmappedPurposeAmount > 0 && ` ${formatCompactEGP(actualTracking.unmappedPurposeAmount)} of purpose-directed money still needs a bucket.`}
                                    {actualTracking.unmappedExpenseAmount > 0 && ` ${formatCompactEGP(actualTracking.unmappedExpenseAmount)} of outflow still needs a matching plan category.`}
                                </AlertDescription>
                            </Alert>
                        )}
                        {!plan && defaults.source === 'plan_template' && (
                            <div className="px-5">
                                <Badge variant="secondary">
                                    Suggested from the selected plan template
                                </Badge>
                            </div>
                        )}
                        <div className="border-b border-border p-5">
                                <div className="mb-3 flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-semibold">Expenses</p>
                                    <p className="text-xs text-muted-foreground">Start from the template, then override this month’s snapshot when reality calls for it.</p>
                                </div>
                                <Badge variant="outline" className="shrink-0">{formatCompactEGP(expensePlannedTotal)} planned</Badge>
                            </div>
                            <p className="mb-3 text-xs text-muted-foreground">Changes here affect this monthly plan only. Edit the plan template when you want the default to change for future months.</p>
                            <div className="flex flex-col gap-2">
                                {expenseItems.map((item, index) => (
                                    <div key={`${item.categoryId}-${index}`} className="grid items-end gap-3 rounded-xl border p-3 sm:grid-cols-[minmax(0,1fr)_150px_150px_40px]">
                                        <div>
                                            <Field>
                                                <FieldLabel htmlFor={`expense-category-${index}`}>Category</FieldLabel>
                                                <Select value={String(item.categoryId)} onValueChange={(value) => setExpenseCategory(index, value)}>
                                                    <SelectTrigger id={`expense-category-${index}`} className="w-full"><SelectValue placeholder="Choose category" /></SelectTrigger>
                                                    <SelectContent><SelectGroup><SelectLabel>Expense categories</SelectLabel>{expenseCategories.map((category) => <SelectItem key={category.id} value={String(category.id)} disabled={expenseItems.some((row, rowIndex) => rowIndex !== index && row.categoryId === category.id)}>{category.name}</SelectItem>)}</SelectGroup></SelectContent>
                                                </Select>
                                            </Field>
                                            {item.actualSource === 'confirmed_ledger' && <Badge className="mt-2" variant="outline">From confirmed ledger</Badge>}
                                        </div>
                                        <Field>
                                            <FieldLabel htmlFor={`expense-planned-${item.categoryId}`}>Planned</FieldLabel>
                                            <Input id={`expense-planned-${item.categoryId}`} type="number" value={String(item.planned)} onChange={(e) => setExpenseItems((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, planned: Number(e.target.value) } : row))} />
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor={`expense-actual-${item.categoryId}`}>Actual</FieldLabel>
                                            <Input id={`expense-actual-${item.categoryId}`} type="number" value={String(item.actual)} onChange={(e) => setExpenseItems((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, actual: Number(e.target.value) } : row))} />
                                        </Field>
                                        <Button type="button" variant="ghost" size="icon" aria-label={`Remove ${item.categoryName} expense`} onClick={() => setExpenseItems((current) => current.filter((_, rowIndex) => rowIndex !== index))}><Trash2 /></Button>
                                    </div>
                                ))}
                            </div>
                            <Button type="button" variant="outline" className="mt-3" disabled={expenseItems.length >= expenseCategories.length} onClick={addExpenseLine}><Plus data-icon="inline-start" />Add expense line</Button>
                        </div>
                        <div className="border-b border-border p-5">
                            <div className="mb-3 flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-semibold">Remaining savings allocation</p>
                                    <p className="text-xs text-muted-foreground">Choose the asset target and the purpose bucket. Planned amounts use the percentage of available cash.</p>
                                </div>
                                <Badge variant={percentageTotal > 100 ? 'destructive' : 'outline'}>{percentageTotal.toFixed(1)}% assigned</Badge>
                            </div>
                        <div className="divide-y divide-border">
                            {items.map((item, index) => {
 const asset = assets.find((candidate) => candidate.id === item.assetId);

 return (
                                <div
                                    key={`${item.assetId ?? 'unlinked'}-${item.bucketId}-${index}`}
                                    className="grid items-center gap-3 p-4 sm:grid-cols-[1.2fr_1fr_1fr_110px_150px_150px]"
                                >
                                    <div>
                                        <p className="text-sm font-semibold text-muted-foreground">{item.bucketName}</p>
                                        <p className="mt-1 text-xs text-muted-foreground">Purpose bucket</p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {item.actual
                                                ? `${Math.round((item.actual / Math.max(1, item.planned)) * 100)}% of plan moved`
                                                : `${availableToAllocate > 0 ? Math.round((item.planned / availableToAllocate) * 100) : 0}% of available cash · no actual recorded yet`}
                                        </p>
                                        {item.actualSource === 'confirmed_ledger' && (
                                            <Badge className="mt-2" variant="outline">
                                                From confirmed ledger
                                            </Badge>
                                        )}
                                    </div>
                                    <Field>
                                        <FieldLabel>Asset</FieldLabel>
                                        <Select value={item.assetId ? String(item.assetId) : ''} onValueChange={(value) => setAsset(index, value)}>
                                            <SelectTrigger className="w-full"><SelectValue placeholder="Choose asset" /></SelectTrigger>
                                            <SelectContent><SelectGroup><SelectLabel>Owned assets</SelectLabel>{assets.map((candidate) => <SelectItem key={candidate.id} value={String(candidate.id)}>{candidate.name} · {candidate.type}</SelectItem>)}</SelectGroup></SelectContent>
                                        </Select>
                                    </Field>
                                    <Field>
                                        <FieldLabel>Assigned bucket</FieldLabel>
                                        <Select disabled={!asset} value={item.bucketId ? String(item.bucketId) : ''} onValueChange={(value) => setItems((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, bucketId: Number(value), bucketName: asset?.buckets.find((bucket) => bucket.id === Number(value))?.name ?? row.bucketName } : row))}>
                                            <SelectTrigger className="w-full"><SelectValue placeholder={asset ? 'Choose bucket' : 'Choose asset first'} /></SelectTrigger>
                                            <SelectContent><SelectGroup><SelectLabel>Asset buckets</SelectLabel>{(asset?.buckets ?? []).map((bucket) => <SelectItem key={bucket.id} value={String(bucket.id)}>{bucket.name}{bucket.goalName ? ` · ${bucket.goalName}` : ''}</SelectItem>)}</SelectGroup></SelectContent>
                                        </Select>
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor={`percent-${index}`}>%</FieldLabel>
                                        <Input id={`percent-${index}`} type="number" min="0" max="100" step="0.1" value={item.allocationPercent === null || item.allocationPercent === undefined ? '' : String(item.allocationPercent)} onChange={(e) => setItems((current) => current.map((row, rowIndex) => rowIndex === index ? { ...row, allocationPercent: e.target.value === '' ? null : Number(e.target.value) } : row))} />
                                    </Field>
                                    <div>
                                        <p className="text-xs text-muted-foreground">Planned</p>
                                        <p className="mt-2 text-sm font-semibold">{formatCompactEGP(plannedAmount(item, available))}</p>
                                    </div>
                                    <Field>
                                        <FieldLabel
                                            htmlFor={`actual-${index}`}
                                        >
                                            Actual
                                        </FieldLabel>
                                        <Input
                                            id={`actual-${index}`}
                                            type="number"
                                            value={String(item.actual)}
                                            onChange={(e) =>
                                                setItems((current) =>
                                                    current.map(
                                                        (row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      actual: Number(
                                                                          e
                                                                              .target
                                                                              .value,
                                                                      ),
                                                                  }
                                                                : row,
                                                    ),
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                            );
})}
                        </div>
                        <Button type="button" variant="outline" className="mt-3" onClick={() => setItems((rows) => [...rows, { bucketId: assets[0]?.buckets[0]?.id ?? 0, bucketName: assets[0]?.buckets[0]?.name ?? '', assetId: assets[0]?.id ?? null, assetName: assets[0]?.name ?? null, assetTarget: assets[0]?.name ?? null, planned: 0, actual: 0, allocationPercent: 0 }])}><Plus data-icon="inline-start" />Add allocation line</Button>
                        </div>
                        <div className="flex justify-end border-t border-border p-5">
                            <Button type="submit">Save monthly plan</Button>
                        </div>
                    </Card>
                </div>
                </fieldset>
                {plan?.status === 'closed' && <div className="mt-4 flex justify-end"><Badge variant="secondary">Closed snapshot · {plan.closedAt ? new Date(plan.closedAt).toLocaleDateString('en-EG') : 'protected'}</Badge></div>}
                {plan && plan.status !== 'closed' && <div className="mt-4 flex justify-end"><Button type="button" variant="outline" onClick={() => router.post(`/allocations/${plan.id}/close`)}>Close month & protect history</Button></div>}
            </form>
        </AppShell>
    );
}
