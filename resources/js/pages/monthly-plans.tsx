import { router } from '@inertiajs/react';
import { Copy, Eye, LockKeyhole } from 'lucide-react';
import { useState } from 'react';
import { AppShell, Badge, Button, Card, CardHeader, PageHeader } from '@/components/app-shell';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCompactEGP } from '@/types/finance';

type Plan = { id: number; month: string; monthLabel: string; templateName: string; income: number; expenses: number; savings: number; actualExpenses: number; actualSavings: number; status: string; closedAt?: string | null };

export default function MonthlyPlans({ plans }: { plans: Plan[] }) {
    const [copyName, setCopyName] = useState('');
    const [copyPlanId, setCopyPlanId] = useState<number | null>(null);

    const createTemplate = (plan: Plan) => {
        const name = copyPlanId === plan.id ? copyName.trim() : window.prompt('New template name', `${plan.templateName} · ${plan.monthLabel}`)?.trim();

        if (!name) {
return;
}

        router.post(`/allocations/${plan.id}/save-as-template`, { name }, { onSuccess: () => {
 setCopyPlanId(null); setCopyName(''); 
} });
    };

    return (
        <AppShell title="Monthly plans">
            <PageHeader eyebrow="Plan, track, learn" title="Monthly plans & history" description="Every month is a snapshot from a template. Actuals and closed months remain visible without rewriting the source template." action={<Button href="/allocations">Open current month</Button>} />
            <Card>
                <CardHeader title="Plan history" meta={`${plans.length} monthly snapshots`} />
                <Table>
                    <TableHeader><TableRow><TableHead>Month</TableHead><TableHead>Template</TableHead><TableHead>Planned</TableHead><TableHead>Actual savings</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Actions</TableHead></TableRow></TableHeader>
                    <TableBody>{plans.map((plan) => <TableRow key={plan.id}>
                        <TableCell className="font-medium">{plan.monthLabel}</TableCell>
                        <TableCell>{plan.templateName}</TableCell>
                        <TableCell><div>{formatCompactEGP(plan.income)} income</div><div className="text-xs text-muted-foreground">{formatCompactEGP(plan.expenses)} expenses · {formatCompactEGP(plan.savings)} savings</div></TableCell>
                        <TableCell><div>{formatCompactEGP(plan.actualSavings)}</div><div className="text-xs text-muted-foreground">{formatCompactEGP(plan.actualExpenses)} expenses</div></TableCell>
                        <TableCell>{plan.status === 'closed' ? <Badge variant="secondary">Closed</Badge> : <Badge variant="outline">Open</Badge>}</TableCell>
                        <TableCell><div className="flex justify-end gap-2">{copyPlanId === plan.id && <Input className="w-40" placeholder="Template name" value={copyName} onChange={(event) => setCopyName(event.target.value)} />}{copyPlanId === plan.id ? <Button type="button" variant="outline" size="sm" onClick={() => createTemplate(plan)}>Create</Button> : <Button type="button" variant="ghost" size="icon" aria-label="Create template from plan" onClick={() => {
 setCopyPlanId(plan.id); setCopyName(`${plan.templateName} · ${plan.monthLabel}`); 
}}><Copy /></Button>}<Button href={`/allocations?month=${plan.month}`} type="button" variant="ghost" size="icon" aria-label="View monthly plan"><Eye /></Button>{plan.status !== 'closed' && <Button type="button" variant="ghost" size="icon" aria-label="Close month" onClick={() => router.post(`/allocations/${plan.id}/close`)}><LockKeyhole /></Button>}</div></TableCell>
                    </TableRow>)}</TableBody>
                </Table>
                {plans.length === 0 && <div className="p-8 text-center text-sm text-muted-foreground">No monthly snapshots yet. Open the monthly plan and choose a template to create one.</div>}
            </Card>
        </AppShell>
    );
}
