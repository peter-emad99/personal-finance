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

type Category = {
    id: number;
    name: string;
    kind: string;
    budgetCategoryId?: number | null;
    budgetCategoryName?: string | null;
    isSystem?: boolean;
};

type BudgetCategory = { id: number; name: string };

const emptyForm = { name: '', kind: 'expense', budget_category_id: '' };

export default function TransactionCategories({
    categories,
    budgetCategories,
}: {
    categories: Category[];
    budgetCategories: BudgetCategory[];
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Category | null>(null);
    const [form, setForm] = useState(emptyForm);
    const expenseCategories = categories.filter((category) => category.kind === 'expense');
    const otherCategories = categories.filter((category) => category.kind !== 'expense');

    const openEditor = (category?: Category) => {
        setEditing(category ?? null);
        setForm(
            category
                ? {
                      name: category.name,
                      kind: category.kind,
                      budget_category_id: category.budgetCategoryId
                          ? String(category.budgetCategoryId)
                          : '',
                  }
                : emptyForm,
        );
        setOpen(true);
    };
    const update = (key: string, value: string) => setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const payload = { ...form, budget_category_id: form.kind === 'expense' && form.budget_category_id ? form.budget_category_id : null };
        const options = {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                setForm(emptyForm);
            },
        };

        if (editing) {
            router.put(`/transaction-categories/${editing.id}`, payload, options);
        } else {
            router.post('/transaction-categories', payload, options);
        }
    };

    return (
        <AppShell title="Transaction categories">
            <PageHeader
                eyebrow="Ledger controls"
                title="Transaction categories"
                description="Create detailed actual categories under the planning expense categories. Reports roll detail up to the selected parent automatically."
                action={<Button onClick={() => openEditor()}>+ Add category</Button>}
            />
            <Card>
                <CardHeader title="Actual category tree" meta="Use a parent expense category for planning, then choose a detailed transaction category when recording actuals." />
                <div className="flex flex-col gap-6 p-5">
                    <CategoryGroup title="Expense details" description="These categories appear after choosing an expense parent such as Essentials or Commitments." categories={expenseCategories} onEdit={openEditor} onArchive={(category) => router.delete(`/transaction-categories/${category.id}`)} />
                    <CategoryGroup title="Income and other details" description="Income categories are standalone, for example Salary or Freelance." categories={otherCategories} onEdit={openEditor} onArchive={(category) => router.delete(`/transaction-categories/${category.id}`)} />
                </div>
            </Card>
            {!categories.length && <EmptyState title="No transaction categories yet" description="Create categories before recording detailed actual income and expenses." />}
            {open && (
                <FormModal title={editing ? 'Edit transaction category' : 'Add transaction category'} onClose={() => setOpen(false)}>
                    <form onSubmit={submit}>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="transaction-category-name">Name</FieldLabel>
                                <Input id="transaction-category-name" required value={form.name} onChange={(event) => update('name', event.target.value)} placeholder="Internet, Electricity, Salary..." />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="transaction-category-kind">Kind</FieldLabel>
                                    <Select
                                        value={form.kind}
                                        onValueChange={(value) => {
                                            const kind = String(value ?? 'expense');
                                            setForm((current) => ({
                                                ...current,
                                                kind,
                                                budget_category_id:
                                                    kind === 'expense'
                                                        ? current.budget_category_id
                                                        : '',
                                            }));
                                        }}
                                    >
                                    <SelectTrigger id="transaction-category-kind" className="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectGroup><SelectLabel>Transaction kind</SelectLabel><SelectItem value="income">Income</SelectItem><SelectItem value="expense">Expense</SelectItem><SelectItem value="transfer">Transfer</SelectItem><SelectItem value="investment">Investment</SelectItem><SelectItem value="adjustment">Adjustment</SelectItem></SelectGroup></SelectContent>
                                </Select>
                            </Field>
                            {form.kind === 'expense' && (
                                <Field>
                                    <FieldLabel htmlFor="transaction-category-parent">Expense category</FieldLabel>
                                    <Select value={form.budget_category_id} onValueChange={(value) => update('budget_category_id', String(value ?? ''))}>
                                        <SelectTrigger id="transaction-category-parent" className="w-full"><SelectValue placeholder="Choose parent category" /></SelectTrigger>
                                        <SelectContent><SelectGroup><SelectLabel>Planning expense categories</SelectLabel>{budgetCategories.map((category) => <SelectItem key={category.id} value={String(category.id)}>{category.name}</SelectItem>)}</SelectGroup></SelectContent>
                                    </Select>
                                </Field>
                            )}
                        </FieldGroup>
                        <div className="mt-6 flex justify-end gap-2">
                            <FormModalClose><Button type="button" variant="ghost">Cancel</Button></FormModalClose>
                            <Button type="submit">{editing ? 'Save changes' : 'Create category'}</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}

function CategoryGroup({ title, description, categories, onEdit, onArchive }: { title: string; description: string; categories: Category[]; onEdit: (category: Category) => void; onArchive: (category: Category) => void }) {
    return (
        <section className="flex flex-col gap-3">
            <div><h2 className="text-sm font-semibold">{title}</h2><p className="text-xs text-muted-foreground">{description}</p></div>
            <div className="divide-y divide-border rounded-xl border">
                {categories.map((category) => (
                    <div key={category.id} className="flex items-center justify-between gap-3 p-4">
                        <div><p className="text-sm font-semibold">{category.name}</p><p className="text-xs text-muted-foreground">{category.budgetCategoryName ? `Parent: ${category.budgetCategoryName}` : 'Standalone category'}</p></div>
                        <div className="flex items-center gap-2"><Badge variant="secondary">{category.kind}</Badge><Button size="sm" variant="ghost" onClick={() => onEdit(category)}>Edit</Button><Button size="sm" variant="danger" disabled={category.isSystem} onClick={() => onArchive(category)}>{category.isSystem ? 'System' : 'Archive'}</Button></div>
                    </div>
                ))}
                {!categories.length && <p className="p-4 text-sm text-muted-foreground">No categories in this group yet.</p>}
            </div>
        </section>
    );
}
