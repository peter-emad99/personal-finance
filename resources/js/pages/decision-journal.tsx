import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { DatePicker } from '@/components/date-picker';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

type Entry = {
    id: number;
    decision: string;
    assumptions: unknown[];
    alternatives: unknown[];
    ruleResult: Record<string, unknown>;
    chosenAction: string | null;
    reviewDate: string | null;
    outcome: string | null;
    status: 'open' | 'reviewed' | 'closed';
};

const emptyForm = {
    decision: '',
    assumptions: '',
    alternatives: '',
    rule_result: '',
    chosen_action: '',
    review_date: '',
    outcome: '',
    status: 'open',
};

export default function DecisionJournal({
    entries,
    archivedEntries,
}: {
    entries: Entry[];
    archivedEntries: Entry[];
}) {
    const [form, setForm] = useState(emptyForm);
    const [editing, setEditing] = useState<number | null>(null);
    const set = (key: string, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const payload = {
            ...form,
            assumptions: parseJson(form.assumptions, []),
            alternatives: parseJson(form.alternatives, []),
            rule_result: parseJson(form.rule_result, {}),
        };
        const url = editing
            ? `/decision-journal/${editing}`
            : '/decision-journal';
        router[editing ? 'put' : 'post'](url, payload as never, {
            onSuccess: () => {
                setForm(emptyForm);
                setEditing(null);
            },
        });
    };
    const edit = (entry: Entry) => {
        setEditing(entry.id);
        setForm({
            decision: entry.decision,
            assumptions: JSON.stringify(entry.assumptions, null, 2),
            alternatives: JSON.stringify(entry.alternatives, null, 2),
            rule_result: JSON.stringify(entry.ruleResult, null, 2),
            chosen_action: entry.chosenAction ?? '',
            review_date: entry.reviewDate ?? '',
            outcome: entry.outcome ?? '',
            status: entry.status,
        });
    };

    return (
        <AppShell title="Decision journal">
            <PageHeader
                eyebrow="Decisions over time"
                title="Decision journal"
                description="Capture the assumptions and rules behind a choice, then return later to record what actually happened."
            />
            <div className="grid gap-4 xl:grid-cols-[0.8fr_1.2fr]">
                <Card>
                    <CardHeader
                        title={editing ? 'Edit entry' : 'Record a decision'}
                        meta="JSON fields keep the owner-agent context complete"
                    />
                    <form onSubmit={submit} className="flex flex-col gap-4 p-5">
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="decision">
                                    Decision
                                </FieldLabel>
                                <Input
                                    id="decision"
                                    value={form.decision}
                                    onChange={(event) =>
                                        set('decision', event.target.value)
                                    }
                                    placeholder="What choice are you considering?"
                                    required
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="chosen-action">
                                    Chosen action
                                </FieldLabel>
                                <Input
                                    id="chosen-action"
                                    value={form.chosen_action}
                                    onChange={(event) =>
                                        set('chosen_action', event.target.value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="review-date">
                                    Review date
                                </FieldLabel>
                                <DatePicker
                                    id="review-date"
                                    value={form.review_date}
                                    onChange={(value) =>
                                        set('review_date', value)
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="assumptions">
                                    Assumptions (JSON array)
                                </FieldLabel>
                                <Textarea
                                    id="assumptions"
                                    value={form.assumptions}
                                    onChange={(event) =>
                                        set('assumptions', event.target.value)
                                    }
                                    placeholder='["income remains stable"]'
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="alternatives">
                                    Alternatives (JSON array)
                                </FieldLabel>
                                <Textarea
                                    id="alternatives"
                                    value={form.alternatives}
                                    onChange={(event) =>
                                        set('alternatives', event.target.value)
                                    }
                                    placeholder='["wait", "buy used"]'
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="rule-result">
                                    Rule result (JSON object)
                                </FieldLabel>
                                <Textarea
                                    id="rule-result"
                                    value={form.rule_result}
                                    onChange={(event) =>
                                        set('rule_result', event.target.value)
                                    }
                                    placeholder='{"passes": true, "rule": "..."}'
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="outcome">
                                    Outcome / reflection
                                </FieldLabel>
                                <Textarea
                                    id="outcome"
                                    value={form.outcome}
                                    onChange={(event) =>
                                        set('outcome', event.target.value)
                                    }
                                />
                            </Field>
                        </FieldGroup>
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setForm(emptyForm);
                                    setEditing(null);
                                }}
                            >
                                Clear
                            </Button>
                            <Button type="submit">
                                {editing ? 'Update entry' : 'Save entry'}
                            </Button>
                        </div>
                    </form>
                </Card>
                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader
                            title="Open decisions"
                            meta="Review dates and outcomes make assumptions auditable"
                        />
                        <div className="divide-y divide-border">
                            {entries.map((entry) => (
                                <EntryRow
                                    key={entry.id}
                                    entry={entry}
                                    onEdit={edit}
                                />
                            ))}
                            {!entries.length && (
                                <p className="p-5 text-sm text-muted-foreground">
                                    No decisions recorded yet.
                                </p>
                            )}
                        </div>
                    </Card>
                    {archivedEntries.length > 0 && (
                        <Card>
                            <CardHeader title="Archived" />
                            <div className="divide-y divide-border">
                                {archivedEntries.map((entry) => (
                                    <div
                                        key={entry.id}
                                        className="flex items-center justify-between p-4 text-sm"
                                    >
                                        <span>{entry.decision}</span>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(
                                                    `/decision-journal/${entry.id}/restore`,
                                                )
                                            }
                                        >
                                            Restore
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    )}
                </div>
            </div>
        </AppShell>
    );
}

function EntryRow({
    entry,
    onEdit,
}: {
    entry: Entry;
    onEdit: (entry: Entry) => void;
}) {
    return (
        <div className="p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold text-foreground">
                        {entry.decision}
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {entry.reviewDate
                            ? `Review ${entry.reviewDate}`
                            : 'No review date'}
                        {entry.chosenAction
                            ? ` · Action: ${entry.chosenAction}`
                            : ''}
                    </p>
                </div>
                <Badge variant="secondary">{entry.status}</Badge>
            </div>
            <div className="mt-4 flex justify-end gap-2">
                <Button size="sm" variant="ghost" onClick={() => onEdit(entry)}>
                    Edit
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    onClick={() => {
                        if (
                            window.confirm(
                                'Archive this decision journal entry?',
                            )
                        ) {
                            router.delete(`/decision-journal/${entry.id}`);
                        }
                    }}
                >
                    Archive
                </Button>
            </div>
        </div>
    );
}

function parseJson(value: string, fallback: unknown): unknown {
    if (!value.trim()) {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
}
