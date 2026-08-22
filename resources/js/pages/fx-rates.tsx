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
type GoldPrice = {
    id: number;
    karat: number;
    unit: string;
    currency: string;
    price_date: string;
    price: number;
    source: string;
    method: string;
};
export default function FxRates({
    rates,
    goldPrices,
}: {
    rates: Rate[];
    goldPrices: GoldPrice[];
}) {
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
                    meta="Daily USD/EGP rates are fetched automatically; manual records remain supported."
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
            <Card className="mt-6">
                <CardHeader
                    title="24K gold reference prices"
                    meta="Spot estimate per gram in EGP. Egyptian dealer premiums, workmanship, and taxes are not included."
                />
                <div className="divide-y divide-border">
                    {goldPrices.map((goldPrice) => (
                        <div
                            key={goldPrice.id}
                            className="flex items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p className="text-sm font-semibold">
                                    {goldPrice.karat}K gold / {goldPrice.unit}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {goldPrice.price_date} · {goldPrice.source} ·{' '}
                                    {goldPrice.method}
                                </p>
                            </div>
                            <p className="text-sm font-semibold">
                                {formatEGP(Number(goldPrice.price))}
                            </p>
                        </div>
                    ))}
                    {!goldPrices.length && (
                        <p className="p-5 text-sm text-muted-foreground">
                            No gold prices recorded yet.
                        </p>
                    )}
                </div>
            </Card>
        </AppShell>
    );
}
