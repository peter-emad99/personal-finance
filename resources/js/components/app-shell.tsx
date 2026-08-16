import { Head, Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

const navigation = [
    { href: '/', label: 'Overview', icon: '◒' },
    { href: '/assets', label: 'Assets', icon: '◈' },
    { href: '/buckets', label: 'Buckets', icon: '◌' },
    { href: '/goals', label: 'Goals', icon: '◎' },
    { href: '/monthly-review', label: 'Monthly review', icon: '↗' },
    { href: '/commitments', label: 'Commitments', icon: '◫' },
    { href: '/liabilities', label: 'Liabilities', icon: '−' },
    { href: '/allocations', label: 'Allocations', icon: '▦' },
    { href: '/scenarios', label: 'Scenarios', icon: '⌁' },
    { href: '/snapshots', label: 'Snapshots', icon: '◷' },
];

export function AppShell({
    children,
    title,
}: PropsWithChildren<{ title: string }>) {
    const currentPath = window.location.pathname;

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-[#f5f6f8] text-[#17202b]">
                <aside className="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r border-[#e5e8ee] bg-[#101928] px-5 py-6 text-white lg:flex">
                    <Link
                        href="/"
                        className="mb-10 flex items-center gap-3 px-2"
                    >
                        <span className="grid h-10 w-10 place-items-center rounded-xl bg-[#a8b7ff] text-lg font-bold text-[#16213a]">
                            P
                        </span>
                        <span>
                            <span className="block text-sm font-semibold tracking-wide">
                                PERSONAL
                            </span>
                            <span className="block text-xs text-[#9aa8c2]">
                                finance OS
                            </span>
                        </span>
                    </Link>
                    <nav className="space-y-1">
                        {navigation.map((item) => {
                            const active =
                                item.href === '/'
                                    ? currentPath === '/'
                                    : currentPath.startsWith(item.href);

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`flex items-center gap-3 rounded-xl px-3 py-3 text-sm transition ${active ? 'bg-[#293752] font-semibold text-white' : 'text-[#aeb8c9] hover:bg-[#1a2639] hover:text-white'}`}
                                >
                                    <span className="w-5 text-center text-base text-[#a8b7ff]">
                                        {item.icon}
                                    </span>
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                    <div className="mt-auto rounded-2xl border border-[#2b3951] bg-[#17243a] p-4">
                        <p className="text-xs font-semibold text-[#cdd5e5]">
                            Decision context
                        </p>
                        <p className="mt-2 text-xs leading-5 text-[#8f9db4]">
                            Export a clean snapshot before discussing your next
                            financial decision.
                        </p>
                        <div className="mt-3 flex gap-2">
                            <a
                                href="/export/context?format=markdown"
                                className="rounded-lg bg-[#a8b7ff] px-2.5 py-2 text-[11px] font-semibold text-[#16213a]"
                            >
                                Markdown
                            </a>
                            <a
                                href="/export/context"
                                className="rounded-lg border border-[#51617b] px-2.5 py-2 text-[11px] font-semibold text-[#d8def0]"
                            >
                                JSON
                            </a>
                        </div>
                    </div>
                </aside>
                <main className="lg:pl-64">
                    <div className="mx-auto max-w-[1500px] px-5 py-6 sm:px-8 lg:px-10 lg:py-9">
                        <nav className="mb-6 flex gap-2 overflow-x-auto pb-1 lg:hidden">
                            {navigation.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`shrink-0 rounded-lg px-3 py-2 text-xs font-semibold ${currentPath === item.href ? 'bg-[#1c2a45] text-white' : 'bg-white text-[#667286]'}`}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                        {children}
                    </div>
                </main>
            </div>
        </>
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
    action?: React.ReactNode;
}) {
    return (
        <header className="mb-8 flex flex-col justify-between gap-5 md:flex-row md:items-end">
            <div>
                <p className="mb-2 text-xs font-bold tracking-[0.18em] text-[#7787d9] uppercase">
                    {eyebrow ?? 'Personal finance OS'}
                </p>
                <h1 className="text-3xl font-semibold tracking-tight text-[#121b2a] md:text-4xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-[#6b7687]">
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
}: PropsWithChildren<{ className?: string }>) {
    return (
        <section
            className={`rounded-2xl border border-[#e4e7ed] bg-white shadow-[0_8px_30px_rgba(26,42,70,0.03)] ${className}`}
        >
            {children}
        </section>
    );
}
export function CardHeader({
    title,
    meta,
    action,
}: {
    title: string;
    meta?: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-[#eef0f4] px-5 py-4">
            <div>
                <h2 className="text-sm font-semibold text-[#202a39]">
                    {title}
                </h2>
                {meta && <p className="mt-1 text-xs text-[#8993a3]">{meta}</p>}
            </div>
            {action}
        </div>
    );
}
export function Button({
    children,
    href,
    type = 'button',
    variant = 'primary',
    onClick,
}: PropsWithChildren<{
    href?: string;
    type?: 'button' | 'submit';
    variant?: 'primary' | 'ghost' | 'danger';
    onClick?: () => void;
}>) {
    const className = `inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition ${variant === 'primary' ? 'bg-[#1f2b45] text-white hover:bg-[#2d3b5b]' : variant === 'danger' ? 'bg-[#fff0f1] text-[#cc4a5d] hover:bg-[#ffe1e4]' : 'border border-[#dfe3ea] bg-white text-[#516075] hover:bg-[#f7f8fa]'}`;

    return href ? (
        <a href={href} className={className}>
            {children}
        </a>
    ) : (
        <button type={type} onClick={onClick} className={className}>
            {children}
        </button>
    );
}
export function Progress({
    value,
    color = '#7c8cf8',
}: {
    value: number;
    color?: string;
}) {
    return (
        <div className="h-2 overflow-hidden rounded-full bg-[#edf0f5]">
            <div
                className="h-full rounded-full transition-all"
                style={{
                    width: `${Math.min(100, Math.max(0, value))}%`,
                    backgroundColor: color,
                }}
            />
        </div>
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
            <div className="mx-auto mb-3 grid h-11 w-11 place-items-center rounded-2xl bg-[#eef0ff] text-xl text-[#7181d9]">
                +
            </div>
            <p className="text-sm font-semibold text-[#273246]">{title}</p>
            <p className="mx-auto mt-1 max-w-sm text-xs leading-5 text-[#8a94a3]">
                {description}
            </p>
        </div>
    );
}
