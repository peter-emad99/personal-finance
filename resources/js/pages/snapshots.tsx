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
import { Field, FormModal } from '@/components/form';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatEGP } from '@/types/finance';

type Snapshot = {
    id: number;
    as_of: string;
    net_worth_egp: number;
    liquid_assets_egp: number;
    investable_net_worth_egp: number;
    free_cash_flow_egp: number;
    emergency_coverage_months: number;
};

export default function Snapshots({ snapshots }: { snapshots: Snapshot[] }) {
    const [open, setOpen] = useState(false);
    const [form, setForm] = useState({
        as_of: new Date().toISOString().slice(0, 10),
        notes: '',
    });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.post('/snapshots', form, { onSuccess: () => setOpen(false) });
    };

    return (
        <AppShell title="Snapshots">
            <PageHeader
                eyebrow="See the trend"
                title="Historical snapshots"
                description="Save a monthly checkpoint so growth is separated into savings, investment gains, and major decisions over time."
                action={
                    <Button onClick={() => setOpen(true)}>
                        + Save snapshot
                    </Button>
                }
            />
            <Card>
                <CardHeader
                    title="Financial history"
                    meta="Each snapshot is a point-in-time view"
                />
                <div className="overflow-x-auto">
                    <Table className="min-w-[720px] text-left text-sm">
                        <TableHeader className="bg-[#fafbfc] text-[11px] tracking-wider text-[#99a2af] uppercase">
                            <TableRow>
                                <TableHead className="px-5 py-3">
                                    As of
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Net worth
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Liquid
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Investable
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Free cash flow
                                </TableHead>
                                <TableHead className="px-5 py-3">
                                    Emergency coverage
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody className="divide-y divide-[#eef0f4]">
                            {snapshots.map((snapshot) => (
                                <TableRow key={snapshot.id}>
                                    <TableCell className="px-5 py-4 font-medium text-[#4d5a6d]">
                                        {new Date(
                                            snapshot.as_of,
                                        ).toLocaleDateString('en-EG', {
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-semibold text-[#273246]">
                                        {formatEGP(snapshot.net_worth_egp)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-[#58657a]">
                                        {formatEGP(snapshot.liquid_assets_egp)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-[#58657a]">
                                        {formatEGP(
                                            snapshot.investable_net_worth_egp,
                                        )}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 font-semibold text-[#328654]">
                                        {formatEGP(snapshot.free_cash_flow_egp)}
                                    </TableCell>
                                    <TableCell className="px-5 py-4 text-[#58657a]">
                                        {snapshot.emergency_coverage_months}{' '}
                                        months
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    {!snapshots.length && (
                        <EmptyState
                            title="No snapshots saved"
                            description="Save the first checkpoint after reviewing your current assets and cash flow."
                        />
                    )}
                </div>
            </Card>
            {open && (
                <FormModal
                    title="Save a snapshot"
                    onClose={() => setOpen(false)}
                >
                    <form onSubmit={submit} className="space-y-4">
                        <Field
                            label="As of"
                            type="date"
                            value={form.as_of}
                            onChange={(e) =>
                                setForm({ ...form, as_of: e.target.value })
                            }
                        />
                        <Field
                            label="Notes"
                            value={form.notes}
                            onChange={(e) =>
                                setForm({ ...form, notes: e.target.value })
                            }
                            placeholder="What changed this month?"
                        />
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Save checkpoint</Button>
                        </div>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}
