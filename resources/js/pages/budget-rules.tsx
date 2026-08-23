import { router } from '@inertiajs/react';
import { Check, Copy, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCompactEGP } from '@/types/finance';

type Template = { id: number; name: string; description?: string | null; isDefault: boolean; monthlyPlanCount: number };
type IncomeRule = { id?: number; name: string; amount: number | null; percent: number | null };
type ExpenseRule = { id?: number; name: string; categoryId: number; amount: number | null; percent: number | null; generatedFromCommitment?: boolean; commitmentName?: string | null };
type Asset = { id: number; name: string; type: string; buckets: { id: number; name: string; goalName?: string | null }[] };
type AllocationRule = { id?: number; assetId: number | null; bucketId: number; bucketName?: string; assetTarget?: string | null; percent: number; legacyUnlinked?: boolean };

type Props = {
    templates: Template[];
    editing: boolean;
    selectedTemplateId: number;
    templateName: string;
    templateDescription?: string | null;
    incomeRules: IncomeRule[];
    expenseRules: ExpenseRule[];
    allocationRules: AllocationRule[];
    assets: Asset[];
    categories: { id: number; name: string; isDefault: boolean }[];
    monthlyIncome: number;
};

export default function BudgetRules({
    templates,
    editing,
    selectedTemplateId,
    templateName,
    templateDescription,
    incomeRules,
    expenseRules,
    allocationRules,
    assets,
    categories,
    monthlyIncome,
}: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [createName, setCreateName] = useState('');
    const [createDescription, setCreateDescription] = useState('');
    const [name, setName] = useState(templateName);
    const [description, setDescription] = useState(templateDescription ?? '');
    const [income, setIncome] = useState(incomeRules);
    const [expenses, setExpenses] = useState(expenseRules);
    const [allocations, setAllocations] = useState(allocationRules);
    const allocationTotal = useMemo(() => allocations.reduce((sum, rule) => sum + Number(rule.percent || 0), 0), [allocations]);

    useEffect(() => {
        // The editor must reset its draft when Inertia swaps the selected template.
        // eslint-disable-next-line react-hooks/set-state-in-effect
        setName(templateName);
        setDescription(templateDescription ?? '');
        setIncome(incomeRules);
        setExpenses(expenseRules);
        setAllocations(allocationRules);
    }, [selectedTemplateId, templateName, templateDescription, incomeRules, expenseRules, allocationRules]);

    const createTemplate = (event: React.FormEvent) => {
        event.preventDefault();

        if (!createName.trim()) {
return;
}

        router.post('/monthly-rules/templates', { name: createName.trim(), description: createDescription.trim() || null }, { onSuccess: () => setCreateOpen(false) });
    };

    const saveDetails = (event: React.FormEvent) => {
        event.preventDefault();
        router.put(`/monthly-rules/templates/${selectedTemplateId}`, { name, description });
    };

    const saveRules = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/monthly-rules', { template_id: selectedTemplateId, income_rules: income, expense_rules: expenses, allocation_rules: allocations });
    };

    const changeAsset = (index: number, value: string | null) => {
        const asset = assets.find((item) => item.id === Number(value));
        setAllocations((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, assetId: asset?.id ?? null, bucketId: asset?.buckets[0]?.id ?? 0, assetTarget: asset?.name } : row));
    };

    return (
        <AppShell title="Plan templates">
            <PageHeader
                eyebrow="Reusable monthly blueprints"
                title={editing ? `Edit template: ${templateName}` : 'Plan templates'}
                description={editing ? 'Update this template’s rules. Existing monthly snapshots stay unchanged.' : 'Manage reusable income, expense, and savings allocation templates. Each monthly plan is a separate snapshot.'}
                action={editing ? <Button type="button" variant="outline" onClick={() => router.get('/monthly-rules')}>Back to templates</Button> : <Button href="/monthly-plans">Monthly plans & history</Button>}
            />

            {!editing ? (
                <Card>
                    <CardHeader
                        title="Plan templates"
                        meta="Create, edit, duplicate, archive, or choose the default template used for new months."
                        action={<Button type="button" onClick={() => setCreateOpen(true)}><Plus data-icon="inline-start" />Create template</Button>}
                    />
                    <Table>
                        <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Notes</TableHead><TableHead>Default</TableHead><TableHead>Monthly plans</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader>
                        <TableBody>
                            {templates.map((template) => (
                                <TableRow key={template.id}>
                                    <TableCell className="font-medium">{template.name}</TableCell>
                                    <TableCell className="max-w-md truncate text-muted-foreground">{template.description || '—'}</TableCell>
                                    <TableCell>{template.isDefault ? <Badge variant="secondary">Default</Badge> : <Button type="button" variant="ghost" size="sm" onClick={() => router.post(`/monthly-rules/templates/${template.id}/default`)}><Check data-icon="inline-start" />Make default</Button>}</TableCell>
                                    <TableCell>{template.monthlyPlanCount}</TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-2">
                                            <Button type="button" variant="outline" size="sm" onClick={() => router.get(`/monthly-rules/templates/${template.id}/edit`)}><Pencil data-icon="inline-start" />Edit</Button>
                                            <Button type="button" variant="outline" size="sm" onClick={() => router.post('/monthly-rules/templates/duplicate', { template_id: template.id, name: `${template.name} copy`, description: template.description })}><Copy data-icon="inline-start" />Duplicate</Button>
                                            <Button type="button" variant="danger" size="sm" disabled={template.isDefault} title={template.isDefault ? 'Choose another default before deleting this template.' : 'Archive template'} onClick={() => {
 if (window.confirm(`Archive “${template.name}”?`)) {
router.delete(`/monthly-rules/templates/${template.id}`);
} 
}}><Trash2 data-icon="inline-start" />Delete</Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            ) : (
                <div className="flex flex-col gap-4">
                    <form onSubmit={saveDetails} className="flex flex-col gap-4">
                        <Card>
                            <CardHeader title="Template details" meta="Changes here affect future monthly plans using this template only." />
                            <div className="p-5"><FieldGroup className="grid gap-4 md:grid-cols-2"><Field><FieldLabel>Name</FieldLabel><Input value={name} onChange={(event) => setName(event.target.value)} /></Field><Field><FieldLabel>Notes</FieldLabel><Textarea value={description} onChange={(event) => setDescription(event.target.value)} placeholder="When should this template be used?" /></Field></FieldGroup><div className="mt-4 flex justify-end"><Button type="submit">Save template details</Button></div></div>
                        </Card>
                    </form>

                    <form onSubmit={saveRules} className="flex flex-col gap-4">
                        <Card>
                            <CardHeader title="Income rules" meta={`Expected monthly income: ${formatCompactEGP(monthlyIncome)} · use a fixed amount or a percentage of actual monthly income`} />
                            <div className="flex flex-col gap-3 p-5">
                                {income.map((rule, index) => <div key={rule.id ?? `income-${index}`} className="grid items-end gap-3 md:grid-cols-[1fr_160px_140px_40px]"><Field><FieldLabel>Source name</FieldLabel><Input value={rule.name} onChange={(event) => setIncome((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, name: event.target.value } : row))} /></Field><Field><FieldLabel>Amount (EGP)</FieldLabel><Input type="number" min="0" value={rule.amount ?? ''} onChange={(event) => setIncome((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, amount: event.target.value === '' ? null : Number(event.target.value), percent: null } : row))} /></Field><Field><FieldLabel>Or % of income</FieldLabel><Input type="number" min="0" max="100" step="0.1" value={rule.percent ?? ''} onChange={(event) => setIncome((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, percent: event.target.value === '' ? null : Number(event.target.value), amount: null } : row))} /></Field><Button type="button" variant="ghost" size="icon" aria-label="Remove income rule" onClick={() => setIncome((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 /></Button></div>)}
                                <Button type="button" variant="outline" className="self-start" onClick={() => setIncome((rows) => [...rows, { name: 'New income source', amount: 0, percent: null }])}><Plus data-icon="inline-start" />Add income rule</Button>
                            </div>
                        </Card>

                        <Card>
                            <CardHeader title="Expense rules" meta="Rules can be multiple per category. Commitment-linked rules are generated automatically." />
                            <div className="flex flex-col gap-3 p-5">
                                {expenses.map((rule, index) => <div key={rule.id ?? `expense-${index}`} className="grid items-end gap-3 md:grid-cols-[1.2fr_1fr_170px_140px_40px]"><Field><FieldLabel>Rule name</FieldLabel><Input value={rule.name} disabled={rule.generatedFromCommitment} onChange={(event) => setExpenses((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, name: event.target.value } : row))} /></Field><Field><FieldLabel>Category</FieldLabel><Select disabled={rule.generatedFromCommitment} value={String(rule.categoryId)} onValueChange={(value) => setExpenses((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, categoryId: Number(value) } : row))}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectGroup><SelectLabel>Expense categories</SelectLabel>{categories.map((category) => <SelectItem key={category.id} value={String(category.id)}>{category.name}{category.isDefault ? ' · default' : ''}</SelectItem>)}</SelectGroup></SelectContent></Select></Field><Field><FieldLabel>Amount (EGP)</FieldLabel><Input type="number" disabled={rule.generatedFromCommitment} value={rule.amount ?? ''} onChange={(event) => setExpenses((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, amount: event.target.value === '' ? null : Number(event.target.value), percent: null } : row))} /></Field><Field><FieldLabel>Or %</FieldLabel><Input type="number" disabled={rule.generatedFromCommitment} value={rule.percent ?? ''} onChange={(event) => setExpenses((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, percent: event.target.value === '' ? null : Number(event.target.value), amount: null } : row))} /></Field>{rule.generatedFromCommitment ? <Badge variant="secondary" className="mb-2">{rule.commitmentName}</Badge> : <Button type="button" variant="ghost" size="icon" aria-label="Remove expense rule" onClick={() => setExpenses((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 /></Button>}</div>)}
                                <Button type="button" variant="outline" className="self-start" onClick={() => setExpenses((rows) => [...rows, { name: 'New expense rule', categoryId: categories[0]?.id ?? 0, amount: 0, percent: null }])}><Plus data-icon="inline-start" />Add expense rule</Button>
                            </div>
                        </Card>

                        <Card>
                            <CardHeader title="Remaining allocation rules" meta="Select an existing asset, then choose only a bucket assigned to that asset." action={<Badge variant={allocationTotal > 100 ? 'destructive' : 'outline'}>{allocationTotal.toFixed(1)}% assigned</Badge>} />
                            <div className="flex flex-col gap-3 p-5">
                                {allocations.map((rule, index) => {
                                    const asset = assets.find((item) => item.id === rule.assetId);

                                    return <div key={rule.id ?? `allocation-${index}`} className="grid items-end gap-3 md:grid-cols-[1.2fr_1.2fr_180px_40px]"><Field><FieldLabel>Asset</FieldLabel><Select value={rule.assetId ? String(rule.assetId) : ''} onValueChange={(value) => changeAsset(index, value)}><SelectTrigger><SelectValue placeholder="Choose asset" /></SelectTrigger><SelectContent><SelectGroup><SelectLabel>Owned assets</SelectLabel>{assets.map((item) => <SelectItem key={item.id} value={String(item.id)}>{item.name} · {item.type}</SelectItem>)}</SelectGroup></SelectContent></Select></Field><Field><FieldLabel>Assigned bucket</FieldLabel><Select disabled={!asset} value={rule.bucketId ? String(rule.bucketId) : ''} onValueChange={(value) => setAllocations((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, bucketId: Number(value) } : row))}><SelectTrigger><SelectValue placeholder={asset ? 'Choose assigned bucket' : 'Choose asset first'} /></SelectTrigger><SelectContent><SelectGroup><SelectLabel>Asset buckets</SelectLabel>{(asset?.buckets ?? []).map((bucket) => <SelectItem key={bucket.id} value={String(bucket.id)}>{bucket.name}{bucket.goalName ? ` · ${bucket.goalName}` : ''}</SelectItem>)}</SelectGroup></SelectContent></Select></Field><Field><FieldLabel>% of remaining</FieldLabel><Input type="number" min="0" max="100" step="0.1" value={String(rule.percent)} onChange={(event) => setAllocations((rows) => rows.map((row, rowIndex) => rowIndex === index ? { ...row, percent: Number(event.target.value) } : row))} /></Field><Button type="button" variant="ghost" size="icon" aria-label="Remove allocation rule" onClick={() => setAllocations((rows) => rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 /></Button>{rule.legacyUnlinked && <p className="text-xs text-muted-foreground md:col-span-4">Legacy rule: choose an asset and an assigned bucket to fully link it.</p>}</div>;
                                })}
                                <Button type="button" variant="outline" className="self-start" onClick={() => setAllocations((rows) => [...rows, { assetId: assets[0]?.id ?? null, bucketId: assets[0]?.buckets[0]?.id ?? 0, assetTarget: assets[0]?.name ?? '', percent: 0 }])}><Plus data-icon="inline-start" />Add allocation rule</Button>
                            </div>
                        </Card>
                        <div className="flex justify-end"><Button type="submit">Save all template rules</Button></div>
                    </form>
                </div>
            )}

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader><DialogTitle>Create plan template</DialogTitle><DialogDescription>Create a reusable blueprint, then add its income, expense, and allocation rules.</DialogDescription></DialogHeader>
                    <form onSubmit={createTemplate} className="flex flex-col gap-4">
                        <FieldGroup><Field><FieldLabel>Name</FieldLabel><Input autoFocus value={createName} onChange={(event) => setCreateName(event.target.value)} placeholder="e.g. Family car priority" required /></Field><Field><FieldLabel>Notes</FieldLabel><Textarea value={createDescription} onChange={(event) => setCreateDescription(event.target.value)} placeholder="When should this template be used?" /></Field></FieldGroup>
                        <DialogFooter><DialogClose render={<Button type="button" variant="outline" />}>Cancel</DialogClose><Button type="submit"><Plus data-icon="inline-start" />Create template</Button></DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppShell>
    );
}
