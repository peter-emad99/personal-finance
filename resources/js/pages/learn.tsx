import {
    ArrowRight,
    BookOpen,
    ChevronRight,
    CircleCheck,
    CircleHelp,
    Coins,
    CreditCard,
    Database,
    Flag,
    Gauge,
    Landmark,
    Leaf,
    ListChecks,
    RefreshCw,
    ShieldCheck,
    Sparkles,
    TrendingUp,
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
type HabitCard = {
    title: string;
    arabicTitle: string;
    description: string;
    arabicDescription: string;
    action: string;
    href: string;
    icon: LucideIcon;
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

const habitCards: HabitCard[] = [
    {
        title: 'Kakeibo · Notice before you spend',
        arabicTitle: 'كايكيبو · لاحظ قبل أن تنفق',
        description:
            'Record the month, then ask what was necessary, what was useful, and what can change next time.',
        arabicDescription:
            'سجّل الشهر، ثم اسأل: ما الضروري؟ ما المفيد؟ وما الشيء الصغير الذي يمكن تغييره؟',
        action: 'Review the month',
        href: '/monthly-review',
        icon: BookOpen,
    },
    {
        title: 'Chokin · Save first',
        arabicTitle: 'تشوكين · ادّخر أولاً',
        description:
            'Give the emergency fund, goals, and investments a job before lifestyle spending expands.',
        arabicDescription:
            'أعطِ الطوارئ والأهداف والاستثمارات أولوية قبل أن تتوسع مصروفات أسلوب الحياة.',
        action: 'Set the monthly plan',
        href: '/allocations',
        icon: ShieldCheck,
    },
    {
        title: 'Kaizen · Improve one small thing',
        arabicTitle: 'كايزن · حسّن شيئاً صغيراً',
        description:
            'Choose one sustainable improvement each month instead of relying on a dramatic reset.',
        arabicDescription:
            'اختر تحسيناً صغيراً ومستداماً كل شهر بدلاً من تغيير كبير يصعب الاستمرار عليه.',
        action: 'Write a decision',
        href: '/decision-journal',
        icon: RefreshCw,
    },
    {
        title: 'Mottainai · Use what you have',
        arabicTitle: 'موتاي ناي · استخدم ما لديك',
        description:
            'Before buying, check whether something you already own can solve the need.',
        arabicDescription:
            'قبل الشراء، تحقّق هل يوجد شيء تملكه بالفعل يمكنه حل المشكلة.',
        action: 'Review commitments',
        href: '/commitments',
        icon: Leaf,
    },
    {
        title: 'Hansei · Reflect and adjust',
        arabicTitle: 'هانسي · راجع وعدّل',
        description:
            'Look at the result without shame, keep the lesson, and make the next month easier.',
        arabicDescription:
            'راجع النتيجة بدون جلد للذات، احتفظ بالدرس، واجعل الشهر القادم أسهل.',
        action: 'Open the journal',
        href: '/decision-journal',
        icon: Sparkles,
    },
    {
        title: 'Hara Hachi Bu · Leave room',
        arabicTitle: 'هارا هاتشي بو · اترك مساحة',
        description:
            'Do not consume every pound. Keep a deliberate margin for safety, goals, and future choices.',
        arabicDescription:
            'لا تستهلك كل جنيه. اترك هامشاً مقصوداً للأمان والأهداف والاختيارات القادمة.',
        action: 'Plan the surplus',
        href: '/allocations',
        icon: Coins,
    },
    {
        title: 'Ikigai · Give money a reason',
        arabicTitle: 'إيكيغاي · أعطِ المال سبباً',
        description:
            'A goal is stronger when it supports a life you actually value, not only a larger number.',
        arabicDescription:
            'يصبح الهدف أقوى عندما يخدم حياة تهمك فعلاً، وليس مجرد رقم أكبر.',
        action: 'Define a goal',
        href: '/goals',
        icon: Flag,
    },
    {
        title: 'Wabi-sabi · Prefer useful and lasting',
        arabicTitle: 'وابي سابي · اختر المفيد والمستمر',
        description:
            'A functional item does not need replacing just because something newer exists.',
        arabicDescription:
            'الشيء العملي لا يحتاج إلى استبدال لمجرد ظهور شيء أحدث.',
        action: 'Review what you own',
        href: '/assets',
        icon: Leaf,
    },
    {
        title: 'Osouji · Clean your finances',
        arabicTitle: 'أوسوجي · نظّف أموالك',
        description:
            'Periodically remove unused subscriptions, stale records, forgotten debts, and unclear allocations.',
        arabicDescription:
            'نظّف دورياً الاشتراكات غير المستخدمة والسجلات القديمة والديون والتخصيصات غير الواضحة.',
        action: 'Review commitments',
        href: '/commitments',
        icon: Database,
    },
    {
        title: 'Shinrin-yoku · Enjoy without buying',
        arabicTitle: 'شينرين يوكو · استمتع بدون شراء',
        description:
            'Keep free activities, relationships, movement, and nature available so spending is not your only source of relief.',
        arabicDescription:
            'احتفظ بأنشطة مجانية وعلاقات وحركة وطبيعة حتى لا يصبح الإنفاق مصدر الراحة الوحيد.',
        action: 'Write a personal rule',
        href: '/decision-journal',
        icon: BookOpen,
    },
];

type LearningBlock = {
    title: string;
    description: string;
    icon: LucideIcon;
    points: string[];
};

const wealthStages: LearningBlock[] = [
    {
        title: 'Stage 1 · Foundation',
        description:
            'Make the system stable before trying to optimise returns.',
        icon: ShieldCheck,
        points: [
            'Know your income, essential costs, commitments, liabilities, and available cash.',
            'Build an accessible emergency reserve using your configured months rule.',
            'Pay attention to high-interest debt and stop new spending from hiding the real cash flow.',
        ],
    },
    {
        title: 'Stage 2 · Growth',
        description:
            'Create a repeatable surplus and direct it toward meaningful goals and diversified investments.',
        icon: TrendingUp,
        points: [
            'Increase income where possible and reduce recurring waste without making life miserable.',
            'Fund goals in priority order so several goals do not all claim the same free cash flow.',
            'Invest consistently according to your own time horizon, liquidity needs, and risk policy.',
        ],
    },
    {
        title: 'Stage 3 · Freedom',
        description:
            'Your invested assets can support your chosen lifestyle without depending entirely on a job.',
        icon: Landmark,
        points: [
            'Estimate annual spending honestly; freedom is based on spending, not status or salary.',
            'Use a withdrawal-rate assumption as a planning estimate, not a promise of returns.',
            'Keep flexibility: taxes, inflation, fees, market declines, and sequence risk still matter.',
        ],
    },
];

const moneyFormulas: LearningBlock[] = [
    {
        title: 'Free cash flow',
        description: 'The amount available to direct after the month’s outflows.',
        icon: Gauge,
        points: [
            'Income − essential costs − lifestyle costs − commitments − debt payments − one-time expenses.',
            'This is the ceiling for the monthly plan; do not allocate more than it.',
            'A positive number is capacity, not permission to spend it all.',
        ],
    },
    {
        title: 'Emergency-fund target',
        description: 'A safety reserve based on essential monthly obligations.',
        icon: ShieldCheck,
        points: [
            'Essential monthly base × configured reserve months.',
            'Use accessible assets for this purpose; long-term or volatile assets may not be available when needed.',
            'When the target is complete, redirect new contributions to goals or investments.',
        ],
    },
    {
        title: 'Financial-freedom estimate',
        description: 'A simple estimate of the portfolio needed to support annual spending.',
        icon: CircleCheck,
        points: [
            'Annual spending ÷ withdrawal rate. At 4%, this is annual spending × 25.',
            'Example: 240,000 EGP annual spending ÷ 0.04 = 6,000,000 EGP planning target.',
            'The app labels this as an estimate because inflation, taxes, fees, returns, and bad market timing change the result.',
        ],
    },
];

const learningQuestions = [
    {
        question: 'What is Kakeibo exactly?',
        answer:
            'Kakeibo is a deliberate spending journal. Before or during the month, record what came in and what went out, then ask four questions: how much money is available, how much do you want to save, how much did you spend, and what will you change next month. In this app, Cash flow and Monthly review are the digital version; the important part is the reflection, not perfect data entry.',
    },
    {
        question: 'How do I start saving if I am broke?',
        answer:
            'Start with visibility, not a heroic percentage. Record the month, protect essentials, list commitments and minimum debt payments, stop one avoidable leak, and save a tiny fixed amount if possible. The first target can be one week of essential costs. When income rises or a debt ends, direct part of the new capacity to the reserve before lifestyle expands.',
    },
    {
        question: 'What are non-spending ways to enjoy life?',
        answer:
            'Walking, exercise, cooking, reading, learning, visiting family, volunteering, free community events, music, nature, and time away from the phone. Keep a short personal list in your Decision journal. The goal is not to remove joy; it is to stop every form of relief from becoming a purchase.',
    },
    {
        question: 'What is the difference between saving and investing?',
        answer:
            'Saving protects near-term needs and emergencies with accessible, lower-volatility money. Investing accepts uncertainty and time so money can grow for long-term goals. Do not put emergency money into an asset you may be forced to sell during a downturn.',
    },
    {
        question: 'Does the 4% rule guarantee financial freedom?',
        answer:
            'No. It is a historical planning shortcut for a diversified portfolio and a particular withdrawal period. The app uses it to show a target, not to promise that 4% will always be safe. Revisit the target when spending, inflation, taxes, health, family responsibilities, or market conditions change.',
    },
    {
        question: 'Why are planned, actual, and purpose different?',
        answer:
            'Planned means what you intended before the month. Actual means what confirmed ledger rows show happened. Purpose means what the money is reserved for. One bank account can hold money for several purposes, so the app keeps the physical asset, purpose bucket, and monthly movement separate.',
    },
    {
        question: 'Why does the app warn when a commitment changes?',
        answer:
            'A subscription or loan payment is not just a line in a list; it changes every future month’s free cash flow. When you close a review, the app saves a small snapshot of active obligations. Later it compares each item by itself and shows added, removed, renamed, or amount changes, plus the monthly capacity impact. You can then update the next plan with the new reality.',
    },
    {
        question: 'How should I read the debt payoff card?',
        answer:
            'Interest is the estimated cost for one month at the recorded annual rate. Principal is the payment left after that interest. The remaining-month estimate applies the same payment repeatedly to the current balance. It is a planning estimate, not a lender statement; fees, changing rates, missed payments, and early-settlement rules can change the result.',
    },
    {
        question: 'What do the dashboard warning thresholds mean?',
        answer:
            'They are personal prompts. Income and outflow thresholds compare the current month with the saved plan. The investment threshold compares your actual investment pace with the investing percentage configured in Monthly allocation rules. Lowering a threshold makes the app more sensitive; raising it reduces noise. The warnings do not block you or make a decision for you.',
    },
    {
        question: 'What happens when I confirm next month’s plan?',
        answer:
            'The app proposes income from the closed review, current recurring obligations, debt payments, emergency funding, goals, and investments. You see the numbers before saving. If the emergency target is already complete, one click redirects the suggested reserve slice to goals or investments. Your lesson is stored with the new plan so the next review starts with context.',
    },
    {
        question: 'How should I record a lender payment?',
        answer:
            'Open Liabilities, choose Record payment, and copy the statement values for total payment, principal, interest, fees, date, and balance after payment. The app checks that the components add up to the total. Recorded facts stay separate from the estimated payoff projection, while the extra-payment cards show what might happen if you pay more each month.',
    },
    {
        question: 'What does automatic preparation on close do?',
        answer:
            'It is an optional shortcut in Financial policy. When enabled, closing a review prepares the next month only if no plan exists for that month. It uses the same safe generator as the confirmation flow and never replaces a plan you already created. Leave it disabled if you want to review every proposal manually.',
    },
    {
        question: 'How do I read the twelve-month history?',
        answer:
            'Each month shows income, outflow, free cash flow, and investing. Bars are relative to the largest value in the displayed window. Use the history to notice direction and consistency, not to claim that market movement or net-worth change came from spending alone.',
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
            'Change watch: compare each active commitment and liability with the last closed review.',
            'Debt payoff picture: estimated interest, principal, and remaining months from the current payment.',
            'Warning thresholds: choose how large a plan difference should be before the dashboard prompts you, and how close investment pace must be to target.',
            'Next-month confirmation: review the proposal, choose goals or investments when the reserve is complete, and carry one spending lesson forward.',
        ],
        relation:
            'Policy sets the emergency target shown on the dashboard. Monthly reviews compare the plan with actual confirmed activity, while the closed snapshot gives the next review a baseline for obligation changes.',
        outcome:
            'Your system becomes consistent across months instead of reacting to every new expense, and you can see when a changed obligation reduces your free cash flow.',
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
    {
        title: 'Debt statements & payoff scenarios',
        description: 'Turn a balance into a lender-aware repayment picture.',
        href: '/liabilities',
        page: 'Liabilities',
        icon: CreditCard,
        fields: [
            'Payment record: total payment, principal, interest, fees, date, and balance after payment.',
            'Recorded totals: what your lender statements actually say over time.',
            'Extra monthly payment scenarios: compare payoff dates and interest savings without changing the real liability.',
        ],
        relation:
            'The current balance and rate drive the estimate; statement records add evidence. Scenarios are decisions to review, not automatic payments.',
        outcome:
            'You can see whether extra debt capacity is worth using and keep the estimate separate from lender facts.',
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
                    badge="THE COMPLETE LOOP · دورة المال"
                    title="How the app turns money into progress"
                    description="The app is not a collection of unrelated forms. Each area answers one question, and the result flows into the next decision."
                />
                <Card>
                    <CardContent className="grid gap-3 p-5 md:grid-cols-6">
                        {[
                            ['1', 'Know', 'Assets, debts, commitments'],
                            ['2', 'Measure', 'Income and actual outflows'],
                            ['3', 'Protect', 'Emergency reserve and minimums'],
                            ['4', 'Direct', 'Goals and investments'],
                            ['5', 'Review', 'Plan versus actual'],
                            ['6', 'Improve', 'Next month and one lesson'],
                        ].map(([number, title, detail]) => (
                            <div
                                key={number}
                                className="flex gap-3 md:flex-col"
                            >
                                <div className="grid size-8 shrink-0 place-items-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                                    {number}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">
                                        {title}
                                    </p>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                        {detail}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </section>

            <section className="mt-8">
                <SectionHeading
                    badge="THREE STAGES · ثلاث مراحل"
                    title="The wealth-building path"
                    description="You do not need to jump to investing first. Stabilise the base, create repeatable surplus, then buy long-term freedom."
                />
                <LearningBlockGrid blocks={wealthStages} />
            </section>

            <section className="mt-8">
                <SectionHeading
                    badge="THE NUMBERS"
                    title="The rules behind the cards"
                    description="These are transparent planning formulas. They explain the dashboard; they are not universal financial advice."
                />
                <LearningBlockGrid blocks={moneyFormulas} />
            </section>

            <section className="mt-8">
                <SectionHeading
                    badge="PLAYBOOK · قواعد عملية"
                    title="Small habits, applied to your real money"
                    description="Use one habit at a time. Each card links to the page where you can act, then the dashboard shows the result."
                />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {habitCards.map((habit) => {
                        const Icon = habit.icon;

                        return (
                            <Card key={habit.title} size="sm">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Icon className="text-muted-foreground" />
                                        {habit.title}
                                    </CardTitle>
                                    <CardDescription>
                                        {habit.arabicTitle}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3 text-sm leading-6">
                                    <p>{habit.description}</p>
                                    <p
                                        dir="rtl"
                                        className="text-muted-foreground"
                                    >
                                        {habit.arabicDescription}
                                    </p>
                                </CardContent>
                                <CardFooter>
                                    <Button href={habit.href} variant="outline">
                                        {habit.action}
                                        <ArrowRight data-icon="inline-end" />
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>
            </section>

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

            <section className="mt-8">
                <SectionHeading
                    badge="QUESTIONS YOU WILL HAVE"
                    title="Short answers for real life"
                    description="Open a question when you need a practical next step. Keep the answer simple, then use the linked page to apply it."
                />
                <div className="flex flex-col gap-3">
                    {learningQuestions.map((item) => (
                        <Card key={item.question} size="sm">
                            <Collapsible>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CircleHelp className="text-muted-foreground" />
                                        {item.question}
                                    </CardTitle>
                                    <CardAction>
                                        <CollapsibleTrigger
                                            render={
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    aria-label={`Show answer to ${item.question}`}
                                                />
                                            }
                                        >
                                            Answer
                                            <ChevronRight data-icon="inline-end" />
                                        </CollapsibleTrigger>
                                    </CardAction>
                                </CardHeader>
                                <CollapsibleContent>
                                    <CardContent className="pt-0 text-sm leading-7 text-muted-foreground">
                                        {item.answer}
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        </Card>
                    ))}
                </div>
            </section>

            <section className="mt-8">
                <Card>
                    <CardHeader>
                        <CardTitle>ملخص النظام بالعربية</CardTitle>
                        <CardDescription>
                            The simplest way to use the system every month
                        </CardDescription>
                    </CardHeader>
                    <CardContent dir="rtl" className="text-sm leading-8 text-muted-foreground">
                        <p>
                            ابدأ بمعرفة ما تملك وما عليك، ثم سجّل دخلك ومصروفاتك. احسب التدفق النقدي الحر، واحمِ صندوق الطوارئ، ثم وزّع الباقي على الأهداف والاستثمار. في نهاية الشهر قارن الخطة بالواقع، اشرح الفرق، واكتب درساً واحداً للشهر القادم. المال له ثلاثة أبعاد: أين يوجد؟ ما الغرض منه؟ وما الذي حدث هذا الشهر؟
                        </p>
                        <p className="mt-3">
                            الحرية المالية ليست راتباً كبيراً فقط؛ هي أن تغطي أصولك المستثمرة نفقاتك مع هامش أمان. قاعدة 4% تقدير تعليمي وليست ضماناً. الاستمرارية والتحسين الصغير أهم من محاولة مثالية قصيرة.
                        </p>
                        <p className="mt-3">
                            الصفحات مترابطة: الأصول توضح أين يوجد المال، والأوعية تحدد الغرض، والتدفق النقدي يوضح ما حدث هذا الشهر، والخطة توزع الفائض، والمراجعة تقارن الخطة بالواقع. الالتزامات والديون تقلل التدفق الحر، وسجلات سداد الديون تفصل أصل الدين عن الفائدة، بينما السيناريوهات تعرض أثر الدفع الإضافي دون تغيير بياناتك الفعلية.
                        </p>
                        <p className="mt-3">
                            يمكنك ضبط حدود التنبيه من السياسة المالية، وتفعيل إنشاء خطة الشهر القادم تلقائياً عند إغلاق المراجعة إذا لم توجد خطة سابقة. الخيار الآمن الافتراضي هو مراجعة الاقتراح يدوياً. يعرض النظام تاريخاً يصل إلى 12 شهراً عندما تتوفر البيانات.
                        </p>
                    </CardContent>
                    <CardFooter>
                        <Button href="/monthly-review" variant="outline">
                            Apply this in the monthly review
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    </CardFooter>
                </Card>
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

function LearningBlockGrid({ blocks }: { blocks: LearningBlock[] }) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            {blocks.map((block) => {
                const Icon = block.icon;

                return (
                    <Card key={block.title} size="sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Icon className="text-muted-foreground" />
                                {block.title}
                            </CardTitle>
                            <CardDescription>
                                {block.description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="flex list-disc flex-col gap-2 pl-4 text-sm leading-6 text-muted-foreground">
                                {block.points.map((point) => (
                                    <li key={point}>{point}</li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                );
            })}
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
