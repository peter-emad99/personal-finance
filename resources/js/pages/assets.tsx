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
import { Fragment, useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import {
    AppShell,
    Badge,
    Button,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { DatePicker } from '@/components/date-picker';
import { FormModal, FormModalClose } from '@/components/form';
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
import { Separator } from '@/components/ui/separator';
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
import type { Asset, AssetType } from '@/types/finance';

type BucketOption = {
    id: number;
    name: string;
    purpose: string | null;
    goalName: string | null;
};

const blank = {
    name: '',
    type: 'Cash',
    asset_type_id: '',
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
    assetTypes,
}: {
    assets: Asset[];
    buckets: BucketOption[];
    assetTypes: AssetType[];
}) {
    const [editing, setEditing] = useState<Asset | null>(null);
    const [open, setOpen] = useState(false);
    const [allocationAsset, setAllocationAsset] = useState<Asset | null>(null);
    const [detailAsset, setDetailAsset] = useState<Asset | null>(null);
    const [allocations, setAllocations] = useState<Record<number, string>>({});
    const [form, setForm] = useState(blank);
    const [view, setView] = useState<'all' | 'class' | 'type'>('all');
    const [filter, setFilter] = useState('all');
    const [collapsedGroups, setCollapsedGroups] = useState<Record<string, boolean>>({});
    const selectedType = assetTypes.find((type) => String(type.id) === form.asset_type_id);
    const activeAssets = useMemo(() => assets.filter((asset) => !asset.archived), [assets]);
    const classSummaries = useMemo(() => {
        const order = ['cash', 'reserved_cash', 'investment', 'fixed_income', 'gold', 'receivable', 'other'];
        const grouped = new Map<string, { label: string; value: number; count: number }>();

        activeAssets.forEach((asset) => {
            const key = asset.assetClass ?? 'other';
            const current = grouped.get(key) ?? { label: asset.assetClassLabel ?? labelize(key), value: 0, count: 0 };
            current.value += asset.currentValue;
            current.count += 1;
            grouped.set(key, current);
        });

        return order
            .filter((key) => grouped.has(key))
            .map((key) => ({ key, ...grouped.get(key)! }));
    }, [activeAssets]);
    const visibleAssets = useMemo(() => assets.filter((asset) => {
        if (filter === 'all' || view === 'all') {
            return true;
        }

        return view === 'class' ? asset.assetClass === filter : asset.assetTypeKey === filter;
    }), [assets, filter, view]);
    const groupedAssets = useMemo(() => {
        if (view === 'all') {
            return [];
        }

        const groups = new Map<string, { label: string; assets: Asset[] }>();
        visibleAssets.forEach((asset) => {
            const key = view === 'class' ? (asset.assetClass ?? 'other') : (asset.assetTypeKey ?? asset.type);
            const label = view === 'class' ? (asset.assetClassLabel ?? labelize(key)) : (asset.assetTypeLabel ?? asset.type);
            const current = groups.get(key) ?? { label, assets: [] };
            current.assets.push(asset);
            groups.set(key, current);
        });

        return [...groups.entries()]
            .map(([key, group]) => ({ key, ...group, value: group.assets.reduce((sum, asset) => sum + asset.currentValue, 0) }))
            .sort((a, b) => b.value - a.value);
    }, [view, visibleAssets]);
    const typeOptions = useMemo(() => Array.from(new Map(assets.map((asset) => [asset.assetTypeKey ?? asset.type, asset.assetTypeLabel ?? asset.type])).entries()), [assets]);
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
                      asset_type_id: asset.assetTypeId ? String(asset.assetTypeId) : '',
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
                : {
                      ...blank,
                      asset_type_id: String(assetTypes.find((type) => type.key === 'bank_cash')?.id ?? ''),
                  },
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
                        {activeAssets.length} active assets · all values are shown in EGP so
                        you can compare them.
                    </CardDescription>
                    <CardAction>
                        <Badge variant="secondary">
                            {formatEGP(activeAssets.reduce((sum, asset) => sum + asset.currentValue, 0))}
                        </Badge>
                    </CardAction>
                </CardHeader>
                <CardContent className="border-b px-5 py-4">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Metric title="All assets" value={activeAssets.reduce((sum, asset) => sum + asset.currentValue, 0)} detail={`${activeAssets.length} active assets`} />
                        {classSummaries.map((summary) => (
                            <Metric key={summary.key} title={summary.label} value={summary.value} detail={`${summary.count} ${summary.count === 1 ? 'asset' : 'assets'}`} />
                        ))}
                    </div>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <Select
                            value={view}
                            onValueChange={(value) => {
                                setView(value as 'all' | 'class' | 'type');
                                setFilter('all');
                            }}
                        >
                            <SelectTrigger className="w-[180px]"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectGroup><SelectItem value="all">Flat list</SelectItem><SelectItem value="class">Group by class</SelectItem><SelectItem value="type">Group by type</SelectItem></SelectGroup></SelectContent>
                        </Select>
                        {view !== 'all' && <Select value={filter} onValueChange={(value) => setFilter(value ?? 'all')}>
                            <SelectTrigger className="w-[220px]"><SelectValue placeholder="Filter" /></SelectTrigger>
                            <SelectContent><SelectGroup><SelectItem value="all">All {view === 'class' ? 'classes' : 'types'}</SelectItem>{(view === 'class' ? classSummaries.map((summary) => [summary.key, summary.label] as const) : typeOptions).map(([value, label]) => value ? <SelectItem key={value} value={value}>{label}</SelectItem> : null)}</SelectGroup></SelectContent>
                        </Select>}
                    </div>
                </CardContent>
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
                                {view === 'all'
                                    ? visibleAssets.map((asset) => (
                                          <AssetTableRow
                                              key={asset.id}
                                              asset={asset}
                                              begin={begin}
                                              openAllocations={openAllocations}
                                              setDetailAsset={setDetailAsset}
                                          />
                                      ))
                                    : groupedAssets.map((group) => {
                                          const collapsed = collapsedGroups[group.key] ?? false;

                                          return (
                                              <Fragment key={group.key}>
                                                  <TableRow className="bg-muted/40">
                                                      <TableCell colSpan={6} className="px-5 py-3">
                                                          <button
                                                              type="button"
                                                              className="flex w-full items-center justify-between gap-3 text-left"
                                                              aria-expanded={!collapsed}
                                                              onClick={() => setCollapsedGroups((current) => ({ ...current, [group.key]: !collapsed }))}
                                                          >
                                                              <span className="flex min-w-0 items-center gap-2">
                                                                  <span className="text-sm font-semibold">{group.label}</span>
                                                                  <Badge variant="secondary">{group.assets.length} {group.assets.length === 1 ? 'asset' : 'assets'}</Badge>
                                                              </span>
                                                              <span className="flex shrink-0 items-center gap-2 text-sm font-semibold tabular-nums">
                                                                  {formatEGP(group.value)}
                                                                  <span className="text-muted-foreground">{collapsed ? 'Show' : 'Hide'}</span>
                                                              </span>
                                                          </button>
                                                      </TableCell>
                                                  </TableRow>
                                                  {!collapsed && group.assets.map((asset) => (
                                                      <AssetTableRow
                                                          key={asset.id}
                                                          asset={asset}
                                                          begin={begin}
                                                          openAllocations={openAllocations}
                                                          setDetailAsset={setDetailAsset}
                                                      />
                                                  ))}
                                              </Fragment>
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
                                    value={form.asset_type_id}
                                    onValueChange={(value) => {
                                        const selected = assetTypes.find((type) => String(type.id) === value);
                                        setForm((current) => ({ ...current, asset_type_id: value ?? '', type: selected?.label ?? current.type, liquidity: selected?.defaultLiquidity ?? current.liquidity }));
                                    }}
                                >
                                    <SelectTrigger id="asset-type">
                                        <SelectValue placeholder="Select asset type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>
                                                Cash & hard assets
                                            </SelectLabel>
                                            {assetTypes.filter((type) => ['cash', 'reserved_cash', 'gold'].includes(type.class)).map((type) => (
                                                    <SelectItem
                                                        key={type.id}
                                                        value={String(type.id)}
                                                    >
                                                        {type.label} <span className="text-muted-foreground">· {type.classLabel}</span>
                                                    </SelectItem>
                                                ))}
                                        </SelectGroup>
                                        <SelectSeparator />
                                        <SelectGroup>
                                            <SelectLabel>
                                                Investments
                                            </SelectLabel>
                                            {assetTypes.filter((type) => ['investment', 'fixed_income'].includes(type.class)).map((type) => (
                                                    <SelectItem
                                                        key={type.id}
                                                        value={String(type.id)}
                                                    >
                                                        {type.label} <span className="text-muted-foreground">· {type.classLabel}</span>
                                                    </SelectItem>
                                                ))}
                                        </SelectGroup>
                                        <SelectSeparator />
                                        <SelectGroup>
                                            {assetTypes.filter((type) => !['cash', 'reserved_cash', 'gold', 'investment', 'fixed_income'].includes(type.class)).map((type) => <SelectItem key={type.id} value={String(type.id)}>{type.label} <span className="text-muted-foreground">· {type.classLabel}</span></SelectItem>)}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    {selectedType ? `Rollup: ${selectedType.classLabel}. Pricing: ${selectedType.pricingBehavior}.` : 'Choose a catalog type. Legacy records remain compatible.'}
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
                                    <DatePicker
                                        id="asset-acquired-on"
                                        value={form.acquired_on}
                                        onChange={(value) =>
                                            update('acquired_on', value)
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
                            <FormModalClose>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </FormModalClose>
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
                            <FormModalClose>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </FormModalClose>
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
            {detailAsset && (
                <FormModal
                    title={detailAsset.name}
                    description="See the full holding first, then follow each linked purpose or adjust the split."
                    onClose={() => setDetailAsset(null)}
                >
                    <div className="flex flex-col gap-5">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <AllocationStat
                                label="Current value"
                                value={formatEGP(detailAsset.currentValue)}
                            />
                            <AllocationStat
                                label="Assigned to purposes"
                                value={formatEGP(
                                    (
                                        detailAsset.bucketAllocations ?? []
                                    ).reduce(
                                        (total, item) => total + item.amount,
                                        0,
                                    ),
                                )}
                            />
                            <AllocationStat
                                label="Not assigned"
                                value={formatEGP(
                                    Math.max(
                                        0,
                                        detailAsset.currentValue -
                                            (
                                                detailAsset.bucketAllocations ??
                                                []
                                            ).reduce(
                                                (total, item) =>
                                                    total + item.amount,
                                                0,
                                            ),
                                    ),
                                )}
                            />
                        </div>
                        <Separator />
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center justify-between gap-3">
                                <p className="font-medium">Linked buckets</p>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        setDetailAsset(null);
                                        openAllocations(detailAsset);
                                    }}
                                >
                                    Edit purpose split
                                </Button>
                            </div>
                            {detailAsset.bucketAllocations?.length ? (
                                detailAsset.bucketAllocations.map((bucket) => (
                                    <Card key={bucket.bucketId} size="sm">
                                        <CardHeader>
                                            <CardTitle>
                                                {bucket.bucketName}
                                            </CardTitle>
                                            <CardDescription>
                                                {bucket.goalName
                                                    ? `Goal: ${bucket.goalName}`
                                                    : (bucket.purpose ??
                                                      'Flexible purpose')}
                                            </CardDescription>
                                            <CardAction>
                                                <span className="font-medium tabular-nums">
                                                    {formatEGP(bucket.amount)}
                                                </span>
                                            </CardAction>
                                        </CardHeader>
                                        <CardFooter className="justify-between gap-2 text-xs text-muted-foreground">
                                            <span>
                                                {bucket.targetAmount
                                                    ? `${formatEGP(Math.max(0, bucket.targetAmount - (bucket.currentAmount ?? 0)))} until target`
                                                    : 'No target limit'}
                                            </span>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.visit('/buckets')
                                                }
                                            >
                                                Open bucket
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    This asset is not assigned to any bucket
                                    yet.
                                </p>
                            )}
                        </div>
                        <Separator />
                        <AssetDetailSection title="Identity & classification">
                            <AssetDetailGrid>
                                <AssetDetailField label="Asset name" value={detailAsset.name} />
                                <AssetDetailField label="Asset type" value={detailAsset.assetTypeLabel ?? detailAsset.type} />
                                <AssetDetailField label="Asset class" value={detailAsset.assetClassLabel ?? 'Other'} />
                                <AssetDetailField label="Catalog key" value={detailAsset.assetTypeKey ?? 'Legacy / not assigned'} />
                                <AssetDetailField label="Pricing behavior" value={detailAsset.assetType?.pricingBehavior ? labelize(detailAsset.assetType.pricingBehavior) : 'Manual / legacy'} />
                                <AssetDetailField label="Status" value={detailAsset.archived ? 'Archived' : 'Active'} />
                            </AssetDetailGrid>
                        </AssetDetailSection>
                        <AssetDetailSection title="Value & performance">
                            <AssetDetailGrid>
                                <AssetDetailField label="Current value" value={formatEGP(detailAsset.currentValue)} />
                                <AssetDetailField label="Cost basis" value={detailAsset.costBasis > 0 ? formatEGP(detailAsset.costBasis) : 'Not provided'} />
                                <AssetDetailField
                                    label="Gain / loss"
                                    value={`${detailAsset.gainLoss >= 0 ? '+' : ''}${formatEGP(detailAsset.gainLoss)}`}
                                    valueClassName={detailAsset.gainLoss >= 0 ? 'text-primary' : 'text-destructive'}
                                />
                                <AssetDetailField label="Native currency" value={detailAsset.currency} />
                                <AssetDetailField label="Quantity" value={detailAsset.quantity !== null ? `${detailAsset.quantity} ${detailAsset.currency}` : 'Not tracked'} />
                                <AssetDetailField label="Unit price" value={detailAsset.unitPrice !== null ? formatEGP(detailAsset.unitPrice) : 'Not tracked'} />
                            </AssetDetailGrid>
                        </AssetDetailSection>
                        <AssetDetailSection title="Access & location">
                            <AssetDetailGrid>
                                <AssetDetailField label="Account / location" value={detailAsset.accountName ?? 'Not specified'} />
                                <AssetDetailField label="Liquidity" value={liquidityLabel(detailAsset.liquidity)} />
                                <AssetDetailField label="Liquid under policy" value={detailAsset.isLiquid ? 'Yes' : 'No'} />
                                <AssetDetailField label="Acquired on" value={detailAsset.acquiredOn ?? 'Not specified'} />
                            </AssetDetailGrid>
                        </AssetDetailSection>
                        <AssetDetailSection title="Notes">
                            <p className="text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                {detailAsset.notes?.trim() || 'No notes recorded.'}
                            </p>
                        </AssetDetailSection>
                    </div>
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

function AssetDetailSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col gap-3">
            <h3 className="text-sm font-semibold">{title}</h3>
            {children}
        </section>
    );
}

function AssetDetailGrid({ children }: { children: ReactNode }) {
    return <div className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">{children}</div>;
}

function AssetDetailField({
    label,
    value,
    valueClassName,
}: {
    label: string;
    value: string;
    valueClassName?: string;
}) {
    return (
        <div className="min-w-0">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={cn('mt-1 break-words text-sm font-medium', valueClassName)}>{value}</p>
        </div>
    );
}

function AssetTableRow({
    asset,
    begin,
    openAllocations,
    setDetailAsset,
}: {
    asset: Asset;
    begin: (asset?: Asset) => void;
    openAllocations: (asset: Asset) => void;
    setDetailAsset: (asset: Asset) => void;
}) {
    const allocated = (asset.bucketAllocations ?? []).reduce(
        (total, item) => total + item.amount,
        0,
    );
    const remaining = Math.max(0, asset.currentValue - allocated);

    return (
        <TableRow className={asset.archived ? 'opacity-60' : ''}>
            <TableCell className="py-4 pl-5">
                <div className="flex flex-col gap-1">
                    <Button
                        variant="link"
                        className="h-auto justify-start p-0 font-medium"
                        onClick={() => setDetailAsset(asset)}
                    >
                        {asset.name}
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        {asset.assetTypeLabel ?? asset.type} ·{' '}
                        {asset.assetClassLabel ?? 'Other'} ·{' '}
                        {asset.accountName ?? asset.currency}
                    </p>
                </div>
            </TableCell>
            <TableCell className="py-4">
                <p className="font-medium tabular-nums">
                    {formatEGP(asset.currentValue)}
                </p>
                {asset.quantity !== null && (
                    <p className="text-xs text-muted-foreground">
                        {asset.quantity} {asset.currency}
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
                        {asset.gainLoss >= 0 ? '+' : ''}
                        {formatEGP(asset.gainLoss)}
                    </p>
                ) : (
                    <span className="text-muted-foreground">Not tracked</span>
                )}
            </TableCell>
            <TableCell className="py-4">
                <Badge variant="outline">
                    {liquidityLabel(asset.liquidity)}
                </Badge>
            </TableCell>
            <TableCell className="py-4">
                <Button
                    variant="outline"
                    className="h-auto w-full max-w-[22rem] justify-start p-3 text-left whitespace-normal"
                    onClick={() => openAllocations(asset)}
                >
                    <div className="flex w-full min-w-0 flex-col gap-1.5">
                        <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                            <span className="font-medium text-primary">
                                {asset.bucketAllocations?.length
                                    ? `${asset.bucketAllocations.length} ${asset.bucketAllocations.length === 1 ? 'purpose' : 'purposes'}`
                                    : 'Assign a purpose'}
                            </span>
                            {asset.bucketAllocations?.length ? (
                                <span className="text-xs font-medium text-muted-foreground tabular-nums">
                                    {formatEGP(allocated)} assigned
                                </span>
                            ) : null}
                        </div>
                        {asset.bucketAllocations?.length ? (
                            <div className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                {asset.bucketAllocations.map((bucket) => (
                                    <span
                                        key={`${bucket.bucketId}-${bucket.amount}`}
                                        className="whitespace-nowrap"
                                    >
                                        {bucket.bucketName}{' '}
                                        <span className="font-medium text-foreground tabular-nums">
                                            {formatEGP(bucket.amount)}
                                        </span>
                                    </span>
                                ))}
                            </div>
                        ) : (
                            <span className="text-xs text-muted-foreground">
                                {formatEGP(remaining)} unassigned
                            </span>
                        )}
                    </div>
                </Button>
            </TableCell>
            <TableCell className="py-4 pr-5 text-right">
                {!asset.archived ? (
                    <div className="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => begin(asset)}
                        >
                            <Pencil data-icon="inline-start" />
                            Edit
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => {
                                if (confirm('Archive this asset?')) {
                                    router.delete(`/assets/${asset.id}`);
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
                        onClick={() => router.post(`/assets/${asset.id}/restore`)}
                    >
                        Restore
                    </Button>
                )}
            </TableCell>
        </TableRow>
    );
}

function Metric({ title, value, detail }: { title: string; value: number; detail?: string }) {
    return (
        <div className="rounded-lg bg-muted/60 p-3">
            <p className="text-xs text-muted-foreground">{title}</p>
            <p className="mt-1 font-medium tabular-nums">{formatEGP(value)}</p>
            {detail && <p className="mt-1 text-xs text-muted-foreground">{detail}</p>}
        </div>
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
