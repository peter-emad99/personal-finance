import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Eye, Search, X } from 'lucide-react';
import { useState } from 'react';

import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type Change = {
    field: string;
    before: unknown;
    after: unknown;
};

type ActivityEntry = {
    id: number;
    action: string;
    actionLabel: string;
    channel: string;
    entityType: string;
    entityClass: string;
    entityId: number | null;
    entityLabel: string;
    toolName: string | null;
    agentId: string | null;
    requestId: string | null;
    beforeState: Record<string, unknown>;
    afterState: Record<string, unknown>;
    changedFields: Change[];
    dashboardVersion: string | null;
    createdAt: string | null;
};

type FilterState = {
    action: string;
    channel: string;
    entity: string;
    search: string;
    from: string;
    to: string;
};

type Option = { value: string; label: string };

const channelLabels: Record<string, string> = {
    web: 'Web',
    mcp: 'MCP',
    cli: 'CLI',
    unknown: 'Unknown',
};

function displayValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function prettyJson(value: Record<string, unknown>) {
    return JSON.stringify(value, null, 2);
}

function channelVariant(channel: string): 'default' | 'secondary' | 'outline' {
    if (channel === 'mcp') {
        return 'secondary';
    }

    if (channel === 'cli') {
        return 'outline';
    }

    return 'default';
}

export default function ActivityLog({
    entries,
    pagination,
    filters,
    actions,
    entities,
}: {
    entries: ActivityEntry[];
    pagination: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: FilterState;
    actions: Option[];
    entities: Option[];
}) {
    const [form, setForm] = useState<FilterState>(filters);
    const [selected, setSelected] = useState<ActivityEntry | null>(null);

    const updateFilter = (key: keyof FilterState, value: string) =>
        setForm((current) => ({ ...current, [key]: value }));

    const applyFilters = (event?: React.FormEvent) => {
        event?.preventDefault();
        const query = Object.fromEntries(
            Object.entries(form).filter(([, value]) => value !== ''),
        );
        router.get('/activity-log', query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        const empty: FilterState = {
            action: '',
            channel: '',
            entity: '',
            search: '',
            from: '',
            to: '',
        };
        setForm(empty);
        router.get(
            '/activity-log',
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const goToPage = (page: number) => {
        if (page < 1 || page > pagination.lastPage) {
            return;
        }

        router.get(
            '/activity-log',
            { ...form, page },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <AppShell title="Activity log">
            <PageHeader
                eyebrow="Workspace history"
                title="Activity log"
                description="Every tracked change is listed with its action, affected record, source channel, request identity, and before/after details."
            />

            <Card className="mb-6">
                <CardHeader
                    title="Find a change"
                    meta="Filter the audit trail by source, action, entity, date, or request metadata."
                />
                <form onSubmit={applyFilters} className="p-5">
                    <FieldGroup className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                        <Field className="lg:col-span-2">
                            <FieldLabel htmlFor="activity-search">
                                Search
                            </FieldLabel>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    id="activity-search"
                                    value={form.search}
                                    onChange={(event) =>
                                        updateFilter(
                                            'search',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Action, tool, entity, request…"
                                    className="pl-9"
                                />
                            </div>
                        </Field>
                        <Field>
                            <FieldLabel>Source</FieldLabel>
                            <Select
                                value={form.channel || 'all'}
                                onValueChange={(value) =>
                                    updateFilter(
                                        'channel',
                                        value === 'all' ? '' : (value ?? ''),
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All sources" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">
                                            All sources
                                        </SelectItem>
                                        {Object.entries(channelLabels).map(
                                            ([value, label]) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel>Action</FieldLabel>
                            <Select
                                value={form.action || 'all'}
                                onValueChange={(value) =>
                                    updateFilter(
                                        'action',
                                        value === 'all' ? '' : (value ?? ''),
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All actions" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">
                                            All actions
                                        </SelectItem>
                                        {actions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel>Entity</FieldLabel>
                            <Select
                                value={form.entity || 'all'}
                                onValueChange={(value) =>
                                    updateFilter(
                                        'entity',
                                        value === 'all' ? '' : (value ?? ''),
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="All entities" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="all">
                                            All entities
                                        </SelectItem>
                                        {entities.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="activity-from">
                                From
                            </FieldLabel>
                            <Input
                                id="activity-from"
                                type="date"
                                value={form.from}
                                onChange={(event) =>
                                    updateFilter('from', event.target.value)
                                }
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="activity-to">To</FieldLabel>
                            <Input
                                id="activity-to"
                                type="date"
                                value={form.to}
                                onChange={(event) =>
                                    updateFilter('to', event.target.value)
                                }
                            />
                        </Field>
                    </FieldGroup>
                    <div className="mt-5 flex flex-wrap gap-2">
                        <Button type="submit">
                            <Search data-icon="inline-start" />
                            Apply filters
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={clearFilters}
                        >
                            <X data-icon="inline-start" />
                            Clear
                        </Button>
                    </div>
                </form>
            </Card>

            <Card>
                <CardHeader
                    title="Tracked changes"
                    meta={`${pagination.total} record${pagination.total === 1 ? '' : 's'} · showing ${entries.length} on this page`}
                />
                <div>
                    {entries.map((entry, index) => (
                        <div key={entry.id}>
                            <div className="flex flex-col gap-4 p-5 lg:flex-row lg:items-start lg:justify-between">
                                <div className="min-w-0 space-y-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge>{entry.actionLabel}</Badge>
                                        <Badge
                                            variant={channelVariant(
                                                entry.channel,
                                            )}
                                        >
                                            {channelLabels[entry.channel] ??
                                                entry.channel}
                                        </Badge>
                                        <span className="text-sm font-medium text-foreground">
                                            {entry.entityLabel}
                                        </span>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {entry.entityType}
                                        {entry.entityId !== null
                                            ? ` · #${entry.entityId}`
                                            : ''}{' '}
                                        ·{' '}
                                        {entry.createdAt
                                            ? new Date(
                                                  entry.createdAt,
                                              ).toLocaleString()
                                            : 'Unknown time'}
                                    </p>
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span>
                                            Tool:{' '}
                                            <span className="text-foreground">
                                                {entry.toolName ?? '—'}
                                            </span>
                                        </span>
                                        <span>
                                            Request:{' '}
                                            <span className="font-mono text-foreground">
                                                {entry.requestId ?? '—'}
                                            </span>
                                        </span>
                                    </div>
                                    {entry.changedFields.length > 0 && (
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {entry.changedFields
                                                .slice(0, 3)
                                                .map(
                                                    (change) =>
                                                        `${change.field}: ${displayValue(change.before)} → ${displayValue(change.after)}`,
                                                )
                                                .join(' · ')}
                                        </p>
                                    )}
                                </div>
                                <Button
                                    variant="outline"
                                    onClick={() => setSelected(entry)}
                                    className="shrink-0"
                                >
                                    <Eye data-icon="inline-start" />
                                    View details
                                </Button>
                            </div>
                            {index < entries.length - 1 && <Separator />}
                        </div>
                    ))}
                    {entries.length === 0 && (
                        <div className="p-10 text-center text-sm text-muted-foreground">
                            No activity matches these filters.
                        </div>
                    )}
                </div>
                {pagination.lastPage > 1 && (
                    <div className="flex items-center justify-between border-t px-5 py-4">
                        <p className="text-xs text-muted-foreground">
                            Page {pagination.currentPage} of{' '}
                            {pagination.lastPage}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Previous page"
                                disabled={pagination.currentPage <= 1}
                                onClick={() =>
                                    goToPage(pagination.currentPage - 1)
                                }
                            >
                                <ChevronLeft />
                            </Button>
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label="Next page"
                                disabled={
                                    pagination.currentPage >=
                                    pagination.lastPage
                                }
                                onClick={() =>
                                    goToPage(pagination.currentPage + 1)
                                }
                            >
                                <ChevronRight />
                            </Button>
                        </div>
                    </div>
                )}
            </Card>

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <SheetContent side="right" className="overflow-y-auto">
                    {selected && (
                        <>
                            <SheetHeader>
                                <SheetTitle>{selected.actionLabel}</SheetTitle>
                                <SheetDescription>
                                    {selected.entityLabel} ·{' '}
                                    {selected.createdAt
                                        ? new Date(
                                              selected.createdAt,
                                          ).toLocaleString()
                                        : 'Unknown time'}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="flex flex-col gap-6 px-4 pb-6">
                                <div className="grid gap-3 rounded-xl border bg-muted/30 p-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Source
                                        </p>
                                        <p className="font-medium">
                                            {channelLabels[selected.channel] ??
                                                selected.channel}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Entity
                                        </p>
                                        <p className="font-medium">
                                            {selected.entityType}
                                            {selected.entityId !== null
                                                ? ` #${selected.entityId}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Tool
                                        </p>
                                        <p className="font-medium break-all">
                                            {selected.toolName ?? '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Agent
                                        </p>
                                        <p className="font-medium break-all">
                                            {selected.agentId ?? '—'}
                                        </p>
                                    </div>
                                    <div className="sm:col-span-2">
                                        <p className="text-xs text-muted-foreground">
                                            Request ID
                                        </p>
                                        <p className="font-mono text-xs break-all">
                                            {selected.requestId ?? '—'}
                                        </p>
                                    </div>
                                </div>

                                <section>
                                    <h2 className="mb-3 text-sm font-semibold">
                                        Changed fields
                                    </h2>
                                    {selected.changedFields.length > 0 ? (
                                        <div className="overflow-hidden rounded-xl border">
                                            {selected.changedFields.map(
                                                (change, index) => (
                                                    <div
                                                        key={change.field}
                                                        className={`grid gap-2 p-3 text-sm sm:grid-cols-[minmax(7rem,0.7fr)_1fr_1fr] ${index < selected.changedFields.length - 1 ? 'border-b' : ''}`}
                                                    >
                                                        <span className="font-medium">
                                                            {change.field}
                                                        </span>
                                                        <span className="text-muted-foreground">
                                                            {displayValue(
                                                                change.before,
                                                            )}
                                                        </span>
                                                        <span>
                                                            {displayValue(
                                                                change.after,
                                                            )}
                                                        </span>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            No field-level difference was
                                            captured.
                                        </p>
                                    )}
                                </section>

                                <section>
                                    <h2 className="mb-3 text-sm font-semibold">
                                        Before
                                    </h2>
                                    <pre className="max-h-72 overflow-auto rounded-xl bg-muted p-4 text-xs leading-5 whitespace-pre-wrap">
                                        {prettyJson(selected.beforeState)}
                                    </pre>
                                </section>
                                <section>
                                    <h2 className="mb-3 text-sm font-semibold">
                                        After
                                    </h2>
                                    <pre className="max-h-72 overflow-auto rounded-xl bg-muted p-4 text-xs leading-5 whitespace-pre-wrap">
                                        {prettyJson(selected.afterState)}
                                    </pre>
                                </section>
                                {selected.dashboardVersion && (
                                    <p className="text-xs text-muted-foreground">
                                        Dashboard version:{' '}
                                        <span className="font-mono break-all">
                                            {selected.dashboardVersion}
                                        </span>
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </AppShell>
    );
}
