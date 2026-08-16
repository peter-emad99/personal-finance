import {
    AppShell,
    Badge,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { formatEGP } from '@/types/finance';

type Props = {
    reconciliation: {
        month: string;
        status: string;
        income: { expected: number; received: number; status: string };
        commitments: { expected: number; paid: number; status: string };
        review: { status: string; source: string; transactionCount: number };
        accounts: Array<{
            id: number;
            name: string;
            ledgerBalance: number;
            reportedBalance: number | null;
            difference: number | null;
            status: string;
        }>;
        pendingImports: number;
    };
};

export default function Reconciliation({ reconciliation }: Props) {
    return (
        <AppShell title="Reconciliation">
            <PageHeader
                eyebrow="Trust the totals"
                title="Reconciliation"
                description="Compare statement balances, reviewed income, commitments, and month completeness before relying on a trend."
                action={
                    <Badge
                        variant={
                            reconciliation.status === 'reconciled'
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {reconciliation.status}
                    </Badge>
                }
            />
            <div className="grid gap-4 md:grid-cols-3">
                <Summary
                    title="Income received"
                    value={formatEGP(reconciliation.income.received)}
                    meta={reconciliation.income.status}
                />
                <Summary
                    title="Commitments paid"
                    value={formatEGP(reconciliation.commitments.paid)}
                    meta={reconciliation.commitments.status}
                />
                <Summary
                    title="Review"
                    value={reconciliation.review.status}
                    meta={`${reconciliation.review.transactionCount} confirmed rows`}
                />
            </div>
            <Card className="mt-4">
                <CardHeader
                    title="Account balances"
                    meta="A difference means the statement balance or ledger needs review."
                />
                <div className="divide-y divide-border">
                    {reconciliation.accounts.map((account) => (
                        <div
                            key={account.id}
                            className="flex items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p className="text-sm font-semibold">
                                    {account.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Ledger {formatEGP(account.ledgerBalance)} ·
                                    Reported{' '}
                                    {account.reportedBalance == null
                                        ? 'not entered'
                                        : formatEGP(account.reportedBalance)}
                                </p>
                            </div>
                            <Badge
                                variant={
                                    account.status === 'reconciled'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {account.status}
                                {account.difference == null
                                    ? ''
                                    : ` · ${formatEGP(account.difference)}`}
                            </Badge>
                        </div>
                    ))}
                    {!reconciliation.accounts.length && (
                        <p className="p-5 text-sm text-muted-foreground">
                            Add an account and statement balance to begin
                            reconciliation.
                        </p>
                    )}
                </div>
            </Card>
            <p className="mt-4 text-sm text-muted-foreground">
                {reconciliation.pendingImports} import rows remain in review.
                The month is {reconciliation.month}.
            </p>
        </AppShell>
    );
}

function Summary({
    title,
    value,
    meta,
}: {
    title: string;
    value: string;
    meta: string;
}) {
    return (
        <Card>
            <div className="p-4">
                <p className="text-xs text-muted-foreground">{title}</p>
                <p className="mt-2 text-xl font-semibold">{value}</p>
                <p className="mt-1 text-xs text-muted-foreground">{meta}</p>
            </div>
        </Card>
    );
}
