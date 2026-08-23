import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { DatePicker } from '@/components/date-picker';
import { FormModal } from '@/components/form';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { formatEGP } from '@/types/finance';

type Liability = { id: number; name: string };
type History = {
    id: number;
    liability_id: number;
    as_of: string;
    balance_egp: number;
    source: string;
    notes?: string | null;
    deleted_at?: string | null;
    liability?: Liability;
};

type FormState = {
    liability_id: string;
    as_of: string;
    balance_egp: string;
    source: string;
    notes: string;
};

const blankForm = (liabilityId = ''): FormState => ({
    liability_id: liabilityId,
    as_of: new Date().toISOString().slice(0, 10),
    balance_egp: '',
    source: 'statement',
    notes: '',
});

export default function LiabilityHistory({
    liabilities,
    histories,
}: {
    liabilities: Liability[];
    histories: History[];
}) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<History | null>(null);
    const [form, setForm] = useState<FormState>(
        blankForm(liabilities[0]?.id ? String(liabilities[0].id) : ''),
    );
    const update = (key: keyof FormState, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const openCreate = () => {
        setEditing(null);
        setForm(blankForm(liabilities[0]?.id ? String(liabilities[0].id) : ''));
        setOpen(true);
    };
    const openEdit = (history: History) => {
        setEditing(history);
        setOpen(true);
        setForm({
            liability_id: String(history.liability_id),
            as_of: history.as_of,
            balance_egp: String(history.balance_egp),
            source: history.source,
            notes: history.notes ?? '',
        });
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => setOpen(false) };

        if (editing) {
            router.put(`/liability-history/${editing.id}`, form, options);
        } else {
            router.post('/liability-history', form, options);
        }
    };

    return (
        <AppShell title="Liability history">
            <PageHeader
                eyebrow="Historical debt balances"
                title="Liability balance history"
                description="Record dated statement balances so historical net worth never falls back to today's liability balance."
                action={
                    <Button onClick={openCreate} disabled={!liabilities.length}>
                        Add balance
                    </Button>
                }
            />
            <Card>
                <CardHeader
                    title="Dated balances"
                    meta="A missing balance history keeps a historical snapshot incomplete."
                />
                <div className="divide-y divide-border">
                    {histories.map((history) => (
                        <div
                            key={history.id}
                            className="flex flex-wrap items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p className="text-sm font-semibold">
                                    {history.liability?.name ??
                                        `Liability ${history.liability_id}`}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {history.as_of} · {history.source}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <p className="text-sm font-semibold">
                                    {formatEGP(Number(history.balance_egp))}
                                </p>
                                {history.deleted_at ? (
                                    <Button
                                        variant="ghost"
                                        onClick={() =>
                                            router.post(
                                                `/liability-history/${history.id}/restore`,
                                            )
                                        }
                                    >
                                        Restore
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            variant="ghost"
                                            onClick={() => openEdit(history)}
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="danger"
                                            onClick={() =>
                                                router.delete(
                                                    `/liability-history/${history.id}`,
                                                )
                                            }
                                        >
                                            Archive
                                        </Button>
                                    </>
                                )}
                                {history.deleted_at && (
                                    <Badge variant="outline">Archived</Badge>
                                )}
                            </div>
                        </div>
                    ))}
                    {!histories.length && (
                        <p className="p-5 text-sm text-muted-foreground">
                            No dated liability balances yet.
                        </p>
                    )}
                </div>
            </Card>
            {open && (
                <FormModal
                    title={
                        editing
                            ? 'Edit liability balance'
                            : 'Add dated liability balance'
                    }
                    onClose={() => setOpen(false)}
                >
                    <form className="flex flex-col gap-4" onSubmit={submit}>
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="liability-history-liability">
                                    Liability id
                                </FieldLabel>
                                <Input
                                    id="liability-history-liability"
                                    required
                                    value={form.liability_id}
                                    onChange={(event) =>
                                        update(
                                            'liability_id',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="liability-history-date">
                                    As of
                                </FieldLabel>
                                <DatePicker
                                    id="liability-history-date"
                                    required
                                    value={form.as_of}
                                    onChange={(value) => update('as_of', value)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="liability-history-balance">
                                    Balance (EGP)
                                </FieldLabel>
                                <Input
                                    id="liability-history-balance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    value={form.balance_egp}
                                    onChange={(event) =>
                                        update(
                                            'balance_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="liability-history-source">
                                    Source
                                </FieldLabel>
                                <Input
                                    id="liability-history-source"
                                    required
                                    value={form.source}
                                    onChange={(event) =>
                                        update('source', event.target.value)
                                    }
                                />
                            </Field>
                        </FieldGroup>
                        <Button type="submit">
                            {editing ? 'Update balance' : 'Save balance'}
                        </Button>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
