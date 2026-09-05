import { router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    CheckCircle2,
    CircleGauge,
    CircleDollarSign,
    Coins,
    Compass,
    Flag,
    GraduationCap,
    Landmark,
    ListChecks,
    LockKeyhole,
    Plus,
    ReceiptText,
    RefreshCw,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    Target,
    TriangleAlert,
    TrendingUp,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import { AppShell, Button, PageHeader, Progress } from '@/components/app-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { formatCompactEGP, formatEGP, labelize } from '@/types/finance';
import type { Goal, Summary } from '@/types/finance';

type Allocation = { label: string; value: number; percent: number };
type WealthGroup = {
    key: string;
    label: string;
    detail: string;
    valueEgp: number;
    percent: number;
    nativeAmount: number | null;
    nativeCurrency: string | null;
};
type WealthBreakdown = {
    groups: WealthGroup[];
    usdToEgp: number | null;
};
type LiquiditySummary = {
    grossImmediateLiquidAssets: number;
    reservedCash: number;
    controllableCashBeforeLiabilities: number;
    activeLiabilities: number;
    controllableCashAfterLiabilities: number;
};
type MarketRatePoint = {
    rate?: number;
    price?: number;
    priceDate: string;
    updatedAt: string | null;
    source: string;
    stale: boolean;
};
type MarketRates = {
    status: 'current' | 'stale' | 'missing';
    updatedAt: string | null;
    staleAfterHours: number;
    usdToEgp: MarketRatePoint | null;
    gold24kPerGram: MarketRatePoint | null;
    disclaimer: string;
};
type MonthlyPlan = {
    incomeSources: {
        label: string;
        currency: string;
        nativeAmount: number;
        amount: number;
    }[];
    plannedIncomeSources: { id?: number; label: string; amount: number }[];
    expenseCategories: { label: string; amount: number }[];
    incomeRules: { label: string; amount: number; percent?: number | null }[];
    plannedExpenseCategories: {
        categoryId?: number | null;
        label: string;
        planned: number;
        actual: number;
    }[];
    income: number;
    expenses: number;
    freeCashFlow: number;
    emergencyTarget: number;
    emergencyGap: number;
    allocationItems: {
        bucketId?: number | null;
        label: string;
        amount: number;
        actual: number;
        allocationPercent?: number | null;
        assetId?: number | null;
    }[];
    plannedTotal: number;
    unallocated: number;
    plannedIncome: number;
    plannedExpenses: number;
    plannedFreeCashFlow: number;
    templateId?: number | null;
    templateName?: string | null;
    planStatus?: string | null;
    source: 'saved_plan' | 'plan_template';
    actualTracking?: {
        source: string;
        transactionCount: number;
        unmappedPurposeAmount: number;
        unmappedExpenseAmount: number;
        matchedExpenseAmount?: number;
    } | null;
};
type MonthlyFlow = {
    source: string;
    status: 'balanced' | 'needs_direction' | 'needs_sync' | 'over_allocated';
    income: number;
    expenses: number;
    freeCashFlow: number;
    outflows: {
        key: string;
        label: string;
        amount: number;
        expected?: number;
        kind: string;
    }[];
    planned: {
        income: number;
        expenses: number;
        freeCashFlow: number;
        varianceIncome: number;
        varianceExpenses: number;
    };
    obligations: {
        commitments: {
            configuredMonthly: number;
            recorded: number;
            variance: number;
        };
        liabilities: {
            configuredMonthlyPayments: number;
            recorded: number;
            variance: number;
            balance: number;
        };
        totalConfiguredMonthly: number;
    };
    allocations: {
        label: string;
        kind: string;
        planned: number;
        actual: number;
        variance: number;
    }[];
    allocationTotal: number;
    actualAllocationTotal: number;
    plannedUnassigned: number;
    actualUnassigned: number;
    unassigned: number;
    plannedOverAllocated: number;
    actualOverAllocated: number;
    overAllocated: number;
    varianceAlerts: {
        code: string;
        label: string;
        actual: number;
        planned: number;
        variance: number;
        variancePercent: number;
        thresholdPercent: number;
        targetPercent?: number;
    }[];
    warnings: string[];
};
type ObligationChanges = {
    status: 'no_baseline' | 'unchanged' | 'changed';
    hasChanges: boolean;
    capturedAt: string | null;
    summary: {
        previousMonthly: number | null;
        currentMonthly: number;
        monthlyDelta: number | null;
        freeCashFlowImpact: number | null;
    };
    commitments: {
        added: ObligationChangeRecord[];
        removed: ObligationChangeRecord[];
        changed: ObligationChangeRecord[];
    };
    liabilities: {
        added: ObligationChangeRecord[];
        removed: ObligationChangeRecord[];
        changed: ObligationChangeRecord[];
    };
};
type ObligationChangeRecord = {
    before?: {
        name?: string;
        monthlyAmount?: number;
        monthlyPayment?: number;
        balance?: number;
    };
    after?: {
        name?: string;
        monthlyAmount?: number;
        monthlyPayment?: number;
        balance?: number;
    };
    monthlyDelta?: number;
    balanceDelta?: number | null;
    name?: string;
};
type DebtSummary = {
    monthlyRequiredPayments: number;
    liabilityBalance: number;
    estimatedMonthlyInterest: number;
    estimatedMonthlyPrincipal: number;
    projectedPayoffMonths: number | null;
};
type MonthlyHistoryItem = {
    month: string;
    label: string;
    income: number;
    expenses: number;
    freeCashFlow: number;
    invested: number;
    debtPayments: number;
    savingsRate: number;
    investmentRate: number;
    source: string;
};
type AttentionItem = {
    key: string;
    title: string;
    reason: string;
    actionUrl: string;
};
type FinancialFreedom = {
    annualSpending: number;
    withdrawalRatePercent: number;
    target: number;
    currentInvestable: number;
    gap: number;
    progressPercent: number;
    annualInvestmentPace: number;
    yearsAtCurrentPace: number | null;
    limitations?: string[];
};
type WealthStage = {
    key: 'foundation' | 'growth' | 'freedom';
    number: number;
    label: string;
    description: string;
    nextAction: string;
    coverageMonths: number;
    reserveMonths: number;
    steps: { key: string; number: number; label: string; ready: boolean }[];
};

const chartColors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

export default function Dashboard({
    summary,
    assetAllocation,
    currencyExposure,
    goals,
    asOf,
    monthlyPlan,
    monthlyFlow,
    wealthTrend,
    dataFreshness,
    marketRates,
    attentionQueue = [],
    financialFreedom,
    wealthStage,
    obligationChanges,
    debtSummary,
    monthlyHistory,
    wealthBreakdown,
    liquiditySummary,
}: {
    summary: Summary;
    assetAllocation: Allocation[];
    currencyExposure: Allocation[];
    goals: Goal[];
    asOf: string;
    monthlyPlan: MonthlyPlan;
    monthlyFlow?: MonthlyFlow;
    wealthTrend: { asOf: string; netWorth: number }[];
    dataFreshness?: {
        cashFlowSource?: string;
        lastUpdated?: string | null;
        demoDataWarning?: boolean;
    };
    marketRates?: MarketRates;
    attentionQueue?: AttentionItem[];
    financialFreedom?: FinancialFreedom;
    wealthStage?: WealthStage;
    obligationChanges?: ObligationChanges;
    debtSummary?: DebtSummary;
    monthlyHistory?: MonthlyHistoryItem[];
    wealthBreakdown?: WealthBreakdown;
    liquiditySummary?: LiquiditySummary;
}) {
    const [showNetWorthDetail, setShowNetWorthDetail] = useState(false);
    const wealthGroups = wealthBreakdown?.groups ?? [];
    const emergencyTarget =
        monthlyPlan?.emergencyTarget ??
        summary.expenses * (summary.emergencyReserveMonths ?? 6);
    const emergencyPercent =
        emergencyTarget > 0
            ? (summary.emergencyFund / emergencyTarget) * 100
            : 0;
    const hasPositiveSurplus = summary.freeCashFlow >= 0;

    return (
        <AppShell title="Dashboard">
            <PageHeader
                eyebrow={new Date(asOf).toLocaleDateString('en-EG', {
                    month: 'long',
                    year: 'numeric',
                })}
                title="Your money, one clear plan"
                description="See what you own, how this month flows, and where every remaining pound is meant to go."
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button href="/learn" variant="ghost">
                            <GraduationCap data-icon="inline-start" />
                            Learn the system
                        </Button>
                        <Button href="/cash-flow" variant="ghost">
                            <Plus data-icon="inline-start" />
                            Add income or expense
                        </Button>
                        <Button href="/assets">
                            <WalletCards data-icon="inline-start" />
                            Update what you own
                        </Button>
                    </div>
                }
            />

            <div className="mb-4 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <Badge variant="outline">
                    {sourceLabel(dataFreshness?.cashFlowSource)}
                </Badge>
                {dataFreshness?.demoDataWarning && (
                    <Badge variant="secondary">
                        Demo data is still present
                    </Badge>
                )}
                <span>
                    Updated{' '}
                    {dataFreshness?.lastUpdated
                        ? new Date(dataFreshness.lastUpdated).toLocaleString(
                              'en-EG',
                          )
                        : 'not yet'}
                </span>
            </div>

            <MarketRatesCard marketRates={marketRates} />

            {dataFreshness?.demoDataWarning && (
                <DemoWorkspaceCard
                    hasObligationChange={obligationChanges?.hasChanges ?? false}
                    emergencyContribution={monthlyPlan?.unallocated ?? 0}
                    onReset={() => {
                        if (
                            window.confirm(
                                'Reset the demo workspace to its original learning data? This only affects the demo user.',
                            )
                        ) {
                            router.post('/demo/reset');
                        }
                    }}
                />
            )}

            <div className="mt-6 grid items-start gap-4 xl:grid-cols-[1.45fr_0.85fr]">
                <Card className="bg-hero text-hero-foreground [--card-spacing:--spacing(6)]">
                    <CardHeader className="bg-hero text-hero-foreground">
                        <CardTitle className="text-hero-foreground/70">
                            Total net worth
                        </CardTitle>
                        <CardDescription className="text-hero-foreground/60">
                            Everything you own, minus active liabilities
                        </CardDescription>
                        <CardAction>
                            <Badge variant="secondary">As of {asOf}</Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="border-x border-hero-foreground/10 bg-hero">
                        <button
                            type="button"
                            className="text-left text-4xl font-semibold tracking-tight sm:text-5xl"
                            onClick={() => setShowNetWorthDetail(true)}
                        >
                            {formatEGP(summary.netWorth)}
                        </button>
                        <div className="mt-8 flex h-3 overflow-hidden rounded-full bg-hero-track">
                            {wealthGroups.map((group, index) => (
                                <span
                                    key={group.label}
                                    style={{
                                        width: `${group.percent}%`,
                                        backgroundColor:
                                            chartColors[
                                                index % chartColors.length
                                            ],
                                    }}
                                />
                            ))}
                        </div>
                        <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {wealthGroups.map((group, index) => (
                                <div key={group.label}>
                                    <div className="flex items-center gap-2 text-xs text-hero-foreground/60">
                                        <span
                                            className="size-2 rounded-full"
                                            style={{
                                                backgroundColor:
                                                    chartColors[
                                                        index %
                                                            chartColors.length
                                                    ],
                                            }}
                                        />
                                        {group.label}
                                    </div>
                                    <p className="mt-1 text-sm font-semibold tabular-nums">
                                        {formatCompactEGP(group.valueEgp)}
                                    </p>
                                    <p className="mt-1 text-[11px] leading-4 text-hero-foreground/60">
                                        {wealthGroupNativeLabel(group)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                    <CardFooter className="grid gap-4 border-hero-foreground/10 bg-hero-foreground/5 sm:grid-cols-2 lg:grid-cols-5">
                        <HeroMetric
                            label="Total assets"
                            value={formatCompactEGP(summary.totalAssets ?? 0)}
                        />
                        <HeroMetric
                            label="Directly controlled"
                            value={formatCompactEGP(
                                summary.directlyControlledAssets,
                            )}
                        />
                        <HeroMetric
                            label="Held elsewhere"
                            value={formatCompactEGP(summary.heldElsewhere)}
                        />
                        <HeroMetric
                            label="Reserved for goals"
                            value={formatCompactEGP(summary.reservedForGoals)}
                        />
                        <HeroMetric
                            label="Liabilities"
                            value={formatCompactEGP(summary.liabilities ?? 0)}
                        />
                    </CardFooter>
                </Card>

                <Card className="[--card-spacing:--spacing(6)]">
                    <CardHeader>
                        <CardTitle>Actual vs planned breathing room</CardTitle>
                        <CardDescription>
                            Actual activity is separate from the saved monthly
                            plan
                        </CardDescription>
                        <CardAction>
                            <Badge
                                variant={
                                    hasPositiveSurplus
                                        ? 'secondary'
                                        : 'destructive'
                                }
                            >
                                {hasPositiveSurplus
                                    ? 'Actual surplus'
                                    : 'Actual deficit'}
                            </Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <p
                            className={cn(
                                'text-4xl font-semibold tracking-tight',
                                !hasPositiveSurplus && 'text-destructive',
                            )}
                        >
                            {formatEGP(summary.freeCashFlow)}
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Actual free cash flow · confirmed income{' '}
                            {formatCompactEGP(summary.income)} · recorded
                            expenses {formatCompactEGP(summary.expenses)}
                        </p>
                        <div className="mt-7 grid grid-cols-2 gap-3">
                            <SmallMetric
                                icon={CircleDollarSign}
                                label="Actual income"
                                value={formatCompactEGP(summary.income)}
                            />
                            <SmallMetric
                                icon={ReceiptText}
                                label="Actual expenses"
                                value={formatCompactEGP(summary.expenses)}
                            />
                        </div>
                        <div className="mt-3 rounded-lg border p-3">
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span className="font-medium">
                                    Planned free cash flow
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {formatCompactEGP(
                                        monthlyPlan?.plannedFreeCashFlow ?? 0,
                                    )}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Planned income{' '}
                                {formatCompactEGP(
                                    monthlyPlan?.plannedIncome ?? 0,
                                )}{' '}
                                minus planned expenses{' '}
                                {formatCompactEGP(
                                    monthlyPlan?.plannedExpenses ?? 0,
                                )}
                            </p>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button href="/monthly-review" variant="ghost">
                            Review the month
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <LiquidityOverviewCard summary={liquiditySummary} />

            <section className="mt-6">
                <SectionHeading
                    eyebrow="Monthly money flow"
                    title="From income to a deliberate surplus"
                    description="Multiple sources and currencies are converted to EGP once, then expenses and allocations use the same base."
                />
                <Card>
                    <CardContent className="grid gap-3 pt-1 lg:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr] lg:items-stretch">
                        <FlowStep
                            icon={CircleDollarSign}
                            label="Planned income"
                            value={monthlyPlan?.plannedIncome ?? summary.income}
                            items={(monthlyPlan
                                ? monthlyPlan.source === 'saved_plan'
                                    ? monthlyPlan.plannedIncomeSources
                                    : monthlyPlan.incomeRules.length
                                      ? monthlyPlan.incomeRules
                                      : monthlyPlan.plannedIncomeSources
                                : []
                            ).map((source) => ({
                                label: source.label,
                                value:
                                    'percent' in source &&
                                    source.percent !== null &&
                                    source.percent !== undefined
                                        ? `${source.percent}% · ${formatCompactEGP(source.amount)}`
                                        : formatCompactEGP(source.amount),
                            }))}
                            empty="Create the monthly plan and add income sources"
                        />
                        <FlowArrow />
                        <FlowStep
                            icon={ReceiptText}
                            label="Planned expenses"
                            value={
                                monthlyPlan?.plannedExpenses ??
                                monthlyPlan?.expenses ??
                                summary.expenses
                            }
                            items={(monthlyPlan?.plannedExpenseCategories
                                ?.length
                                ? monthlyPlan.plannedExpenseCategories
                                : (monthlyPlan?.expenseCategories ?? [])
                            ).map((expense) => ({
                                label: expense.label,
                                value: formatCompactEGP(
                                    'planned' in expense
                                        ? expense.planned
                                        : expense.amount,
                                ),
                            }))}
                            empty="Add your recurring and flexible expenses"
                        />
                        <FlowArrow />
                        <FlowStep
                            icon={ListChecks}
                            label="Planned allocations"
                            value={monthlyPlan?.plannedTotal ?? 0}
                            items={(monthlyPlan?.allocationItems ?? []).map(
                                (item) => ({
                                    label: item.label,
                                    value: formatCompactEGP(item.amount),
                                }),
                            )}
                            empty="Add allocation rules to give the surplus a job"
                        />
                        <FlowArrow />
                        <FlowStep
                            icon={Target}
                            label="Planned unassigned remainder"
                            value={monthlyPlan?.unallocated ?? 0}
                            items={[
                                {
                                    label: 'Current plan',
                                    value:
                                        monthlyPlan?.source === 'saved_plan'
                                            ? 'Saved for this month'
                                            : 'Preview from template',
                                },
                            ]}
                            empty="The plan is fully assigned"
                        />
                    </CardContent>
                </Card>
            </section>

            <MonthlyFlowDetails flow={monthlyFlow} />

            <div className="mt-6 grid items-start gap-4 xl:grid-cols-[1fr_1fr]">
                <ObligationChangesCard changes={obligationChanges} />
                <DebtProgressCard summary={debtSummary} />
            </div>

            <MonthlyHistoryCard history={monthlyHistory} />

            <div className="mt-6 grid items-start gap-4 xl:grid-cols-[1.1fr_0.9fr]">
                <MonthlyPlanComparisonCard
                    monthlyPlan={monthlyPlan}
                    actualSource={
                        monthlyFlow?.source ?? dataFreshness?.cashFlowSource
                    }
                />
                <WealthJourneyCard
                    stage={wealthStage}
                    financialFreedom={financialFreedom}
                />
            </div>

            <div className="mt-6 grid items-start gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Emergency fund</CardTitle>
                        <CardDescription>
                            Your first protection before longer-term investing
                        </CardDescription>
                        <CardAction>
                            <Badge
                                variant={
                                    emergencyPercent >= 100
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {summary.emergencyCoverageMonths} of{' '}
                                {summary.emergencyReserveMonths ?? 6} months
                            </Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <p className="text-3xl font-semibold">
                                    {formatEGP(summary.emergencyFund)}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    of {formatCompactEGP(emergencyTarget)}{' '}
                                    target
                                </p>
                            </div>
                            <ShieldCheck className="size-9 text-muted-foreground" />
                        </div>
                        <div className="mt-5">
                            <Progress value={emergencyPercent} />
                        </div>
                        <div className="mt-3 flex justify-between gap-3 text-xs text-muted-foreground">
                            <span>
                                {Math.min(100, emergencyPercent).toFixed(0)}%
                                covered
                            </span>
                            <span>
                                {formatCompactEGP(
                                    monthlyPlan?.emergencyGap ?? 0,
                                )}{' '}
                                gap
                            </span>
                        </div>
                    </CardContent>
                    <CardFooter>
                        <Button href="/buckets" variant="ghost">
                            Manage emergency reserve
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Where the surplus goes</CardTitle>
                        <CardDescription>
                            {monthlyPlan?.source === 'saved_plan'
                                ? `Saved snapshot${monthlyPlan.templateName ? ` · ${monthlyPlan.templateName}` : ''}`
                                : `Preview from ${monthlyPlan?.templateName ?? 'the selected template'}`}
                        </CardDescription>
                        <CardAction>
                            <Badge variant="outline">
                                {monthlyPlan?.source === 'saved_plan'
                                    ? 'Saved snapshot'
                                    : 'Template rules'}
                            </Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        {(monthlyPlan?.allocationItems ?? []).map(
                            (item, index) => {
                                const percent =
                                    monthlyPlan.plannedTotal > 0
                                        ? (item.amount /
                                              monthlyPlan.plannedTotal) *
                                          100
                                        : 0;

                                return (
                                    <div
                                        key={`${item.bucketId ?? item.label}-${item.label}`}
                                    >
                                        <div className="mb-2 flex items-start justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <span
                                                    className="size-2.5 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            chartColors[
                                                                index %
                                                                    chartColors.length
                                                            ],
                                                    }}
                                                />
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {item.label}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.allocationPercent !==
                                                            null &&
                                                        item.allocationPercent !==
                                                            undefined
                                                            ? `${item.allocationPercent}% of post-expense cash`
                                                            : 'Fixed plan amount'}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold">
                                                    {formatCompactEGP(
                                                        item.amount,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {percent.toFixed(0)}%
                                                </p>
                                            </div>
                                        </div>
                                        <Progress
                                            value={percent}
                                            color={
                                                chartColors[
                                                    index % chartColors.length
                                                ]
                                            }
                                        />
                                    </div>
                                );
                            },
                        )}
                        {!(monthlyPlan?.allocationItems ?? []).length && (
                            <p className="text-sm text-muted-foreground">
                                There is no positive surplus to allocate yet.
                            </p>
                        )}
                        {(monthlyPlan?.unallocated ?? 0) > 0 && (
                            <div className="flex items-center justify-between rounded-lg bg-muted p-3 text-xs">
                                <span className="text-muted-foreground">
                                    Still unassigned
                                </span>
                                <span className="font-semibold">
                                    {formatCompactEGP(monthlyPlan.unallocated)}
                                </span>
                            </div>
                        )}
                    </CardContent>
                    <CardFooter>
                        <Button href="/allocations">
                            <Sparkles data-icon="inline-start" />
                            Adjust monthly split
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <section className="mt-6">
                <SectionHeading
                    eyebrow="Goals"
                    title="Goals backed by real assets"
                    description="A goal can be funded by cash, gold, or an investment fund without pretending the money lives somewhere else."
                    action={
                        <Button href="/goals" variant="ghost">
                            Manage goals
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    }
                />
                <div className="grid items-start gap-4 lg:grid-cols-2">
                    {goals.slice(0, 4).map((goal) => (
                        <GoalCard key={goal.id} goal={goal} />
                    ))}
                    {!goals.length && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>Create your first goal</CardTitle>
                                <CardDescription>
                                    Add a phone, car, travel plan, or any target
                                    you want to fund over time.
                                </CardDescription>
                            </CardHeader>
                            <CardFooter>
                                <Button href="/goals">
                                    <Plus data-icon="inline-start" />
                                    Add goal
                                </Button>
                            </CardFooter>
                        </Card>
                    )}
                </div>
            </section>

            <div className="mt-6 grid items-start gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Portfolio mix</CardTitle>
                        <CardDescription>
                            A simple view of the assets building your wealth
                        </CardDescription>
                        <CardAction>
                            <Button href="/assets" variant="ghost" size="sm">
                                View assets
                            </Button>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="grid gap-6 md:grid-cols-2">
                        <AllocationList
                            title="By asset type"
                            items={assetAllocation}
                        />
                        <AllocationList
                            title="By currency"
                            items={currencyExposure}
                        />
                    </CardContent>
                    {!!wealthTrend.length && (
                        <CardFooter className="flex-col items-stretch gap-3">
                            <div className="flex items-center justify-between gap-3 text-xs">
                                <span className="font-medium">
                                    Net worth history
                                </span>
                                <span className="text-muted-foreground">
                                    {wealthTrend.length} recorded point
                                    {wealthTrend.length === 1 ? '' : 's'}
                                </span>
                            </div>
                            <TrendBars points={wealthTrend} />
                        </CardFooter>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Next best actions</CardTitle>
                        <CardDescription>
                            Only the things that need attention now
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {attentionQueue.slice(0, 3).map((item, index) => (
                            <a
                                key={item.key}
                                href={item.actionUrl}
                                className="group flex items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted"
                            >
                                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-muted text-xs font-semibold text-muted-foreground group-hover:bg-background">
                                    {index + 1}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium">
                                        {item.title}
                                    </span>
                                    <span className="mt-1 block text-xs leading-5 text-muted-foreground">
                                        {item.reason}
                                    </span>
                                </span>
                                <ArrowRight className="size-4 shrink-0 text-muted-foreground" />
                            </a>
                        ))}
                        {!attentionQueue.length && (
                            <div className="flex items-center gap-3 rounded-lg bg-muted p-4">
                                <ShieldCheck className="size-5 text-muted-foreground" />
                                <p className="text-sm text-muted-foreground">
                                    Nothing urgent. Keep this month's data up to
                                    date.
                                </p>
                            </div>
                        )}
                    </CardContent>
                    <CardFooter>
                        <Button href="/cash-flow" variant="ghost">
                            Open money activity
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <Sheet
                open={showNetWorthDetail}
                onOpenChange={setShowNetWorthDetail}
            >
                <SheetContent side="right">
                    <SheetHeader>
                        <SheetTitle>How net worth is calculated</SheetTitle>
                        <SheetDescription>
                            Assets minus active liabilities, all converted to
                            EGP.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="flex flex-col gap-4 px-4">
                        <CalculationRow
                            label="Total assets"
                            value={summary.totalAssets ?? 0}
                        />
                        <CalculationRow
                            label="Held elsewhere / receivables"
                            value={summary.heldElsewhere}
                        />
                        <CalculationRow
                            label="Directly controlled assets"
                            value={summary.directlyControlledAssets}
                        />
                        <CalculationRow
                            label="Active liabilities"
                            value={-(summary.liabilities ?? 0)}
                        />
                        <CalculationRow
                            label="Net worth"
                            value={summary.netWorth}
                            total
                        />
                        <p className="text-xs leading-5 text-muted-foreground">
                            Net worth includes receivables because they are
                            still owned by you. Directly controlled assets
                            exclude money currently held by other people.
                        </p>
                    </div>
                </SheetContent>
            </Sheet>
        </AppShell>
    );
}

function DemoWorkspaceCard({
    hasObligationChange,
    emergencyContribution,
    onReset,
}: {
    hasObligationChange: boolean;
    emergencyContribution: number;
    onReset: () => void;
}) {
    return (
        <Card className="mt-6 border-dashed [--card-spacing:--spacing(5)]">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ListChecks data-icon="inline-start" />
                    Try the demo learning path
                </CardTitle>
                <CardDescription>
                    This workspace contains a realistic year of decisions. Use
                    these links to understand the full monthly loop.
                </CardDescription>
                <CardAction>
                    <Badge variant="secondary">Learning workspace</Badge>
                </CardAction>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <DemoAction
                    href="/monthly-review"
                    icon={ReceiptText}
                    title="Review this month"
                    description="Compare the plan, confirmed transactions, and the latest lesson."
                />
                <DemoAction
                    href="/liabilities"
                    icon={Landmark}
                    title="Study the debt"
                    description="Record a lender payment and compare extra-payment scenarios."
                />
                <DemoAction
                    href="/scenarios"
                    icon={Target}
                    title="Test the goals"
                    description="See how a 1.5M EGP car competes with investing and shorter goals."
                />
                <DemoAction
                    href="/valuations"
                    icon={WalletCards}
                    title="Trust the history"
                    description="Inspect dated asset values, FX rates, and liability balances."
                />
            </CardContent>
            <CardFooter className="flex flex-col items-stretch gap-3 border-t bg-muted/20 sm:flex-row sm:items-center sm:justify-between">
                <Alert className="border-0 bg-transparent p-0">
                    <Sparkles data-icon="inline-start" />
                    <AlertTitle>Current demo lesson</AlertTitle>
                    <AlertDescription>
                        {hasObligationChange
                            ? `A commitment changed after the last close. ${formatCompactEGP(emergencyContribution)} is still unassigned in the current plan and needs a deliberate decision.`
                            : 'Close the current review after making a change to see the system explain its impact.'}
                    </AlertDescription>
                </Alert>
                <Button
                    type="button"
                    variant="ghost"
                    className="shrink-0"
                    onClick={onReset}
                >
                    <RotateCcw data-icon="inline-start" />
                    Reset demo data
                </Button>
            </CardFooter>
        </Card>
    );
}

function MarketRatesCard({ marketRates }: { marketRates?: MarketRates }) {
    const [syncing, setSyncing] = useState(false);
    const statusLabel =
        marketRates?.status === 'current'
            ? 'Updated'
            : marketRates?.status === 'stale'
              ? 'Needs refresh'
              : 'Not available';

    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <TrendingUp data-icon="inline-start" />
                    Market rates
                </CardTitle>
                <CardDescription>
                    Daily reference prices used to mark matching USD and gold
                    assets in EGP.
                </CardDescription>
                <CardAction className="flex items-center gap-2">
                    <Badge
                        variant={
                            marketRates?.status === 'current'
                                ? 'secondary'
                                : 'outline'
                        }
                    >
                        {statusLabel}
                    </Badge>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={syncing}
                        onClick={() => {
                            setSyncing(true);
                            router.post(
                                '/market-rates/sync',
                                {},
                                {
                                    preserveScroll: true,
                                    onFinish: () => setSyncing(false),
                                },
                            );
                        }}
                    >
                        <RefreshCw data-icon="inline-start" />
                        {syncing ? 'Refreshing…' : 'Refresh rates'}
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent className="grid gap-3 md:grid-cols-2">
                <MarketRateMetric
                    icon={CircleDollarSign}
                    label="USD to EGP"
                    value={
                        marketRates?.usdToEgp
                            ? formatMarketNumber(
                                  marketRates.usdToEgp.rate ?? 0,
                              ) + ' EGP'
                            : 'No rate yet'
                    }
                    meta={
                        marketRates?.usdToEgp
                            ? marketRates.usdToEgp.priceDate +
                              ' · ' +
                              marketRates.usdToEgp.source
                            : 'Run the daily sync to load a rate.'
                    }
                    stale={marketRates?.usdToEgp?.stale ?? true}
                />
                <MarketRateMetric
                    icon={Coins}
                    label="24K gold / gram"
                    value={
                        marketRates?.gold24kPerGram
                            ? formatEGP(marketRates.gold24kPerGram.price ?? 0)
                            : 'No price yet'
                    }
                    meta={
                        marketRates?.gold24kPerGram
                            ? marketRates.gold24kPerGram.priceDate +
                              ' · spot estimate'
                            : 'Run the daily sync to load a price.'
                    }
                    stale={marketRates?.gold24kPerGram?.stale ?? true}
                />
            </CardContent>
            <CardFooter className="flex-col items-start gap-2 border-t bg-muted/20 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs leading-5 text-muted-foreground">
                    {marketRates?.disclaimer ??
                        'Prices are loaded server-side and cached locally.'}{' '}
                    Refreshing revalues matching USD and gold assets; bucket
                    totals follow their assigned share of those assets.{' '}
                    <a
                        href="https://www.exchangerate-api.com"
                        target="_blank"
                        rel="noreferrer"
                        className="underline underline-offset-2"
                    >
                        ExchangeRate-API
                    </a>
                </p>
                <Button href="/fx-rates" variant="ghost" size="sm">
                    View history
                    <ArrowRight data-icon="inline-end" />
                </Button>
            </CardFooter>
        </Card>
    );
}

function MarketRateMetric({
    icon: Icon,
    label,
    value,
    meta,
    stale,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    meta: string;
    stale: boolean;
}) {
    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                <Icon data-icon="inline-start" />
                {label}
                {stale && <Badge variant="outline">Stale</Badge>}
            </div>
            <p className="mt-3 text-2xl font-semibold tabular-nums">{value}</p>
            <p className="mt-1 text-xs text-muted-foreground">{meta}</p>
        </div>
    );
}

function formatMarketNumber(value: number) {
    return value.toLocaleString('en-EG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function DemoAction({
    href,
    icon: Icon,
    title,
    description,
}: {
    href: string;
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <a
            href={href}
            className="group rounded-lg border p-4 transition-colors hover:bg-muted"
        >
            <div className="flex items-center gap-2 text-sm font-medium">
                <Icon className="text-muted-foreground" />
                {title}
                <ArrowRight className="ml-auto text-muted-foreground transition-transform group-hover:translate-x-0.5" />
            </div>
            <p className="mt-2 text-xs leading-5 text-muted-foreground">
                {description}
            </p>
        </a>
    );
}

function wealthGroupNativeLabel(group: WealthGroup) {
    if (group.nativeAmount === null || group.nativeCurrency === null) {
        return group.detail;
    }

    const amount = group.nativeAmount.toLocaleString('en-EG', {
        maximumFractionDigits: 2,
    });

    return `${amount} ${group.nativeCurrency} · ${group.detail}`;
}

function LiquidityOverviewCard({ summary }: { summary?: LiquiditySummary }) {
    if (!summary) {
        return null;
    }

    return (
        <Card className="mt-4">
            <CardHeader>
                <CardTitle>Liquidity at a glance</CardTitle>
                <CardDescription>
                    What is liquid now, what is reserved, and what remains after
                    active liabilities
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <LiquidityMetric
                    label="Gross immediate liquid"
                    value={summary.grossImmediateLiquidAssets}
                    detail="Includes reserved cash"
                />
                <LiquidityMetric
                    label="Reserved cash"
                    value={summary.reservedCash}
                    detail="Not available for normal spending"
                />
                <LiquidityMetric
                    label="Controllable before liabilities"
                    value={summary.controllableCashBeforeLiabilities}
                    detail="Gross liquid less reserved cash"
                />
                <LiquidityMetric
                    label="Controllable after liabilities"
                    value={summary.controllableCashAfterLiabilities}
                    detail={`Less ${formatCompactEGP(summary.activeLiabilities)} active liabilities`}
                />
            </CardContent>
            <CardFooter className="border-t bg-muted/20">
                <p className="text-xs leading-5 text-muted-foreground">
                    Liabilities are subtracted in this liquidity view only to
                    show spendable capacity. Net worth independently remains
                    total assets minus active liabilities.
                </p>
            </CardFooter>
        </Card>
    );
}

function LiquidityMetric({
    label,
    value,
    detail,
}: {
    label: string;
    value: number;
    detail: string;
}) {
    return (
        <div className="rounded-lg bg-muted p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-2 text-xl font-semibold tabular-nums">
                {formatEGP(value)}
            </p>
            <p className="mt-1 text-xs leading-4 text-muted-foreground">
                {detail}
            </p>
        </div>
    );
}

function sourceLabel(source?: string) {
    if (!source || source === 'ledger_incomplete') {
        return 'Cash flow needs setup';
    }

    return source === 'confirmed_ledger'
        ? 'Using confirmed transactions'
        : `Using ${source.replaceAll('_', ' ')}`;
}

function HeroMetric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-hero-foreground/60">{label}</p>
            <p className="mt-1 text-sm font-semibold text-hero-foreground">
                {value}
            </p>
        </div>
    );
}

function SmallMetric({
    icon: Icon,
    label,
    value,
}: {
    icon: LucideIcon;
    label: string;
    value: string;
}) {
    return (
        <div className="rounded-lg bg-muted p-3">
            <Icon className="size-4 text-muted-foreground" />
            <p className="mt-3 text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-sm font-semibold">{value}</p>
        </div>
    );
}

function SectionHeading({
    eyebrow,
    title,
    description,
    action,
}: {
    eyebrow: string;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="mb-4 flex flex-col justify-between gap-3 md:flex-row md:items-end">
            <div>
                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    {eyebrow}
                </p>
                <h2 className="mt-1 text-xl font-semibold tracking-tight">
                    {title}
                </h2>
                <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                    {description}
                </p>
            </div>
            {action}
        </div>
    );
}

function FlowStep({
    icon: Icon,
    label,
    value,
    items,
    empty,
}: {
    icon: LucideIcon;
    label: string;
    value: number;
    items: { label: string; value: string }[];
    empty: string;
}) {
    return (
        <div className="flex min-w-0 flex-col rounded-lg bg-muted p-4">
            <div className="flex items-center gap-2 text-muted-foreground">
                <span className="grid size-8 place-items-center rounded-lg bg-background">
                    <Icon className="size-4" />
                </span>
                <p className="text-xs font-medium">{label}</p>
            </div>
            <p className="mt-4 text-xl font-semibold">
                {formatCompactEGP(value)}
            </p>
            <div className="mt-4 flex flex-col gap-2">
                {items.slice(0, 3).map((item) => (
                    <div
                        key={`${item.label}-${item.value}`}
                        className="flex items-start justify-between gap-2 text-[11px]"
                    >
                        <span className="truncate text-muted-foreground">
                            {labelize(item.label)}
                        </span>
                        <span className="shrink-0 font-medium">
                            {item.value}
                        </span>
                    </div>
                ))}
                {!items.length && (
                    <p className="text-[11px] leading-4 text-muted-foreground">
                        {empty}
                    </p>
                )}
            </div>
        </div>
    );
}

function FlowArrow() {
    return (
        <div className="grid place-items-center text-muted-foreground">
            <ArrowDownRight className="size-4 lg:hidden" />
            <ArrowRight className="hidden size-4 lg:block" />
        </div>
    );
}

function ObligationChangesCard({ changes }: { changes?: ObligationChanges }) {
    if (!changes || changes.status === 'no_baseline') {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Obligation change watch</CardTitle>
                    <CardDescription>
                        Close a review once to create the comparison baseline.
                    </CardDescription>
                </CardHeader>
                <CardContent className="text-sm leading-6 text-muted-foreground">
                    The app will compare every active commitment and liability
                    next time, without asking you to link them manually.
                </CardContent>
                <CardFooter>
                    <Button href="/monthly-review" variant="ghost" size="sm">
                        Open monthly review{' '}
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                </CardFooter>
            </Card>
        );
    }

    const changeCount = [
        ...changes.commitments.added,
        ...changes.commitments.removed,
        ...changes.commitments.changed,
        ...changes.liabilities.added,
        ...changes.liabilities.removed,
        ...changes.liabilities.changed,
    ].length;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Obligation change watch</CardTitle>
                <CardDescription>
                    What changed since the last closed review
                </CardDescription>
                <CardAction>
                    <Badge
                        variant={
                            changes.hasChanges ? 'destructive' : 'secondary'
                        }
                    >
                        {changes.hasChanges
                            ? `${changeCount} change${changeCount === 1 ? '' : 's'}`
                            : 'No changes'}
                    </Badge>
                </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {changes.hasChanges ? (
                    <>
                        <div className="rounded-lg bg-muted p-3 text-sm">
                            <span className="font-semibold">
                                {formatCompactEGP(
                                    changes.summary.monthlyDelta ?? 0,
                                )}
                            </span>{' '}
                            monthly capacity change · positive means less free
                            cash flow
                        </div>
                        <ObligationChangeLines
                            label="Commitments"
                            group={changes.commitments}
                        />
                        <ObligationChangeLines
                            label="Liabilities"
                            group={changes.liabilities}
                        />
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        The active records still match the snapshot captured at
                        the last close.
                    </p>
                )}
            </CardContent>
            <CardFooter>
                <Button href="/monthly-review" variant="ghost" size="sm">
                    Review the difference <ArrowRight data-icon="inline-end" />
                </Button>
            </CardFooter>
        </Card>
    );
}

function ObligationChangeLines({
    label,
    group,
}: {
    label: string;
    group: {
        added: ObligationChangeRecord[];
        removed: ObligationChangeRecord[];
        changed: ObligationChangeRecord[];
    };
}) {
    const lines = [
        ...group.added.map((item) => `Added: ${item.name}`),
        ...group.removed.map((item) => `Removed: ${item.name}`),
        ...group.changed.map(
            (item) =>
                `Changed: ${item.after?.name ?? item.before?.name} (${formatCompactEGP(item.monthlyDelta ?? 0)} / month)`,
        ),
    ];

    if (!lines.length) {
        return null;
    }

    return (
        <div>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="mt-1 flex flex-col gap-1 text-sm">
                {lines.map((line) => (
                    <span key={line}>{line}</span>
                ))}
            </div>
        </div>
    );
}

function DebtProgressCard({ summary }: { summary?: DebtSummary }) {
    if (!summary) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Debt payoff picture</CardTitle>
                <CardDescription>
                    Transparent estimate from the current balance, rate, and
                    payment
                </CardDescription>
                <CardAction>
                    <Button href="/liabilities" variant="ghost" size="sm">
                        Manage debt
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-3">
                <SmallMetric
                    icon={CircleDollarSign}
                    label="Balance"
                    value={formatCompactEGP(summary.liabilityBalance)}
                />
                <SmallMetric
                    icon={ArrowDownRight}
                    label="Principal / month"
                    value={formatCompactEGP(summary.estimatedMonthlyPrincipal)}
                />
                <SmallMetric
                    icon={ReceiptText}
                    label="Interest / month"
                    value={formatCompactEGP(summary.estimatedMonthlyInterest)}
                />
            </CardContent>
            <CardFooter className="text-xs text-muted-foreground">
                {summary.projectedPayoffMonths !== null
                    ? `At the current payment, the estimate is ${summary.projectedPayoffMonths} months remaining. Verify it against the lender statement.`
                    : 'The current payment is not enough to create a payoff projection.'}
            </CardFooter>
        </Card>
    );
}

function MonthlyHistoryCard({ history }: { history?: MonthlyHistoryItem[] }) {
    if (!history?.length) {
        return null;
    }

    const max = Math.max(
        ...history.flatMap((item) => [
            item.income,
            item.expenses,
            Math.max(0, item.freeCashFlow),
        ]),
        1,
    );

    return (
        <Card className="mt-6">
            <CardHeader>
                <CardTitle>Twelve-month money history</CardTitle>
                <CardDescription>
                    Income, outflow, free cash flow, and investing trend
                    together
                </CardDescription>
                <CardAction>
                    <Button href="/monthly-review" variant="ghost" size="sm">
                        Open review history
                    </Button>
                </CardAction>
            </CardHeader>
            <CardContent className="grid gap-4 lg:grid-cols-6">
                {history.map((item) => (
                    <div key={item.month} className="min-w-0">
                        <div className="flex items-center justify-between gap-2 text-xs">
                            <span className="font-medium">{item.label}</span>
                            <span className="text-muted-foreground">
                                {item.savingsRate.toFixed(0)}% saved
                            </span>
                        </div>
                        <div className="mt-2 flex h-28 items-end gap-1 rounded-lg bg-muted/50 p-2">
                            <HistoryBar
                                value={item.income}
                                max={max}
                                label="Income"
                            />
                            <HistoryBar
                                value={item.expenses}
                                max={max}
                                label="Outflow"
                            />
                            <HistoryBar
                                value={Math.max(0, item.freeCashFlow)}
                                max={max}
                                label="Free"
                            />
                            <HistoryBar
                                value={item.invested}
                                max={max}
                                label="Invested"
                            />
                        </div>
                        <p className="mt-2 truncate text-xs text-muted-foreground">
                            Free {formatCompactEGP(item.freeCashFlow)} ·
                            Invested {formatCompactEGP(item.invested)}
                        </p>
                    </div>
                ))}
            </CardContent>
            <CardFooter className="text-xs text-muted-foreground">
                Bars are relative to the largest value in this twelve-month
                window. Savings rate = free cash flow ÷ income.
            </CardFooter>
        </Card>
    );
}

function HistoryBar({
    value,
    max,
    label,
}: {
    value: number;
    max: number;
    label: string;
}) {
    return (
        <span
            title={`${label}: ${formatCompactEGP(value)}`}
            className="min-h-1 flex-1 rounded-t-sm bg-primary"
            style={{ height: `${Math.max(4, (value / max) * 100)}%` }}
        />
    );
}

function MonthlyFlowDetails({ flow }: { flow?: MonthlyFlow }) {
    if (!flow) {
        return null;
    }

    const allocationCapacity = Math.max(flow.planned.freeCashFlow, 0);
    const allocationPercent =
        allocationCapacity > 0
            ? Math.min(100, (flow.allocationTotal / allocationCapacity) * 100)
            : 0;
    const statusLabel = {
        balanced: 'Balanced',
        needs_direction: 'Needs direction',
        needs_sync: 'Needs sync',
        over_allocated: 'Over allocated',
    }[flow.status];

    return (
        <section className="mt-4">
            <Alert variant={flow.warnings.length ? 'destructive' : 'default'}>
                {flow.warnings.length ? <TriangleAlert /> : <CheckCircle2 />}
                <AlertTitle>
                    {flow.warnings.length
                        ? 'The monthly flow needs attention'
                        : 'The monthly flow is connected'}
                </AlertTitle>
                <AlertDescription>
                    {flow.warnings.length ? (
                        <div className="flex flex-col gap-2">
                            <ul className="flex list-disc flex-col gap-1 pl-4">
                                {flow.warnings.map((warning) => (
                                    <li key={warning}>{warning}</li>
                                ))}
                            </ul>
                            {flow.varianceAlerts.length > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    Threshold details:{' '}
                                    {flow.varianceAlerts
                                        .map(
                                            (alert) =>
                                                `${alert.label}: ${alert.variancePercent}% (threshold ${alert.thresholdPercent}%)`,
                                        )
                                        .join(' · ')}
                                </p>
                            )}
                        </div>
                    ) : (
                        'Your recorded outflow, active obligations, and allocation plan agree for this month.'
                    )}
                </AlertDescription>
            </Alert>
            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Plan health</CardTitle>
                    <CardDescription>
                        Follow the money from recorded outflow to the purpose
                        assigned to the surplus.
                    </CardDescription>
                    <CardAction>
                        <Badge
                            variant={
                                flow.status === 'balanced'
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {statusLabel}
                        </Badge>
                    </CardAction>
                </CardHeader>
                <CardContent className="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
                    <div className="flex flex-col gap-3">
                        <FlowHealthRow label="Income" value={flow.income} />
                        {flow.outflows
                            .filter((outflow) => outflow.amount !== 0)
                            .map((outflow) => (
                                <FlowHealthRow
                                    key={outflow.key}
                                    label={outflow.label}
                                    value={-outflow.amount}
                                    detail={
                                        outflow.expected !== undefined &&
                                        Math.abs(
                                            outflow.expected - outflow.amount,
                                        ) > 0.01
                                            ? `planned ${formatCompactEGP(outflow.expected)}`
                                            : undefined
                                    }
                                />
                            ))}
                        <div className="rounded-lg bg-muted p-4">
                            <div className="flex items-center justify-between gap-3">
                                <span className="text-sm font-semibold">
                                    Free cash flow
                                </span>
                                <span className="text-lg font-semibold">
                                    {formatCompactEGP(flow.freeCashFlow)}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Planned:{' '}
                                {formatCompactEGP(flow.planned.freeCashFlow)} ·
                                Difference:{' '}
                                {formatCompactEGP(
                                    flow.freeCashFlow -
                                        flow.planned.freeCashFlow,
                                )}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-col gap-4">
                        <div>
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span className="font-semibold">
                                    Planned surplus assigned
                                </span>
                                <span className="text-muted-foreground">
                                    {formatCompactEGP(flow.allocationTotal)} /{' '}
                                    {formatCompactEGP(allocationCapacity)}
                                </span>
                            </div>
                            <div className="mt-3">
                                <Progress value={allocationPercent} />
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Actual linked:{' '}
                                {formatCompactEGP(flow.actualAllocationTotal)} ·
                                actual free cash without a linked allocation:{' '}
                                {formatCompactEGP(flow.actualUnassigned)}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {flow.overAllocated > 0
                                    ? `${formatCompactEGP(flow.plannedOverAllocated)} over planned cash flow`
                                    : flow.plannedUnassigned > 0
                                      ? `${formatCompactEGP(flow.plannedUnassigned)} still unassigned in the plan`
                                      : flow.actualUnassigned > 0
                                        ? `${formatCompactEGP(flow.actualUnassigned)} actual cash remains without a linked allocation`
                                        : 'Every planned pound has an allocation rule'}
                            </p>
                        </div>
                        <div className="rounded-lg border p-4">
                            <p className="text-sm font-semibold">
                                Required monthly obligations
                            </p>
                            <div className="mt-3 flex flex-col gap-2 text-sm">
                                <FlowHealthRow
                                    label="Commitments"
                                    value={
                                        flow.obligations.commitments
                                            .configuredMonthly
                                    }
                                    detail={`reviewed ${formatCompactEGP(flow.obligations.commitments.recorded)}`}
                                />
                                <FlowHealthRow
                                    label="Liability payments"
                                    value={
                                        flow.obligations.liabilities
                                            .configuredMonthlyPayments
                                    }
                                    detail={`reviewed ${formatCompactEGP(flow.obligations.liabilities.recorded)}`}
                                />
                            </div>
                            <p className="mt-3 text-xs text-muted-foreground">
                                {formatCompactEGP(
                                    flow.obligations.totalConfiguredMonthly,
                                )}{' '}
                                must be protected before flexible spending or
                                investing.
                            </p>
                        </div>
                        <div className="flex flex-col gap-2">
                            <p className="text-sm font-semibold">
                                Planned allocations
                            </p>
                            {flow.allocations.map((allocation) => (
                                <div
                                    key={allocation.label}
                                    className="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span className="truncate text-muted-foreground">
                                        {allocation.label}
                                    </span>
                                    <span className="shrink-0 font-medium">
                                        {formatCompactEGP(allocation.planned)}
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            · actual{' '}
                                            {formatCompactEGP(
                                                allocation.actual,
                                            )}
                                        </span>
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                </CardContent>
                <CardFooter className="justify-between gap-3">
                    <span className="text-xs text-muted-foreground">
                        Planned income {formatCompactEGP(flow.planned.income)} ·
                        planned outflow{' '}
                        {formatCompactEGP(flow.planned.expenses)}
                    </span>
                    <Button href="/monthly-review" variant="ghost" size="sm">
                        Open review
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                </CardFooter>
            </Card>
        </section>
    );
}

function FlowHealthRow({
    label,
    value,
    detail,
}: {
    label: string;
    value: number;
    detail?: string;
}) {
    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <span className="min-w-0 truncate text-muted-foreground">
                {label}
            </span>
            <span
                className={cn(
                    'shrink-0 font-medium',
                    value < 0 && 'text-muted-foreground',
                )}
            >
                {formatCompactEGP(value)}
                {detail && (
                    <span className="ml-1 text-xs font-normal text-muted-foreground">
                        · {detail}
                    </span>
                )}
            </span>
        </div>
    );
}

function MonthlyPlanComparisonCard({
    monthlyPlan,
    actualSource,
}: {
    monthlyPlan?: MonthlyPlan;
    actualSource?: string;
}) {
    const planExpenses = monthlyPlan?.plannedExpenseCategories ?? [];
    const planAllocations = monthlyPlan?.allocationItems ?? [];
    const planIsSaved = monthlyPlan?.source === 'saved_plan';
    const actualTracking = monthlyPlan?.actualTracking;
    const actualExpenseTotal = planExpenses.reduce(
        (total, item) => total + item.actual,
        0,
    );
    const actualAllocationTotal = planAllocations.reduce(
        (total, item) => total + item.actual,
        0,
    );
    const actualSourceLabel =
        actualSource === 'confirmed_ledger'
            ? 'Confirmed ledger'
            : actualSource === 'monthly_review'
              ? 'Monthly review'
              : actualSource === 'cash_flow'
                ? 'Cash-flow entries'
                : actualSource
                  ? labelize(actualSource)
                  : 'Current month actuals';

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <CircleGauge className="text-muted-foreground" />
                    Current month: plan vs actual
                </CardTitle>
                <CardDescription>
                    {planIsSaved
                        ? `${monthlyPlan?.templateName ?? 'Plan template'} is saved for this month. Editing the template will not rewrite this snapshot.`
                        : `${monthlyPlan?.templateName ?? 'The active template'} is only a preview. Save a monthly plan to freeze these values for this month.`}
                </CardDescription>
                <CardAction>
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Badge variant={planIsSaved ? 'secondary' : 'outline'}>
                            {planIsSaved ? 'Saved plan' : 'Template preview'}
                        </Badge>
                        <Badge variant="outline">
                            {monthlyPlan
                                ? `${monthlyPlan.plannedFreeCashFlow.toLocaleString('en-EG')} EGP planned free`
                                : 'Set up'}
                        </Badge>
                    </div>
                </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {monthlyPlan && (
                    <div className="flex flex-col gap-4">
                        <div className="rounded-xl border p-3">
                            <p className="text-sm font-semibold">Income</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Actual income is the confirmed month total. It
                                is not guessed into individual plan sources.
                            </p>
                            <div className="mt-3">
                                <PlanActualRow
                                    label="Total monthly income"
                                    planned={monthlyPlan.plannedIncome}
                                    actual={monthlyPlan.income}
                                />
                            </div>
                            {monthlyPlan.plannedIncomeSources.length > 0 && (
                                <div className="mt-3 flex flex-col gap-1.5 border-t pt-3">
                                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Plan sources
                                    </p>
                                    {monthlyPlan.plannedIncomeSources.map(
                                        (source) => (
                                            <FlowHealthRow
                                                key={`income-source-${source.id ?? source.label}`}
                                                label={source.label}
                                                value={source.amount}
                                            />
                                        ),
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="rounded-xl border p-3">
                            <p className="text-sm font-semibold">
                                Plan expense categories
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                These rows come directly from the current month
                                plan. Actuals appear only when a ledger
                                transaction is linked to the same category.
                            </p>
                            <div className="mt-3 flex flex-col gap-3">
                                {planExpenses.map((item) => (
                                    <PlanActualRow
                                        key={`expense-${item.categoryId ?? item.label}`}
                                        label={item.label}
                                        planned={item.planned}
                                        actual={item.actual}
                                    />
                                ))}
                                {!planExpenses.length && (
                                    <p className="text-sm text-muted-foreground">
                                        No expense categories are in this plan.
                                    </p>
                                )}
                            </div>
                            {actualTracking &&
                                actualTracking.unmappedExpenseAmount > 0 && (
                                    <UnmappedRow
                                        label="Unmapped actual expenses"
                                        amount={
                                            actualTracking.unmappedExpenseAmount
                                        }
                                        detail="No matching plan category"
                                    />
                                )}
                        </div>

                        <div className="rounded-xl border p-3">
                            <p className="text-sm font-semibold">
                                Plan allocation rules
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                These are the exact bucket and asset rows saved
                                in this month’s plan. No row is renamed into an
                                emergency, goal, or investment category here.
                            </p>
                            <div className="mt-3 flex flex-col gap-3">
                                {planAllocations.map((item) => (
                                    <PlanActualRow
                                        key={`allocation-${item.bucketId ?? item.label}-${item.label}`}
                                        label={item.label}
                                        planned={item.amount}
                                        actual={item.actual}
                                        detail={
                                            item.allocationPercent !== null &&
                                            item.allocationPercent !== undefined
                                                ? `${item.allocationPercent}% rule`
                                                : undefined
                                        }
                                    />
                                ))}
                                {!planAllocations.length && (
                                    <p className="text-sm text-muted-foreground">
                                        No allocation rules are in this plan.
                                    </p>
                                )}
                            </div>
                            {actualTracking &&
                                actualTracking.unmappedPurposeAmount > 0 && (
                                    <UnmappedRow
                                        label="Unlinked actual allocations"
                                        amount={
                                            actualTracking.unmappedPurposeAmount
                                        }
                                        detail="No purpose bucket selected"
                                    />
                                )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <SmallMetric
                                icon={CircleDollarSign}
                                label="Planned unassigned remainder"
                                value={formatCompactEGP(
                                    monthlyPlan.unallocated,
                                )}
                            />
                            <SmallMetric
                                icon={TrendingUp}
                                label="Actual linked allocations"
                                value={formatCompactEGP(actualAllocationTotal)}
                            />
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Actual source: {actualSourceLabel}. Linked expense
                            actuals total {formatCompactEGP(actualExpenseTotal)}
                            ; unlinked transactions stay separate instead of
                            being classified by name.
                        </p>
                    </div>
                )}
                {!monthlyPlan && (
                    <p className="text-sm text-muted-foreground">
                        Add a monthly plan to see exact planned categories and
                        allocation rules beside actuals.
                    </p>
                )}
            </CardContent>
            <CardFooter className="justify-between gap-3">
                <span className="text-xs text-muted-foreground">
                    {planIsSaved
                        ? 'This month is a saved snapshot; actuals do not rewrite the plan.'
                        : 'This is a live template preview until you save the monthly plan.'}
                </span>
                <Button
                    href={planIsSaved ? '/allocations' : '/monthly-rules'}
                    variant="ghost"
                    size="sm"
                >
                    {planIsSaved ? 'Edit current plan' : 'Edit template'}
                    <ArrowRight data-icon="inline-end" />
                </Button>
            </CardFooter>
        </Card>
    );
}

function PlanActualRow({
    label,
    planned,
    actual,
    detail,
}: {
    label: string;
    planned: number;
    actual: number;
    detail?: string;
}) {
    const progress =
        planned > 0 ? (actual / planned) * 100 : actual > 0 ? 100 : 0;
    const variance = actual - planned;

    return (
        <div>
            <div className="flex items-start justify-between gap-3 text-sm">
                <span className="min-w-0 truncate font-medium">{label}</span>
                <span className="shrink-0 text-right text-xs text-muted-foreground">
                    Planned {formatCompactEGP(planned)} · Actual{' '}
                    {formatCompactEGP(actual)}
                </span>
            </div>
            <div className="mt-1.5 flex items-center gap-3">
                <div className="flex-1">
                    <Progress value={Math.min(100, Math.max(0, progress))} />
                </div>
                <span className="w-28 shrink-0 text-right text-[11px] text-muted-foreground">
                    {variance === 0
                        ? 'on plan'
                        : `${variance > 0 ? '+' : ''}${formatCompactEGP(variance)} variance`}
                </span>
            </div>
            {detail && (
                <p className="mt-1 text-[11px] text-muted-foreground">
                    {detail}
                </p>
            )}
        </div>
    );
}

function UnmappedRow({
    label,
    amount,
    detail,
}: {
    label: string;
    amount: number;
    detail: string;
}) {
    return (
        <div className="mt-3 rounded-lg bg-muted/60 p-3 text-sm">
            <div className="flex items-center justify-between gap-3">
                <span className="font-medium">{label}</span>
                <span className="font-medium tabular-nums">
                    {formatCompactEGP(amount)}
                </span>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">{detail}</p>
        </div>
    );
}

function WealthJourneyCard({
    stage,
    financialFreedom,
}: {
    stage?: WealthStage;
    financialFreedom?: FinancialFreedom;
}) {
    const progress = financialFreedom?.progressPercent ?? 0;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Compass className="text-muted-foreground" />
                    Your wealth journey
                </CardTitle>
                <CardDescription>
                    A simple view of the next station, not a score.
                </CardDescription>
                <CardAction>
                    <Badge variant="secondary">
                        {stage?.label ?? 'Set up'}
                    </Badge>
                </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
                <div className="grid grid-cols-3 gap-2">
                    {(
                        stage?.steps ?? [
                            {
                                key: 'foundation',
                                number: 1,
                                label: 'Foundation',
                                ready: false,
                            },
                            {
                                key: 'growth',
                                number: 2,
                                label: 'Growth',
                                ready: false,
                            },
                            {
                                key: 'freedom',
                                number: 3,
                                label: 'Freedom',
                                ready: false,
                            },
                        ]
                    ).map((step) => (
                        <div
                            key={step.key}
                            className={cn(
                                'rounded-lg border p-2 text-center',
                                step.ready && 'border-primary/40 bg-muted',
                            )}
                        >
                            <div className="mx-auto grid size-7 place-items-center rounded-full bg-muted text-xs font-semibold">
                                {step.number}
                            </div>
                            <p className="mt-2 text-[11px] font-medium">
                                {step.label}
                            </p>
                            <p className="mt-1 text-[10px] text-muted-foreground">
                                {step.ready ? 'Ready' : 'In progress'}
                            </p>
                        </div>
                    ))}
                </div>
                <div>
                    <div className="flex items-center justify-between gap-3 text-xs">
                        <span className="flex items-center gap-2 font-medium">
                            <Target className="text-muted-foreground" />
                            Financial freedom target
                        </span>
                        <span className="text-muted-foreground">
                            {progress}%
                        </span>
                    </div>
                    <div className="mt-2">
                        <Progress value={progress} />
                    </div>
                    <div className="mt-2 flex justify-between gap-3 text-xs text-muted-foreground">
                        <span>
                            {formatCompactEGP(
                                financialFreedom?.currentInvestable ?? 0,
                            )}{' '}
                            invested capital
                        </span>
                        <span>
                            target{' '}
                            {formatCompactEGP(financialFreedom?.target ?? 0)}
                        </span>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <SmallMetric
                        icon={Target}
                        label="Still needed"
                        value={formatCompactEGP(financialFreedom?.gap ?? 0)}
                    />
                    <SmallMetric
                        icon={CircleGauge}
                        label="At current pace"
                        value={
                            financialFreedom?.yearsAtCurrentPace
                                ? `${financialFreedom.yearsAtCurrentPace} years`
                                : 'Add investments'
                        }
                    />
                </div>
                <div className="rounded-lg bg-muted p-3">
                    <p className="text-sm font-medium">
                        {stage?.nextAction ??
                            'Add your monthly spending and investment plan.'}
                    </p>
                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                        {stage?.description ??
                            'The dashboard will show your next station once your data is ready.'}
                    </p>
                </div>
            </CardContent>
            <CardFooter className="justify-between gap-3">
                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                    <LockKeyhole />
                    Assumption-based planning
                </span>
                <Button href="/settings/financial" variant="ghost" size="sm">
                    Adjust freedom assumptions
                    <ArrowRight data-icon="inline-end" />
                </Button>
            </CardFooter>
        </Card>
    );
}

function GoalCard({ goal }: { goal: Goal }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Flag className="size-4 text-muted-foreground" />
                    {goal.name}
                </CardTitle>
                <CardDescription>
                    {goal.deadline
                        ? `Target date ${new Date(goal.deadline).toLocaleDateString('en-EG', { month: 'short', year: 'numeric' })}`
                        : 'No target date yet'}
                </CardDescription>
                <CardAction>
                    <Badge variant={goal.onTrack ? 'secondary' : 'outline'}>
                        {goal.onTrack ? 'On track' : 'Needs adjustment'}
                    </Badge>
                </CardAction>
            </CardHeader>
            <CardContent>
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <p className="text-2xl font-semibold">
                            {formatCompactEGP(goal.allocatedAmount)}
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            of {formatCompactEGP(goal.targetAmount)}
                        </p>
                    </div>
                    <p className="text-sm font-semibold">
                        {goal.fundingPercent}%
                    </p>
                </div>
                <div className="mt-4">
                    <Progress value={goal.fundingPercent} />
                </div>
                <div className="mt-5 flex flex-col gap-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        Backed by
                    </p>
                    {(goal.fundingSources ?? []).slice(0, 3).map((source) => (
                        <div
                            key={source.assetId}
                            className="flex items-center justify-between gap-3 rounded-lg bg-muted px-3 py-2"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <AssetIcon type={source.assetType} />
                                <div className="min-w-0">
                                    <p className="truncate text-xs font-medium">
                                        {source.assetName}
                                    </p>
                                    <p className="text-[11px] text-muted-foreground">
                                        {source.assetType}
                                    </p>
                                </div>
                            </div>
                            <span className="shrink-0 text-xs font-semibold">
                                {formatCompactEGP(source.amount)}
                            </span>
                        </div>
                    ))}
                    {!(goal.fundingSources ?? []).length && (
                        <p className="text-xs text-muted-foreground">
                            Link an asset allocation to this goal's bucket.
                        </p>
                    )}
                </div>
            </CardContent>
            <CardFooter className="justify-between gap-3">
                <span className="text-xs text-muted-foreground">
                    {formatCompactEGP(goal.requiredMonthlyContribution)}/month
                    needed
                </span>
                <Button href="/goals" variant="ghost" size="sm">
                    Adjust goal
                </Button>
            </CardFooter>
        </Card>
    );
}

function AssetIcon({ type }: { type: string }) {
    const normalized = type.toLowerCase();
    const Icon = normalized.includes('gold')
        ? Coins
        : normalized.includes('cash') || normalized.includes('usd')
          ? Landmark
          : TrendingUp;

    return (
        <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-background text-muted-foreground">
            <Icon className="size-4" />
        </span>
    );
}

function AllocationList({
    title,
    items,
}: {
    title: string;
    items: Allocation[];
}) {
    return (
        <div>
            <p className="mb-4 text-xs font-medium text-muted-foreground">
                {title}
            </p>
            <div className="flex flex-col gap-4">
                {items.slice(0, 5).map((item, index) => (
                    <div key={item.label}>
                        <div className="mb-1.5 flex items-center justify-between gap-3 text-xs">
                            <span className="truncate">{item.label}</span>
                            <span className="shrink-0 text-muted-foreground">
                                {item.percent}% · {formatCompactEGP(item.value)}
                            </span>
                        </div>
                        <Progress
                            value={item.percent}
                            color={chartColors[index % chartColors.length]}
                        />
                    </div>
                ))}
                {!items.length && (
                    <p className="text-xs text-muted-foreground">
                        Add assets to see the mix.
                    </p>
                )}
            </div>
        </div>
    );
}

function TrendBars({
    points,
}: {
    points: { asOf: string; netWorth: number }[];
}) {
    const visible = points.slice(-10);
    const max = Math.max(...visible.map((point) => point.netWorth), 1);

    return (
        <div className="flex h-16 items-end gap-1.5">
            {visible.map((point) => (
                <span
                    key={point.asOf}
                    className="min-h-1 flex-1 rounded-t-sm bg-primary"
                    style={{
                        height: `${Math.max(6, (point.netWorth / max) * 64)}px`,
                    }}
                    title={`${point.asOf}: ${formatEGP(point.netWorth)}`}
                />
            ))}
        </div>
    );
}

function CalculationRow({
    label,
    value,
    total = false,
}: {
    label: string;
    value: number;
    total?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex items-center justify-between gap-4 rounded-lg p-4',
                total ? 'bg-primary text-primary-foreground' : 'bg-muted',
            )}
        >
            <span className="text-sm">{label}</span>
            <span className="text-sm font-semibold">{formatEGP(value)}</span>
        </div>
    );
}
