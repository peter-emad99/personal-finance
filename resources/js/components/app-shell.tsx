import { Head, Link } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    Calculator,
    CreditCard,
    Database,
    FileClock,
    Flag,
    Gauge,
    LayoutDashboard,
    ListChecks,
    Monitor,
    Moon,
    Repeat2,
    Sun,
    WalletCards,
} from 'lucide-react';
import type { ComponentProps, PropsWithChildren, ReactNode } from 'react';

import { ThemeProvider, useTheme } from '@/components/theme-provider';
import type { ThemeMode } from '@/components/theme-provider';
import { Button as UiButton } from '@/components/ui/button';
import {
    Card as UiCard,
    CardDescription as UiCardDescription,
    CardHeader as UiCardHeader,
    CardTitle as UiCardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Progress as UiProgress } from '@/components/ui/progress';
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
    SidebarSeparator,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const navigation = [
    { href: '/', label: 'Overview', icon: LayoutDashboard },
    { href: '/assets', label: 'Assets', icon: WalletCards },
    { href: '/buckets', label: 'Buckets', icon: Database },
    { href: '/goals', label: 'Goals', icon: Flag },
    { href: '/monthly-review', label: 'Monthly review', icon: Gauge },
    { href: '/commitments', label: 'Commitments', icon: Repeat2 },
    { href: '/liabilities', label: 'Liabilities', icon: CreditCard },
    { href: '/allocations', label: 'Allocations', icon: ListChecks },
    { href: '/scenarios', label: 'Scenarios', icon: Calculator },
    { href: '/snapshots', label: 'Snapshots', icon: FileClock },
];

function AppSidebar({ currentPath }: { currentPath: string }) {
    return (
        <Sidebar collapsible="icon">
            <SidebarHeader className="p-3 md:p-4">
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
            <SidebarSeparator />
            <SidebarContent className="px-2">
                <SidebarGroup className="px-2 py-3">
                    <SidebarGroupLabel>Workspace</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu className="gap-1.5">
                            {navigation.map((item) => {
                                const active =
                                    item.href === '/'
                                        ? currentPath === '/'
                                        : currentPath.startsWith(item.href);
                                const Icon = item.icon;

                                return (
                                    <SidebarMenuItem key={item.href}>
                                        <SidebarMenuButton
                                            render={<Link href={item.href} />}
                                            isActive={active}
                                            className="h-10 px-3"
                                            tooltip={item.label}
                                        >
                                            <Icon />
                                            <span>{item.label}</span>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
            <SidebarFooter className="gap-3 p-3 md:p-4">
                <div className="rounded-xl border border-sidebar-border bg-sidebar-accent/40 p-3 group-data-[collapsible=icon]:hidden">
                    <div className="flex items-center gap-2 text-xs font-semibold text-sidebar-foreground">
                        <BriefcaseBusiness className="size-3.5 text-sidebar-primary" />
                        Decision context
                    </div>
                    <p className="mt-2 text-xs leading-5 text-sidebar-foreground/60">
                        Export a clean snapshot before discussing your next
                        financial decision.
                    </p>
                    <div className="mt-3 flex gap-2">
                        <a
                            href="/export/context?format=markdown"
                            className="rounded-md bg-sidebar-primary px-2.5 py-1.5 text-[11px] font-semibold text-sidebar-primary-foreground"
                        >
                            Markdown
                        </a>
                        <a
                            href="/export/context"
                            className="rounded-md border border-sidebar-border px-2.5 py-1.5 text-[11px] font-semibold text-sidebar-foreground"
                        >
                            JSON
                        </a>
                    </div>
                </div>
                <ThemeMenu inSidebar />
            </SidebarFooter>
        </Sidebar>
    );
}

export function AppShell({
    children,
    title,
}: PropsWithChildren<{ title: string }>) {
    const currentPath = window.location.pathname;

    return (
        <ThemeProvider>
            <TooltipProvider>
                <Head title={title} />
                <SidebarProvider>
                    <AppSidebar currentPath={currentPath} />
                    <SidebarInset>
                        <header className="flex h-14 shrink-0 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur-sm md:hidden">
                            <SidebarTrigger />
                            <span className="text-sm font-semibold text-foreground">
                                Personal finance OS
                            </span>
                            <div className="ml-auto">
                                <ThemeMenu />
                            </div>
                        </header>
                        <main className="min-h-screen">
                            <div className="mx-auto max-w-[1500px] px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
                                {children}
                            </div>
                        </main>
                    </SidebarInset>
                </SidebarProvider>
            </TooltipProvider>
        </ThemeProvider>
    );
}

function ThemeMenu({ inSidebar = false }: { inSidebar?: boolean }) {
    const { theme, setTheme } = useTheme();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <UiButton
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Choose theme"
                        className={cn(
                            inSidebar
                                ? 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        )}
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
                <DropdownMenuLabel>Theme</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {(['system', 'light', 'dark'] as ThemeMode[]).map((mode) => (
                    <DropdownMenuItem
                        key={mode}
                        onClick={() => setTheme(mode)}
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
                        {theme === mode && (
                            <span className="ml-auto text-xs text-muted-foreground">
                                Active
                            </span>
                        )}
                    </DropdownMenuItem>
                ))}
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
