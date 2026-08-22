import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatEGP } from '@/types/finance';
import type { Asset, Bucket } from '@/types/finance';

type Summary = {
    totalAssets: number;
    fullyAssignedPurposeBalance: number;
    allocatedToGoals: number;
    allocatedToNonGoals: number;
    trulyUnallocated: number;
};

export default function AllocationReconciliation({
    summary,
    assets,
    buckets,
    dataFreshness,
}: {
    summary: Summary;
    assets: Asset[];
    buckets: Bucket[];
    dataFreshness: { source: string; status: string };
}) {
    const [selectedAsset, setSelectedAsset] = useState<Asset | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [allocations, setAllocations] = useState<Record<number, string>>({});
    const allocatedTotal = useMemo(
        () =>
            Object.values(allocations).reduce(
                (total, value) => total + Number(value || 0),
                0,
            ),
        [allocations],
    );
    const openAsset = (asset: Asset) => {
        setSelectedAsset(asset);
        setSheetOpen(true);
        setAllocations(
            Object.fromEntries(
                buckets.map((bucket) => [
                    bucket.id,
                    String(
                        asset.bucketAllocations?.find(
                            (row) => row.bucketId === bucket.id,
                        )?.amount ?? 0,
                    ),
                ]),
            ),
        );
    };
    const save = (event: React.FormEvent) => {
        event.preventDefault();

        if (
            !selectedAsset ||
            allocatedTotal > selectedAsset.currentValue + 0.005
        ) {
            return;
        }

        router.put(
            `/assets/${selectedAsset.id}/allocations`,
            {
                allocations: Object.entries(allocations).map(
                    ([bucket_id, amount_egp]) => ({
                        bucket_id: Number(bucket_id),
                        amount_egp: Number(amount_egp || 0),
                    }),
                ),
            },
            { onSuccess: () => setSheetOpen(false) },
        );
    };

    return (
        <AppShell title="Allocation reconciliation">
            <PageHeader
                eyebrow="Purpose integrity"
                title="Allocation reconciliation"
                description="Assign each asset once, keep the purpose totals explainable, and find genuinely unallocated money."
                action={
                    <Button href="/allocations" variant="ghost">
                        Monthly plans
                    </Button>
                }
            />
            <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <SummaryCard label="Total assets" value={summary.totalAssets} />
                <SummaryCard
                    label="Fully assigned"
                    value={summary.fullyAssignedPurposeBalance}
                />
                <SummaryCard
                    label="Goal purposes"
                    value={summary.allocatedToGoals}
                />
                <SummaryCard
                    label="Other purposes"
                    value={summary.allocatedToNonGoals}
                />
                <SummaryCard
                    label="Truly unallocated"
                    value={summary.trulyUnallocated}
                    warning={summary.trulyUnallocated > 0.01}
                />
            </div>
            <Card>
                <CardHeader
                    title="Asset balances"
                    meta={`Source: ${dataFreshness.source} · ${dataFreshness.status}`}
                />
                <div className="divide-y divide-border">
                    {assets.map((asset) => {
                        const assigned =
                            asset.bucketAllocations?.reduce(
                                (sum, row) => sum + row.amount,
                                0,
                            ) ?? 0;

                        return (
                            <button
                                key={asset.id}
                                type="button"
                                className="flex w-full items-center justify-between gap-4 p-4 text-left hover:bg-muted/50"
                                onClick={() => openAsset(asset)}
                            >
                                <span>
                                    <span className="block text-sm font-semibold text-foreground">
                                        {asset.name}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {asset.type} ·{' '}
                                        {assigned >= asset.currentValue - 0.005
                                            ? 'fully assigned'
                                            : `${formatEGP(Math.max(0, asset.currentValue - assigned))} unallocated`}
                                    </span>
                                </span>
                                <span className="text-sm font-semibold text-foreground">
                                    {formatEGP(asset.currentValue)}
                                </span>
                            </button>
                        );
                    })}
                    {!assets.length && (
                        <p className="p-5 text-sm text-muted-foreground">
                            Add an asset before assigning purposes.
                        </p>
                    )}
                </div>
            </Card>
            <Sheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                onOpenChangeComplete={(open) => {
                    if (!open) {
                        setSelectedAsset(null);
                    }
                }}
            >
                <SheetContent side="right">
                    <SheetHeader>
                        <SheetTitle>
                            {selectedAsset
                                ? `Assign ${selectedAsset.name}`
                                : 'Assign purposes'}
                        </SheetTitle>
                        <SheetDescription>
                            {selectedAsset
                                ? `Cannot exceed ${formatEGP(selectedAsset.currentValue)}.`
                                : ''}
                        </SheetDescription>
                    </SheetHeader>
                    {selectedAsset && (
                        <form
                            onSubmit={save}
                            className="flex flex-col gap-4 px-4"
                        >
                            <FieldGroup>
                                {buckets.map((bucket) => (
                                    <Field key={bucket.id}>
                                        <FieldLabel
                                            htmlFor={`bucket-${bucket.id}`}
                                        >
                                            {bucket.name}
                                        </FieldLabel>
                                        <Input
                                            id={`bucket-${bucket.id}`}
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={
                                                allocations[bucket.id] ?? '0'
                                            }
                                            onChange={(event) =>
                                                setAllocations((current) => ({
                                                    ...current,
                                                    [bucket.id]:
                                                        event.target.value,
                                                }))
                                            }
                                        />
                                    </Field>
                                ))}
                            </FieldGroup>
                            <p
                                className={
                                    allocatedTotal >
                                    selectedAsset.currentValue + 0.005
                                        ? 'text-sm text-destructive'
                                        : 'text-sm text-muted-foreground'
                                }
                            >
                                Assigned: {formatEGP(allocatedTotal)} ·
                                Remaining:{' '}
                                {formatEGP(
                                    selectedAsset.currentValue - allocatedTotal,
                                )}
                            </p>
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setSheetOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        allocatedTotal >
                                        selectedAsset.currentValue + 0.005
                                    }
                                >
                                    Save allocations
                                </Button>
                            </div>
                        </form>
                    )}
                </SheetContent>
            </Sheet>
        </AppShell>
    );
}

function SummaryCard({
    label,
    value,
    warning,
}: {
    label: string;
    value: number;
    warning?: boolean;
}) {
    return (
        <Card className={warning ? 'border-destructive/30' : undefined}>
            <div className="p-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-2 text-xl font-semibold text-foreground">
                    {formatEGP(value)}
                </p>
            </div>
        </Card>
    );
}
