import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    EmptyState,
    PageHeader,
} from '@/components/app-shell';
import { Field, FormModal, SelectField } from '@/components/form';
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
    is_liquid: true,
    notes: '',
};

export default function Assets({
    assets,
    buckets,
}: {
    assets: Asset[];
    buckets: BucketOption[];
}) {
    const [open, setOpen] = useState(false);
    const [allocationAsset, setAllocationAsset] = useState<Asset | null>(null);
    const [allocations, setAllocations] = useState<Record<number, string>>({});
    const [form, setForm] = useState(blank);
    const update = (key: string, value: string | boolean) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/assets', form, {
            onSuccess: () => {
                setOpen(false);
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
                action={
                    <Button onClick={() => setOpen(true)}>+ Add asset</Button>
                }
            />
            <Card>
                <CardHeader
                    title="Everything you own"
                    meta={`${assets.length} assets · values shown in EGP`}
                />
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[840px] text-left text-sm">
                        <thead className="bg-[#fafbfc] text-[11px] tracking-wider text-[#99a2af] uppercase">
                            <tr>
                                <th className="px-5 py-3">Asset</th>
                                <th className="px-5 py-3">Type</th>
                                <th className="px-5 py-3">Value</th>
                                <th className="px-5 py-3">Gain / loss</th>
                                <th className="px-5 py-3">Liquidity</th>
                                <th className="px-5 py-3">Purpose</th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#eef0f4]">
                            {assets.map((asset) => (
                                <tr
                                    key={asset.id}
                                    className="hover:bg-[#fbfcfe]"
                                >
                                    <td className="px-5 py-4">
                                        <p className="font-semibold text-[#273246]">
                                            {asset.name}
                                        </p>
                                        <p className="mt-1 text-xs text-[#9aa3b1]">
                                            {asset.quantity
                                                ? `${asset.quantity} ${asset.currency}`
                                                : (asset.accountName ??
                                                  'No account set')}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-[#58657a]">
                                        {asset.type}
                                    </td>
                                    <td className="px-5 py-4 font-semibold text-[#273246]">
                                        {formatEGP(asset.currentValue)}
                                    </td>
                                    <td
                                        className={`px-5 py-4 font-medium ${asset.gainLoss >= 0 ? 'text-[#328654]' : 'text-[#c65365]'}`}
                                    >
                                        {asset.gainLoss >= 0 ? '+' : ''}
                                        {formatEGP(asset.gainLoss)}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span className="rounded-full bg-[#eef1ff] px-2.5 py-1 text-[11px] font-semibold text-[#6878d5]">
                                            {labelize(asset.liquidity)}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 text-xs text-[#738094]">
                                        <button
                                            onClick={() =>
                                                openAllocations(asset)
                                            }
                                            className="text-left font-semibold text-[#6878d5] hover:text-[#4d5aaf]"
                                        >
                                            {asset.bucketAllocations?.length
                                                ? asset.bucketAllocations
                                                      .map(
                                                          (bucket) =>
                                                              bucket.bucketName,
                                                      )
                                                      .join(', ')
                                                : 'Assign purpose'}
                                        </button>
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        <button
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        'Remove this asset?',
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/assets/${asset.id}`,
                                                    );
                                                }
                                            }}
                                            className="text-xs font-semibold text-[#a4acb9] hover:text-[#c65365]"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {!assets.length && (
                        <EmptyState
                            title="No assets yet"
                            description="Add cash, gold, USD, investments, deposits, or anything else you own."
                        />
                    )}
                </div>
            </Card>
            {open && (
                <FormModal title="Add an asset" onClose={() => setOpen(false)}>
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Field
                            label="Name"
                            required
                            value={form.name}
                            onChange={(e) => update('name', e.target.value)}
                            placeholder="e.g. Gold holdings"
                        />
                        <SelectField
                            label="Type"
                            value={form.type}
                            onChange={(e) => update('type', e.target.value)}
                        >
                            <option>Cash</option>
                            <option>USD</option>
                            <option>Gold</option>
                            <option>Egyptian equities</option>
                            <option>Mutual funds</option>
                            <option>Fixed income</option>
                            <option>Other</option>
                        </SelectField>
                        <Field
                            label="Quantity"
                            type="number"
                            step="any"
                            value={form.quantity}
                            onChange={(e) => update('quantity', e.target.value)}
                            placeholder="Optional"
                        />
                        <Field
                            label="Currency"
                            value={form.currency}
                            onChange={(e) => update('currency', e.target.value)}
                        />
                        <Field
                            label="Current value (EGP)"
                            type="number"
                            step="0.01"
                            required
                            value={form.current_value_egp}
                            onChange={(e) =>
                                update('current_value_egp', e.target.value)
                            }
                        />
                        <Field
                            label="Cost basis (EGP)"
                            type="number"
                            step="0.01"
                            value={form.cost_basis_egp}
                            onChange={(e) =>
                                update('cost_basis_egp', e.target.value)
                            }
                        />
                        <Field
                            label="Unit price (EGP)"
                            type="number"
                            step="0.01"
                            value={form.unit_price_egp}
                            onChange={(e) =>
                                update('unit_price_egp', e.target.value)
                            }
                        />
                        <SelectField
                            label="Liquidity"
                            value={form.liquidity}
                            onChange={(e) =>
                                update('liquidity', e.target.value)
                            }
                        >
                            <option value="immediate">Immediate</option>
                            <option value="within_3_days">Within 3 days</option>
                            <option value="longer_term">Longer term</option>
                            <option value="illiquid">Illiquid</option>
                        </SelectField>
                        <Field
                            label="Account / location"
                            className="sm:col-span-2"
                            value={form.account_name}
                            onChange={(e) =>
                                update('account_name', e.target.value)
                            }
                            placeholder="e.g. Brokerage, bank, physical"
                        />
                        <div className="flex items-center gap-2 text-sm text-[#58657a]">
                            <input
                                type="checkbox"
                                checked={form.is_liquid}
                                onChange={(e) =>
                                    update('is_liquid', e.target.checked)
                                }
                            />{' '}
                            Include in liquid assets
                        </div>
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
                    <form onSubmit={saveAllocations} className="space-y-4">
                        <p className="text-sm leading-6 text-[#738094]">
                            Split this asset across buckets. The total cannot
                            exceed {formatEGP(allocationAsset.currentValue)}.
                        </p>
                        {buckets.map((bucket) => (
                            <Field
                                key={bucket.id}
                                label={`${bucket.name} (EGP)`}
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
