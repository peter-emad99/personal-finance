import { router } from '@inertiajs/react';
import {
    CircleHelp,
    Coins,
    Landmark,
    Pencil,
    Plus,
    ShieldCheck,
    WalletCards,
} from 'lucide-react';
import { useMemo, useState } from 'react';

import {
    AppShell,
    Badge,
    Button,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { FormModal } from '@/components/form';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLegend,
    FieldSet,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectSeparator,
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { formatEGP, labelize } from '@/types/finance';
import type { Asset } from '@/types/finance';

type BucketOption = {
    id: number;
    name: string;
    purpose: string | null;
    goalName: string | null;
};

const assetTypes = [
    ['Cash', 'Cash', 'EGP notes, bank balance, or wallet money available now.'],
    [
        'USD',
        'Foreign currency',
        'USD or another currency balance you own. Report its EGP value today.',
    ],
    [
        'Gold',
        'Gold',
        'Physical gold or a gold-backed holding. Its value can move with the market.',
    ],
    [
        'Egyptian equities',
        'Egyptian equities',
        'Individual shares listed on the Egyptian Exchange.',
    ],
    [
        'Mutual funds',
        'Mutual funds',
        'A pooled investment fund. It may invest in shares, bonds, or a mix—check the fund itself.',
    ],
    [
        'Fixed income',
        'Fixed income',
        'A certificate, bond, treasury bill, or fixed-income fund. It is an asset you own, not your monthly salary.',
    ],
    [
        'Other',
        'Other',
        'Anything valuable that does not fit the options above.',
    ],
] as const;

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
    const selectedType = assetTypes.find(([value]) => value === form.type);
    const totals = useMemo(
        () => allocationSummary(allocationAsset, allocations),
        [allocationAsset, allocations],
    );
    const update = (key: keyof typeof blank, value: string) =>
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
                      cost_basis_egp: asset.costBasis
                          ? String(asset.costBasis)
                          : '',
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
        router[editing ? 'put' : 'post'](
            editing ? `/assets/${editing.id}` : '/assets',
            form,
            {
                onSuccess: () => {
                    setOpen(false);
                    setEditing(null);
                    setForm(blank);
                },
            },
        );
    };
    const openAllocations = (asset: Asset) => {
        setAllocationAsset(asset);
        setAllocations(
            Object.fromEntries(
                (asset.bucketAllocations ?? []).map((item) => [
                    item.bucketId,
                    String(item.amount),
                ]),
            ),
        );
    };
    const saveAllocations = (event: React.FormEvent) => {
        event.preventDefault();

        if (!allocationAsset || totals.overAllocated) {
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
        <AppShell title="What you own">
            <PageHeader
                eyebrow="WHAT YOU OWN"
                title="Assets & their purpose"
                description="An asset is the real thing you own. A purpose is the job you want part of its value to do. Keep those separate, then connect them here."
                action={
                    <Button onClick={() => begin()}>
                        <Plus data-icon="inline-start" />
                        Add asset
                    </Button>
                }
            />

            <div className="mb-6 grid gap-4 lg:grid-cols-3">
                <ConceptCard
                    icon={WalletCards}
                    title="1. Asset = what you own"
                    description="Cash, gold, a certificate, a fund, or shares. It has a market value and a location."
                />
                <ConceptCard
                    icon={Coins}
                    title="2. Bucket = what it is for"
                    description="Emergency reserve, short-term savings, a car, or long-term investing. It is not a new account."
                />
                <ConceptCard
                    icon={ShieldCheck}
                    title="3. Split one asset freely"
                    description="The same asset can support multiple buckets. Only the combined allocated amount must stay within its value."
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Everything you own</CardTitle>
                    <CardDescription>
                        {assets.length} assets · all values are shown in EGP so
                        you can compare them.
                    </CardDescription>
                    <CardAction>
                        <Badge variant="secondary">
                            {formatEGP(
                                assets.reduce(
                                    (total, asset) =>
                                        total + asset.currentValue,
                                    0,
                                ),
                            )}
                        </Badge>
                    </CardAction>
                </CardHeader>
                <CardContent className="px-0">
                    <div className="overflow-x-auto">
                        <Table className="min-w-[980px] text-left text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="pl-5">
                                        Asset
                                    </TableHead>
                                    <TableHead>Current value</TableHead>
                                    <TableHead>Gain / loss</TableHead>
                                    <TableHead>Available</TableHead>
                                    <TableHead>Purpose split</TableHead>
                                    <TableHead className="pr-5 text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assets.map((asset) => {
                                    const allocated = (
                                        asset.bucketAllocations ?? []
                                    ).reduce(
                                        (total, item) => total + item.amount,
                                        0,
                                    );
                                    const remaining = Math.max(
                                        0,
                                        asset.currentValue - allocated,
                                    );

                                    return (
                                        <TableRow
                                            key={asset.id}
                                            className={
                                                asset.archived
                                                    ? 'opacity-60'
                                                    : ''
                                            }
                                        >
                                            <TableCell className="py-4 pl-5">
                                                <div className="flex flex-col gap-1">
                                                    <p className="font-medium">
                                                        {asset.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {asset.type} ·{' '}
                                                        {asset.accountName ??
                                                            asset.currency}
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="py-4">
                                                <p className="font-medium tabular-nums">
                                                    {formatEGP(
                                                        asset.currentValue,
                                                    )}
                                                </p>
                                                {asset.quantity !== null && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {asset.quantity}{' '}
                                                        {asset.currency}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell className="py-4">
                                                {asset.costBasis > 0 ? (
                                                    <p
                                                        className={cn(
                                                            'font-medium tabular-nums',
                                                            asset.gainLoss >= 0
                                                                ? 'text-primary'
                                                                : 'text-destructive',
                                                        )}
                                                    >
                                                        {asset.gainLoss >= 0
                                                            ? '+'
                                                            : ''}
                                                        {formatEGP(
                                                            asset.gainLoss,
                                                        )}
                                                    </p>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Not tracked
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="py-4">
                                                <Badge variant="outline">
                                                    {liquidityLabel(
                                                        asset.liquidity,
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="py-4">
                                                <Button
                                                    variant="ghost"
                                                    className="h-auto max-w-72 justify-start p-0 text-left whitespace-normal"
                                                    onClick={() =>
                                                        openAllocations(asset)
                                                    }
                                                >
                                                    <div className="flex w-full flex-col gap-1">
                                                        <span className="font-medium text-primary">
                                                            {asset
                                                                .bucketAllocations
                                                                ?.length
                                                                ? `${asset.bucketAllocations.length} purposes · ${formatEGP(allocated)} assigned`
                                                                : 'Assign a purpose'}
                                                        </span>
                                                        <span className="truncate text-xs text-muted-foreground">
                                                            {asset
                                                                .bucketAllocations
                                                                ?.length
                                                                ? asset.bucketAllocations
                                                                      .map(
                                                                          (
                                                                              bucket,
                                                                          ) =>
                                                                              `${bucket.bucketName} ${formatEGP(bucket.amount)}`,
                                                                      )
                                                                      .join(
                                                                          ' · ',
                                                                      )
                                                                : `${formatEGP(remaining)} is unassigned`}
                                                        </span>
                                                    </div>
                                                </Button>
                                            </TableCell>
                                            <TableCell className="py-4 pr-5 text-right">
                                                {!asset.archived ? (
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                begin(asset)
                                                            }
                                                        >
                                                            <Pencil data-icon="inline-start" />
                                                            Edit
                                                        </Button>
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
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
                                                    </div>
                                                ) : (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
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
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                    {!assets.length && (
                        <div className="px-5 py-10">
                            <EmptyAssetState onAdd={() => begin()} />
                        </div>
                    )}
                </CardContent>
                <CardFooter className="text-sm text-muted-foreground">
                    Current value is what the asset is worth now. Purpose
                    amounts are assignments; they do not move or sell the asset.
                </CardFooter>
            </Card>

            {open && (
                <FormModal
                    title={editing ? `Edit ${editing.name}` : 'Add an asset'}
                    description="Record the real thing you own first. You will assign its purpose after saving."
                    onClose={() => {
                        setOpen(false);
                        setEditing(null);
                    }}
                >
                    <form onSubmit={submit} className="flex flex-col gap-6">
                        <FieldGroup className="grid gap-4 sm:grid-cols-2">
                            <Field className="sm:col-span-2">
                                <FieldLabel htmlFor="asset-name">
                                    What do you own?
                                </FieldLabel>
                                <Input
                                    id="asset-name"
                                    required
                                    value={form.name}
                                    onChange={(event) =>
                                        update('name', event.target.value)
                                    }
                                    placeholder="e.g. Banque Misr fixed-income fund"
                                />
                                <FieldDescription>
                                    Use a name you will recognize later:
                                    product, bank, broker, or physical holding.
                                </FieldDescription>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="asset-type">
                                    Asset type{' '}
                                    <Help text="Type describes what the asset is. It does not decide its purpose; the purpose split comes after saving." />
                                </FieldLabel>
                                <Select
                                    value={form.type}
                                    onValueChange={(value) =>
                                        update('type', String(value ?? ''))
                                    }
                                >
                                    <SelectTrigger id="asset-type">
                                        <SelectValue placeholder="Select asset type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>
                                                Cash & hard assets
                                            </SelectLabel>
                                            {assetTypes
                                                .slice(0, 3)
                                                .map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                        </SelectGroup>
                                        <SelectSeparator />
                                        <SelectGroup>
                                            <SelectLabel>
                                                Investments
                                            </SelectLabel>
                                            {assetTypes
                                                .slice(3, 6)
                                                .map(([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                        </SelectGroup>
                                        <SelectSeparator />
                                        <SelectGroup>
                                            <SelectItem value="Other">
                                                Other
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    {selectedType?.[2]}
                                </FieldDescription>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="asset-liquidity">
                                    When can you use it?{' '}
                                    <Help text="Liquidity is how quickly you can turn the asset into spendable cash, without assuming its price or terms." />
                                </FieldLabel>
                                <Select
                                    value={form.liquidity}
                                    onValueChange={(value) =>
                                        update('liquidity', String(value ?? ''))
                                    }
                                >
                                    <SelectTrigger id="asset-liquidity">
                                        <SelectValue placeholder="Select availability" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="immediate">
                                                Available now
                                            </SelectItem>
                                            <SelectItem value="within_3_days">
                                                Within 3 days
                                            </SelectItem>
                                            <SelectItem value="longer_term">
                                                Longer than 3 days
                                            </SelectItem>
                                            <SelectItem value="illiquid">
                                                Not readily sellable
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    Choose based on practical access, fees, and
                                    restrictions—not only whether it has a
                                    value.
                                </FieldDescription>
                            </Field>
                        </FieldGroup>

                        <FieldSet>
                            <FieldLegend>Value and performance</FieldLegend>
                            <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                <Field>
                                    <FieldLabel htmlFor="asset-current-value">
                                        Current value (EGP){' '}
                                        <Help text="What the entire holding would reasonably be worth today, expressed in EGP. This is required." />
                                    </FieldLabel>
                                    <Input
                                        id="asset-current-value"
                                        type="number"
                                        step="0.01"
                                        required
                                        value={form.current_value_egp}
                                        onChange={(event) =>
                                            update(
                                                'current_value_egp',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="asset-cost-basis">
                                        Cost basis (EGP){' '}
                                        <Help text="What you originally paid or contributed for this holding. It lets the app calculate gain or loss against current value." />
                                    </FieldLabel>
                                    <Input
                                        id="asset-cost-basis"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.cost_basis_egp}
                                        onChange={(event) =>
                                            update(
                                                'cost_basis_egp',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Optional"
                                    />
                                    <FieldDescription>
                                        Leave empty when you do not know it; it
                                        is not a monthly expense.
                                    </FieldDescription>
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="asset-quantity">
                                        Quantity{' '}
                                        <Help text="Units you hold: grams of gold, fund units, shares, or dollars. Optional for a plain cash balance." />
                                    </FieldLabel>
                                    <Input
                                        id="asset-quantity"
                                        type="number"
                                        min="0"
                                        step="any"
                                        value={form.quantity}
                                        onChange={(event) =>
                                            update(
                                                'quantity',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Optional"
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="asset-unit-price">
                                        Unit price (EGP){' '}
                                        <Help text="Current EGP value of one unit. Use it for gold grams, shares, or fund units when helpful." />
                                    </FieldLabel>
                                    <Input
                                        id="asset-unit-price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={form.unit_price_egp}
                                        onChange={(event) =>
                                            update(
                                                'unit_price_egp',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Optional"
                                    />
                                </Field>
                            </FieldGroup>
                        </FieldSet>

                        <FieldSet>
                            <FieldLegend>Where it lives</FieldLegend>
                            <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                <Field>
                                    <FieldLabel htmlFor="asset-currency">
                                        Native currency
                                    </FieldLabel>
                                    <Input
                                        id="asset-currency"
                                        required
                                        value={form.currency}
                                        onChange={(event) =>
                                            update(
                                                'currency',
                                                event.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="EGP, USD"
                                        maxLength={8}
                                    />
                                    <FieldDescription>
                                        Keep the currency you actually hold;
                                        current value remains in EGP.
                                    </FieldDescription>
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="asset-account">
                                        Account or location
                                    </FieldLabel>
                                    <Input
                                        id="asset-account"
                                        value={form.account_name}
                                        onChange={(event) =>
                                            update(
                                                'account_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Bank, broker, home safe…"
                                    />
                                </Field>
                            </FieldGroup>
                        </FieldSet>
                        <FieldSet>
                            <FieldLegend>Optional context</FieldLegend>
                            <FieldGroup className="grid gap-4 sm:grid-cols-2">
                                <Field>
                                    <FieldLabel htmlFor="asset-acquired-on">
                                        Acquired on{' '}
                                        <Help text="The date you bought or received the holding. It helps you remember the history; it does not change its value." />
                                    </FieldLabel>
                                    <Input
                                        id="asset-acquired-on"
                                        type="date"
                                        value={form.acquired_on}
                                        onChange={(event) =>
                                            update(
                                                'acquired_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="asset-notes">
                                        Notes
                                    </FieldLabel>
                                    <Textarea
                                        id="asset-notes"
                                        value={form.notes}
                                        onChange={(event) =>
                                            update('notes', event.target.value)
                                        }
                                        placeholder="Terms, maturity date, fund name, or anything useful later"
                                    />
                                    <FieldDescription>
                                        For context such as a certificate
                                        maturity date or investment
                                        restrictions.
                                    </FieldDescription>
                                </Field>
                            </FieldGroup>
                        </FieldSet>
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
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
                    title={`Give ${allocationAsset.name} a purpose`}
                    description="This does not split or sell the holding. It only records how much of today’s value is reserved for each job."
                    onClose={() => setAllocationAsset(null)}
                >
                    <form
                        onSubmit={saveAllocations}
                        className="flex flex-col gap-5"
                    >
                        <div className="grid gap-3 sm:grid-cols-3">
                            <AllocationStat
                                label="Asset value"
                                value={formatEGP(totals.value)}
                            />
                            <AllocationStat
                                label="Assigned"
                                value={formatEGP(totals.assigned)}
                            />
                            <AllocationStat
                                label={
                                    totals.overAllocated
                                        ? 'Over by'
                                        : 'Still flexible'
                                }
                                value={formatEGP(Math.abs(totals.remaining))}
                                destructive={totals.overAllocated}
                            />
                        </div>
                        <Progress value={totals.percent} />
                        {totals.overAllocated && (
                            <p className="text-sm text-destructive">
                                Reduce the purpose amounts by{' '}
                                {formatEGP(Math.abs(totals.remaining))} before
                                saving.
                            </p>
                        )}
                        {!buckets.length ? (
                            <Card size="sm">
                                <CardHeader>
                                    <CardTitle>
                                        Create a purpose first
                                    </CardTitle>
                                    <CardDescription>
                                        Examples: Emergency reserve, Car,
                                        Short-term savings, or Long-term
                                        investing.
                                    </CardDescription>
                                </CardHeader>
                                <CardFooter>
                                    <Button href="/buckets">
                                        Create a bucket
                                    </Button>
                                </CardFooter>
                            </Card>
                        ) : (
                            <FieldGroup>
                                {buckets.map((bucket) => (
                                    <Field
                                        key={bucket.id}
                                        data-invalid={totals.overAllocated}
                                    >
                                        <FieldLabel
                                            htmlFor={`allocation-${bucket.id}`}
                                        >
                                            <span>{bucket.name}</span>
                                            <span className="font-normal text-muted-foreground">
                                                {bucket.goalName
                                                    ? `Goal: ${bucket.goalName}`
                                                    : (bucket.purpose ??
                                                      'Flexible purpose')}
                                            </span>
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
                                                    [bucket.id]:
                                                        event.target.value,
                                                }))
                                            }
                                            placeholder="0"
                                            aria-invalid={totals.overAllocated}
                                        />
                                    </Field>
                                ))}
                            </FieldGroup>
                        )}
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAllocationAsset(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    !buckets.length || totals.overAllocated
                                }
                            >
                                Save purpose split
                            </Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}

function ConceptCard({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof WalletCards;
    title: string;
    description: string;
}) {
    return (
        <Card size="sm">
            <CardHeader>
                <Icon className="text-primary" />
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
        </Card>
    );
}
function AllocationStat({
    label,
    value,
    destructive = false,
}: {
    label: string;
    value: string;
    destructive?: boolean;
}) {
    return (
        <div className="rounded-lg bg-muted/60 p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'mt-1 font-medium tabular-nums',
                    destructive && 'text-destructive',
                )}
            >
                {value}
            </p>
        </div>
    );
}
function EmptyAssetState({ onAdd }: { onAdd: () => void }) {
    return (
        <div className="flex flex-col items-center gap-3 text-center">
            <Landmark className="text-muted-foreground" />
            <p className="font-medium">Start with what you already own</p>
            <p className="max-w-md text-sm text-muted-foreground">
                Add cash, gold, USD, a certificate, a fund, shares, or any other
                holding. You can give it a purpose afterwards.
            </p>
            <Button onClick={onAdd}>
                <Plus data-icon="inline-start" />
                Add your first asset
            </Button>
        </div>
    );
}
function Help({ text }: { text: string }) {
    return (
        <Tooltip>
            <TooltipTrigger aria-label={text}>
                <CircleHelp className="text-muted-foreground" />
            </TooltipTrigger>
            <TooltipContent>{text}</TooltipContent>
        </Tooltip>
    );
}
function allocationSummary(
    asset: Asset | null,
    allocations: Record<number, string>,
) {
    const value = asset?.currentValue ?? 0;
    const assigned = Object.values(allocations).reduce(
        (total, amount) => total + Math.max(0, Number(amount) || 0),
        0,
    );
    const remaining = value - assigned;

    return {
        value,
        assigned,
        remaining,
        percent: value > 0 ? Math.min(100, (assigned / value) * 100) : 0,
        overAllocated: remaining < -0.005,
    };
}
function liquidityLabel(value: string) {
    return (
        {
            immediate: 'Available now',
            within_3_days: 'Within 3 days',
            longer_term: 'Longer than 3 days',
            illiquid: 'Not readily sellable',
        }[value] ?? labelize(value)
    );
}
