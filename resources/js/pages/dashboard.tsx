import { useState } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
    Progress,
} from '@/components/app-shell';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { formatCompactEGP, formatEGP } from '@/types/finance';
import type { Bucket, Goal, Summary } from '@/types/finance';

type Allocation = { label: string; value: number; percent: number };
type Liquidity = { label: string; value: number };
type Commitment = {
    id: number;
    name: string;
    monthlyAmount: number;
    nextDueOn: string | null;
};

const colors = [
    'var(--chart-1)',
    'var(--chart-3)',
    'var(--chart-2)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--ring)',
];

export default function Dashboard({
    summary,
    assetAllocation,
    currencyExposure,
    liquidity,
    goals,
    buckets,
    insights,
    asOf,
    targetAllocation,
    wealthMetrics,
    wealthTrend,
    recurringCommitments,
    liabilities,
    dataFreshness,
    attentionQueue,
    decisionJournal,
    allocationPolicy,
}: {
    summary: Summary;
    assetAllocation: Allocation[];
    currencyExposure: Allocation[];
    liquidity: Liquidity[];
    goals: Goal[];
    buckets: Bucket[];
    insights: string[];
    asOf: string;
    targetAllocation: Record<string, number>;
    wealthMetrics: {
        savingsRate: number;
        investmentRate?: number;
        monthlyWealthContribution: number;
        debtToNetWorth: number;
        committedIncomeRate: number;
    };
    wealthTrend: {
        asOf: string;
        netWorth: number;
        investableNetWorth: number;
    }[];
    recurringCommitments: Commitment[];
    liabilities: {
        id: number;
        name: string;
        balance: number;
        monthlyPayment: number;
    }[];
    dataFreshness?: {
        lastUpdated?: string | null;
        cashFlowSource?: string;
        valuationFreshness?: {
            status: string;
            ageDays: number | null;
            freshnessPeriodDays: number;
        };
        reviewStatus?: string;
        demoDataWarning?: boolean;
        backup?: { status: string; verifiedAt?: string | null };
    };
    attentionQueue?: {
        key: string;
        title: string;
        reason: string;
        rule: string;
        source: string;
        actionUrl: string;
    }[];
    decisionJournal?: {
        id: number;
        decision: string;
        chosenAction: string | null;
        reviewDate: string | null;
        status: string;
    }[];
    allocationPolicy?: {
        label: string;
        currentPercent: number;
        targetPercent: number;
        minPercent: number;
        maxPercent: number;
        tolerancePercent: number;
        status: string;
        rule: string;
        source: string;
    }[];
}) {
    const [detail, setDetail] = useState<{
        title: string;
        description: string;
        formula: string;
        source: string;
    } | null>(null);
    const largestAsset = assetAllocation[0];
    const largestCurrency = currencyExposure[0];

    return (
        <AppShell title="Overview">
            <PageHeader
                eyebrow={new Date(asOf).toLocaleDateString('en-EG', {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                })}
                title="Your financial picture"
                description="A calm view of what you own, what your money is for, and the decisions that need attention."
                action={
                    <div className="flex gap-2">
                        <Button
                            href="/export/context?format=markdown"
                            variant="ghost"
                        >
                            Export context
                        </Button>
                        <Button href="/assets">Update assets</Button>
                    </div>
                }
            />
            <Card className="mb-4 border-dashed">
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 p-4 text-xs text-muted-foreground">
                    <span className="font-semibold text-foreground">
                        Data status
                    </span>
                    <span>
                        Cash flow:{' '}
                        {dataFreshness?.cashFlowSource?.replaceAll('_', ' ') ??
                            'unknown'}
                    </span>
                    <span>
                        Review: {dataFreshness?.reviewStatus ?? 'missing'}
                    </span>
                    <span>
                        Valuation:{' '}
                        {dataFreshness?.valuationFreshness?.status ?? 'unknown'}
                        {dataFreshness?.valuationFreshness?.ageDays !== null &&
                        dataFreshness?.valuationFreshness?.ageDays !== undefined
                            ? ` (${dataFreshness.valuationFreshness.ageDays}d old)`
                            : ''}
                    </span>
                    <span>
                        Backup: {dataFreshness?.backup?.status ?? 'missing'}
                    </span>
                    {dataFreshness?.demoDataWarning && (
                        <Badge variant="secondary">Demo data detected</Badge>
                    )}
                    <span className="ml-auto">
                        Updated{' '}
                        {dataFreshness?.lastUpdated
                            ? new Date(
                                  dataFreshness.lastUpdated,
                              ).toLocaleString('en-EG')
                            : 'not yet'}
                    </span>
                    <Button href="/monthly-review" size="sm">
                        Review this month
                    </Button>
                </div>
            </Card>
            <div className="grid gap-4 xl:grid-cols-[1.65fr_1fr]">
                <Card className="overflow-hidden border-0 bg-sidebar text-sidebar-foreground">
                    <div className="relative p-6 sm:p-8">
                        <div className="absolute -top-28 -right-20 h-72 w-72 rounded-full border-[32px] border-sidebar-primary/15" />
                        <div className="absolute -right-4 -bottom-36 h-72 w-72 rounded-full border-[32px] border-sidebar-primary/10" />
                        <div className="relative">
                            <div className="flex items-center justify-between">
                                <p className="text-xs font-semibold tracking-[0.16em] text-sidebar-foreground/70 uppercase">
                                    Net worth
                                </p>
                                <Badge className="rounded-full border-0 bg-sidebar-primary/15 px-3 py-1 text-xs text-sidebar-primary">
                                    As of {asOf}
                                </Badge>
                            </div>
                            <button
                                type="button"
                                className="mt-4 text-left text-4xl font-semibold tracking-tight sm:text-5xl"
                                onClick={() =>
                                    setDetail({
                                        title: 'Net worth',
                                        description:
                                            'Total assets minus active liabilities.',
                                        formula: `${formatEGP(summary.totalAssets ?? 0)} − ${formatEGP(summary.liabilities ?? 0)} = ${formatEGP(summary.netWorth)}`,
                                        source: 'assets and liabilities',
                                    })
                                }
                            >
                                {formatEGP(summary.netWorth)}
                            </button>
                            <div className="mt-8 grid grid-cols-2 gap-5 border-t border-white/10 pt-5 sm:grid-cols-5">
                                <Metric
                                    label="Investable"
                                    value={formatCompactEGP(
                                        summary.investableNetWorth,
                                    )}
                                />
                                <Metric
                                    label="Available ≤3d"
                                    value={formatCompactEGP(
                                        summary.availableWithinThreeDays ??
                                            summary.liquidAssets,
                                    )}
                                />
                                <Metric
                                    label="Reserved"
                                    value={formatCompactEGP(
                                        summary.reservedForGoals,
                                    )}
                                />
                                <Metric
                                    label="Liabilities"
                                    value={formatCompactEGP(
                                        summary.liabilities ?? 0,
                                    )}
                                />
                                <Metric
                                    label="Free cash flow"
                                    value={formatCompactEGP(
                                        summary.freeCashFlow,
                                    )}
                                />
                            </div>
                        </div>
                    </div>
                </Card>
                <Card className="p-6">
                    <div className="flex items-start justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                Emergency fund
                            </p>
                            <p className="mt-3 text-3xl font-semibold text-foreground">
                                {summary.emergencyCoverageMonths}{' '}
                                <span className="text-base font-medium text-muted-foreground">
                                    months
                                </span>
                            </p>
                        </div>
                        <Badge
                            className={`rounded-full border-0 px-2.5 py-1 text-xs font-semibold ${summary.emergencyCoverageMonths >= (summary.emergencyReserveMonths ?? 6) ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'}`}
                        >
                            {summary.emergencyCoverageMonths >=
                            (summary.emergencyReserveMonths ?? 6)
                                ? 'On baseline'
                                : 'Needs attention'}
                        </Badge>
                    </div>
                    <Progress
                        value={
                            (summary.emergencyCoverageMonths /
                                Math.max(
                                    1,
                                    summary.emergencyReserveMonths ?? 6,
                                )) *
                            100
                        }
                        color="var(--chart-2)"
                    />
                    <div className="mt-3 flex justify-between text-xs text-muted-foreground">
                        <span>{formatEGP(summary.emergencyFund)} saved</span>
                        <span>
                            {summary.emergencyReserveMonths ?? 6} months target
                        </span>
                    </div>
                    <div className="mt-7 grid grid-cols-2 gap-3">
                        <MiniMetric
                            label="Monthly income"
                            value={formatCompactEGP(summary.income)}
                            positive
                        />
                        <MiniMetric
                            label="Monthly expenses"
                            value={formatCompactEGP(summary.expenses)}
                        />
                    </div>
                </Card>
            </div>
            <div className="mt-4 grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <Card>
                    <CardHeader
                        title="Wealth rhythm"
                        meta="The signals that matter more than daily spending"
                        action={
                            <Button href="/monthly-review" variant="ghost">
                                Open monthly review
                            </Button>
                        }
                    />
                    <div className="grid gap-3 p-5 sm:grid-cols-5">
                        <MiniMetric
                            label="Invested this month"
                            value={formatCompactEGP(
                                wealthMetrics.monthlyWealthContribution,
                            )}
                            positive
                        />
                        <MiniMetric
                            label="Savings rate"
                            value={`${wealthMetrics.savingsRate}%`}
                            positive
                        />
                        <MiniMetric
                            label="Investment rate"
                            value={`${wealthMetrics.investmentRate ?? summary.investmentRate ?? 0}%`}
                        />
                        <MiniMetric
                            label="Income committed"
                            value={`${wealthMetrics.committedIncomeRate}%`}
                        />
                        <MiniMetric
                            label="Debt / net worth"
                            value={`${wealthMetrics.debtToNetWorth}%`}
                        />
                    </div>
                    <div className="border-t border-border px-5 py-4">
                        <div className="flex items-end gap-2">
                            {wealthTrend.slice(-8).map((point) => {
                                const max = Math.max(
                                    ...wealthTrend.map((item) => item.netWorth),
                                    1,
                                );

                                return (
                                    <div
                                        key={point.asOf}
                                        className="flex flex-1 flex-col items-center gap-1"
                                    >
                                        <div
                                            className="w-full rounded-t-md bg-primary"
                                            style={{
                                                height: `${Math.max(8, (point.netWorth / max) * 72)}px`,
                                            }}
                                            title={`${point.asOf}: ${formatEGP(point.netWorth)}`}
                                        />
                                        <span className="text-[9px] text-muted-foreground">
                                            {point.asOf.slice(5)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Commitments and debt"
                        meta="What is already spoken for"
                        action={
                            <Button href="/commitments" variant="ghost">
                                Manage
                            </Button>
                        }
                    />
                    <div className="flex flex-col gap-3 p-5">
                        <div className="flex items-center justify-between rounded-xl bg-amber-100 px-3.5 py-3 dark:bg-amber-950/50">
                            <span className="text-xs font-semibold text-amber-800 dark:text-amber-300">
                                Recurring commitments
                            </span>
                            <span className="text-sm font-semibold text-amber-700 dark:text-amber-300">
                                {formatCompactEGP(
                                    summary.recurringCommitments ?? 0,
                                )}
                                /mo
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-xl bg-destructive/10 px-3.5 py-3">
                            <span className="text-xs font-semibold text-destructive">
                                Active liabilities
                            </span>
                            <span className="text-sm font-semibold text-destructive">
                                {formatCompactEGP(summary.liabilities ?? 0)}
                            </span>
                        </div>
                        {recurringCommitments.slice(0, 3).map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between border-b border-border pb-2 text-xs"
                            >
                                <span className="text-muted-foreground">
                                    {item.name}
                                </span>
                                <span className="font-semibold text-muted-foreground">
                                    {formatCompactEGP(item.monthlyAmount)}
                                </span>
                            </div>
                        ))}
                        {liabilities.slice(0, 2).map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between text-xs"
                            >
                                <span className="text-muted-foreground">
                                    {item.name}
                                </span>
                                <span className="font-semibold text-destructive">
                                    {formatCompactEGP(item.balance)}
                                </span>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
            <div className="mt-4 grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <Card>
                    <CardHeader
                        title="Attention queue"
                        meta="At most three explainable next actions"
                    />
                    <div className="flex flex-col gap-3 p-5">
                        {(attentionQueue ?? []).map((alert) => (
                            <div
                                key={alert.key}
                                className="flex items-start justify-between gap-3 rounded-xl bg-muted p-3"
                            >
                                <div>
                                    <p className="text-sm font-semibold text-foreground">
                                        {alert.title}
                                    </p>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        {alert.reason}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Rule: {alert.rule}
                                    </p>
                                </div>
                                <Button
                                    href={alert.actionUrl}
                                    size="sm"
                                    variant="ghost"
                                >
                                    Fix
                                </Button>
                            </div>
                        ))}
                        {!(attentionQueue ?? []).length && (
                            <p className="text-sm text-muted-foreground">
                                No actionable alerts under the configured rules.
                            </p>
                        )}
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Decision journal"
                        meta="Recent choices and review dates"
                        action={
                            <Button href="/decision-journal" variant="ghost">
                                Open journal
                            </Button>
                        }
                    />
                    <div className="flex flex-col gap-3 p-5">
                        {(decisionJournal ?? []).slice(0, 3).map((entry) => (
                            <div
                                key={entry.id}
                                className="border-b border-border pb-3 text-xs last:border-0"
                            >
                                <p className="font-semibold text-foreground">
                                    {entry.decision}
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {entry.chosenAction ?? 'Action not chosen'}{' '}
                                    · {entry.reviewDate ?? 'No review date'}
                                </p>
                            </div>
                        ))}
                        {!(decisionJournal ?? []).length && (
                            <p className="text-sm text-muted-foreground">
                                Record a decision when an assumption matters.
                            </p>
                        )}
                    </div>
                </Card>
            </div>
            <div className="mt-4 grid gap-4 xl:grid-cols-[1.1fr_1fr_0.9fr]">
                <Card>
                    <CardHeader
                        title="Asset allocation"
                        meta={
                            largestAsset
                                ? `${largestAsset.label} is your largest position`
                                : 'Add assets to see the mix'
                        }
                    />
                    <div className="flex flex-col gap-4 p-5">
                        {assetAllocation.map((item, index) => (
                            <div key={item.label}>
                                <div className="mb-1.5 flex items-center justify-between text-xs">
                                    <span className="flex items-center gap-2 font-medium text-muted-foreground">
                                        <span
                                            className="h-2.5 w-2.5 rounded-full"
                                            style={{
                                                background:
                                                    colors[
                                                        index % colors.length
                                                    ],
                                            }}
                                        />
                                        {item.label}
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {item.percent}%{' '}
                                        <span className="ml-2 font-normal text-muted-foreground">
                                            {formatCompactEGP(item.value)}
                                        </span>
                                    </span>
                                </div>
                                <Progress
                                    value={item.percent}
                                    color={colors[index % colors.length]}
                                />
                            </div>
                        ))}
                        {assetAllocation.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No assets yet.
                            </p>
                        )}
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Current vs target"
                        meta="Your personal allocation policy"
                    />
                    <div className="flex flex-col gap-4 p-5">
                        {(
                            allocationPolicy ??
                            Object.entries(targetAllocation).map(
                                ([label, target]) => ({
                                    label,
                                    currentPercent:
                                        assetAllocation.find(
                                            (item) => item.label === label,
                                        )?.percent ?? 0,
                                    targetPercent: target,
                                    minPercent: target,
                                    maxPercent: target,
                                    tolerancePercent: 0,
                                    status: 'within_range',
                                    rule: `Target ${target}%`,
                                    source: 'financial_settings',
                                }),
                            )
                        ).map((item, index) => {
                            const {
                                label,
                                currentPercent: current,
                                targetPercent: target,
                            } = item;

                            return (
                                <div key={label}>
                                    <div className="mb-1.5 flex items-center justify-between text-xs">
                                        <span className="font-medium text-muted-foreground">
                                            {label}
                                        </span>
                                        <span
                                            className={
                                                item.status !== 'within_range'
                                                    ? 'font-semibold text-amber-700 dark:text-amber-300'
                                                    : 'text-muted-foreground'
                                            }
                                        >
                                            {current}%{' '}
                                            <span className="text-muted-foreground/70">
                                                / {item.minPercent}–
                                                {item.maxPercent}%
                                            </span>
                                        </span>
                                    </div>
                                    <div className="relative h-2 rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full"
                                            style={{
                                                width: `${Math.min(100, current)}%`,
                                                backgroundColor:
                                                    colors[
                                                        index % colors.length
                                                    ],
                                            }}
                                        />
                                        <span
                                            className="absolute -top-1 h-4 w-0.5 bg-foreground"
                                            style={{ left: `${target}%` }}
                                        />
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Liquidity view"
                        meta="If you need money tomorrow"
                    />
                    <div className="flex flex-col gap-3 p-5">
                        {liquidity.map((item, index) => (
                            <div
                                key={item.label}
                                className="flex items-center justify-between rounded-xl bg-muted px-3.5 py-3"
                            >
                                <div>
                                    <p className="text-xs font-semibold text-muted-foreground">
                                        {item.label.replaceAll('_', ' ')}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        {index === 0
                                            ? 'Cash and current accounts'
                                            : index === 1
                                              ? 'Redeemable quickly'
                                              : index === 2
                                                ? 'May take longer to sell'
                                                : 'Not readily accessible'}
                                    </p>
                                </div>
                                <span className="text-sm font-semibold text-foreground">
                                    {formatCompactEGP(item.value)}
                                </span>
                            </div>
                        ))}
                        {largestCurrency && (
                            <div className="border-t border-border pt-4">
                                <p className="text-[11px] tracking-wider text-muted-foreground uppercase">
                                    Largest currency exposure
                                </p>
                                <p className="mt-1 text-sm font-semibold text-muted-foreground">
                                    {largestCurrency.label} ·{' '}
                                    {largestCurrency.percent}%
                                </p>
                            </div>
                        )}
                    </div>
                </Card>
            </div>
            <div className="mt-4 grid gap-4 xl:grid-cols-[1.35fr_0.9fr]">
                <Card>
                    <CardHeader
                        title="Goals that matter next"
                        meta="Reserved money is not investable money"
                        action={
                            <Button href="/goals" variant="ghost">
                                View goals
                            </Button>
                        }
                    />
                    <div className="divide-y divide-border">
                        {goals.slice(0, 3).map((goal) => (
                            <div key={goal.id} className="p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="text-sm font-semibold text-foreground">
                                            {goal.name}
                                        </h3>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {goal.deadline
                                                ? `Due ${new Date(goal.deadline).toLocaleDateString('en-EG', { month: 'short', year: 'numeric' })}`
                                                : 'No deadline set'}{' '}
                                            · {goal.monthsRemaining ?? '—'}{' '}
                                            months left
                                        </p>
                                    </div>
                                    <Badge
                                        className={`rounded-full border-0 px-2.5 py-1 text-[11px] font-semibold ${goal.onTrack ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300'}`}
                                    >
                                        {goal.onTrack
                                            ? 'On track'
                                            : 'Off track'}
                                    </Badge>
                                </div>
                                <div className="mt-4 flex items-center gap-4">
                                    <div className="flex-1">
                                        <Progress
                                            value={goal.fundingPercent}
                                            color={
                                                goal.onTrack
                                                    ? 'var(--chart-2)'
                                                    : 'var(--chart-3)'
                                            }
                                        />
                                    </div>
                                    <span className="text-xs font-semibold text-muted-foreground">
                                        {goal.fundingPercent}%
                                    </span>
                                </div>
                                <div className="mt-3 flex flex-wrap justify-between gap-2 text-xs">
                                    <span className="text-muted-foreground">
                                        {formatEGP(goal.allocatedAmount)}{' '}
                                        allocated
                                    </span>
                                    <span className="font-semibold text-muted-foreground">
                                        {formatCompactEGP(
                                            goal.requiredMonthlyContribution,
                                        )}
                                        /month needed
                                    </span>
                                </div>
                                {!goal.onTrack && (
                                    <p className="mt-3 rounded-lg bg-amber-100 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                        Off track by{' '}
                                        {formatCompactEGP(goal.gapPerMonth)}
                                        /month against current free cash flow.
                                    </p>
                                )}
                            </div>
                        ))}
                        {goals.length === 0 && (
                            <p className="p-5 text-sm text-muted-foreground">
                                Create your first goal to start planning.
                            </p>
                        )}
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Rules-based signals"
                        meta="Observations from your data"
                    />
                    <div className="flex flex-col gap-3 p-5">
                        {insights.length ? (
                            insights.map((insight, index) => (
                                <div
                                    key={insight}
                                    className="flex gap-3 rounded-xl bg-muted p-3.5"
                                >
                                    <span
                                        className={`mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs ${index === 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-secondary text-secondary-foreground'}`}
                                    >
                                        {index === 0 ? '!' : 'i'}
                                    </span>
                                    <p className="text-xs leading-5 text-muted-foreground">
                                        {insight}
                                    </p>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No signals yet. Keep your data current.
                            </p>
                        )}
                        <div className="mt-5 rounded-xl border border-dashed border-border p-4">
                            <p className="text-xs font-semibold text-muted-foreground">
                                What this dashboard is for
                            </p>
                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                Clarity before action: allocation, liquidity,
                                goals, and trade-offs. It does not make trades
                                or pretend to be a financial advisor.
                            </p>
                        </div>
                    </div>
                </Card>
            </div>
            <div className="mt-4">
                <Card>
                    <CardHeader
                        title="Purpose of your money"
                        meta="Buckets keep ownership separate from intent"
                        action={
                            <Button href="/assets" variant="ghost">
                                Manage assets
                            </Button>
                        }
                    />
                    <div className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5">
                        {buckets.map((bucket) => (
                            <div
                                key={bucket.id}
                                className="rounded-xl border border-border p-3.5"
                            >
                                <span
                                    className="mb-3 block h-1.5 w-8 rounded-full"
                                    style={{ backgroundColor: bucket.color }}
                                />
                                <p className="text-xs font-semibold text-muted-foreground">
                                    {bucket.name}
                                </p>
                                <p className="mt-2 text-lg font-semibold text-foreground">
                                    {formatCompactEGP(bucket.currentAmount)}
                                </p>
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {bucket.goalName ??
                                        bucket.purpose ??
                                        'Flexible allocation'}
                                </p>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
            <Sheet
                open={detail !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetail(null);
                    }
                }}
            >
                <SheetContent side="right">
                    <SheetHeader>
                        <SheetTitle>
                            {detail?.title ?? 'Calculation detail'}
                        </SheetTitle>
                        <SheetDescription>
                            {detail?.description}
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-4 px-4 text-sm">
                        <div className="rounded-xl bg-muted p-4">
                            <p className="text-xs text-muted-foreground">
                                Formula
                            </p>
                            <p className="mt-2 font-semibold text-foreground">
                                {detail?.formula}
                            </p>
                        </div>
                        <div className="rounded-xl border border-border p-4">
                            <p className="text-xs text-muted-foreground">
                                Source
                            </p>
                            <p className="mt-2 text-foreground">
                                {detail?.source}
                            </p>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Values are planning projections and retain the
                                source status shown in the dashboard strip.
                            </p>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AppShell>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-[11px] text-sidebar-foreground/70">{label}</p>
            <p className="mt-1 text-sm font-semibold">{value}</p>
        </div>
    );
}
function MiniMetric({
    label,
    value,
    positive,
}: {
    label: string;
    value: string;
    positive?: boolean;
}) {
    return (
        <div className="rounded-xl bg-muted p-3">
            <p className="text-[11px] text-muted-foreground">{label}</p>
            <p
                className={`mt-1 text-sm font-semibold ${positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-foreground'}`}
            >
                {value}
            </p>
        </div>
    );
}
