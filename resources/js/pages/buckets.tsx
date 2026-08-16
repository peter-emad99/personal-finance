import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { FormModal } from '@/components/form';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { formatEGP } from '@/types/finance';

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
    archived?: boolean;
};

export default function Buckets({ buckets }: { buckets: Bucket[] }) {
    const [editing, setEditing] = useState<Bucket | null>(null);
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        name: '',
        purpose: '',
        target_amount_egp: '',
        color: '#7c8cf8',
        goal_id: null as number | null,
    });
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const begin = (bucket?: Bucket) => {
        setEditing(bucket ?? null);
        setForm(
            bucket
                ? {
                      name: bucket.name,
                      purpose: bucket.purpose ?? '',
                      target_amount_egp: String(bucket.targetAmount),
                      color: bucket.color,
                      goal_id: bucket.goalId ?? null,
                  }
                : {
                      name: '',
                      purpose: '',
                      target_amount_egp: '',
                      color: '#7c8cf8',
                      goal_id: null,
                  },
        );
        setOpen(true);
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const url = editing ? `/buckets/${editing.id}` : '/buckets';
        router[editing ? 'put' : 'post'](url, form, {
            onSuccess: () => {
                setOpen(false);
                setEditing(null);
            },
        });
    };

    return (
        <AppShell title="Buckets">
            <PageHeader
                eyebrow="Purpose before performance"
                title="Buckets"
                description="An asset tells you what you own. A bucket tells you what that money is for."
                action={<Button onClick={() => begin()}>+ Add bucket</Button>}
            />
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {buckets.map((bucket) => (
                    <Card
                        key={bucket.id}
                        className={bucket.archived ? 'opacity-60' : ''}
                    >
                        <div className="p-5">
                            <div className="flex items-start justify-between">
                                <div>
                                    <span
                                        className="mb-3 block h-1.5 w-9 rounded-full"
                                        style={{
                                            backgroundColor: bucket.color,
                                        }}
                                    />
                                    <h2 className="text-base font-semibold text-foreground">
                                        {bucket.name}
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {bucket.goalName ??
                                            bucket.purpose ??
                                            'Flexible allocation'}
                                    </p>
                                </div>
                                <Badge className="rounded-full border-0 bg-muted px-2.5 py-1 text-[10px] font-semibold text-muted-foreground">
                                    {bucket.assetCount} assets
                                </Badge>
                            </div>
                            <p className="mt-6 text-2xl font-semibold text-foreground">
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
                                    <div className="mt-2 flex justify-between text-xs text-muted-foreground">
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
                            <div className="mt-5 flex items-center justify-between gap-2">
                                <a
                                    href="/assets"
                                    className="text-xs font-semibold text-primary"
                                >
                                    Assign assets →
                                </a>
                                <div>
                                    {!bucket.archived && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                className="mr-1 h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                                onClick={() => begin(bucket)}
                                            >
                                                Edit
                                            </Button>
                                            {bucket.goalId ? (
                                                <span className="text-[11px] text-muted-foreground">
                                                    Managed by goal
                                                </span>
                                            ) : (
                                                <Button
                                                    variant="danger"
                                                    className="h-7 border-0 bg-transparent px-2 text-xs text-destructive hover:bg-transparent"
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
                                        </>
                                    )}
                                    {bucket.archived && (
                                        <Button
                                            variant="ghost"
                                            className="h-7 border-0 bg-transparent px-2 text-xs text-primary hover:bg-transparent"
                                            onClick={() =>
                                                router.post(
                                                    `/buckets/${bucket.id}/restore`,
                                                )
                                            }
                                        >
                                            Restore
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </div>
                    </Card>
                ))}
                {!buckets.length && (
                    <Card className="sm:col-span-2 xl:col-span-3">
                        <div className="p-10 text-center text-sm text-muted-foreground">
                            Create your first bucket to separate goals from
                            long-term wealth.
                        </div>
                    </Card>
                )}
            </div>
            {open && (
                <FormModal
                    title={
                        editing
                            ? 'Edit purpose bucket'
                            : 'Create a purpose bucket'
                    }
                    onClose={() => {
                        setOpen(false);
                        setEditing(null);
                    }}
                >
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <Field>
                            <FieldLabel htmlFor="bucket-name">
                                Bucket name
                            </FieldLabel>
                            <Input
                                id="bucket-name"
                                required
                                value={form.name}
                                onChange={(e) => update('name', e.target.value)}
                                placeholder="e.g. Opportunity Fund"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="bucket-purpose">
                                Purpose
                            </FieldLabel>
                            <Input
                                id="bucket-purpose"
                                value={form.purpose}
                                onChange={(e) =>
                                    update('purpose', e.target.value)
                                }
                                placeholder="What is this money for?"
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="bucket-target">
                                Target amount (EGP)
                            </FieldLabel>
                            <Input
                                id="bucket-target"
                                type="number"
                                value={form.target_amount_egp}
                                onChange={(e) =>
                                    update('target_amount_egp', e.target.value)
                                }
                            />
                        </Field>
                        <Field orientation="horizontal">
                            <FieldLabel htmlFor="bucket-color">
                                Color
                            </FieldLabel>
                            <Input
                                id="bucket-color"
                                type="color"
                                value={form.color}
                                onChange={(e) =>
                                    update('color', e.target.value)
                                }
                                className="w-14"
                            />
                        </Field>
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">
                                {editing ? 'Save changes' : 'Create bucket'}
                            </Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
