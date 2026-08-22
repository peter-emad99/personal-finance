import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Calculator,
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
    Sun,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps, PropsWithChildren, ReactNode } from 'react';

import { ExportContextActions } from '@/components/export-context-actions';
import { ThemeProvider, useTheme } from '@/components/theme-provider';
import type { ThemeMode } from '@/components/theme-provider';
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

const navigationGroups: NavigationGroup[] = [
    {
        label: 'Your money',
        items: [
            { href: '/', label: 'Dashboard', icon: LayoutDashboard },
            { href: '/learn', label: 'Learn the system', icon: GraduationCap },
            { href: '/cash-flow', label: 'Income & expenses', icon: Gauge },
            { href: '/allocations', label: 'Monthly plan', icon: ListChecks },
            { href: '/goals', label: 'Goals', icon: Flag },
            { href: '/assets', label: 'What you own', icon: WalletCards },
        ],
    },
    {
        label: 'Manage',
        items: [
            { href: '/buckets', label: 'Purpose buckets', icon: Database },
            { href: '/commitments', label: 'Commitments', icon: Repeat2 },
            { href: '/liabilities', label: 'Liabilities', icon: CreditCard },
            { href: '/monthly-review', label: 'Monthly review', icon: Gauge },
            {
                href: '/scenarios',
                label: 'Purchase scenarios',
                icon: Calculator,
            },
        ],
    },
    {
        label: 'Advanced',
        items: [
            { href: '/ledger', label: 'Ledger & imports', icon: BookOpen },
            {
                href: '/transaction-categories',
                label: 'Categories',
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
                href: '/reconciliation',
                label: 'Reconciliation',
                icon: ListChecks,
            },
            {
                href: '/allocation-reconciliation',
                label: 'Reconcile purposes',
                icon: ListChecks,
            },
            { href: '/snapshots', label: 'Snapshots', icon: FileClock },
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
    }>();
    const currentPath = page.url.split('?')[0];
    const { flash } = page.props;

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
                            <div className="ml-auto">
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
        <UiCardHeader className="border-b px-5 py-4">
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
            {action && <div className="self-start">{action}</div>}
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
