import {
    AppShell,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
import { formatEGP } from '@/types/finance';
type Rate = {
    id: number;
    base_currency: string;
    quote_currency: string;
    rate_date: string;
    rate: number;
    source: string;
    method: string;
};
export default function FxRates({ rates }: { rates: Rate[] }) {
    return (
        <AppShell title="FX rates">
            <PageHeader
                eyebrow="Currency history"
                title="FX-rate records"
                description="Keep conversion rate, date, source, and method explicit for historical values."
                action={
                    <Button href="/ledger" variant="ghost">
                        Back to ledger
                    </Button>
                }
            />
            <Card>
                <CardHeader
                    title="Recorded rates"
                    meta="Rates are historical records; no external integration is enabled in Phase 2."
                />
                <div className="divide-y divide-border">
                    {rates.map((rate) => (
                        <div
                            key={rate.id}
                            className="flex items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p className="text-sm font-semibold">
                                    {rate.base_currency}/{rate.quote_currency}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {rate.rate_date} · {rate.source} ·{' '}
                                    {rate.method}
                                </p>
                            </div>
                            <p className="text-sm font-semibold">
                                {formatEGP(Number(rate.rate))}
                            </p>
                        </div>
                    ))}
                    {!rates.length && (
                        <p className="p-5 text-sm text-muted-foreground">
                            No FX rates recorded.
                        </p>
                    )}
                </div>
            </Card>
        </AppShell>
    );
}
