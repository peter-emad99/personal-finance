import { useMemo, useState } from 'react';
import {
    AppShell,
    Card,
    CardHeader,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatCompactEGP, formatEGP } from '@/types/finance';
import type { Summary } from '@/types/finance';

export default function Scenarios({
    dashboard,
}: {
    dashboard: { summary: Summary };
}) {
    const [price, setPrice] = useState('1600000');
    const [mode, setMode] = useState('cash');
    const [downPayment, setDownPayment] = useState('800000');
    const [interest, setInterest] = useState('18');
    const [tenure, setTenure] = useState('36');
    const [monthlySavings, setMonthlySavings] = useState(
        String(dashboard.summary.freeCashFlow),
    );
    const result = useMemo(() => {
        const p = Number(price || 0);
        const down =
            mode === 'cash'
                ? p
                : Math.min(p, Math.max(0, Number(downPayment || 0)));
        const principal = Math.max(0, p - down);
        const months = Number(tenure || 1);
        const monthlyRate = Number(interest || 0) / 100 / 12;
        const payment =
            principal === 0
                ? 0
                : monthlyRate === 0
                  ? principal / months
                  : (principal *
                        monthlyRate *
                        Math.pow(1 + monthlyRate, months)) /
                    (Math.pow(1 + monthlyRate, months) - 1);
        const totalFinancing = payment * months;
        const afterPurchaseCash = Math.max(
            0,
            (dashboard.summary.availableNow ?? dashboard.summary.liquidAssets) -
                down,
        );
        const coverage =
            dashboard.summary.expenses > 0
                ? afterPurchaseCash / dashboard.summary.expenses
                : 0;
        const afterPurchaseFreeCashFlow =
            monthlySavings.trim() === ''
                ? dashboard.summary.freeCashFlow -
                  (mode === 'finance' ? payment : 0)
                : Number(monthlySavings);
        const reserveMonths = dashboard.summary.emergencyReserveMonths ?? 6;
        const confidence =
            coverage >= reserveMonths && afterPurchaseFreeCashFlow >= 0
                ? 'comfortable'
                : coverage >= reserveMonths / 2 &&
                    afterPurchaseFreeCashFlow >= 0
                  ? 'review'
                  : 'not ready';

        return {
            p,
            down,
            principal,
            payment,
            totalInterest: Math.max(0, totalFinancing - principal),
            afterPurchaseCash,
            coverage,
            afterPurchaseFreeCashFlow,
            confidence,
        };
    }, [
        price,
        mode,
        downPayment,
        interest,
        tenure,
        monthlySavings,
        dashboard.summary,
    ]);
    const choices =
        mode === 'cash'
            ? [
                  { label: 'Cash purchase', value: result.down },
                  { label: 'Cash remaining', value: result.afterPurchaseCash },
                  {
                      label: 'Emergency coverage',
                      value: result.coverage,
                      suffix: ' months',
                  },
              ]
            : [
                  { label: 'Down payment', value: result.down },
                  { label: 'Monthly payment', value: result.payment },
                  { label: 'Total interest', value: result.totalInterest },
              ];

    return (
        <AppShell title="Scenarios">
            <PageHeader
                eyebrow="Think in trade-offs"
                title="Scenario planner"
                description="Compare a major decision against liquidity, emergency coverage, financing cost, and the capital left to invest."
            />
            <div className="grid gap-4 xl:grid-cols-[0.9fr_1.35fr]">
                <Card>
                    <CardHeader
                        title="Scenario inputs"
                        meta="Planning math, not a prediction"
                    />
                    <div className="flex flex-col gap-4 p-5">
                        <Field>
                            <FieldLabel htmlFor="scenario-mode">
                                Scenario
                            </FieldLabel>
                            <Select
                                value={mode}
                                onValueChange={(value) =>
                                    setMode(String(value ?? ''))
                                }
                            >
                                <SelectTrigger
                                    id="scenario-mode"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select scenario" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="cash">
                                            Buy car cash
                                        </SelectItem>
                                        <SelectItem value="finance">
                                            Finance part of purchase
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="scenario-price">
                                Car price (EGP)
                            </FieldLabel>
                            <Input
                                id="scenario-price"
                                type="number"
                                value={price}
                                onChange={(e) => setPrice(e.target.value)}
                            />
                        </Field>
                        <Field>
                            <FieldLabel htmlFor="scenario-savings">
                                Expected monthly savings after purchase (EGP)
                            </FieldLabel>
                            <Input
                                id="scenario-savings"
                                type="number"
                                value={monthlySavings}
                                onChange={(e) =>
                                    setMonthlySavings(e.target.value)
                                }
                            />
                        </Field>
                        {mode === 'finance' && (
                            <>
                                <Field>
                                    <FieldLabel htmlFor="scenario-down-payment">
                                        Down payment (EGP)
                                    </FieldLabel>
                                    <Input
                                        id="scenario-down-payment"
                                        type="number"
                                        value={downPayment}
                                        onChange={(e) =>
                                            setDownPayment(e.target.value)
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="scenario-interest">
                                        Annual interest (%)
                                    </FieldLabel>
                                    <Input
                                        id="scenario-interest"
                                        type="number"
                                        step="0.1"
                                        value={interest}
                                        onChange={(e) =>
                                            setInterest(e.target.value)
                                        }
                                    />
                                </Field>
                                <Field>
                                    <FieldLabel htmlFor="scenario-tenure">
                                        Tenure (months)
                                    </FieldLabel>
                                    <Input
                                        id="scenario-tenure"
                                        type="number"
                                        value={tenure}
                                        onChange={(e) =>
                                            setTenure(e.target.value)
                                        }
                                    />
                                </Field>
                            </>
                        )}
                        <div className="rounded-xl border border-dashed border-border p-4">
                            <p className="text-xs font-semibold text-muted-foreground">
                                Current starting point
                            </p>
                            <div className="mt-3 grid grid-cols-2 gap-3 text-xs">
                                <span className="text-muted-foreground">
                                    Available now
                                </span>
                                <span className="text-right font-semibold text-muted-foreground">
                                    {formatCompactEGP(
                                        dashboard.summary.availableNow ??
                                            dashboard.summary.liquidAssets,
                                    )}
                                </span>
                                <span className="text-muted-foreground">
                                    Free cash flow
                                </span>
                                <span className="text-right font-semibold text-muted-foreground">
                                    {formatCompactEGP(
                                        dashboard.summary.freeCashFlow,
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>
                </Card>
                <div className="flex flex-col gap-4">
                    <Card className="overflow-hidden">
                        <div className="bg-sidebar p-6 text-sidebar-foreground">
                            <p className="text-xs tracking-[0.16em] text-sidebar-foreground/70 uppercase">
                                Selected scenario
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold">
                                {mode === 'cash'
                                    ? 'Buy the car cash'
                                    : '50% down + financing'}
                            </h2>
                            <p className="mt-2 max-w-xl text-sm leading-6 text-sidebar-foreground/70">
                                A transparent comparison using your current
                                numbers. Change the inputs and watch the
                                trade-offs update.
                            </p>
                        </div>
                        <div className="grid gap-3 p-5 sm:grid-cols-3">
                            {choices.map((choice) => (
                                <div
                                    key={choice.label}
                                    className="rounded-xl bg-muted p-4"
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {choice.label}
                                    </p>
                                    <p className="mt-2 text-lg font-semibold text-foreground">
                                        {choice.suffix
                                            ? `${choice.value.toFixed(1)}${choice.suffix}`
                                            : formatCompactEGP(choice.value)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </Card>
                    <Card>
                        <CardHeader
                            title="Impact on your financial position"
                            meta="What remains after the decision"
                        />
                        <div className="flex flex-col gap-5 p-5">
                            <div
                                className={`rounded-xl p-4 ${result.confidence === 'comfortable' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : result.confidence === 'review' ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-destructive/10 text-destructive'}`}
                            >
                                <p className="text-xs font-semibold tracking-wider uppercase">
                                    Financial confidence: {result.confidence}
                                </p>
                                <p className="mt-1 text-xs leading-5">
                                    After this decision, estimated free cash
                                    flow is{' '}
                                    {formatEGP(
                                        result.afterPurchaseFreeCashFlow,
                                    )}{' '}
                                    per month and liquid coverage is{' '}
                                    {result.coverage.toFixed(1)} months.
                                </p>
                            </div>
                            <Impact
                                label="Cash / liquidity remaining"
                                value={result.afterPurchaseCash}
                                total={
                                    dashboard.summary.availableNow ??
                                    dashboard.summary.liquidAssets
                                }
                                color="var(--chart-1)"
                            />
                            <Impact
                                label="Emergency coverage"
                                value={result.coverage}
                                total={6}
                                suffix=" months"
                                color={
                                    result.coverage >= 6
                                        ? 'var(--chart-2)'
                                        : 'var(--chart-3)'
                                }
                            />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl border border-border p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Financing cost
                                    </p>
                                    <p className="mt-2 text-xl font-semibold text-foreground">
                                        {formatEGP(result.totalInterest)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Interest over the full term
                                    </p>
                                </div>
                                <div className="rounded-xl border border-border p-4">
                                    <p className="text-xs text-muted-foreground">
                                        Monthly payment
                                    </p>
                                    <p className="mt-2 text-xl font-semibold text-foreground">
                                        {formatEGP(result.payment)}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Before other obligations
                                    </p>
                                </div>
                            </div>
                            <div className="rounded-xl bg-amber-100 p-4 text-xs leading-5 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                This is a planning calculator. Add a
                                depreciation assumption, insurance, maintenance,
                                and a real lender quote before making a purchase
                                decision.
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </AppShell>
    );
}

function Impact({
    label,
    value,
    total,
    color,
    suffix = '',
}: {
    label: string;
    value: number;
    total: number;
    color: string;
    suffix?: string;
}) {
    return (
        <div>
            <div className="flex justify-between text-xs">
                <span className="font-semibold text-muted-foreground">
                    {label}
                </span>
                <span className="font-semibold text-foreground">
                    {suffix ? `${value.toFixed(1)}${suffix}` : formatEGP(value)}
                </span>
            </div>
            <div className="mt-2">
                <Progress
                    value={(value / Math.max(1, total)) * 100}
                    color={color}
                />
            </div>
        </div>
    );
}
