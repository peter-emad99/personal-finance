import { router } from '@inertiajs/react';
import { Archive, LockKeyhole, Pencil, Plus, RotateCcw, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import { AppShell, Badge, Button, Card, CardHeader, EmptyState, PageHeader } from '@/components/app-shell';
import { FormModal, FormModalClose } from '@/components/form';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { AssetType, AssetView } from '@/types/finance';

type Form = { key: string; label: string; description: string; sort_order: string };
type LineForm = Form & { color: string };
const emptyForm: Form = { key: '', label: '', description: '', sort_order: '100' };
const emptyLine: LineForm = { key: '', label: '', description: '', color: '', sort_order: '100' };

export default function AssetViews({ assetViews, assetTypes }: { assetViews: AssetView[]; assetTypes: AssetType[] }) {
    const activeViews = assetViews.filter((view) => view.isActive);
    const [selectedId, setSelectedId] = useState<number | null>(activeViews[0]?.id ?? null);
    const selected = assetViews.find((view) => view.id === selectedId) ?? activeViews[0] ?? null;
    const [viewModal, setViewModal] = useState(false);
    const [editingView, setEditingView] = useState<AssetView | null>(null);
    const [viewForm, setViewForm] = useState<Form>(emptyForm);
    const [lineModal, setLineModal] = useState(false);
    const [editingLine, setEditingLine] = useState<AssetView['lines'][number] | null>(null);
    const [lineForm, setLineForm] = useState<LineForm>(emptyLine);
    const [typeToAssign, setTypeToAssign] = useState('');

    const openView = (view?: AssetView) => {
        setEditingView(view ?? null);
        setViewForm(view ? { key: view.key, label: view.label, description: view.description ?? '', sort_order: String(view.sortOrder) } : emptyForm);
        setViewModal(true);
    };
    const openLine = (line?: AssetView['lines'][number]) => {
        setEditingLine(line ?? null);
        setLineForm(line ? { key: line.key, label: line.label, description: line.description ?? '', color: line.color ?? '', sort_order: String(line.sortOrder) } : emptyLine);
        setLineModal(true);
    };
    const submitView = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => setViewModal(false) };

        if (editingView) {
router.put(`/asset-views/${editingView.id}`, viewForm, options);
} else {
router.post('/asset-views', viewForm, options);
}
    };
    const submitLine = (event: React.FormEvent) => {
        event.preventDefault();

        if (!selected) {
return;
}

        const options = { onSuccess: () => setLineModal(false) };

        if (editingLine) {
router.put(`/asset-views/${selected.id}/lines/${editingLine.id}`, lineForm, options);
} else {
router.post(`/asset-views/${selected.id}/lines`, lineForm, options);
}
    };
    const assignedIds = useMemo(() => new Set(selected?.lines.flatMap((line) => line.assetTypes.map((type) => type.id)) ?? []), [selected]);

    return (
        <AppShell title="Asset views">
            <PageHeader eyebrow="Portfolio settings" title="Asset views" description="Create reporting lenses with ordered lines. Views organize presentation only; they never change asset class, liquidity, balances, liabilities, or net worth." action={<Button onClick={() => openView()}><Plus data-icon="inline-start" />Add custom view</Button>} />
            <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
                <Card>
                    <CardHeader title="Views" meta={`${activeViews.length} active · system and owner custom`} />
                    <div className="divide-y divide-border">
                        {assetViews.map((view) => <button key={view.id} type="button" onClick={() => setSelectedId(view.id)} className={`flex w-full items-start justify-between gap-3 p-4 text-left hover:bg-muted/40 ${selected?.id === view.id ? 'bg-muted/50' : ''}`}>
                            <span><span className="block font-medium">{view.label}</span><span className="text-xs text-muted-foreground"><code>{view.key}</code> · {view.lines.length} lines</span></span>
                            {view.isSystem ? <LockKeyhole className="mt-1 size-4 text-muted-foreground" /> : !view.isActive ? <Badge variant="secondary">Archived</Badge> : null}
                        </button>)}
                        {!assetViews.length && <EmptyState title="No views yet" description="The default view will appear after migrations run." />}
                    </div>
                </Card>
                {selected ? <Card>
                    <CardHeader title={selected.label} meta={`${selected.description ?? 'Dynamic portfolio lens'} · ${selected.isSystem ? 'Protected core view' : 'Owner custom view'}`} action={!selected.isSystem ? <div className="flex gap-2"><Button size="sm" variant="ghost" onClick={() => openView(selected)}><Pencil data-icon="inline-start" />Edit</Button>{selected.isActive ? <Button size="sm" variant="destructive" onClick={() => router.post(`/asset-views/${selected.id}/archive`)}><Archive data-icon="inline-start" />Archive</Button> : <Button size="sm" variant="outline" onClick={() => router.post(`/asset-views/${selected.id}/restore`)}><RotateCcw data-icon="inline-start" />Restore</Button>}</div> : <Badge variant="outline"><LockKeyhole data-icon="inline-start" />System</Badge>} />
                    <div className="flex flex-col gap-4 p-5">
                        <div className="flex flex-col gap-3 rounded-lg border bg-muted/20 p-4 sm:flex-row sm:items-end">
                            <div className="min-w-0 flex-1"><FieldLabel htmlFor="asset-view-type">Assign an asset type to a line</FieldLabel><Select value={typeToAssign || undefined} onValueChange={(value) => setTypeToAssign(String(value ?? ''))}><SelectTrigger id="asset-view-type"><SelectValue placeholder="Choose a type below" /></SelectTrigger><SelectContent>{assetTypes.filter((type) => !assignedIds.has(type.id)).map((type) => <SelectItem key={type.id} value={String(type.id)}>{type.label} · {type.class}</SelectItem>)}</SelectContent></Select></div><FieldDescription className="sm:max-w-xs">Select a line’s Add type action after choosing a type. A type can be reused in another view, but only once in this view.</FieldDescription>
                        </div>
                        <div className="divide-y divide-border rounded-lg border">
                            {selected.lines.map((line) => <div key={line.id} className="p-4"><div className="flex flex-wrap items-start justify-between gap-3"><div><div className="flex items-center gap-2"><span className="font-medium">{line.label}</span>{line.isFallback && <Badge variant="secondary">Fallback</Badge>}{!line.isActive && <Badge variant="secondary">Archived</Badge>}</div><p className="text-xs text-muted-foreground"><code>{line.key}</code>{line.description ? ` · ${line.description}` : ''}</p></div>{!selected.isSystem && !line.isFallback && <div className="flex gap-2"><Button size="sm" variant="ghost" onClick={() => openLine(line)}><Pencil data-icon="inline-start" />Edit</Button><Button size="sm" variant="outline" onClick={() => router.post(`/asset-views/${selected.id}/lines/${line.id}/${line.isActive ? 'archive' : 'restore'}`)}>{line.isActive ? 'Archive' : 'Restore'}</Button></div>}</div><div className="mt-3 flex flex-wrap gap-2">{line.assetTypes.map((type) => <Badge key={type.id} variant="outline">{type.label}{!selected.isSystem && <button type="button" className="ml-1" aria-label={`Remove ${type.label}`} onClick={() => router.delete(`/asset-views/${selected.id}/lines/${line.id}/types/${type.id}`)}><X className="size-3" /></button>}</Badge>)}{!selected.isSystem && !line.isFallback && typeToAssign && <Button size="sm" variant="ghost" onClick={() => {
 router.post(`/asset-views/${selected.id}/lines/${line.id}/types`, { asset_type_id: Number(typeToAssign) }); setTypeToAssign(''); 
}}>Add selected type</Button>}{!line.assetTypes.length && <span className="text-xs text-muted-foreground">No types assigned; matching assets appear in Unassigned / Other.</span>}</div></div>)}
                        </div>
                        {!selected.isSystem && <Button variant="outline" onClick={() => openLine()}><Plus data-icon="inline-start" />Add line</Button>}
                    </div>
                </Card> : <EmptyState title="Select a view" description="Choose a view to inspect its lines and memberships." />}
            </div>
            {viewModal && <FormModal title={editingView ? `Edit ${editingView.label}` : 'Create asset view'} description="A view is a presentation lens only." onClose={() => setViewModal(false)}><form onSubmit={submitView} className="flex flex-col gap-5"><FieldGroup><Field><FieldLabel>Key</FieldLabel><Input required={!editingView} disabled={!!editingView} value={viewForm.key} onChange={(event) => setViewForm({ ...viewForm, key: event.target.value })} /><FieldDescription>Lowercase stable identifier.</FieldDescription></Field><Field><FieldLabel>Label</FieldLabel><Input required value={viewForm.label} onChange={(event) => setViewForm({ ...viewForm, label: event.target.value })} /></Field><Field><FieldLabel>Description</FieldLabel><Textarea value={viewForm.description} onChange={(event) => setViewForm({ ...viewForm, description: event.target.value })} /></Field></FieldGroup><div className="flex justify-end gap-2"><FormModalClose><Button type="button" variant="ghost">Cancel</Button></FormModalClose><Button type="submit">Save view</Button></div></form></FormModal>}
            {lineModal && selected && <FormModal title={editingLine ? `Edit ${editingLine.label}` : 'Add view line'} description="Lines are ordered labels inside this view." onClose={() => setLineModal(false)}><form onSubmit={submitLine} className="flex flex-col gap-5"><FieldGroup><Field><FieldLabel>Key</FieldLabel><Input required={!editingLine} disabled={!!editingLine} value={lineForm.key} onChange={(event) => setLineForm({ ...lineForm, key: event.target.value })} /></Field><Field><FieldLabel>Label</FieldLabel><Input required value={lineForm.label} onChange={(event) => setLineForm({ ...lineForm, label: event.target.value })} /></Field><Field><FieldLabel>Description</FieldLabel><Textarea value={lineForm.description} onChange={(event) => setLineForm({ ...lineForm, description: event.target.value })} /></Field><Field><FieldLabel>Color</FieldLabel><Input value={lineForm.color} onChange={(event) => setLineForm({ ...lineForm, color: event.target.value })} placeholder="#4bb79f" /></Field></FieldGroup><div className="flex justify-end gap-2"><FormModalClose><Button type="button" variant="ghost">Cancel</Button></FormModalClose><Button type="submit">Save line</Button></div></form></FormModal>}
        </AppShell>
    );
}
