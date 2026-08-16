import { router } from '@inertiajs/react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    PageHeader,
} from '@/components/app-shell';
type Category = {
    id: number;
    name: string;
    kind: string;
    parent_name?: string | null;
};
export default function TransactionCategories({
    categories,
}: {
    categories: Category[];
}) {
    return (
        <AppShell title="Transaction categories">
            <PageHeader
                eyebrow="Ledger controls"
                title="Transaction categories"
                description="Keep categorisation controlled and reviewable so monthly totals remain explainable."
                action={
                    <Button href="/ledger" variant="ghost">
                        Back to ledger
                    </Button>
                }
            />
            <Card>
                <CardHeader
                    title="Categories"
                    meta="Categories are reference records used by confirmed transactions and imports."
                />
                <div className="divide-y divide-border">
                    {categories.map((category) => (
                        <div
                            key={category.id}
                            className="flex items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p className="text-sm font-semibold">
                                    {category.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {category.parent_name || 'Top level'}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant="secondary">
                                    {category.kind}
                                </Badge>
                                <Button
                                    size="sm"
                                    variant="danger"
                                    onClick={() =>
                                        router.delete(
                                            `/transaction-categories/${category.id}`,
                                        )
                                    }
                                >
                                    Archive
                                </Button>
                            </div>
                        </div>
                    ))}
                    {!categories.length && (
                        <p className="p-5 text-sm text-muted-foreground">
                            No categories yet. Imports can create named
                            categories during queueing.
                        </p>
                    )}
                </div>
            </Card>
        </AppShell>
    );
}
