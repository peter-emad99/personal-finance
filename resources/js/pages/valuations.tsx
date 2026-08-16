import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { FormModal } from '@/components/form';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { formatEGP } from '@/types/finance';

type Asset = { id: number; name: string };
type Valuation = {
    id: number;
    asset_id: number;
    valued_on: string;
    value_egp: number;
    source: string;
    valuation_method: string;
    asset?: Asset;
};
export default function Valuations({
    assets,
    valuations,
}: {
    assets: Asset[];
    valuations: Valuation[];
}) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        asset_id: assets[0]?.id ? String(assets[0].id) : '',
        valued_on: new Date().toISOString().slice(0, 10),
        value_egp: '',
        currency: 'EGP',
        source: 'manual',
        valuation_method: 'manual_mark',
        notes: '',
    });
    const update = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));

    return (
        <AppShell title="Asset valuations">
            <PageHeader
                eyebrow="Historical marks"
                title="Asset valuation history"
                description="Add dated source-labelled values without overwriting prior checkpoints."
                action={
                    <Button onClick={() => setOpen(true)}>Add valuation</Button>
                }
            />
            <Card>
                <CardHeader
                    title="Valuations"
                    meta="Each row is a dated, auditable mark."
                />
                <div className="divide-y divide-border">
                    {valuations.map((valuation) => (
                        <div
                            key={valuation.id}
                            className="flex items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p className="text-sm font-semibold">
                                    {valuation.asset?.name ??
                                        `Asset ${valuation.asset_id}`}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {valuation.valued_on} · {valuation.source} ·{' '}
                                    {valuation.valuation_method}
                                </p>
                            </div>
                            <p className="text-sm font-semibold">
                                {formatEGP(Number(valuation.value_egp))}
                            </p>
                        </div>
                    ))}
                    {!valuations.length && (
                        <p className="p-5 text-sm text-muted-foreground">
                            No dated valuations yet.
                        </p>
                    )}
                </div>
            </Card>
            {open && (
                <FormModal
                    title="Add dated valuation"
                    onClose={() => setOpen(false)}
                >
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.post('/valuations', form, {
                                onSuccess: () => setOpen(false),
                            });
                        }}
                    >
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="valuation-asset">
                                    Asset id
                                </FieldLabel>
                                <Input
                                    id="valuation-asset"
                                    required
                                    value={form.asset_id}
                                    onChange={(event) =>
                                        update('asset_id', event.target.value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="valuation-date">
                                    Valued on
                                </FieldLabel>
                                <Input
                                    id="valuation-date"
                                    type="date"
                                    required
                                    value={form.valued_on}
                                    onChange={(event) =>
                                        update('valued_on', event.target.value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="valuation-value">
                                    Value (EGP)
                                </FieldLabel>
                                <Input
                                    id="valuation-value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={form.value_egp}
                                    onChange={(event) =>
                                        update('value_egp', event.target.value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="valuation-source">
                                    Source
                                </FieldLabel>
                                <Input
                                    id="valuation-source"
                                    required
                                    value={form.source}
                                    onChange={(event) =>
                                        update('source', event.target.value)
                                    }
                                />
                            </Field>
                        </FieldGroup>
                        <Button type="submit">Save valuation</Button>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
