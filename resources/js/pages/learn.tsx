import {
    ArrowRight,
    BookOpen,
    ChevronRight,
    CircleCheck,
    CircleHelp,
    Coins,
    Database,
    Flag,
    Gauge,
    Landmark,
    ListChecks,
    ShieldCheck,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
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
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';

type GuideStep = {
    number: string;
    title: string;
    description: string;
    href: string;
    action: string;
    icon: LucideIcon;
};

type GuideSection = {
    title: string;
    description: string;
    href: string;
    page: string;
    icon: LucideIcon;
    fields: string[];
    relation: string;
    outcome: string;
};

const gettingStarted: GuideStep[] = [
    {
        number: '01',
        title: 'Add what you own',
        description:
            'Start with cash, USD, gold, funds, shares, and any other asset you can value today.',
        href: '/assets',
        action: 'Add assets',
        icon: WalletCards,
    },
    {
        number: '02',
        title: 'Set your monthly flow',
        description:
            'Record each income source and your monthly expenses. USD entries keep their original value and an EGP equivalent.',
        href: '/cash-flow',
        action: 'Add income & expenses',
        icon: Gauge,
    },
    {
        number: '03',
        title: 'Give the surplus a job',
        description:
            'Use the monthly plan to reserve emergency cash, fund goals, and invest what remains.',
        href: '/allocations',
        action: 'Make a monthly plan',
        icon: ListChecks,
    },
    {
        number: '04',
        title: 'Connect a real goal',
        description:
            'Create a phone, car, or other goal, then link its contribution to the assets or buckets that hold the money.',
        href: '/goals',
        action: 'Create a goal',
        icon: Flag,
    },
];

const basicSections: GuideSection[] = [
    {
        title: 'Dashboard',
        description: 'Your monthly financial control room.',
        href: '/',
        page: 'Dashboard',
        icon: Landmark,
        fields: [
            'Net worth: everything you own minus what you owe.',
            'This month’s breathing room: income after expenses.',
            'Emergency coverage: how many months your reserve can cover.',
        ],
        relation:
            'It reads from assets, income & expenses, monthly plans, buckets, and goals. It is the best place to decide what to do next—not the place where every record is created.',
        outcome:
            'You can see your position, spot the next important action, and jump directly to the right page.',
    },
    {
        title: 'What you own',
        description: 'The real items behind your wealth.',
        href: '/assets',
        page: 'What you own',
        icon: WalletCards,
        fields: [
            'Name and type: cash, USD, gold, shares, fund, or a custom asset.',
            'Current value and currency: what it is worth today.',
            'Purpose allocation: how much of this asset supports safety, a goal, or investing.',
        ],
        relation:
            'Assets feed net worth and can be assigned to purpose buckets. A single asset can support more than one goal or purpose.',
        outcome:
            'You know where your money physically lives, not only what it is planned for.',
    },
    {
        title: 'Income & expenses',
        description: 'The monthly cash-flow input.',
        href: '/cash-flow',
        page: 'Income & expenses',
        icon: Gauge,
        fields: [
            'Type: income or expense.',
            'Amount and currency: EGP or USD; USD needs an exchange rate for your EGP total.',
            'Category and date: makes your monthly total understandable.',
        ],
        relation:
            'These entries set free cash flow. The monthly plan uses that remaining amount as its ceiling.',
        outcome:
            'You can answer: after this month’s essentials, how much is actually free?',
    },
    {
        title: 'Monthly plan',
        description: 'How the remaining money is divided before it disappears.',
        href: '/allocations',
        page: 'Monthly plan',
        icon: ListChecks,
        fields: [
            'Monthly income and expenses: the plan’s available cash calculation.',
            'Purpose bucket and amount: where each piece of the surplus should go.',
            'Plan total: must stay within your free cash flow.',
        ],
        relation:
            'The plan funds emergency reserves, goals, and investments through purpose buckets. It is a plan; asset allocations show where the money actually sits.',
        outcome:
            'Every remaining pound gets a clear job before the month is over.',
    },
    {
        title: 'Goals',
        description:
            'Targets that are funded by real money, not wishful totals.',
        href: '/goals',
        page: 'Goals',
        icon: Flag,
        fields: [
            'Target amount and deadline: what you want and when.',
            'Planned contribution: the normal amount to set aside each month.',
            'Funding sources: assets or buckets that will be used for the goal.',
        ],
        relation:
            'A goal can draw from a fixed-income fund, cash, gold, or any other asset allocation. Changing a month’s contribution does not erase the relationship.',
        outcome:
            'You can see whether the target is on track and exactly which money backs it.',
    },
];

const advancedSections: GuideSection[] = [
    {
        title: 'Purpose buckets',
        description: 'Labels for why money exists.',
        href: '/buckets',
        page: 'Purpose buckets',
        icon: Database,
        fields: [
            'Name: for example Emergency reserve, Car, or Long-term investing.',
            'Type: safety, goal, investment, or another purpose.',
            'Target: an optional amount that makes progress measurable.',
        ],
        relation:
            'Buckets connect plans, goals, and asset allocations. They answer “what is this money for?” while assets answer “where is it held?”',
        outcome:
            'You can separate money for different jobs without pretending it must live in separate accounts.',
    },
    {
        title: 'Financial policy & monthly review',
        description: 'Your rules and your monthly feedback loop.',
        href: '/settings/financial',
        page: 'Financial policy',
        icon: ShieldCheck,
        fields: [
            'Emergency reserve months: normally 3–6 months of essential expenses.',
            'Monthly limits: guardrails for debt and decisions.',
            'Monthly review: save what actually happened and the decision you made.',
        ],
        relation:
            'Policy sets the emergency target shown on the dashboard. Monthly reviews compare the plan with actual confirmed activity.',
        outcome:
            'Your system becomes consistent across months instead of reacting to every new expense.',
    },
    {
        title: 'Ledger, categories & imports',
        description: 'A precise record for people who want reconciliation.',
        href: '/ledger',
        page: 'Ledger & imports',
        icon: BookOpen,
        fields: [
            'Accounts: the places transactions move through.',
            'Transactions: confirmed entries become the source for a reviewed month.',
            'Import queue: CSV rows stay reviewable until you accept or reject them.',
        ],
        relation:
            'When a month has confirmed ledger entries, they become the preferred source instead of the simpler cash-flow entries.',
        outcome:
            'You get auditable totals and can reconcile them with your accounts.',
    },
    {
        title: 'Valuations, FX & reconciliation',
        description: 'Keep mixed-currency and investment values trustworthy.',
        href: '/valuations',
        page: 'Valuation history',
        icon: Coins,
        fields: [
            'Valuation: a dated current value for an asset.',
            'FX rate: converts USD and other currencies into EGP reporting.',
            'Reconciliation: finds gaps between purposes, assets, and records.',
        ],
        relation:
            'These tools keep the dashboard’s EGP totals, net worth trend, and asset-purpose backing reliable over time.',
        outcome:
            'You can trust the numbers even when values move or currencies change.',
    },
];

export default function Learn() {
    return (
        <AppShell title="Learn the system">
            <PageHeader
                eyebrow="GUIDED ONBOARDING"
                title="Learn your money system"
                description="Start with the essentials, then go deeper only when you need more control. Every lesson links to the exact page where you can use it."
                action={
                    <Button href="/" variant="ghost">
                        Go to dashboard
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                }
            />

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1.7fr)_minmax(280px,0.8fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle>Start here: set up the system</CardTitle>
                        <CardDescription>
                            Follow this order once. After that, the dashboard
                            becomes your daily and monthly starting point.
                        </CardDescription>
                        <CardAction>
                            <Badge variant="secondary">4 steps</Badge>
                        </CardAction>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3">
                        {gettingStarted.map((step) => {
                            const Icon = step.icon;

                            return (
                                <div
                                    key={step.number}
                                    className="flex gap-3 rounded-lg border border-border p-3"
                                >
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-semibold text-secondary-foreground">
                                        {step.number}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Icon className="text-muted-foreground" />
                                            <p className="font-medium">
                                                {step.title}
                                            </p>
                                        </div>
                                        <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                            {step.description}
                                        </p>
                                    </div>
                                    <Button
                                        href={step.href}
                                        variant="outline"
                                        size="sm"
                                    >
                                        {step.action}
                                        <ChevronRight data-icon="inline-end" />
                                    </Button>
                                </div>
                            );
                        })}
                    </CardContent>
                    <CardFooter className="gap-2 text-sm text-muted-foreground">
                        <CircleCheck className="text-primary" />
                        You do not need to use every advanced tool to get value
                        from the app.
                    </CardFooter>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>The simple mental model</CardTitle>
                        <CardDescription>
                            Keep these three questions separate.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <ModelLine label="What do I own?" detail="Assets" />
                        <ModelLine
                            label="What is it for?"
                            detail="Purpose buckets"
                        />
                        <ModelLine
                            label="What happens this month?"
                            detail="Income, expenses, and plan"
                        />
                    </CardContent>
                    <CardFooter className="flex-col items-start gap-2 text-sm text-muted-foreground">
                        <CircleHelp className="text-primary" />
                        Goals sit across the model: they have a purpose, a
                        monthly plan, and real assets backing them.
                    </CardFooter>
                </Card>
            </div>

            <section className="mt-8">
                <SectionHeading
                    badge="BASIC"
                    title="Use these pages first"
                    description="This is the complete everyday workflow. You can run your financial life from these pages and the dashboard."
                />
                <GuideList sections={basicSections} defaultOpen />
            </section>

            <section className="mt-8">
                <SectionHeading
                    badge="ADVANCED"
                    title="Add control when you need it"
                    description="Use these tools for detailed tracking, historical accuracy, and reviewing financial decisions."
                />
                <GuideList sections={advancedSections} />
            </section>
        </AppShell>
    );
}

function SectionHeading({
    badge,
    title,
    description,
}: {
    badge: string;
    title: string;
    description: string;
}) {
    return (
        <div className="mb-4 flex flex-col gap-2">
            <Badge variant="outline" className="w-fit">
                {badge}
            </Badge>
            <h2 className="text-xl font-semibold tracking-tight">{title}</h2>
            <p className="max-w-3xl text-sm leading-6 text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function GuideList({
    sections,
    defaultOpen = false,
}: {
    sections: GuideSection[];
    defaultOpen?: boolean;
}) {
    return (
        <div className="flex flex-col gap-3">
            {sections.map((section) => {
                const Icon = section.icon;

                return (
                    <Card key={section.href} size="sm">
                        <Collapsible defaultOpen={defaultOpen}>
                            <CardHeader>
                                <div className="flex items-start gap-3">
                                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground">
                                        <Icon />
                                    </div>
                                    <div>
                                        <CardTitle>{section.title}</CardTitle>
                                        <CardDescription>
                                            {section.description}
                                        </CardDescription>
                                    </div>
                                </div>
                                <CardAction className="flex items-center gap-2">
                                    <Button
                                        href={section.href}
                                        variant="outline"
                                        size="sm"
                                    >
                                        Open {section.page}
                                        <ArrowRight data-icon="inline-end" />
                                    </Button>
                                    <CollapsibleTrigger
                                        render={
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                aria-label={`Show ${section.title} guide`}
                                            />
                                        }
                                    >
                                        Details
                                        <ChevronRight data-icon="inline-end" />
                                    </CollapsibleTrigger>
                                </CardAction>
                            </CardHeader>
                            <CollapsibleContent>
                                <CardContent className="flex flex-col gap-4 pt-1">
                                    <Separator />
                                    <div className="grid gap-4 lg:grid-cols-3">
                                        <GuideDetail title="What the main fields mean">
                                            <ul className="flex list-disc flex-col gap-2 pl-4 text-sm leading-6 text-muted-foreground">
                                                {section.fields.map((field) => (
                                                    <li key={field}>{field}</li>
                                                ))}
                                            </ul>
                                        </GuideDetail>
                                        <GuideDetail title="How it connects">
                                            <p>{section.relation}</p>
                                        </GuideDetail>
                                        <GuideDetail title="What you can do after">
                                            <p>{section.outcome}</p>
                                        </GuideDetail>
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>
                );
            })}
        </div>
    );
}

function GuideDetail({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm font-medium">{title}</p>
            <div className="text-sm leading-6 text-muted-foreground">
                {children}
            </div>
        </div>
    );
}

function ModelLine({ label, detail }: { label: string; detail: string }) {
    return (
        <div className="flex flex-col gap-1">
            <p className="text-sm font-medium">{label}</p>
            <Progress value={100} />
            <p className="text-sm text-muted-foreground">{detail}</p>
        </div>
    );
}
