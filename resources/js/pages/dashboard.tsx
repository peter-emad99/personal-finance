import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
    Progress,
} from '@/components/app-shell';
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
    '#7c8cf8',
    '#f6c453',
    '#79c2d0',
    '#f08da1',
    '#a6d98c',
    '#9a8ce3',
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
}) {
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
            <div className="grid gap-4 xl:grid-cols-[1.65fr_1fr]">
                <Card className="overflow-hidden border-0 bg-[#1c2a45] text-white">
                    <div className="relative p-6 sm:p-8">
                        <div className="absolute -top-28 -right-20 h-72 w-72 rounded-full border-[32px] border-[#5a6dad]/15" />
                        <div className="absolute -right-4 -bottom-36 h-72 w-72 rounded-full border-[32px] border-[#a8b7ff]/10" />
                        <div className="relative">
                            <div className="flex items-center justify-between">
                                <p className="text-xs font-semibold tracking-[0.16em] text-[#aeb9d1] uppercase">
                                    Net worth
                                </p>
                                <Badge className="rounded-full border-0 bg-[#a8b7ff]/15 px-3 py-1 text-xs text-[#c7d0ff]">
                                    As of {asOf}
                                </Badge>
                            </div>
                            <p className="mt-4 text-4xl font-semibold tracking-tight sm:text-5xl">
                                {formatEGP(summary.netWorth)}
                            </p>
                            <div className="mt-8 grid grid-cols-2 gap-5 border-t border-white/10 pt-5 sm:grid-cols-5">
                                <Metric
                                    label="Investable"
                                    value={formatCompactEGP(
                                        summary.investableNetWorth,
                                    )}
                                />
                                <Metric
                                    label="Liquid"
                                    value={formatCompactEGP(
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
                            <p className="text-xs font-semibold tracking-[0.14em] text-[#8993a3] uppercase">
                                Emergency fund
                            </p>
                            <p className="mt-3 text-3xl font-semibold text-[#202a39]">
                                {summary.emergencyCoverageMonths}{' '}
                                <span className="text-base font-medium text-[#8993a3]">
                                    months
                                </span>
                            </p>
                        </div>
                        <Badge
                            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${summary.emergencyCoverageMonths >= 6 ? 'bg-[#eaf8ef] text-[#328654]' : 'bg-[#fff4df] text-[#a46e14]'}`}
                        >
                            {summary.emergencyCoverageMonths >= 6
                                ? 'On baseline'
                                : 'Needs attention'}
                        </Badge>
                    </div>
                    <Progress
                        value={(summary.emergencyCoverageMonths / 6) * 100}
                        color="#63bf85"
                    />
                    <div className="mt-3 flex justify-between text-xs text-[#8a94a3]">
                        <span>{formatEGP(summary.emergencyFund)} saved</span>
                        <span>6 months target</span>
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
                    <div className="grid gap-3 p-5 sm:grid-cols-4">
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
                            label="Income committed"
                            value={`${wealthMetrics.committedIncomeRate}%`}
                        />
                        <MiniMetric
                            label="Debt / net worth"
                            value={`${wealthMetrics.debtToNetWorth}%`}
                        />
                    </div>
                    <div className="border-t border-[#eef0f4] px-5 py-4">
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
                                            className="w-full rounded-t-md bg-[#a8b7ff]"
                                            style={{
                                                height: `${Math.max(8, (point.netWorth / max) * 72)}px`,
                                            }}
                                            title={`${point.asOf}: ${formatEGP(point.netWorth)}`}
                                        />
                                        <span className="text-[9px] text-[#9aa3b1]">
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
                    <div className="space-y-3 p-5">
                        <div className="flex items-center justify-between rounded-xl bg-[#fff8ef] px-3.5 py-3">
                            <span className="text-xs font-semibold text-[#80602a]">
                                Recurring commitments
                            </span>
                            <span className="text-sm font-semibold text-[#a46e14]">
                                {formatCompactEGP(
                                    summary.recurringCommitments ?? 0,
                                )}
                                /mo
                            </span>
                        </div>
                        <div className="flex items-center justify-between rounded-xl bg-[#fff0f1] px-3.5 py-3">
                            <span className="text-xs font-semibold text-[#95505d]">
                                Active liabilities
                            </span>
                            <span className="text-sm font-semibold text-[#c65365]">
                                {formatCompactEGP(summary.liabilities ?? 0)}
                            </span>
                        </div>
                        {recurringCommitments.slice(0, 3).map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between border-b border-[#eef0f4] pb-2 text-xs"
                            >
                                <span className="text-[#697589]">
                                    {item.name}
                                </span>
                                <span className="font-semibold text-[#4d5a6d]">
                                    {formatCompactEGP(item.monthlyAmount)}
                                </span>
                            </div>
                        ))}
                        {liabilities.slice(0, 2).map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between text-xs"
                            >
                                <span className="text-[#697589]">
                                    {item.name}
                                </span>
                                <span className="font-semibold text-[#c65365]">
                                    {formatCompactEGP(item.balance)}
                                </span>
                            </div>
                        ))}
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
                    <div className="space-y-4 p-5">
                        {assetAllocation.map((item, index) => (
                            <div key={item.label}>
                                <div className="mb-1.5 flex items-center justify-between text-xs">
                                    <span className="flex items-center gap-2 font-medium text-[#4f5b6e]">
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
                                    <span className="font-semibold text-[#273246]">
                                        {item.percent}%{' '}
                                        <span className="ml-2 font-normal text-[#929aa8]">
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
                            <p className="text-sm text-[#8993a3]">
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
                    <div className="space-y-4 p-5">
                        {Object.entries(targetAllocation).map(
                            ([label, target], index) => {
                                const current =
                                    assetAllocation.find(
                                        (item) => item.label === label,
                                    )?.percent ?? 0;
                                const delta = current - target;

                                return (
                                    <div key={label}>
                                        <div className="mb-1.5 flex items-center justify-between text-xs">
                                            <span className="font-medium text-[#4f5b6e]">
                                                {label}
                                            </span>
                                            <span
                                                className={
                                                    delta > 3
                                                        ? 'font-semibold text-[#bd7a1b]'
                                                        : 'text-[#8993a3]'
                                                }
                                            >
                                                {current}%{' '}
                                                <span className="text-[#b3bac5]">
                                                    / {target}%
                                                </span>
                                            </span>
                                        </div>
                                        <div className="relative h-2 rounded-full bg-[#edf0f5]">
                                            <div
                                                className="h-full rounded-full"
                                                style={{
                                                    width: `${Math.min(100, current)}%`,
                                                    backgroundColor:
                                                        colors[
                                                            index %
                                                                colors.length
                                                        ],
                                                }}
                                            />
                                            <span
                                                className="absolute -top-1 h-4 w-0.5 bg-[#273246]"
                                                style={{ left: `${target}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            },
                        )}
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Liquidity view"
                        meta="If you need money tomorrow"
                    />
                    <div className="space-y-3 p-5">
                        {liquidity.map((item, index) => (
                            <div
                                key={item.label}
                                className="flex items-center justify-between rounded-xl bg-[#f8f9fb] px-3.5 py-3"
                            >
                                <div>
                                    <p className="text-xs font-semibold text-[#4d5a6d]">
                                        {item.label.replaceAll('_', ' ')}
                                    </p>
                                    <p className="mt-1 text-[11px] text-[#9aa3b0]">
                                        {index === 0
                                            ? 'Cash and current accounts'
                                            : index === 1
                                              ? 'Redeemable quickly'
                                              : index === 2
                                                ? 'May take longer to sell'
                                                : 'Not readily accessible'}
                                    </p>
                                </div>
                                <span className="text-sm font-semibold text-[#273246]">
                                    {formatCompactEGP(item.value)}
                                </span>
                            </div>
                        ))}
                        {largestCurrency && (
                            <div className="border-t border-[#eef0f4] pt-4">
                                <p className="text-[11px] tracking-wider text-[#9aa3b1] uppercase">
                                    Largest currency exposure
                                </p>
                                <p className="mt-1 text-sm font-semibold text-[#4d5a6d]">
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
                    <div className="divide-y divide-[#eef0f4]">
                        {goals.slice(0, 3).map((goal) => (
                            <div key={goal.id} className="p-5">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 className="text-sm font-semibold text-[#273246]">
                                            {goal.name}
                                        </h3>
                                        <p className="mt-1 text-xs text-[#8993a3]">
                                            {goal.deadline
                                                ? `Due ${new Date(goal.deadline).toLocaleDateString('en-EG', { month: 'short', year: 'numeric' })}`
                                                : 'No deadline set'}{' '}
                                            · {goal.monthsRemaining ?? '—'}{' '}
                                            months left
                                        </p>
                                    </div>
                                    <Badge
                                        className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${goal.onTrack ? 'bg-[#eaf8ef] text-[#328654]' : 'bg-[#fff1e8] text-[#b76a2b]'}`}
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
                                                    ? '#63bf85'
                                                    : '#f0ad59'
                                            }
                                        />
                                    </div>
                                    <span className="text-xs font-semibold text-[#4d5a6d]">
                                        {goal.fundingPercent}%
                                    </span>
                                </div>
                                <div className="mt-3 flex flex-wrap justify-between gap-2 text-xs">
                                    <span className="text-[#8993a3]">
                                        {formatEGP(goal.allocatedAmount)}{' '}
                                        allocated
                                    </span>
                                    <span className="font-semibold text-[#4d5a6d]">
                                        {formatCompactEGP(
                                            goal.requiredMonthlyContribution,
                                        )}
                                        /month needed
                                    </span>
                                </div>
                                {!goal.onTrack && (
                                    <p className="mt-3 rounded-lg bg-[#fff8ef] px-3 py-2 text-xs text-[#a46e14]">
                                        Off track by{' '}
                                        {formatCompactEGP(goal.gapPerMonth)}
                                        /month against current free cash flow.
                                    </p>
                                )}
                            </div>
                        ))}
                        {goals.length === 0 && (
                            <p className="p-5 text-sm text-[#8993a3]">
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
                    <div className="space-y-3 p-5">
                        {insights.length ? (
                            insights.map((insight, index) => (
                                <div
                                    key={insight}
                                    className="flex gap-3 rounded-xl bg-[#f8f9fb] p-3.5"
                                >
                                    <span
                                        className={`mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs ${index === 0 ? 'bg-[#fff0d8] text-[#a46e14]' : 'bg-[#e9edff] text-[#6576d2]'}`}
                                    >
                                        {index === 0 ? '!' : 'i'}
                                    </span>
                                    <p className="text-xs leading-5 text-[#58657a]">
                                        {insight}
                                    </p>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-[#8993a3]">
                                No signals yet. Keep your data current.
                            </p>
                        )}
                        <div className="mt-5 rounded-xl border border-dashed border-[#d9deea] p-4">
                            <p className="text-xs font-semibold text-[#4d5a6d]">
                                What this dashboard is for
                            </p>
                            <p className="mt-1 text-xs leading-5 text-[#8993a3]">
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
                                className="rounded-xl border border-[#edf0f4] p-3.5"
                            >
                                <span
                                    className="mb-3 block h-1.5 w-8 rounded-full"
                                    style={{ backgroundColor: bucket.color }}
                                />
                                <p className="text-xs font-semibold text-[#4d5a6d]">
                                    {bucket.name}
                                </p>
                                <p className="mt-2 text-lg font-semibold text-[#273246]">
                                    {formatCompactEGP(bucket.currentAmount)}
                                </p>
                                <p className="mt-1 text-[11px] text-[#9aa3b1]">
                                    {bucket.goalName ??
                                        bucket.purpose ??
                                        'Flexible allocation'}
                                </p>
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
        </AppShell>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-[11px] text-[#aeb9d1]">{label}</p>
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
        <div className="rounded-xl bg-[#f8f9fb] p-3">
            <p className="text-[11px] text-[#8993a3]">{label}</p>
            <p
                className={`mt-1 text-sm font-semibold ${positive ? 'text-[#328654]' : 'text-[#273246]'}`}
            >
                {value}
            </p>
        </div>
    );
}
