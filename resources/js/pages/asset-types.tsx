import { router } from '@inertiajs/react';
import { Archive, LockKeyhole, Pencil, Plus, RotateCcw } from 'lucide-react';
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
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
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

type AssetType = {
    id: number;
    key: string;
    label: string;
    class: string;
    classLabel: string;
    defaultLiquidity: string;
    pricingBehavior: string;
    isSystem?: boolean;
    isActive: boolean;
    assetCount: number;
};

const classes = [
    { value: 'cash', label: 'Cash' },
    { value: 'reserved_cash', label: 'Reserved cash' },
    { value: 'investment', label: 'Investment' },
    { value: 'gold', label: 'Gold' },
    { value: 'fixed_income', label: 'Fixed income' },
    { value: 'receivable', label: 'Receivable' },
    { value: 'other', label: 'Other' },
];

const liquidityOptions = [
    { value: 'immediate', label: 'Available now' },
    { value: 'within_3_days', label: 'Within 3 days' },
    { value: 'longer_term', label: 'Longer term' },
    { value: 'illiquid', label: 'Illiquid' },
];

const pricingOptions = [
    { value: 'manual', label: 'Manual value' },
    { value: 'fx', label: 'Foreign exchange' },
    { value: 'gold', label: 'Gold price' },
];

const emptyForm = {
    key: '',
    label: '',
    class: 'investment',
    default_liquidity: 'longer_term',
    pricing_behavior: 'manual',
};

export default function AssetTypes({ assetTypes }: { assetTypes: AssetType[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<AssetType | null>(null);
    const [form, setForm] = useState(emptyForm);

    const openEditor = (assetType?: AssetType) => {
        setEditing(assetType ?? null);
        setForm(
            assetType
                ? {
                      key: assetType.key,
                      label: assetType.label,
                      class: assetType.class,
                      default_liquidity: assetType.defaultLiquidity,
                      pricing_behavior: assetType.pricingBehavior,
                  }
                : emptyForm,
        );
        setOpen(true);
    };

    const update = (key: keyof typeof emptyForm, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                setForm(emptyForm);
            },
        };

        if (editing) {
            router.put(`/asset-types/${editing.id}`, form, options);
        } else {
            router.post('/asset-types', form, options);
        }
    };

    const systemTypes = assetTypes.filter((assetType) => assetType.isSystem);
    const customTypes = assetTypes.filter((assetType) => !assetType.isSystem);

    return (
        <AppShell title="Asset types">
            <PageHeader
                eyebrow="Portfolio settings"
                title="Asset types"
                description="Manage the catalog that explains what each asset is. Types roll up into classes for dashboards, liquidity, and reporting; they do not change the asset value or its purpose bucket."
                action={
                    <Button onClick={() => openEditor()}>
                        <Plus data-icon="inline-start" />
                        Add custom type
                    </Button>
                }
            />

            <div className="flex flex-col gap-4">
                <Card>
                    <CardHeader
                        title="How the catalog works"
                        meta="System types are shared and protected. Custom types belong only to this owner and can be archived without deleting asset history."
                    />
                    <div className="grid gap-3 p-5 md:grid-cols-3">
                        <CatalogNote title="Type" detail="What the holding is: ETF, certificate, gold, or cash." />
                        <CatalogNote title="Class" detail="The reporting group used by metrics and dashboards." />
                        <CatalogNote title="Pricing & access" detail="How its value is maintained and how quickly it can be used." />
                    </div>
                </Card>

                <TypeSection
                    title="System types"
                    description="Safe defaults used by the application. They can be selected but not renamed here."
                    assetTypes={systemTypes}
                    onEdit={openEditor}
                />

                <TypeSection
                    title="Custom types"
                    description="Owner-specific types for holdings that need a more precise label."
                    assetTypes={customTypes}
                    onEdit={openEditor}
                    onArchive={(assetType) =>
                        router.post(`/asset-types/${assetType.id}/archive`)
                    }
                    onRestore={(assetType) =>
                        router.post(`/asset-types/${assetType.id}/restore`)
                    }
                />
            </div>

            {open && (
                <FormModal
                    title={editing ? `Edit ${editing.label}` : 'Add asset type'}
                    description="Choose the reporting class carefully. Existing assets keep their values; changing a custom type changes how those assets are grouped."
                    onClose={() => {
                        setOpen(false);
                        setEditing(null);
                    }}
                >
                    <form onSubmit={submit} className="flex flex-col gap-6">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="asset-type-key">Key</FieldLabel>
                                <Input
                                    id="asset-type-key"
                                    required={!editing}
                                    disabled={!!editing}
                                    value={form.key}
                                    onChange={(event) => update('key', event.target.value)}
                                    placeholder="e.g. crypto_asset"
                                />
                                <FieldDescription>
                                    Lowercase identifier. It cannot be changed after creation.
                                </FieldDescription>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="asset-type-label">Name</FieldLabel>
                                <Input
                                    id="asset-type-label"
                                    required
                                    value={form.label}
                                    onChange={(event) => update('label', event.target.value)}
                                    placeholder="e.g. Crypto asset"
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="asset-type-class">Asset class</FieldLabel>
                                <Select value={form.class} onValueChange={(value) => update('class', String(value ?? ''))}>
                                    <SelectTrigger id="asset-type-class"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Reporting class</SelectLabel>
                                            {classes.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="asset-type-liquidity">Default liquidity</FieldLabel>
                                <Select value={form.default_liquidity} onValueChange={(value) => update('default_liquidity', String(value ?? ''))}>
                                    <SelectTrigger id="asset-type-liquidity"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Access speed</SelectLabel>
                                            {liquidityOptions.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="asset-type-pricing">Pricing behavior</FieldLabel>
                                <Select value={form.pricing_behavior} onValueChange={(value) => update('pricing_behavior', String(value ?? ''))}>
                                    <SelectTrigger id="asset-type-pricing"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Value source</SelectLabel>
                                            {pricingOptions.map((item) => <SelectItem key={item.value} value={item.value}>{item.label}</SelectItem>)}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        </FieldGroup>
                        <div className="flex justify-end gap-2">
                            <FormModalClose><Button type="button" variant="ghost">Cancel</Button></FormModalClose>
                            <Button type="submit">{editing ? 'Save changes' : 'Create type'}</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}

function TypeSection({
    title,
    description,
    assetTypes,
    onEdit,
    onArchive,
    onRestore,
}: {
    title: string;
    description: string;
    assetTypes: AssetType[];
    onEdit: (assetType: AssetType) => void;
    onArchive?: (assetType: AssetType) => void;
    onRestore?: (assetType: AssetType) => void;
}) {
    return (
        <Card>
            <CardHeader title={title} meta={`${assetTypes.length} types · ${description}`} />
            {!assetTypes.length ? (
                <EmptyState title="No custom asset types yet" description="Create one when the system types are not precise enough for a holding." />
            ) : (
                <div className="divide-y divide-border">
                    {assetTypes.map((assetType) => (
                        <div key={assetType.id} className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium">{assetType.label}</p>
                                    {assetType.isSystem && <Badge variant="outline"><LockKeyhole data-icon="inline-start" />System</Badge>}
                                    {!assetType.isActive && <Badge variant="secondary">Archived</Badge>}
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    <code>{assetType.key}</code> · {assetType.classLabel} · {formatOption(assetType.defaultLiquidity)} · {formatOption(assetType.pricingBehavior)} · {assetType.assetCount} {assetType.assetCount === 1 ? 'asset' : 'assets'}
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                {assetType.isSystem ? (
                                    <Badge variant="secondary">Protected</Badge>
                                ) : (
                                    <>
                                        <Button size="sm" variant="ghost" onClick={() => onEdit(assetType)}>
                                            <Pencil data-icon="inline-start" />Edit
                                        </Button>
                                        {assetType.isActive ? (
                                            <Button size="sm" variant="destructive" onClick={() => onArchive?.(assetType)}>
                                                <Archive data-icon="inline-start" />Archive
                                            </Button>
                                        ) : (
                                            <Button size="sm" variant="outline" onClick={() => onRestore?.(assetType)}>
                                                <RotateCcw data-icon="inline-start" />Restore
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

function CatalogNote({ title, detail }: { title: string; detail: string }) {
    return (
        <div className="rounded-lg border bg-muted/30 p-4">
            <p className="text-sm font-medium">{title}</p>
            <p className="mt-1 text-xs leading-5 text-muted-foreground">{detail}</p>
        </div>
    );
}

function formatOption(value: string) {
    return value.replaceAll('_', ' ');
}
