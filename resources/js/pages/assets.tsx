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
import { formatEGP, labelize } from '@/types/finance';
import type { Asset } from '@/types/finance';

type BucketOption = { id: number; name: string };
const blank = {
    name: '',
    type: 'Cash',
    quantity: '',
    currency: 'EGP',
    cost_basis_egp: '',
    current_value_egp: '',
    unit_price_egp: '',
    acquired_on: '',
    account_name: '',
    liquidity: 'immediate',
    notes: '',
};

export default function Assets({
    assets,
    buckets,
}: {
    assets: Asset[];
    buckets: BucketOption[];
}) {
    const [editing, setEditing] = useState<Asset | null>(null);
    const [open, setOpen] = useState(false);
    const [allocationAsset, setAllocationAsset] = useState<Asset | null>(null);
    const [allocations, setAllocations] = useState<Record<number, string>>({});
    const [form, setForm] = useState(blank);
    const update = (key: string, value: string | boolean) =>
        setForm((current) => ({ ...current, [key]: value }));
    const begin = (asset?: Asset) => {
        setEditing(asset ?? null);
        setForm(
            asset
                ? {
                      name: asset.name,
                      type: asset.type,
                      quantity:
                          asset.quantity === null ? '' : String(asset.quantity),
                      currency: asset.currency,
                      cost_basis_egp: String(asset.costBasis),
                      current_value_egp: String(asset.currentValue),
                      unit_price_egp:
                          asset.unitPrice === null
                              ? ''
                              : String(asset.unitPrice),
                      acquired_on: asset.acquiredOn ?? '',
                      account_name: asset.accountName ?? '',
                      liquidity: asset.liquidity,
                      notes: asset.notes ?? '',
                  }
                : blank,
        );
        setOpen(true);
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const url = editing ? `/assets/${editing.id}` : '/assets';
        router[editing ? 'put' : 'post'](url, form, {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
                setForm(blank);
            },
        });
    };
    const openAllocations = (asset: Asset) => {
        setAllocationAsset(asset);
        setAllocations(
            Object.fromEntries(
                (asset.bucketAllocations ?? []).map((allocation) => [
                    allocation.bucketId,
                    String(allocation.amount),
                ]),
            ),
        );
    };
    const saveAllocations = (event: React.FormEvent) => {
        event.preventDefault();

        if (!allocationAsset) {
            return;
        }

        router.put(
            `/assets/${allocationAsset.id}/allocations`,
            {
                allocations: Object.entries(allocations).map(
                    ([bucketId, amount]) => ({
                        bucket_id: Number(bucketId),
                        amount_egp: Number(amount || 0),
                    }),
                ),
            },
            { onSuccess: () => setAllocationAsset(null) },
        );
    };

    return (
        <AppShell title="Assets">
            <PageHeader
                eyebrow="Balance sheet"
                title="Assets"
                description="Track what you own, where it lives, how liquid it is, and what each part is meant to do."
                action={<Button onClick={() => begin()}>+ Add asset</Button>}
            />
            <Card>
                <CardHeader
                    title="Everything you own"
                    meta={`${assets.length} assets · values shown in EGP`}
                />
                <div className="overflow-x-auto">
                    <Table className="min-w-[840px] text-left text-sm">
                        <TableHeader className="bg-muted/50 text-[11px] tracking-wider text-muted-foreground uppercase">
                            <TableRow>
                                <TableHead className="px-5 py-3">
                                    Asset
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Type
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Value
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Gain / loss
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Liquidity
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Purpose
                                </TableHead>
                                <TableHead className="px-5 py-3" />
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-border">
                            {assets.map((asset) => (
                                <TableRow
                                    key={asset.id}
                                    className={`hover:bg-muted/40 ${asset.archived ? 'opacity-60' : ''}`}
                                >
                                    <TableCell className="px-5 py-4">
                                        <p className="font-semibold text-foreground">
                                            {asset.name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {asset.quantity
                                                ? `${asset.quantity} ${asset.currency}`
                                                : (asset.accountName ??
                                                  'No account set')}
                                        </p>
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-muted-foreground">
                                        {asset.type}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-semibold text-foreground">
                                        {formatEGP(asset.currentValue)}
                                    </TableCell>
                                    <TableCell
                                        className={`px-5 py-4 font-medium ${asset.gainLoss >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive'}`}
                                    >
                                        {asset.gainLoss >= 0 ? '+' : ''}
                                        {formatEGP(asset.gainLoss)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4">
                                        <Badge className="rounded-full border-0 bg-secondary px-2.5 py-1 text-[11px] font-semibold text-secondary-foreground">
                                            {labelize(asset.liquidity)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-xs text-muted-foreground">
                                        <Button
                                            variant="ghost"
                                            className="h-auto justify-start border-0 p-0 text-left text-xs font-semibold text-primary hover:bg-transparent hover:text-primary/80"
                                            onClick={() =>
                                                openAllocations(asset)
                                            }
                                        >
                                            {asset.bucketAllocations?.length
                                                ? asset.bucketAllocations
                                                      .map(
                                                          (bucket) =>
                                                              bucket.bucketName,
                                                      )
                                                      .join(', ')
                                                : 'Assign purpose'}
                                        </Button>
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-right">
                                        {!asset.archived && (
                                            <>
                                                <Button
                                                    variant="ghost"
                                                    className="mr-1 h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                                    onClick={() => begin(asset)}
                                                >
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="danger"
                                                    className="h-7 border-0 bg-transparent px-2 text-xs text-muted-foreground hover:bg-transparent hover:text-destructive"
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                'Archive this asset?',
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/assets/${asset.id}`,
                                                            );
                                                        }
                                                    }}
                                                >
                                                    Archive
                                                </Button>
                                            </>
                                        )}
                                        {asset.archived && (
                                            <Button
                                                variant="ghost"
                                                className="h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                                onClick={() =>
                                                    router.post(
                                                        `/assets/${asset.id}/restore`,
                                                    )
                                                }
                                            >
                                                Restore
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {!assets.length && (
                        <EmptyState
                            title="No assets yet"
                            description="Add cash, gold, USD, investments, deposits, or anything else you own."
                        />
                    )}
                </div>
            </Card>
            {open && (
                <FormModal
                    title={editing ? 'Edit asset' : 'Add an asset'}
                    onClose={() => {
                        setOpen(false);
                        setEditing(null);
                    }}
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Field>
                            <FieldLabel htmlFor="asset-name">Name</FieldLabel>
                            <Input
                                id="asset-name"
                                required
                                value={form.name}
                                onChange={(e) => update('name', e.target.value)}
                                placeholder="e.g. Gold holdings"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="asset-type">Type</FieldLabel>
                            <Select
                                value={form.type}
                                onValueChange={(value) =>
                                    update('type', String(value ?? ''))
                                }
                            >
                                <SelectTrigger
                                    id="asset-type"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select asset type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="Cash">
                                            Cash
                                        </SelectItem>
                                        <SelectItem value="USD">USD</SelectItem>
                                        <SelectItem value="Gold">
                                            Gold
                                        </SelectItem>
                                        <SelectItem value="Egyptian equities">
                                            Egyptian equities
                                        </SelectItem>
                                        <SelectItem value="Mutual funds">
                                            Mutual funds
                                        </SelectItem>
                                        <SelectItem value="Fixed income">
                                            Fixed income
                                        </SelectItem>
                                        <SelectItem value="Other">
                                            Other
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="asset-quantity">
                                Quantity
                            </FieldLabel>
                            <Input
                                id="asset-quantity"
                                type="number"
                                step="any"
                                value={form.quantity}
                                onChange={(e) =>
                                    update('quantity', e.target.value)
                                }
                                placeholder="Optional"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="asset-currency">
                                Currency
                            </FieldLabel>
                            <Input
                                id="asset-currency"
                                value={form.currency}
                                onChange={(e) =>
                                    update('currency', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="asset-current-value">
                                Current value (EGP)
                            </FieldLabel>
                            <Input
                                id="asset-current-value"
                                type="number"
                                step="0.01"
                                required
                                value={form.current_value_egp}
                                onChange={(e) =>
                                    update('current_value_egp', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="asset-cost-basis">
                                Cost basis (EGP)
                            </FieldLabel>
                            <Input
                                id="asset-cost-basis"
                                type="number"
                                step="0.01"
                                value={form.cost_basis_egp}
                                onChange={(e) =>
                                    update('cost_basis_egp', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="asset-unit-price">
                                Unit price (EGP)
                            </FieldLabel>
                            <Input
                                id="asset-unit-price"
                                type="number"
                                step="0.01"
                                value={form.unit_price_egp}
                                onChange={(e) =>
                                    update('unit_price_egp', e.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="asset-liquidity">
                                Liquidity
                            </FieldLabel>
                            <Select
                                value={form.liquidity}
                                onValueChange={(value) =>
                                    update('liquidity', String(value ?? ''))
                                }
                            >
                                <SelectTrigger
                                    id="asset-liquidity"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select liquidity" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="immediate">
                                            Immediate
                                        </SelectItem>
                                        <SelectItem value="within_3_days">
                                            Within 3 days
                                        </SelectItem>
                                        <SelectItem value="longer_term">
                                            Longer term
                                        </SelectItem>
                                        <SelectItem value="illiquid">
                                            Illiquid
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field className="sm:col-span-2">
                            <FieldLabel htmlFor="asset-account">
                                Account / location
                            </FieldLabel>
                            <Input
                                id="asset-account"
                                value={form.account_name}
                                onChange={(e) =>
                                    update('account_name', e.target.value)
                                }
                                placeholder="e.g. Brokerage, bank, physical"
                            />
                        </Field>
                        <p className="text-xs leading-5 text-muted-foreground sm:col-span-2">
                            Availability is determined by the selected liquidity
                            tier; the legacy liquid flag is no longer used in
                            calculations.
                        </p>
                        <div className="flex justify-end gap-2 sm:col-span-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Save asset</Button>
                        </div>
                    </form>
                </FormModal>
            )}
            {allocationAsset && (
                <FormModal
                    title={`Assign ${allocationAsset.name}`}
                    onClose={() => setAllocationAsset(null)}
                >
                    <form
                        onSubmit={saveAllocations}
                        className="flex flex-col gap-4"
                    >
                        <p className="text-sm leading-6 text-muted-foreground">
                            Split this asset across buckets. The total cannot
                            exceed {formatEGP(allocationAsset.currentValue)}.
                        </p>
                        {buckets.map((bucket) => (
                            <Field key={bucket.id}>
                                <FieldLabel htmlFor={`allocation-${bucket.id}`}>
                                    {bucket.name} (EGP)
                                </FieldLabel>
                                <Input
                                    id={`allocation-${bucket.id}`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={allocations[bucket.id] ?? ''}
                                    onChange={(event) =>
                                        setAllocations((current) => ({
                                            ...current,
                                            [bucket.id]: event.target.value,
                                        }))
                                    }
                                    placeholder="0"
                                />
                            </Field>
                        ))}
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                onClick={() => setAllocationAsset(null)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Save purpose</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
