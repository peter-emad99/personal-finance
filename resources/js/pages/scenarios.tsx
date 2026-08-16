import { useMemo, useState } from 'react';
import {
    AppShell,
    Card,
    CardHeader,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import { Field, SelectField } from '@/components/form';
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
        const down = mode === 'cash' ? p : Number(downPayment || 0);
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
            dashboard.summary.liquidAssets - down,
        );
        const coverage =
            dashboard.summary.expenses > 0
                ? afterPurchaseCash / dashboard.summary.expenses
                : 0;
        const afterPurchaseFreeCashFlow =
            dashboard.summary.freeCashFlow - (mode === 'finance' ? payment : 0);
        const confidence =
            coverage >= 6 && afterPurchaseFreeCashFlow >= 0
                ? 'comfortable'
                : coverage >= 3 && afterPurchaseFreeCashFlow >= 0
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
    }, [price, mode, downPayment, interest, tenure, dashboard.summary]);
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
                    <div className="space-y-4 p-5">
                        <SelectField
                            label="Scenario"
                            value={mode}
                            onChange={(e) => setMode(e.target.value)}
                        >
                            <option value="cash">Buy car cash</option>
                            <option value="finance">
                                Finance part of purchase
                            </option>
                        </SelectField>
                        <Field
                            label="Car price (EGP)"
                            type="number"
                            value={price}
                            onChange={(e) => setPrice(e.target.value)}
                        />
                        <Field
                            label="Monthly savings after purchase (EGP)"
                            type="number"
                            value={monthlySavings}
                            onChange={(e) => setMonthlySavings(e.target.value)}
                        />
                        {mode === 'finance' && (
                            <>
                                <Field
                                    label="Down payment (EGP)"
                                    type="number"
                                    value={downPayment}
                                    onChange={(e) =>
                                        setDownPayment(e.target.value)
                                    }
                                />
                                <Field
                                    label="Annual interest (%)"
                                    type="number"
                                    step="0.1"
                                    value={interest}
                                    onChange={(e) =>
                                        setInterest(e.target.value)
                                    }
                                />
                                <Field
                                    label="Tenure (months)"
                                    type="number"
                                    value={tenure}
                                    onChange={(e) => setTenure(e.target.value)}
                                />
                            </>
                        )}
                        <div className="rounded-xl border border-dashed border-[#d9deea] p-4">
                            <p className="text-xs font-semibold text-[#4d5a6d]">
                                Current starting point
                            </p>
                            <div className="mt-3 grid grid-cols-2 gap-3 text-xs">
                                <span className="text-[#8993a3]">
                                    Liquid assets
                                </span>
                                <span className="text-right font-semibold text-[#4d5a6d]">
                                    {formatCompactEGP(
                                        dashboard.summary.liquidAssets,
                                    )}
                                </span>
                                <span className="text-[#8993a3]">
                                    Free cash flow
                                </span>
                                <span className="text-right font-semibold text-[#4d5a6d]">
                                    {formatCompactEGP(
                                        dashboard.summary.freeCashFlow,
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>
                </Card>
                <div className="space-y-4">
                    <Card className="overflow-hidden">
                        <div className="bg-[#1c2a45] p-6 text-white">
                            <p className="text-xs tracking-[0.16em] text-[#aeb9d1] uppercase">
                                Selected scenario
                            </p>
                            <h2 className="mt-2 text-2xl font-semibold">
                                {mode === 'cash'
                                    ? 'Buy the car cash'
                                    : '50% down + financing'}
                            </h2>
                            <p className="mt-2 max-w-xl text-sm leading-6 text-[#bac5da]">
                                A transparent comparison using your current
                                numbers. Change the inputs and watch the
                                trade-offs update.
                            </p>
                        </div>
                        <div className="grid gap-3 p-5 sm:grid-cols-3">
                            {choices.map((choice) => (
                                <div
                                    key={choice.label}
                                    className="rounded-xl bg-[#f8f9fb] p-4"
                                >
                                    <p className="text-xs text-[#8993a3]">
                                        {choice.label}
                                    </p>
                                    <p className="mt-2 text-lg font-semibold text-[#273246]">
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
                        <div className="space-y-5 p-5">
                            <div
                                className={`rounded-xl p-4 ${result.confidence === 'comfortable' ? 'bg-[#eaf8ef] text-[#328654]' : result.confidence === 'review' ? 'bg-[#fff8ef] text-[#a46e14]' : 'bg-[#fff0f1] text-[#c65365]'}`}
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
                                total={dashboard.summary.liquidAssets}
                                color="#7c8cf8"
                            />
                            <Impact
                                label="Emergency coverage"
                                value={result.coverage}
                                total={6}
                                suffix=" months"
                                color={
                                    result.coverage >= 6 ? '#63bf85' : '#f0ad59'
                                }
                            />
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl border border-[#edf0f4] p-4">
                                    <p className="text-xs text-[#8993a3]">
                                        Financing cost
                                    </p>
                                    <p className="mt-2 text-xl font-semibold text-[#273246]">
                                        {formatEGP(result.totalInterest)}
                                    </p>
                                    <p className="mt-1 text-xs text-[#8993a3]">
                                        Interest over the full term
                                    </p>
                                </div>
                                <div className="rounded-xl border border-[#edf0f4] p-4">
                                    <p className="text-xs text-[#8993a3]">
                                        Monthly payment
                                    </p>
                                    <p className="mt-2 text-xl font-semibold text-[#273246]">
                                        {formatEGP(result.payment)}
                                    </p>
                                    <p className="mt-1 text-xs text-[#8993a3]">
                                        Before other obligations
                                    </p>
                                </div>
                            </div>
                            <div className="rounded-xl bg-[#fff8ef] p-4 text-xs leading-5 text-[#966b2a]">
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
                <span className="font-semibold text-[#58657a]">{label}</span>
                <span className="font-semibold text-[#4d5a6d]">
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
