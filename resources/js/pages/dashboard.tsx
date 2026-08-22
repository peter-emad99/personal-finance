import {
    ArrowDownRight,
    ArrowRight,
    CircleDollarSign,
    Coins,
    Flag,
    GraduationCap,
    Landmark,
    Plus,
    ReceiptText,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { AppShell, Button, PageHeader, Progress } from '@/components/app-shell';
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
import type { Asset, Goal, Summary } from '@/types/finance';

type Allocation = { label: string; value: number; percent: number };
type MonthlyPlan = {
    incomeSources: {
        label: string;
        currency: string;
        nativeAmount: number;
        amount: number;
    }[];
    expenseCategories: { label: string; amount: number }[];
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
        kind: 'emergency' | 'goal' | 'investment';
    }[];
    plannedTotal: number;
    unallocated: number;
    source: 'saved_plan' | 'starter_template';
};
type AttentionItem = {
    key: string;
    title: string;
    reason: string;
    actionUrl: string;
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
    assets,
    assetAllocation,
    currencyExposure,
    goals,
    asOf,
    monthlyPlan,
    wealthTrend,
    dataFreshness,
    attentionQueue = [],
}: {
    summary: Summary;
    assets: Asset[];
    assetAllocation: Allocation[];
    currencyExposure: Allocation[];
    goals: Goal[];
    asOf: string;
    monthlyPlan: MonthlyPlan;
    wealthTrend: { asOf: string; netWorth: number }[];
    dataFreshness?: {
        cashFlowSource?: string;
        lastUpdated?: string | null;
        demoDataWarning?: boolean;
    };
    attentionQueue?: AttentionItem[];
}) {
    const [showNetWorthDetail, setShowNetWorthDetail] = useState(false);
    const wealthGroups = useMemo(() => buildWealthGroups(assets), [assets]);
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

            <div className="grid gap-4 xl:grid-cols-[1.45fr_0.85fr]">
                <Card className="bg-primary text-primary-foreground [--card-spacing:--spacing(6)]">
                    <CardHeader>
                        <CardTitle className="text-primary-foreground/70">
                            Total net worth
                        </CardTitle>
                        <CardDescription className="text-primary-foreground/60">
                            Everything you own, minus active liabilities
                        </CardDescription>
                        <CardAction>
                            <Badge variant="secondary">As of {asOf}</Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent>
                        <button
                            type="button"
                            className="text-left text-4xl font-semibold tracking-tight sm:text-5xl"
                            onClick={() => setShowNetWorthDetail(true)}
                        >
                            {formatEGP(summary.netWorth)}
                        </button>
                        <div className="mt-8 flex h-3 overflow-hidden rounded-full bg-primary-foreground/10">
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
                                    <div className="flex items-center gap-2 text-xs text-primary-foreground/60">
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
                                    <p className="mt-1 text-sm font-semibold">
                                        {formatCompactEGP(group.value)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                    <CardFooter className="grid gap-4 border-primary-foreground/10 bg-primary-foreground/5 sm:grid-cols-3">
                        <HeroMetric
                            label="Total assets"
                            value={formatCompactEGP(summary.totalAssets ?? 0)}
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
                        <CardTitle>This month's breathing room</CardTitle>
                        <CardDescription>
                            What remains after expenses and obligations
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
                                    ? 'Available'
                                    : 'Over budget'}
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
                            {summary.income > 0
                                ? `${Math.max(0, summary.savingsRate ?? (summary.freeCashFlow / summary.income) * 100).toFixed(1)}% of income is still yours to direct.`
                                : 'Add an income source to start planning this month.'}
                        </p>
                        <div className="mt-7 grid grid-cols-2 gap-3">
                            <SmallMetric
                                icon={CircleDollarSign}
                                label="Income"
                                value={formatCompactEGP(summary.income)}
                            />
                            <SmallMetric
                                icon={ReceiptText}
                                label="Expenses"
                                value={formatCompactEGP(summary.expenses)}
                            />
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
                            label="Income"
                            value={monthlyPlan?.income ?? summary.income}
                            items={(monthlyPlan?.incomeSources ?? []).map(
                                (source) => ({
                                    label: source.label,
                                    value: formatSourceAmount(source),
                                }),
                            )}
                            empty="Add salary, freelance work, or another source"
                        />
                        <FlowArrow />
                        <FlowStep
                            icon={ReceiptText}
                            label="Monthly expenses"
                            value={monthlyPlan?.expenses ?? summary.expenses}
                            items={(monthlyPlan?.expenseCategories ?? []).map(
                                (expense) => ({
                                    label: expense.label,
                                    value: formatCompactEGP(expense.amount),
                                }),
                            )}
                            empty="Add your recurring and flexible expenses"
                        />
                        <FlowArrow />
                        <FlowStep
                            icon={ShieldCheck}
                            label="Emergency contribution"
                            value={
                                monthlyPlan?.allocationItems.find(
                                    (item) => item.kind === 'emergency',
                                )?.amount ?? 0
                            }
                            items={[
                                {
                                    label: 'Remaining gap',
                                    value: formatCompactEGP(
                                        monthlyPlan?.emergencyGap ?? 0,
                                    ),
                                },
                            ]}
                            empty="Your reserve is already covered"
                        />
                        <FlowArrow />
                        <FlowStep
                            icon={TrendingUp}
                            label="Ready to allocate"
                            value={Math.max(
                                0,
                                (monthlyPlan?.freeCashFlow ??
                                    summary.freeCashFlow) -
                                    (monthlyPlan?.allocationItems.find(
                                        (item) => item.kind === 'emergency',
                                    )?.amount ?? 0),
                            )}
                            items={[
                                {
                                    label: 'Goals + investments',
                                    value: 'Give every pound a job',
                                },
                            ]}
                            empty="Income is fully used this month"
                        />
                    </CardContent>
                </Card>
            </section>

            <div className="mt-6 grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
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
                                ? 'Your saved plan for this month'
                                : 'A starter split until you save your own plan'}
                        </CardDescription>
                        <CardAction>
                            <Badge variant="outline">
                                {monthlyPlan?.source === 'saved_plan'
                                    ? 'Saved plan'
                                    : 'Flexible template'}
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
                                    <div key={`${item.kind}-${item.label}`}>
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
                                                        {allocationKindLabel(
                                                            item.kind,
                                                        )}
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
                <div className="grid gap-4 lg:grid-cols-2">
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

            <div className="mt-6 grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
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
                            label="Active liabilities"
                            value={-(summary.liabilities ?? 0)}
                        />
                        <CalculationRow
                            label="Net worth"
                            value={summary.netWorth}
                            total
                        />
                        <p className="text-xs leading-5 text-muted-foreground">
                            Goal reservations change what is available to
                            invest, but they do not reduce net worth because you
                            still own the underlying asset.
                        </p>
                    </div>
                </SheetContent>
            </Sheet>
        </AppShell>
    );
}

function buildWealthGroups(assets: Asset[]) {
    const total = Math.max(
        1,
        assets.reduce((sum, asset) => sum + asset.currentValue, 0),
    );
    const cashEgp = assets
        .filter(
            (asset) =>
                asset.currency === 'EGP' &&
                asset.type.toLowerCase().includes('cash'),
        )
        .reduce((sum, asset) => sum + asset.currentValue, 0);
    const usd = assets
        .filter((asset) => asset.currency === 'USD')
        .reduce((sum, asset) => sum + asset.currentValue, 0);
    const gold = assets
        .filter(
            (asset) =>
                asset.currency.toLowerCase() === 'gold' ||
                asset.type.toLowerCase().includes('gold'),
        )
        .reduce((sum, asset) => sum + asset.currentValue, 0);
    const investments = Math.max(0, total - cashEgp - usd - gold);

    return [
        { label: 'EGP cash', value: cashEgp },
        { label: 'US dollars', value: usd },
        { label: 'Gold', value: gold },
        { label: 'Investments', value: investments },
    ]
        .filter((group) => group.value > 0)
        .map((group) => ({
            ...group,
            percent: (group.value / total) * 100,
        }));
}

function sourceLabel(source?: string) {
    if (!source || source === 'ledger_incomplete') {
        return 'Cash flow needs setup';
    }

    return source === 'confirmed_ledger'
        ? 'Using confirmed transactions'
        : `Using ${source.replaceAll('_', ' ')}`;
}

function formatSourceAmount(source: MonthlyPlan['incomeSources'][number]) {
    if (source.currency === 'USD') {
        return `${source.nativeAmount.toLocaleString('en-EG')} USD · ${formatCompactEGP(source.amount)}`;
    }

    return formatCompactEGP(source.amount);
}

function allocationKindLabel(
    kind: MonthlyPlan['allocationItems'][number]['kind'],
) {
    return {
        emergency: 'Safety first',
        goal: 'Near-term goal',
        investment: 'Long-term growth',
    }[kind];
}

function HeroMetric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-primary-foreground/60">{label}</p>
            <p className="mt-1 text-sm font-semibold text-primary-foreground">
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
