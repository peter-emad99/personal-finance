import { router } from '@inertiajs/react';
import {
    ChevronRight,
    CircleHelp,
    Flag,
    Pencil,
    Plus,
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
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { formatEGP } from '@/types/finance';

type AssetAllocation = {
    assetId: number;
    assetName: string;
    assetType: string;
    amount: number;
};
type Bucket = {
    id: number;
    name: string;
    purpose: string | null;
    color: string;
    goalName: string | null;
    goalId: number | null;
    targetAmount: number;
    currentAmount: number;
    assetCount: number;
    assetAllocations: AssetAllocation[];
    archived?: boolean;
};
type AssetOption = {
    id: number;
    name: string;
    type: string;
    currentValue: number;
    allocated: number;
    bucketAllocations: {
        bucketId: number;
        bucketName: string;
        purpose: string | null;
        goalName: string | null;
        amount: number;
    }[];
};

const blank = {
    name: '',
    purpose: '',
    target_amount_egp: '',
    color: '#7c8cf8',
    goal_id: null as number | null,
};

export default function Buckets({
    buckets,
    assets,
}: {
    buckets: Bucket[];
    assets: AssetOption[];
}) {
    const [editing, setEditing] = useState<Bucket | null>(null);
    const [open, setOpen] = useState(false);
    const [fundingBucket, setFundingBucket] = useState<Bucket | null>(null);
    const [detailAsset, setDetailAsset] = useState<AssetOption | null>(null);
    const [allocations, setAllocations] = useState<Record<number, string>>({});
    const [form, setForm] = useState(blank);
    const activeBuckets = buckets.filter((bucket) => !bucket.archived);
    const archivedBuckets = buckets.filter((bucket) => bucket.archived);
    const funding = useMemo(
        () => bucketFunding(fundingBucket, allocations, assets),
        [fundingBucket, allocations, assets],
    );

    const update = (key: keyof typeof blank, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const begin = (bucket?: Bucket) => {
        setEditing(bucket ?? null);
        setForm(
            bucket
                ? {
                      name: bucket.name,
                      purpose: bucket.purpose ?? '',
                      target_amount_egp: bucket.targetAmount
                          ? String(bucket.targetAmount)
                          : '',
                      color: bucket.color,
                      goal_id: bucket.goalId,
                  }
                : blank,
        );
        setOpen(true);
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router[editing ? 'put' : 'post'](
            editing ? `/buckets/${editing.id}` : '/buckets',
            form,
            {
                onSuccess: () => {
                    setOpen(false);
                    setEditing(null);
                },
            },
        );
    };
    const beginFunding = (bucket: Bucket) => {
        setFundingBucket(bucket);
        setAllocations(
            Object.fromEntries(
                bucket.assetAllocations.map((item) => [
                    item.assetId,
                    String(item.amount),
                ]),
            ),
        );
    };
    const submitFunding = (event: React.FormEvent) => {
        event.preventDefault();

        if (!fundingBucket || funding.overTarget || funding.overAsset) {
            return;
        }

        router.put(
            `/buckets/${fundingBucket.id}/allocations`,
            {
                allocations: Object.entries(allocations).map(
                    ([assetId, amount]) => ({
                        asset_id: Number(assetId),
                        amount_egp: Number(amount || 0),
                    }),
                ),
            },
            { onSuccess: () => setFundingBucket(null) },
        );
    };

    return (
        <AppShell title="Purpose buckets">
            <PageHeader
                eyebrow="WHERE EACH EGP IS MEANT TO GO"
                title="Purpose buckets"
                description="Choose a purpose first, then fund it from the assets you already own. A goal automatically has its own protected bucket."
                action={
                    <Button onClick={() => begin()}>
                        <Plus data-icon="inline-start" />
                        Add a purpose
                    </Button>
                }
            />

            <Card className="mb-6" size="sm">
                <CardHeader>
                    <CardTitle>How funding works</CardTitle>
                    <CardDescription>
                        A bucket is a label for a job, not another bank account
                        or investment.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-3">
                    <Explanation
                        title="1. Create a purpose"
                        text="For example: emergency reserve, short-term savings, or long-term investing."
                    />
                    <Explanation
                        title="2. Fund it with real assets"
                        text="Choose cash, gold, a fund, or another asset, and enter the amount that supports this purpose."
                    />
                    <Explanation
                        title="3. Respect both limits"
                        text="An asset cannot be assigned beyond its value. A bucket with a target cannot be funded above that target."
                    />
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
                {activeBuckets.map((bucket) => {
                    const progress =
                        bucket.targetAmount > 0
                            ? (bucket.currentAmount / bucket.targetAmount) * 100
                            : 0;
                    const remaining = Math.max(
                        0,
                        bucket.targetAmount - bucket.currentAmount,
                    );

                    return (
                        <Card key={bucket.id} id={`bucket-${bucket.id}`}>
                            <CardHeader>
                                <div className="flex items-start gap-3">
                                    <div
                                        className="mt-1 size-3 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor: bucket.color,
                                        }}
                                    />
                                    <div>
                                        <CardTitle>{bucket.name}</CardTitle>
                                        <CardDescription>
                                            {bucket.goalName
                                                ? `Goal bucket · ${bucket.goalName}`
                                                : (bucket.purpose ??
                                                  'Flexible purpose')}
                                        </CardDescription>
                                    </div>
                                </div>
                                <CardAction>
                                    {bucket.goalId ? (
                                        <Badge variant="secondary">
                                            <Flag data-icon="inline-start" />
                                            Goal-linked
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Flexible
                                        </Badge>
                                    )}
                                </CardAction>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div className="flex items-end justify-between gap-3">
                                    <div>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {formatEGP(bucket.currentAmount)}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            funded by {bucket.assetCount}{' '}
                                            {bucket.assetCount === 1
                                                ? 'asset'
                                                : 'assets'}
                                        </p>
                                    </div>
                                    {bucket.targetAmount > 0 && (
                                        <p className="text-right text-sm text-muted-foreground">
                                            Target
                                            <br />
                                            <span className="font-medium text-foreground">
                                                {formatEGP(bucket.targetAmount)}
                                            </span>
                                        </p>
                                    )}
                                </div>
                                {bucket.targetAmount > 0 && (
                                    <>
                                        <Progress
                                            value={progress}
                                            color={bucket.color}
                                        />
                                        <div className="flex justify-between text-xs text-muted-foreground">
                                            <span>
                                                {Math.min(
                                                    100,
                                                    Math.round(progress),
                                                )}
                                                % funded
                                            </span>
                                            <span>
                                                {formatEGP(remaining)} remaining
                                            </span>
                                        </div>
                                    </>
                                )}
                                <Separator />
                                {bucket.assetAllocations.length ? (
                                    <div className="flex flex-col gap-2">
                                        {bucket.assetAllocations.map((item) => {
                                            const asset = assets.find(
                                                (candidate) =>
                                                    candidate.id ===
                                                    item.assetId,
                                            );

                                            return (
                                                <Card
                                                    key={item.assetId}
                                                    size="sm"
                                                >
                                                    <CardHeader>
                                                        <CardTitle>
                                                            {item.assetName}
                                                        </CardTitle>
                                                        <CardDescription>
                                                            {item.assetType} ·
                                                            Real holding funding
                                                            this purpose
                                                        </CardDescription>
                                                        <CardAction>
                                                            <span className="font-medium tabular-nums">
                                                                {formatEGP(
                                                                    item.amount,
                                                                )}
                                                            </span>
                                                        </CardAction>
                                                    </CardHeader>
                                                    <CardFooter className="justify-between gap-2 text-xs text-muted-foreground">
                                                        <span>
                                                            {asset
                                                                ? `${formatEGP(Math.max(0, asset.currentValue - asset.allocated))} still unassigned`
                                                                : 'Allocation recorded'}
                                                        </span>
                                                        {asset && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    setDetailAsset(
                                                                        asset,
                                                                    )
                                                                }
                                                            >
                                                                Open asset
                                                            </Button>
                                                        )}
                                                    </CardFooter>
                                                </Card>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No asset is funding this purpose yet.
                                    </p>
                                )}
                            </CardContent>
                            <CardFooter className="justify-between gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => beginFunding(bucket)}
                                >
                                    <WalletCards data-icon="inline-start" />
                                    Fund this bucket
                                </Button>
                                <div className="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => begin(bucket)}
                                    >
                                        <Pencil data-icon="inline-start" />
                                        Edit
                                    </Button>
                                    {!bucket.goalId && (
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        'Archive this bucket?',
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/buckets/${bucket.id}`,
                                                    );
                                                }
                                            }}
                                        >
                                            Archive
                                        </Button>
                                    )}
                                </div>
                            </CardFooter>
                        </Card>
                    );
                })}
                {!activeBuckets.length && (
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>No purpose buckets yet</CardTitle>
                            <CardDescription>
                                Create an emergency reserve, a savings pool, or
                                a goal. When you create a goal, its bucket is
                                created automatically.
                            </CardDescription>
                        </CardHeader>
                        <CardFooter>
                            <Button onClick={() => begin()}>
                                <Plus data-icon="inline-start" />
                                Create a purpose
                            </Button>
                        </CardFooter>
                    </Card>
                )}
            </div>

            {archivedBuckets.length > 0 && (
                <Collapsible className="group/archived mt-8">
                    <Card size="sm">
                        <CardHeader>
                            <CardTitle>Archived purposes</CardTitle>
                            <CardDescription>
                                {archivedBuckets.length} hidden purpose
                                {archivedBuckets.length === 1 ? '' : 's'}.
                                Restore one when you want to use it again.
                            </CardDescription>
                            <CardAction>
                                <CollapsibleTrigger
                                    render={
                                        <Button variant="outline" size="sm" />
                                    }
                                >
                                    Show archived
                                    <ChevronRight
                                        data-icon="inline-end"
                                        className="transition-transform group-data-open/archived:rotate-90"
                                    />
                                </CollapsibleTrigger>
                            </CardAction>
                        </CardHeader>
                        <CollapsibleContent>
                            <CardContent className="flex flex-col gap-2">
                                {archivedBuckets.map((bucket) => (
                                    <div
                                        key={bucket.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {bucket.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {bucket.goalName
                                                    ? `Goal bucket · ${bucket.goalName}`
                                                    : (bucket.purpose ??
                                                      'Flexible purpose')}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    `/buckets/${bucket.id}/restore`,
                                                )
                                            }
                                        >
                                            Restore
                                        </Button>
                                    </div>
                                ))}
                            </CardContent>
                        </CollapsibleContent>
                    </Card>
                </Collapsible>
            )}

            {open && (
                <FormModal
                    title={
                        editing
                            ? `Edit ${editing.name}`
                            : 'Create a purpose bucket'
                    }
                    description="Use a bucket to define a job for money. Create a Goal instead when the purpose needs a deadline and a target you want to track."
                    onClose={() => {
                        setOpen(false);
                        setEditing(null);
                    }}
                >
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="bucket-name">
                                    Purpose name
                                </FieldLabel>
                                <Input
                                    id="bucket-name"
                                    required
                                    value={form.name}
                                    onChange={(event) =>
                                        update('name', event.target.value)
                                    }
                                    placeholder="e.g. Emergency reserve"
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="bucket-purpose">
                                    What is it for?
                                </FieldLabel>
                                <Input
                                    id="bucket-purpose"
                                    value={form.purpose}
                                    onChange={(event) =>
                                        update('purpose', event.target.value)
                                    }
                                    placeholder="Short description"
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="bucket-target">
                                    Target amount (EGP){' '}
                                    <Help text="Optional for flexible investing. Set a target for an emergency reserve or a specific saving purpose, and funding will not be allowed above it." />
                                </FieldLabel>
                                <Input
                                    id="bucket-target"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.target_amount_egp}
                                    onChange={(event) =>
                                        update(
                                            'target_amount_egp',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Optional"
                                />
                                <FieldDescription>
                                    A Goal controls its own target
                                    automatically, so edit it from Goals
                                    instead.
                                </FieldDescription>
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="bucket-color">
                                    Color
                                </FieldLabel>
                                <Input
                                    id="bucket-color"
                                    type="color"
                                    value={form.color}
                                    onChange={(event) =>
                                        update('color', event.target.value)
                                    }
                                    className="w-16"
                                />
                            </Field>
                        </FieldGroup>
                        <div className="flex justify-end gap-2">
                            <FormModalClose>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </FormModalClose>
                            <Button type="submit">
                                {editing ? 'Save changes' : 'Create purpose'}
                            </Button>
                        </div>
                    </form>
                </FormModal>
            )}

            {fundingBucket && (
                <FormModal
                    title={`Fund ${fundingBucket.name}`}
                    description="Enter how much of each real asset is reserved for this purpose. The amount remains in the original bank, fund, or holding."
                    onClose={() => setFundingBucket(null)}
                >
                    <form
                        onSubmit={submitFunding}
                        className="flex flex-col gap-5"
                    >
                        <div className="grid gap-3 sm:grid-cols-3">
                            <FundingStat
                                label="Assigned here"
                                value={formatEGP(funding.assigned)}
                            />
                            <FundingStat
                                label="Target capacity"
                                value={
                                    funding.target === null
                                        ? 'No target limit'
                                        : formatEGP(funding.target)
                                }
                            />
                            <FundingStat
                                label={
                                    funding.overTarget
                                        ? 'Over target by'
                                        : 'Target remaining'
                                }
                                value={
                                    funding.target === null
                                        ? '—'
                                        : formatEGP(
                                              Math.abs(funding.targetRemaining),
                                          )
                                }
                                destructive={funding.overTarget}
                            />
                        </div>
                        {funding.target !== null && (
                            <Progress
                                value={
                                    funding.target > 0
                                        ? (funding.assigned / funding.target) *
                                          100
                                        : 0
                                }
                            />
                        )}
                        {funding.overTarget && (
                            <p className="text-sm text-destructive">
                                Reduce the selected assets by{' '}
                                {formatEGP(Math.abs(funding.targetRemaining))}{' '}
                                before saving.
                            </p>
                        )}
                        {funding.overAsset && (
                            <p className="text-sm text-destructive">
                                One or more assets exceed their available value.
                                Check the red row.
                            </p>
                        )}
                        <FieldGroup>
                            {assets.map((asset) => {
                                const existing = Number(
                                    allocations[asset.id] || 0,
                                );
                                const alreadyElsewhere =
                                    asset.allocated -
                                    (fundingBucket.assetAllocations.find(
                                        (item) => item.assetId === asset.id,
                                    )?.amount ?? 0);
                                const available = Math.max(
                                    0,
                                    asset.currentValue - alreadyElsewhere,
                                );
                                const invalid = existing > available + 0.005;
                                const otherAllocations =
                                    asset.bucketAllocations.filter(
                                        (item) =>
                                            item.bucketId !== fundingBucket.id,
                                    );
                                const unassigned = Math.max(
                                    0,
                                    asset.currentValue - asset.allocated,
                                );

                                return (
                                    <Field
                                        key={asset.id}
                                        data-invalid={invalid}
                                    >
                                        <Card size="sm" className="w-full">
                                            <CardHeader className="border-b">
                                                <CardTitle>
                                                    {asset.name}
                                                </CardTitle>
                                                <CardDescription>
                                                    {asset.type} ·{' '}
                                                    {formatEGP(
                                                        asset.currentValue,
                                                    )}{' '}
                                                    total value
                                                </CardDescription>
                                                <CardAction>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setDetailAsset(
                                                                asset,
                                                            )
                                                        }
                                                    >
                                                        View details
                                                    </Button>
                                                </CardAction>
                                            </CardHeader>
                                            <CardContent className="flex flex-col gap-4">
                                                <div className="grid gap-3 sm:grid-cols-3">
                                                    <Card size="sm">
                                                        <CardContent className="flex flex-col gap-1">
                                                            <span className="text-xs text-muted-foreground">
                                                                Reserved here
                                                            </span>
                                                            <span className="font-medium text-foreground">
                                                                {formatEGP(
                                                                    existing,
                                                                )}
                                                            </span>
                                                        </CardContent>
                                                    </Card>
                                                    <Card size="sm">
                                                        <CardContent className="flex flex-col gap-1">
                                                            <span className="text-xs text-muted-foreground">
                                                                Reserved
                                                                elsewhere
                                                            </span>
                                                            <span className="font-medium text-foreground">
                                                                {formatEGP(
                                                                    alreadyElsewhere,
                                                                )}
                                                            </span>
                                                        </CardContent>
                                                    </Card>
                                                    <Card size="sm">
                                                        <CardContent className="flex flex-col gap-1">
                                                            <span className="text-xs text-muted-foreground">
                                                                Available to
                                                                assign
                                                            </span>
                                                            <span className="font-medium text-foreground">
                                                                {formatEGP(
                                                                    unassigned,
                                                                )}
                                                            </span>
                                                        </CardContent>
                                                    </Card>
                                                </div>
                                                {otherAllocations.length >
                                                    0 && (
                                                    <div className="flex flex-col gap-2">
                                                        <p className="text-sm font-medium">
                                                            Also supporting
                                                        </p>
                                                        <div className="flex flex-wrap gap-2">
                                                            {otherAllocations.map(
                                                                (item) => (
                                                                    <Button
                                                                        key={
                                                                            item.bucketId
                                                                        }
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="sm"
                                                                        onClick={() => {
                                                                            setFundingBucket(
                                                                                null,
                                                                            );
                                                                            requestAnimationFrame(
                                                                                () =>
                                                                                    document
                                                                                        .getElementById(
                                                                                            `bucket-${item.bucketId}`,
                                                                                        )
                                                                                        ?.scrollIntoView(
                                                                                            {
                                                                                                behavior:
                                                                                                    'smooth',
                                                                                                block: 'center',
                                                                                            },
                                                                                        ),
                                                                            );
                                                                        }}
                                                                    >
                                                                        {
                                                                            item.bucketName
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {formatEGP(
                                                                            item.amount,
                                                                        )}
                                                                    </Button>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                                <Field>
                                                    <FieldLabel
                                                        htmlFor={`bucket-asset-${asset.id}`}
                                                    >
                                                        Amount to reserve for{' '}
                                                        {fundingBucket.name}
                                                    </FieldLabel>
                                                    <Input
                                                        id={`bucket-asset-${asset.id}`}
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={
                                                            allocations[
                                                                asset.id
                                                            ] ?? ''
                                                        }
                                                        onChange={(event) =>
                                                            setAllocations(
                                                                (current) => ({
                                                                    ...current,
                                                                    [asset.id]:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                }),
                                                            )
                                                        }
                                                        placeholder="0"
                                                        aria-invalid={invalid}
                                                    />
                                                    <FieldDescription>
                                                        Up to{' '}
                                                        {formatEGP(available)}{' '}
                                                        can be reserved from
                                                        this asset.
                                                    </FieldDescription>
                                                    {invalid && (
                                                        <FieldDescription className="text-destructive">
                                                            Reduce this amount
                                                            before saving.
                                                        </FieldDescription>
                                                    )}
                                                </Field>
                                            </CardContent>
                                        </Card>
                                    </Field>
                                );
                            })}
                        </FieldGroup>
                        <div className="flex justify-end gap-2">
                            <FormModalClose>
                                <Button type="button" variant="outline">
                                    Cancel
                                </Button>
                            </FormModalClose>
                            <Button
                                type="submit"
                                disabled={
                                    funding.overTarget || funding.overAsset
                                }
                            >
                                Save funding
                            </Button>
                        </div>
                    </form>
                </FormModal>
            )}
            {detailAsset && (
                <FormModal
                    title={detailAsset.name}
                    description="This is the real asset. Purpose assignments reserve parts of its value; they do not create separate holdings."
                    onClose={() => setDetailAsset(null)}
                >
                    <div className="flex flex-col gap-5">
                        <div className="grid gap-3 sm:grid-cols-3">
                            <FundingStat
                                label="Asset value"
                                value={formatEGP(detailAsset.currentValue)}
                            />
                            <FundingStat
                                label="Assigned to purposes"
                                value={formatEGP(detailAsset.allocated)}
                            />
                            <FundingStat
                                label="Not assigned"
                                value={formatEGP(
                                    Math.max(
                                        0,
                                        detailAsset.currentValue -
                                            detailAsset.allocated,
                                    ),
                                )}
                            />
                        </div>
                        <Separator />
                        <div className="flex flex-col gap-2">
                            <p className="font-medium">Linked purposes</p>
                            {detailAsset.bucketAllocations.length ? (
                                detailAsset.bucketAllocations.map((item) => (
                                    <Card key={item.bucketId} size="sm">
                                        <CardHeader>
                                            <CardTitle>
                                                {item.bucketName}
                                            </CardTitle>
                                            <CardDescription>
                                                {item.goalName
                                                    ? `Goal bucket · ${item.goalName}`
                                                    : (item.purpose ??
                                                      'Flexible purpose')}
                                            </CardDescription>
                                            <CardAction>
                                                <span className="font-medium tabular-nums">
                                                    {formatEGP(item.amount)}
                                                </span>
                                            </CardAction>
                                        </CardHeader>
                                        <CardFooter>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setDetailAsset(null);
                                                    requestAnimationFrame(() =>
                                                        document
                                                            .getElementById(
                                                                `bucket-${item.bucketId}`,
                                                            )
                                                            ?.scrollIntoView({
                                                                behavior:
                                                                    'smooth',
                                                                block: 'center',
                                                            }),
                                                    );
                                                }}
                                            >
                                                Open bucket
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No purpose is linked to this asset yet.
                                </p>
                            )}
                        </div>
                    </div>
                </FormModal>
            )}
        </AppShell>
    );
}

function Explanation({ title, text }: { title: string; text: string }) {
    return (
        <div className="flex flex-col gap-1">
            <p className="font-medium">{title}</p>
            <p className="text-sm leading-6 text-muted-foreground">{text}</p>
        </div>
    );
}
function FundingStat({
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
function bucketFunding(
    bucket: Bucket | null,
    allocations: Record<number, string>,
    assets: AssetOption[],
) {
    const assigned = Object.values(allocations).reduce(
        (total, item) => total + Math.max(0, Number(item) || 0),
        0,
    );
    const targetAmount = bucket?.targetAmount ?? 0;
    const target = targetAmount > 0 ? targetAmount : null;
    const targetRemaining = target === null ? 0 : target - assigned;
    const overAsset = assets.some((asset) => {
        const currentAllocation =
            bucket?.assetAllocations.find((item) => item.assetId === asset.id)
                ?.amount ?? 0;
        const available =
            asset.currentValue - (asset.allocated - currentAllocation);

        return (Number(allocations[asset.id]) || 0) > available + 0.005;
    });

    return {
        assigned,
        target,
        targetRemaining,
        overTarget: targetRemaining < -0.005,
        overAsset,
    };
}
