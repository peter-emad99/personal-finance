import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    Calculator,
    CircleHelp,
    CreditCard,
    ChevronRight,
    Database,
    FileClock,
    FileJson,
    FileText,
    Flag,
    Gauge,
    GraduationCap,
    LayoutDashboard,
    ListChecks,
    LogOut,
    Monitor,
    Moon,
    Repeat2,
    Settings,
    SlidersHorizontal,
    Sun,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, PropsWithChildren, ReactNode } from 'react';

import { ExportContextActions } from '@/components/export-context-actions';
import { ThemeProvider, useTheme } from '@/components/theme-provider';
import type { ThemeMode } from '@/components/theme-provider';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button as UiButton } from '@/components/ui/button';
import {
    Card as UiCard,
    CardAction as UiCardAction,
    CardDescription as UiCardDescription,
    CardHeader as UiCardHeader,
    CardTitle as UiCardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Progress as UiProgress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarProvider,
    SidebarRail,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type NavigationItem = {
    href: string;
    label: string;
    icon: LucideIcon;
};

type NavigationGroup = {
    label: string;
    items: NavigationItem[];
};

const pageHints: Record<string, { title: string; description: string }> = {
    'Learn the system': {
        title: 'How to learn and apply the system',
        description:
            'Start with the eight-step setup, then use the dashboard read order, formula guide, and complete page map. Each guide card opens the page where you can act.',
    },
    Dashboard: {
        title: 'How to use this page',
        description:
            'Start here. Read the cash-flow status and attention queue first, then open the one action that improves your position this month.',
    },
    'Income & expenses': {
        title: 'What to record here',
        description:
            'Enter income and real outflows for the selected month. Free cash flow is the amount left after expenses and becomes the ceiling for your monthly plan.',
    },
    Allocations: {
        title: 'What this plan means',
        description:
            'A monthly plan is a snapshot generated from a template. Planned is what you intend to direct; actual is what confirmed ledger transactions show happened. Keep allocations within cash left after planned expense rules.',
    },
    'Monthly plans': {
        title: 'How monthly snapshots work',
        description:
            'Each row is one month’s saved income, expense, allocation, and actual snapshot. Closing a plan protects its history while templates continue to evolve.',
    },
    'Plan templates': {
        title: 'How reusable rules work',
        description:
            'Templates define income rules, category-based expense rules, and percentage allocation rules for future monthly snapshots. Editing a template does not rewrite saved months.',
    },
    'Budget categories': {
        title: 'How expense categories work',
        description:
            'Categories group planned and actual outflows. They are separate from purpose buckets: categories say what you spend on, while buckets say what surplus money is for.',
    },
    Goals: {
        title: 'How goals work',
        description:
            'A goal is a target plus a deadline and monthly pace. Its bucket explains the purpose; its asset allocations show where the money is held.',
    },
    'What you own': {
        title: 'Asset versus purpose',
        description:
            'Assets are what you own. Buckets are what the money is for. One asset can support several purposes, so allocating a purpose does not create new money.',
    },
    'Purpose buckets': {
        title: 'Why buckets matter',
        description:
            'Buckets prevent one balance from being counted for several jobs. Use them for emergency savings, goals, spending reserves, or long-term investing.',
    },
    Commitments: {
        title: 'What commitments do',
        description:
            'Commitments are recurring costs already spoken for. Active monthly equivalents are linked into reviews and reduce the cash available for goals and investing.',
    },
    Liabilities: {
        title: 'What liabilities do',
        description:
            'Liabilities reduce net worth and their required payments reduce monthly free cash flow. Record the balance, rate, payment, lender statements, and any extra-payment scenario as accurately as possible.',
    },
    'Monthly review': {
        title: 'Your monthly feedback loop',
        description:
            'Compare the plan with what happened, synchronise active commitments and debts, inspect item-level changes and payoff estimates, write the lesson, close the month, then review and confirm the next plan. If the reserve is complete, redirect the released amount to goals or investments.',
    },
    Scenarios: {
        title: 'Use scenarios before buying',
        description:
            'A scenario is a rule-based estimate, not a guarantee. Check the price, cash left, payment, emergency reserve, and debt burden before making a decision.',
    },
    'Ledger and imports': {
        title: 'The precise source',
        description:
            'Confirmed ledger rows become the preferred source for monthly actuals. Imports stay pending until you review and accept them; transfers are not spending.',
    },
    'Transaction categories': {
        title: 'Why categories matter',
        description:
            'Categories explain what a ledger row represents. Consistent names improve essential, lifestyle, recurring, debt, and investment summaries.',
    },
    'Asset valuations': {
        title: 'Keep values dated',
        description:
            'A valuation is a dated estimate of what an asset is worth. Add a new valuation when the value changes so history can separate contributions from market movement.',
    },
    'FX rates': {
        title: 'Mixed currencies',
        description:
            'The app reports totals in EGP. Keep the original currency and use a dated exchange rate so conversions remain explainable.',
    },
    'Liability history': {
        title: 'Track debt progress',
        description:
            'Record dated balances from statements. This lets the system show whether debt is falling and prevents current balances from being mistaken for historical facts.',
    },
    Reconciliation: {
        title: 'What reconciliation checks',
        description:
            'Compare confirmed ledger balances with reported account balances and compare expected commitments with actual activity. Differences are review tasks, not automatic errors.',
    },
    'Allocation reconciliation': {
        title: 'Purpose reconciliation',
        description:
            'Make sure assets are not allocated beyond their value and that every allocation has one clear purpose. A purpose is not a second account balance.',
    },
    Snapshots: {
        title: 'Use snapshots as checkpoints',
        description:
            'A snapshot records your position at a date. Use current-date checkpoints for progress; historical accuracy requires dated valuations, liability histories, and ledger rows.',
    },
    'Financial policy': {
        title: 'Your rules drive the advice',
        description:
            'Set your base currency, emergency months, liquidity rule, allocation targets, warning thresholds, and decision guardrails. The dashboard uses these settings instead of pretending one rule fits everyone.',
    },
    'Decision journal': {
        title: 'Turn choices into learning',
        description:
            'Write the decision, assumptions, alternatives, and result. The goal is not perfect prediction; it is better decisions with a visible record of what you learned.',
    },
    Operations: {
        title: 'Protect the workspace',
        description:
            'Use backups and integrity checks before relying on the system for important decisions. Keep exported financial context private and deliberate.',
    },
    'Activity log': {
        title: 'A traceable workspace',
        description:
            'Every tracked create, update, archive, restore, export, sync, and bulk operation shows its source channel and the before/after state that was recorded.',
    },
};

const navigationGroups: NavigationGroup[] = [
    {
        label: 'Plan',
        items: [
            { href: '/', label: 'Dashboard', icon: LayoutDashboard },
            { href: '/learn', label: 'Learn the system', icon: GraduationCap },
            {
                href: '/monthly-plans',
                label: 'Monthly plans & history',
                icon: FileClock,
            },
            { href: '/allocations', label: 'Monthly plan', icon: ListChecks },
            {
                href: '/monthly-rules',
                label: 'Plan templates',
                icon: SlidersHorizontal,
            },
        ],
    },
    {
        label: 'Track',
        items: [
            { href: '/ledger', label: 'Ledger & imports', icon: BookOpen },
            { href: '/commitments', label: 'Commitments', icon: Repeat2 },
            { href: '/liabilities', label: 'Liabilities', icon: CreditCard },
            { href: '/monthly-review', label: 'Monthly review', icon: Gauge },
        ],
    },
    {
        label: 'Portfolio',
        items: [
            { href: '/assets', label: 'What you own', icon: WalletCards },
            { href: '/buckets', label: 'Purpose buckets', icon: Database },
            { href: '/goals', label: 'Goals', icon: Flag },
        ],
    },
    {
        label: 'Analyze',
        items: [
            {
                href: '/reconciliation',
                label: 'Reconciliation',
                icon: ListChecks,
            },
            {
                href: '/allocation-reconciliation',
                label: 'Reconcile purposes',
                icon: ListChecks,
            },
            {
                href: '/scenarios',
                label: 'Purchase scenarios',
                icon: Calculator,
            },
            { href: '/snapshots', label: 'Snapshots', icon: FileClock },
        ],
    },
    {
        label: 'Settings',
        items: [
            {
                href: '/budget-categories',
                label: 'Expense categories',
                icon: ListChecks,
            },
            {
                href: '/transaction-categories',
                label: 'Ledger categories',
                icon: ListChecks,
            },
            {
                href: '/valuations',
                label: 'Valuation history',
                icon: FileClock,
            },
            { href: '/fx-rates', label: 'FX rates', icon: Repeat2 },
            {
                href: '/liability-history',
                label: 'Liability history',
                icon: FileClock,
            },
            {
                href: '/settings/financial',
                label: 'Financial policy',
                icon: Settings,
            },
            {
                href: '/decision-journal',
                label: 'Decision journal',
                icon: FileClock,
            },
            { href: '/operations', label: 'Operations', icon: Settings },
            { href: '/activity-log', label: 'Activity log', icon: Activity },
        ],
    },
];

function isNavigationItemActive(href: string, currentPath: string) {
    return href === '/' ? currentPath === '/' : currentPath.startsWith(href);
}

function AppSidebar({ currentPath }: { currentPath: string }) {
    return (
        <Sidebar variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            render={<Link href="/" />}
                            size="lg"
                            tooltip="Personal finance OS"
                        >
                            <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-sidebar-primary text-sm font-bold text-sidebar-primary-foreground">
                                P
                            </span>
                            <span className="grid flex-1 text-left text-xs leading-4">
                                <span className="font-semibold tracking-wide">
                                    PERSONAL
                                </span>
                                <span className="text-sidebar-foreground/60">
                                    finance OS
                                </span>
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent className="gap-0">
                {navigationGroups.map((group) => (
                    <Collapsible
                        key={group.label}
                        defaultOpen={group.label !== 'Advanced'}
                        className="group/collapsible"
                    >
                        <SidebarGroup>
                            <SidebarGroupLabel
                                render={<CollapsibleTrigger />}
                                className="group/label text-xs font-semibold tracking-wide text-sidebar-foreground uppercase hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                            >
                                {group.label}
                                <ChevronRight className="ml-auto transition-transform group-data-open/collapsible:rotate-90" />
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                <SidebarGroupContent>
                                    <SidebarMenu>
                                        {group.items.map((item) => {
                                            const active =
                                                isNavigationItemActive(
                                                    item.href,
                                                    currentPath,
                                                );
                                            const Icon = item.icon;

                                            return (
                                                <SidebarMenuItem
                                                    key={item.href}
                                                >
                                                    <SidebarMenuButton
                                                        render={
                                                            <Link
                                                                href={item.href}
                                                            />
                                                        }
                                                        isActive={active}
                                                        tooltip={item.label}
                                                    >
                                                        <Icon />
                                                        <span>
                                                            {item.label}
                                                        </span>
                                                    </SidebarMenuButton>
                                                </SidebarMenuItem>
                                            );
                                        })}
                                    </SidebarMenu>
                                </SidebarGroupContent>
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                ))}
            </SidebarContent>
            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <ExportContextActions
                            trigger={
                                <SidebarMenuButton tooltip="Export context">
                                    <FileText />
                                    <span>Export context</span>
                                </SidebarMenuButton>
                            }
                        />
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <ExportContextActions
                            trigger={
                                <SidebarMenuButton tooltip="Export JSON">
                                    <FileJson />
                                    <span>Export JSON</span>
                                </SidebarMenuButton>
                            }
                        />
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            onClick={() => router.post('/logout')}
                            tooltip="Sign out"
                        >
                            <LogOut />
                            <span>Sign out</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}

export function AppShell({
    children,
    title,
}: PropsWithChildren<{ title: string }>) {
    const page = usePage<{
        flash?: { success?: string | null; error?: string | null };
        auth?: { user?: { name?: string | null } | null };
    }>();
    const currentPath = page.url.split('?')[0];
    const { flash } = page.props;
    const userName = page.props.auth?.user?.name ?? 'Local owner';
    const hint = pageHints[title];

    return (
        <ThemeProvider>
            <TooltipProvider>
                <Head title={title} />
                <SidebarProvider>
                    <AppSidebar currentPath={currentPath} />
                    <SidebarInset>
                        <header className="sticky top-0 flex h-16 shrink-0 items-center gap-2 border-b bg-background px-4">
                            <SidebarTrigger className="-ml-1" />
                            <Separator
                                orientation="vertical"
                                className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                            />
                            <Breadcrumb className="min-w-0">
                                <BreadcrumbList className="flex-nowrap">
                                    <BreadcrumbItem className="hidden sm:inline-flex">
                                        <BreadcrumbLink
                                            render={<Link href="/" />}
                                        >
                                            Personal finance OS
                                        </BreadcrumbLink>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator className="hidden sm:block" />
                                    <BreadcrumbItem className="min-w-0">
                                        <BreadcrumbPage className="truncate">
                                            {title}
                                        </BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                            <div className="ml-auto flex items-center gap-3">
                                <span className="hidden max-w-48 truncate text-xs text-muted-foreground sm:inline">
                                    {userName}
                                </span>
                                <ThemeMenu />
                            </div>
                        </header>
                        <div className="flex flex-1 flex-col gap-4 p-4">
                            {(flash?.success || flash?.error) && (
                                <div
                                    role={flash.error ? 'alert' : 'status'}
                                    className={cn(
                                        'mx-auto w-full max-w-[1500px] pt-4 text-sm',
                                        flash.error
                                            ? 'text-destructive'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {flash.error ?? flash.success}
                                </div>
                            )}
                            <div className="mx-auto w-full max-w-[1500px]">
                                {hint && (
                                    <Alert className="mb-5">
                                        <CircleHelp />
                                        <AlertTitle>{hint.title}</AlertTitle>
                                        <AlertDescription>
                                            {hint.description}
                                        </AlertDescription>
                                    </Alert>
                                )}
                                {children}
                            </div>
                        </div>
                    </SidebarInset>
                </SidebarProvider>
            </TooltipProvider>
        </ThemeProvider>
    );
}

function ThemeMenu() {
    const { theme, setTheme } = useTheme();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <UiButton
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Choose theme"
                        className="text-muted-foreground hover:bg-muted hover:text-foreground"
                    />
                }
            >
                {theme === 'dark' ? (
                    <Moon />
                ) : theme === 'light' ? (
                    <Sun />
                ) : (
                    <Monitor />
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuRadioGroup
                    value={theme}
                    onValueChange={(value) => setTheme(value as ThemeMode)}
                >
                    <DropdownMenuLabel>Theme</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {(['system', 'light', 'dark'] as ThemeMode[]).map(
                        (mode) => (
                            <DropdownMenuRadioItem
                                key={mode}
                                value={mode}
                                className="capitalize"
                            >
                                {mode === 'system' ? (
                                    <Monitor />
                                ) : mode === 'light' ? (
                                    <Sun />
                                ) : (
                                    <Moon />
                                )}
                                {mode}
                            </DropdownMenuRadioItem>
                        ),
                    )}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function PageHeader({
    eyebrow,
    title,
    description,
    action,
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <header className="mb-8 flex flex-col justify-between gap-5 md:flex-row md:items-end">
            <div>
                <p className="mb-2 text-xs font-bold tracking-[0.18em] text-primary/70 uppercase">
                    {eyebrow ?? 'Personal finance OS'}
                </p>
                <h1 className="text-3xl font-semibold tracking-tight text-foreground md:text-4xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {action && <div>{action}</div>}
        </header>
    );
}

export function Card({
    children,
    className = '',
    ...props
}: PropsWithChildren<ComponentProps<typeof UiCard>>) {
    return (
        <UiCard className={cn('shadow-sm', className)} {...props}>
            {children}
        </UiCard>
    );
}

export function CardHeader({
    title,
    meta,
    action,
}: {
    title: string;
    meta?: string;
    action?: ReactNode;
}) {
    return (
        <UiCardHeader className="rounded-t-2xl border-b border-border bg-muted/60 px-5 py-4">
            <div>
                <UiCardTitle className="text-sm font-semibold">
                    {title}
                </UiCardTitle>
                {meta && (
                    <UiCardDescription className="mt-1 text-xs">
                        {meta}
                    </UiCardDescription>
                )}
            </div>
            {action && <UiCardAction>{action}</UiCardAction>}
        </UiCardHeader>
    );
}

type AppButtonProps = Omit<ComponentProps<typeof UiButton>, 'variant'> & {
    href?: string;
    variant?:
        | 'primary'
        | 'ghost'
        | 'danger'
        | ComponentProps<typeof UiButton>['variant'];
};

export function Button({
    children,
    href,
    variant = 'primary',
    className,
    nativeButton,
    ...props
}: PropsWithChildren<AppButtonProps>) {
    const mappedVariant =
        variant === 'primary'
            ? 'default'
            : variant === 'ghost'
              ? 'outline'
              : variant === 'danger'
                ? 'destructive'
                : variant;

    return (
        <UiButton
            variant={mappedVariant}
            className={cn('h-9 rounded-lg', className)}
            render={href ? <a href={href} /> : undefined}
            nativeButton={href ? false : nativeButton}
            {...props}
        >
            {children}
        </UiButton>
    );
}

export function Progress({ value, color }: { value: number; color?: string }) {
    return (
        <UiProgress
            value={Math.min(100, Math.max(0, value))}
            className="h-2"
            indicatorClassName="bg-primary"
            indicatorStyle={color ? { backgroundColor: color } : undefined}
        />
    );
}

export function EmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="px-5 py-12 text-center">
            <div className="mx-auto mb-3 grid size-11 place-items-center rounded-xl bg-secondary text-xl text-secondary-foreground">
                +
            </div>
            <p className="text-sm font-semibold text-foreground">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

export { Badge } from '@/components/ui/badge';
