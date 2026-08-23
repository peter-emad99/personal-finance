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
    FileClock,
    Gauge,
    Landmark,
    Leaf,
    ListChecks,
    RefreshCw,
    ShieldCheck,
    SlidersHorizontal,
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
        title: 'Set your policy first',
        description:
            'Choose your base currency, emergency reserve months, liquidity rule, alert thresholds, and whether closing a review may prepare the next month automatically. The safe default is manual confirmation.',
        href: '/settings/financial',
        action: 'Set financial policy',
        icon: ShieldCheck,
    },
    {
        number: '02',
        title: 'Add what you own',
        description:
            'Start with cash, USD, gold, funds, shares, and any other asset you can value today. Add purpose buckets when you know what each amount is for.',
        href: '/assets',
        action: 'Add assets',
        icon: WalletCards,
    },
    {
        number: '03',
        title: 'Record commitments and debt',
        description:
            'Add subscriptions, recurring obligations, liabilities, balances, rates, and required payments. These reduce future free cash flow and make warnings meaningful.',
        href: '/commitments',
        action: 'Add commitments',
        icon: CreditCard,
    },
    {
        number: '04',
        title: 'Set your monthly flow',
        description:
            'Record each income source and monthly expense. Use budget categories for planned outflows; USD entries keep their original value and an EGP equivalent.',
        href: '/cash-flow',
        action: 'Add income & expenses',
        icon: Gauge,
    },
    {
        number: '05',
        title: 'Define your monthly rules',
        description:
            'Create reusable income, expense, and allocation rules in a plan template. Percentages use income for income and expenses, then available cash for allocations.',
        href: '/monthly-rules',
        action: 'Set plan rules',
        icon: SlidersHorizontal,
    },
    {
        number: '06',
        title: 'Give the surplus a job',
        description:
            'Choose a template for the month, review its expense categories, and adjust the allocation snapshot before saving.',
        href: '/allocations',
        action: 'Make a monthly plan',
        icon: ListChecks,
    },
    {
        number: '07',
        title: 'Connect a real goal',
        description:
            'Create a phone, car, or other goal, then link its contribution to the assets or buckets that hold the money.',
        href: '/goals',
        action: 'Create a goal',
        icon: Flag,
    },
    {
        number: '08',
        title: 'Review and protect the snapshot',
        description:
            'Compare planned with actual results, then close the month when you want that history protected from future template edits.',
        href: '/monthly-plans',
        action: 'View plan history',
        icon: RefreshCw,
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

const dashboardFormulas: LearningBlock[] = [
    {
        title: 'Current position',
        description:
            'The top summary cards describe what exists today, not a historical net-worth statement.',
        icon: Landmark,
        points: [
            'Total assets = the sum of each asset current_value_egp. Net worth = total assets − active liability balances.',
            'Directly controlled assets = total assets minus assets classified as receivables or held elsewhere.',
            'Available now and within 3 days use each asset’s explicit liquidity tier. They are not the same as total net worth.',
            'Emergency fund uses assets assigned to an emergency bucket and allowed by your emergency liquidity policy.',
        ],
    },
    {
        title: 'Monthly actuals',
        description:
            'The dashboard chooses the most reliable source available for the selected month.',
        icon: Gauge,
        points: [
            'Confirmed ledger rows are preferred when they exist for the month.',
            'Otherwise an open or closed monthly review is used; if there is no review, the dashboard falls back to cash-flow entries.',
            'Income and outflow produce free cash flow = income − total outflow. The total includes every confirmed non-transfer outflow; the plan comparison only assigns it to a category when the source record is explicitly linked.',
            'A review or ledger source changes actuals; it does not rewrite the plan snapshot.',
        ],
    },
    {
        title: 'Plan calculation',
        description:
            'The plan explains what you intended to do with the month before reality is compared with it.',
        icon: ListChecks,
        points: [
            'A saved monthly plan is the source for its month. Without one, the dashboard evaluates the active template live.',
            'Planned income comes from fixed income rules or percentages of the month’s income. Planned expenses are summed by budget category from fixed or percentage rules.',
            'Planned free cash flow = planned income − planned expenses. Allocation rules take a percentage of that post-expense amount.',
            'The dashboard card renders the exact expense categories and allocation rows from the current month plan. It does not create universal Essentials, Lifestyle, Debt, Emergency, Goals, or Investments rows.',
            'An expense actual matches a plan category only through the linked budget category on the transaction category. An allocation actual matches only through the explicitly selected purpose bucket.',
            'Descriptions and names are never used to guess a category or purpose. Unlinked expenses and allocations are shown separately so you can fix the source record.',
            'Planned unassigned remainder = max(0, planned free cash flow − the plan’s allocation rows). Actual values do not rewrite the saved plan.',
        ],
    },
    {
        title: 'Goals, safety, and warnings',
        description:
            'The lower dashboard cards turn the numbers into decisions without pretending to decide for you.',
        icon: ShieldCheck,
        points: [
            'Emergency target = essential monthly base × configured reserve months. Coverage months = eligible emergency amount ÷ that monthly base.',
            'Savings rate = free cash flow ÷ income. Investment rate = actual investing ÷ income. Both are 0 when income is 0.',
            'Goal funding is processed in priority order from the remaining monthly cash flow, so one pound is not promised to every goal at once.',
            'Variance alerts compare actual income/outflow with plan using your thresholds. Commitment and debt warnings compare recorded payments with active configuration.',
        ],
    },
];

const dashboardReadOrder = [
    ['1', 'Check the source', 'Read data freshness, valuation freshness, and whether actuals come from the confirmed ledger, a review, or cash-flow fallback.'],
    ['2', 'Read current position', 'Use net worth, controlled assets, liquidity, emergency coverage, and liabilities to understand today.'],
    ['3', 'Read the month', 'Compare actual income and outflow with the saved plan or the active template, then inspect free cash flow.'],
    ['4', 'Follow the attention queue', 'Resolve over-allocation, unassigned cash, changed commitments, debt mismatches, or stale values before optimising.'],
    ['5', 'Take one action', 'Open the linked page, make the smallest useful correction, and return to the dashboard to confirm the result.'],
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
            'They are personal prompts. Income and outflow thresholds compare the current month with the saved plan. The investment threshold compares actual investing with the investment amount planned for this month. Lowering a threshold makes the app more sensitive; raising it reduces noise. The warnings do not block you or make a decision for you.',
    },
    {
        question: 'What happens when I confirm next month’s plan?',
        answer:
            'The app carries forward the selected plan template when the closed month has one. It recalculates income rules, expense rules, commitment-linked outflows, and allocation percentages using the new month’s income. Without a source template, it uses the review-based fallback. You see the numbers before saving, and your lesson is stored with the new plan.',
    },
    {
        question: 'How should I record a lender payment?',
        answer:
            'Open Liabilities, choose Record payment, and copy the statement values for total payment, principal, interest, fees, date, and balance after payment. The app checks that the components add up to the total. Recorded facts stay separate from the estimated payoff projection, while the extra-payment cards show what might happen if you pay more each month.',
    },
    {
        question: 'What does automatic preparation on close do?',
        answer:
            'It is an optional shortcut in Financial policy and is off by default. When enabled, closing a review prepares the next month only if no plan exists for that month. It uses the same safe generator as the confirmation flow and never replaces a plan you already created.',
    },
    {
        question: 'How do income, expense, and allocation rules work?',
        answer:
            'Income rules can be fixed EGP amounts or percentages of the month’s income. Expense rules roll up by budget category and can also be fixed or percentage-based. Allocation rules use percentages of the cash left after planned expenses, then connect that amount to an asset target and purpose bucket. The total allocation percentage cannot exceed 100%.',
    },
    {
        question: 'What is the difference between a template and a monthly plan?',
        answer:
            'A template is reusable policy for future months. A monthly plan is the snapshot for one month: it stores the income, expense categories, allocation rows, and actuals you reviewed. Editing a template does not rewrite an existing saved or closed monthly snapshot.',
    },
    {
        question: 'Why do budget categories matter?',
        answer:
            'Categories make expense rules and actual ledger spending comparable. A custom category is preserved in the monthly snapshot, while the default categories give you a useful starting structure. The category describes the outflow; the purpose bucket describes what saved or invested money is for.',
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
            'Current month plan versus actual: a saved plan when one exists, otherwise a live template preview beside actual month data.',
            'This month’s breathing room: actual income after actual expenses.',
            'Emergency coverage: how many months your reserve can cover.',
        ],
        relation:
            'It reads from assets, income & expenses, the saved monthly plan or active template, buckets, and goals. It is the best place to decide what to do next—not the place where every record is created.',
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
        description: 'A month-specific snapshot generated from reusable rules.',
        href: '/allocations',
        page: 'Monthly plan',
        icon: ListChecks,
        fields: [
            'Template and income: the rules selected for this month and their calculated EGP result.',
            'Expense categories: the planned outflow snapshot, including commitment-linked rules.',
            'Allocation rows: percentages of the cash left after expenses, linked to an asset and purpose bucket.',
            'Actuals: manual or confirmed-ledger values that do not rewrite the planned snapshot.',
        ],
        relation:
            'The plan applies a template once, then stores the result for the selected month. It funds emergency reserves, goals, and investments through purpose buckets; asset allocations show where the money actually sits.',
        outcome:
            'Every planned pound has a category, purpose, or an explicit unallocated remainder.',
    },
    {
        title: 'Plan templates & rules',
        description: 'Reusable monthly policy for income, expenses, and allocations.',
        href: '/monthly-rules',
        page: 'Plan templates',
        icon: SlidersHorizontal,
        fields: [
            'Income rules: fixed amounts or percentages of income; multiple sources are allowed.',
            'Expense rules: fixed amounts or percentages, grouped by a budget category.',
            'Allocation rules: a percentage of post-expense cash, linked to an asset target and purpose bucket.',
            'Template actions: duplicate, set a default, edit, or create one from a monthly snapshot.',
        ],
        relation:
            'Templates are the reusable source of truth for future plans. Commitment-linked rules stay connected to active commitments, while a saved monthly plan remains a historical snapshot.',
        outcome:
            'Your dashboard and new monthly plans use the same visible rules instead of hidden fallback percentages.',
    },
    {
        title: 'Budget categories',
        description: 'The labels that make planned and actual expenses comparable.',
        href: '/budget-categories',
        page: 'Budget categories',
        icon: Database,
        fields: [
            'Categories are user-owned labels for planned outflows; the app does not assume what a name means.',
            'Custom categories: add a category when your real spending needs a clearer label.',
            'Monthly snapshot: category amounts are copied into the plan for the month.',
            'Actual matching: link transaction categories to the same budget category. Unlinked transactions remain unmapped.',
        ],
        relation:
            'Budget categories describe outflows. They are separate from purpose buckets, which describe why surplus money is reserved or invested.',
        outcome:
            'You can see which rule or category caused a plan number and compare it with actual ledger spending.',
    },
    {
        title: 'Monthly snapshots & history',
        description: 'A safe record of what you intended and what happened.',
        href: '/monthly-plans',
        page: 'Monthly plans & history',
        icon: RefreshCw,
        fields: [
            'Open plan: still editable for the month.',
            'Closed plan: protected from template changes and normal edits.',
            'Actuals: synced from confirmed ledger transactions or entered manually.',
        ],
        relation:
            'A template can evolve while past months stay honest. Use “save as template” when a real month teaches you a better repeatable rule.',
        outcome:
            'You can learn from history without accidentally rewriting it.',
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
        title: 'Commitments',
        description: 'Recurring costs that are already spoken for.',
        href: '/commitments',
        page: 'Commitments',
        icon: RefreshCw,
        fields: [
            'Name, amount, currency, frequency, and active status.',
            'Monthly equivalent: the amount used in monthly cash-flow checks.',
            'Change history: what was added, removed, renamed, or repriced since the last closed review.',
        ],
        relation:
            'Active commitments are synchronised into templates and monthly reviews, then reduce free cash flow before allocations.',
        outcome:
            'You see the recurring costs that future months must carry before you make new promises.',
    },
    {
        title: 'Monthly review',
        description: 'The close-of-month feedback loop.',
        href: '/monthly-review',
        page: 'Monthly review',
        icon: BookOpen,
        fields: [
            'Actual income and expense totals, including commitments and debt payments.',
            'Plan versus actual differences, obligation changes, and the lesson for next month.',
            'Close and prepare-next actions: close protects the review and confirmation carries a proposal forward.',
        ],
        relation:
            'The review turns evidence into a dated decision. When a ledger month is confirmed, its rows become the preferred actual source on the dashboard.',
        outcome:
            'You finish the month with an explanation, a protected snapshot, and a better next plan.',
    },
    {
        title: 'Purpose buckets',
        description: 'Labels for why money exists.',
        href: '/buckets',
        page: 'Purpose buckets',
        icon: Database,
        fields: [
            'Name and purpose: describe the job you choose for the money; the label is not interpreted as a type.',
            'Policy purpose: explicitly choose emergency reserve, investment, goal-linked, or other when a policy calculation needs that meaning.',
            'Target: an optional amount that makes progress measurable.',
            'Monthly actual matching: explicitly select this bucket on contribution or investment transactions.',
        ],
        relation:
            'Buckets connect plans, goals, and asset allocations. They answer “what is this money for?” while assets answer “where is it held?”',
        outcome:
            'You can separate money for different jobs without pretending it must live in separate accounts.',
    },
    {
        title: 'Liabilities',
        description: 'Balances and required payments that affect the plan.',
        href: '/liabilities',
        page: 'Liabilities',
        icon: CreditCard,
        fields: [
            'Current balance, annual interest rate, minimum monthly payment, and active status.',
            'Record payment: statement total, principal, interest, fees, date, and balance after payment.',
            'Payoff estimate and extra-payment scenarios are planning estimates, separate from lender facts.',
        ],
        relation:
            'Liability balances reduce net worth. Required payments reduce monthly free cash flow and are compared with review and ledger totals.',
        outcome:
            'The dashboard shows debt impact without confusing a projection with a lender statement.',
    },
    {
        title: 'Reconciliation',
        description: 'Check account totals and monthly evidence.',
        href: '/reconciliation',
        page: 'Reconciliation',
        icon: ListChecks,
        fields: [
            'Account balances: what the ledger says each account contains.',
            'Confirmed income and commitment payments for the month.',
            'Pending import rows and review status.',
        ],
        relation:
            'Reconciliation validates the ledger source before it becomes the dashboard’s preferred actual source.',
        outcome:
            'You know whether a total is complete, pending review, or needs an account-level correction.',
    },
    {
        title: 'Allocation reconciliation',
        description: 'Check that purpose allocations are backed by real assets.',
        href: '/allocation-reconciliation',
        page: 'Allocation reconciliation',
        icon: ListChecks,
        fields: [
            'Asset value versus amounts allocated to purpose buckets.',
            'Over-allocated assets and allocations without a clear purpose.',
            'Goal and emergency funding coverage.',
        ],
        relation:
            'It protects the distinction between a physical asset and the jobs assigned to it.',
        outcome:
            'You avoid counting the same money twice across goals, emergency savings, and investments.',
    },
    {
        title: 'Purchase scenarios',
        description: 'Test a purchase before committing real money.',
        href: '/scenarios',
        page: 'Purchase scenarios',
        icon: Gauge,
        fields: [
            'Price, down payment, financing, and monthly payment assumptions.',
            'Cash remaining after purchase and the minimum-cash policy check.',
            'Debt burden and emergency reserve impact.',
        ],
        relation:
            'Scenarios read the dashboard’s current liquidity, free cash flow, liabilities, and financial policy.',
        outcome:
            'You can compare a decision with your safety rules before it becomes a transaction.',
    },
    {
        title: 'Snapshots',
        description: 'Dated checkpoints for your financial position.',
        href: '/snapshots',
        page: 'Snapshots',
        icon: RefreshCw,
        fields: [
            'Snapshot date, asset values, liabilities, and position summary.',
            'Current checkpoints for progress reviews.',
            'Historical accuracy depends on dated valuations, liability histories, and ledger rows.',
        ],
        relation:
            'Snapshots preserve a point-in-time view; current asset values alone cannot recreate the past.',
        outcome:
            'You can compare real checkpoints without treating today’s values as historical facts.',
    },
    {
        title: 'Ledger & imports',
        description: 'The precise transaction source.',
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
        title: 'Ledger categories',
        description: 'Labels that make transaction summaries meaningful.',
        href: '/transaction-categories',
        page: 'Transaction categories',
        icon: Database,
        fields: [
            'Names and types used by ledger transactions.',
            'Consistent labels for essentials, lifestyle, commitments, debt, and investing.',
            'Archive or restore categories without deleting transaction history.',
        ],
        relation:
            'Ledger categories explain actual rows; budget categories explain planned outflows. Similar names help comparison, but they are separate systems.',
        outcome:
            'Your actual monthly totals remain understandable when transactions come from imports or multiple accounts.',
    },
    {
        title: 'Asset valuations',
        description: 'Keep asset values dated and explainable.',
        href: '/valuations',
        page: 'Valuation history',
        icon: Coins,
        fields: [
            'Valuation: a dated current value for an asset.',
            'Source and method: how the value was obtained.',
            'Freshness: compare the latest valuation age with your policy window.',
        ],
        relation:
            'The dashboard uses the asset current value for today and shows valuation freshness so you can judge confidence.',
        outcome:
            'You can distinguish contributions, price movement, and stale information over time.',
    },
    {
        title: 'FX rates',
        description: 'Explain mixed-currency EGP reporting.',
        href: '/fx-rates',
        page: 'FX rates',
        icon: Coins,
        fields: [
            'Currency pair and effective date.',
            'Rate used to convert native amounts into the base EGP report.',
            'Historical rates for explaining why converted values changed.',
        ],
        relation:
            'Cash-flow and asset records keep their native currency while the dashboard reports comparable EGP totals.',
        outcome:
            'You can explain an EGP number without losing the original USD or other-currency amount.',
    },
    {
        title: 'Liability history',
        description: 'Track debt balances from dated statements.',
        href: '/liability-history',
        page: 'Liability history',
        icon: FileClock,
        fields: [
            'Statement date and balance for each liability.',
            'Progress across dates, separate from the current liability record.',
            'Archived history remains available for review.',
        ],
        relation:
            'Historical balances let you tell whether debt is falling; the dashboard’s current balance remains the live position.',
        outcome:
            'You can see debt direction instead of inferring history from one current number.',
    },
    {
        title: 'Financial policy',
        description: 'The personal rules that drive dashboard guardrails.',
        href: '/settings/financial',
        page: 'Financial policy',
        icon: ShieldCheck,
        fields: [
            'Base currency, emergency reserve months, eligible liquidity, and minimum cash after a purchase.',
            'Allocation targets, debt limits, valuation freshness, and goal funding policy.',
            'Income/outflow variance thresholds, investment minimum, and optional auto-prepare setting.',
            'Financial-freedom withdrawal-rate and spending assumptions.',
        ],
        relation:
            'Policy supplies the thresholds and assumptions used by the dashboard, reviews, and purchase scenarios.',
        outcome:
            'The app reflects your rules instead of applying one universal percentage to everyone.',
    },
    {
        title: 'Decision journal',
        description: 'Record assumptions so outcomes become learning.',
        href: '/decision-journal',
        page: 'Decision journal',
        icon: BookOpen,
        fields: [
            'Decision, reason, assumptions, alternatives, and expected outcome.',
            'Review date and result after reality is known.',
            'Open or completed status for decisions still needing attention.',
        ],
        relation:
            'Monthly review lessons and purchase decisions become a visible feedback loop instead of disappearing from memory.',
        outcome:
            'You improve the rule behind the next plan, not only the number on the current dashboard.',
    },
    {
        title: 'Operations',
        description: 'Protect the workspace and its data.',
        href: '/operations',
        page: 'Operations',
        icon: Database,
        fields: [
            'Create and verify backups before important changes.',
            'Run integrity checks to find broken references or inconsistent records.',
            'Download, restore, or archive backups deliberately.',
        ],
        relation:
            'Operations does not change your financial logic; it protects the records that make every other page trustworthy.',
        outcome:
            'You can recover from mistakes and check the workspace before relying on it for decisions.',
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
                            <Badge variant="secondary">8 steps</Badge>
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
                        <ModelLine
                            label="What actually happened?"
                            detail="Ledger, cash flow, and monthly review"
                        />
                        <ModelLine
                            label="What should I improve?"
                            detail="Dashboard attention and decision journal"
                        />
                    </CardContent>
                    <CardFooter className="flex-col items-start gap-2 text-sm text-muted-foreground">
                        <CircleHelp className="text-primary" />
                        Goals sit across the model: they have a purpose, a
                        monthly plan, actual progress, and real assets backing
                        them. A purpose allocation does not create new money.
                    </CardFooter>
                </Card>
            </div>

            <section className="mt-8">
                <SectionHeading
                    badge="DASHBOARD · READ IT IN 60 SECONDS"
                    title="The main dashboard, in the right order"
                    description="The dashboard is a decision screen. First check where its numbers came from, then understand today, then act on the month. The links in the cards take you to the page that owns the data."
                />
                <Card>
                    <CardContent className="grid gap-4 p-5 md:grid-cols-5">
                        {dashboardReadOrder.map(([number, title, detail]) => (
                            <div key={number} className="flex gap-3 md:flex-col">
                                <div className="grid size-8 shrink-0 place-items-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
                                    {number}
                                </div>
                                <div>
                                    <p className="text-sm font-semibold">{title}</p>
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
                    badge="THE COMPLETE LOOP · دورة المال"
                    title="How the app turns money into progress"
                    description="The app is not a collection of unrelated forms. Each area answers one question, and the result flows into the next decision."
                />
                <Card>
                    <CardContent className="grid gap-3 p-5 md:grid-cols-6">
                        {[
                            ['1', 'Configure', 'Policy, categories, and reusable rules'],
                            ['2', 'Know', 'Assets, buckets, debts, and commitments'],
                            ['3', 'Measure', 'Income and actual outflows'],
                            ['4', 'Plan', 'Template rules become a month snapshot'],
                            ['5', 'Protect', 'Emergency reserve and required minimums'],
                            ['6', 'Direct', 'Goals, investments, and unallocated cash'],
                            ['7', 'Review', 'Plan versus actual evidence'],
                            ['8', 'Improve', 'Close, learn, and prepare next month'],
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
                    badge="DASHBOARD FORMULAS"
                    title="How the dashboard data is calculated"
                    description="These are the current calculation rules behind the main cards, monthly flow, allocations, emergency coverage, goals, and warnings. They are transparent planning formulas, not universal financial advice."
                />
                <div className="flex flex-col gap-4">
                    <LearningBlockGrid blocks={dashboardFormulas} />
                    <Separator />
                    <p className="text-sm font-medium">Supporting planning formulas</p>
                    <LearningBlockGrid blocks={moneyFormulas} />
                </div>
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
                    description="These pages create the records that feed the dashboard: current position, monthly inputs, reusable rules, a month snapshot, and goal progress."
                />
                <GuideList sections={basicSections} defaultOpen />
            </section>

            <section className="mt-8">
                <SectionHeading
                    badge="EVERY OTHER PAGE"
                    title="The complete page-by-page reference"
                    description="Open these when you need recurring-cost control, ledger evidence, historical accuracy, decision checks, reconciliation, or workspace protection. Each page explains its inputs, connections, and result."
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
