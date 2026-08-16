import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { Field, FormModal } from '@/components/form';
import { formatEGP } from '@/types/finance';

type Bucket = {
    id: number;
    name: string;
    purpose: string | null;
    color: string;
    goalName: string | null;
    targetAmount: number;
    currentAmount: number;
    assetCount: number;
};

export default function Buckets({ buckets }: { buckets: Bucket[] }) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        name: '',
        purpose: '',
        target_amount_egp: '',
        color: '#7c8cf8',
    });
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/buckets', form, {
            onSuccess: () => {
                setOpen(false);
                setForm({
                    name: '',
                    purpose: '',
                    target_amount_egp: '',
                    color: '#7c8cf8',
                });
            },
        });
    };

    return (
        <AppShell title="Buckets">
            <PageHeader
                eyebrow="Purpose before performance"
                title="Buckets"
                description="An asset tells you what you own. A bucket tells you what that money is for."
                action={
                    <Button onClick={() => setOpen(true)}>+ Add bucket</Button>
                }
            />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {buckets.map((bucket) => (
                    <Card key={bucket.id}>
                        <div className="p-5">
                            <div className="flex items-start justify-between">
                                <div>
                                    <span
                                        className="mb-3 block h-1.5 w-9 rounded-full"
                                        style={{
                                            backgroundColor: bucket.color,
                                        }}
                                    />
                                    <h2 className="text-base font-semibold text-[#273246]">
                                        {bucket.name}
                                    </h2>
                                    <p className="mt-1 text-xs text-[#8993a3]">
                                        {bucket.goalName ??
                                            bucket.purpose ??
                                            'Flexible allocation'}
                                    </p>
                                </div>
                                <span className="rounded-full bg-[#f8f9fb] px-2.5 py-1 text-[10px] font-semibold text-[#8993a3]">
                                    {bucket.assetCount} assets
                                </span>
                            </div>
                            <p className="mt-6 text-2xl font-semibold text-[#273246]">
                                {formatEGP(bucket.currentAmount)}
                            </p>
                            {bucket.targetAmount > 0 && (
                                <>
                                    <div className="mt-4">
                                        <Progress
                                            value={
                                                (bucket.currentAmount /
                                                    bucket.targetAmount) *
                                                100
                                            }
                                            color={bucket.color}
                                        />
                                    </div>
                                    <div className="mt-2 flex justify-between text-xs text-[#8993a3]">
                                        <span>
                                            {Math.round(
                                                (bucket.currentAmount /
                                                    bucket.targetAmount) *
                                                    100,
                                            )}
                                            % funded
                                        </span>
                                        <span>
                                            Target{' '}
                                            {formatEGP(bucket.targetAmount)}
                                        </span>
                                    </div>
                                </>
                            )}
                            <a
                                href="/assets"
                                className="mt-5 inline-block text-xs font-semibold text-[#6878d5]"
                            >
                                Assign assets →
                            </a>
                        </div>
                    </Card>
                ))}
                {!buckets.length && (
                    <Card className="sm:col-span-2 xl:col-span-3">
                        <div className="p-10 text-center text-sm text-[#8993a3]">
                            Create your first bucket to separate goals from
                            long-term wealth.
                        </div>
                    </Card>
                )}
            </div>
            {open && (
                <FormModal
                    title="Create a purpose bucket"
                    onClose={() => setOpen(false)}
                >
                    <form onSubmit={submit} className="space-y-4">
                        <Field
                            label="Bucket name"
                            required
                            value={form.name}
                            onChange={(e) => update('name', e.target.value)}
                            placeholder="e.g. Opportunity Fund"
                        />
                        <Field
                            label="Purpose"
                            value={form.purpose}
                            onChange={(e) => update('purpose', e.target.value)}
                            placeholder="What is this money for?"
                        />
                        <Field
                            label="Target amount (EGP)"
                            type="number"
                            value={form.target_amount_egp}
                            onChange={(e) =>
                                update('target_amount_egp', e.target.value)
                            }
                        />
                        <label className="flex items-center gap-3 text-xs font-semibold text-[#58657a]">
                            Color{' '}
                            <input
                                type="color"
                                value={form.color}
                                onChange={(e) =>
                                    update('color', e.target.value)
                                }
                                className="h-9 w-14 rounded-lg border border-[#dfe3ea] bg-white p-1"
                            />
                        </label>
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Create bucket</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
