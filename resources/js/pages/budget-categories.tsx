import { router } from '@inertiajs/react';
import { Pencil, Plus, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AppShell, Badge, Button, Card, CardHeader, PageHeader } from '@/components/app-shell';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type Category = { id: number; name: string; color?: string | null; isDefault: boolean; isActive: boolean };

export default function BudgetCategories({ categories }: { categories: Category[] }) {
    const [name, setName] = useState('');
    const [color, setColor] = useState('');
    const [isDefault, setIsDefault] = useState(true);

    const create = (event: React.FormEvent) => {
        event.preventDefault();

        if (!name.trim()) {
return;
}

        router.post('/budget-categories', { name: name.trim(), color: color || null, is_default: isDefault }, { onSuccess: () => {
 setName(''); setColor(''); 
} });
    };

    const edit = (category: Category) => {
        const nextName = window.prompt('Category name', category.name);

        if (!nextName?.trim()) {
return;
}

        router.put(`/budget-categories/${category.id}`, { name: nextName.trim(), color: category.color, is_default: category.isDefault, is_active: category.isActive });
    };

    return (
        <AppShell title="Expense categories">
            <PageHeader eyebrow="Budget building blocks" title="Expense categories" description="Manage the categories that can appear in monthly plans. Default categories are included in new plans; archived categories remain in history." />
            <div className="flex flex-col gap-4">
            <Card>
                <CardHeader title="Add category" meta="Categories are separate from ledger transaction categories." />
                <form onSubmit={create} className="p-5">
                    <FieldGroup className="grid gap-3 md:grid-cols-[1fr_180px_auto_auto]">
                        <Field><FieldLabel>Name</FieldLabel><Input placeholder="e.g. Health" value={name} onChange={(event) => setName(event.target.value)} /></Field>
                        <Field><FieldLabel>Color</FieldLabel><Input placeholder="#7c8cf8" value={color} onChange={(event) => setColor(event.target.value)} /></Field>
                        <label className="flex items-center gap-2 self-end pb-2 text-sm"><Checkbox checked={isDefault} onCheckedChange={(checked) => setIsDefault(checked === true)} />Default in new plans</label>
                        <Button type="submit" className="self-end"><Plus data-icon="inline-start" />Add category</Button>
                    </FieldGroup>
                </form>
            </Card>
            <Card>
                <CardHeader title="Your categories" meta={`${categories.length} categories`} />
                <Table>
                    <TableHeader><TableRow><TableHead>Name</TableHead><TableHead>Default</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader>
                    <TableBody>{categories.map((category) => <TableRow key={category.id}>
                        <TableCell><span className="mr-2 inline-block size-3 rounded-full" style={{ backgroundColor: category.color ?? '#7c8cf8' }} />{category.name}</TableCell>
                        <TableCell><label className="flex items-center gap-2"><Checkbox checked={category.isDefault} disabled={!category.isActive} onCheckedChange={(checked) => router.put(`/budget-categories/${category.id}`, { name: category.name, color: category.color, is_default: checked === true, is_active: category.isActive })} /><span className="text-xs text-muted-foreground">New plans</span></label></TableCell>
                        <TableCell>{category.isActive ? <Badge variant="outline">Active</Badge> : <Badge variant="secondary">Archived</Badge>}</TableCell>
                        <TableCell className="text-right"><div className="flex justify-end gap-2"><Button type="button" variant="ghost" size="icon" aria-label={`Edit ${category.name}`} onClick={() => edit(category)}><Pencil /></Button>{category.isActive ? <Button type="button" variant="ghost" size="icon" aria-label={`Archive ${category.name}`} onClick={() => router.delete(`/budget-categories/${category.id}`)}><Trash2 /></Button> : <Button type="button" variant="ghost" size="icon" aria-label={`Restore ${category.name}`} onClick={() => router.post(`/budget-categories/${category.id}/restore`)}><RotateCcw /></Button>}</div></TableCell>
                    </TableRow>)}</TableBody>
                </Table>
            </Card>
            </div>
        </AppShell>
    );
}
