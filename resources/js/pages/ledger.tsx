import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AppShell,
    Badge,
    Button,
    Card,
    CardHeader,
    EmptyState,
    PageHeader,
} from '@/components/app-shell';
import { FormModal } from '@/components/form';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatEGP } from '@/types/finance';

type Account = {
    id: number;
    name: string;
    type: string;
    currency: string;
    opening_balance_egp: number;
    reported_balance_egp?: number | null;
    ledger_balance_egp?: number;
};
type Transaction = {
    id: number;
    occurred_on: string;
    description?: string | null;
    transaction_type: string;
    amount_egp: number;
    review_state: string;
    account?: Account | null;
    category?: { name: string } | null;
    purpose_bucket_id?: number | null;
    purpose_bucket?: { name: string } | null;
};
type PurposeBucket = { id: number; name: string; goal_id?: number | null };
type ImportRow = {
    id: number;
    row_number: number;
    description?: string | null;
    amount?: number | null;
    review_state: string;
    duplicate_of_id?: number | null;
};
type ImportBatch = {
    id: number;
    file_name?: string | null;
    status: string;
    rows: ImportRow[];
};
type Reconciliation = {
    status: string;
    pendingImports: number;
    accounts: Array<{
        id: number;
        name: string;
        status: string;
        difference: number | null;
    }>;
};

export default function Ledger({
    accounts,
    transactions,
    imports,
    reconciliation,
    buckets,
}: {
    accounts: Account[];
    transactions: Transaction[];
    categories: unknown[];
    imports: ImportBatch[];
    reconciliation: Reconciliation;
    buckets: PurposeBucket[];
}) {
    const [accountOpen, setAccountOpen] = useState(false);
    const [transactionOpen, setTransactionOpen] = useState(false);
    const [csvOpen, setCsvOpen] = useState(false);
    const [account, setAccount] = useState({
        name: '',
        type: 'bank',
        currency: 'EGP',
        opening_balance_egp: '0',
        reported_balance_egp: '',
        reported_balance_as_of: '',
        notes: '',
    });
    const [transaction, setTransaction] = useState({
        account_id: '',
        transaction_type: 'expense',
        occurred_on: new Date().toISOString().slice(0, 10),
        description: '',
        amount: '',
        currency: 'EGP',
        amount_egp: '',
        purpose_bucket_id: '',
        notes: '',
    });
    const [csv, setCsv] = useState(
        'date,description,amount,currency,transaction_type\n',
    );
    const updateAccountForm = (key: string, value: string) =>
        setAccount((current) => ({ ...current, [key]: value }));
    const updateTransactionForm = (key: string, value: string) =>
        setTransaction((current) => ({ ...current, [key]: value }));

    return (
        <AppShell title="Ledger and imports">
            <PageHeader
                eyebrow="Financial history"
                title="Ledger and reconciliation"
                description="Confirmed ledger entries are the single source for reviewed months. CSV imports stay in a review queue until you explicitly accept each row."
                action={
                    <div className="flex gap-2">
                        <Button variant="ghost" href="/reconciliation">
                            Reconciliation
                        </Button>
                        <Button onClick={() => setCsvOpen(true)}>
                            Review CSV
                        </Button>
                    </div>
                }
            />
            <div className="mb-4 grid gap-4 sm:grid-cols-3">
                <Summary label="Accounts" value={String(accounts.length)} />
                <Summary
                    label="Pending import rows"
                    value={String(reconciliation.pendingImports)}
                />
                <Summary label="Month status" value={reconciliation.status} />
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader
                        title="Accounts"
                        meta="Reported balances are compared with confirmed ledger entries."
                        action={
                            <Button
                                size="sm"
                                onClick={() => setAccountOpen(true)}
                            >
                                Add account
                            </Button>
                        }
                    />
                    <div className="divide-y divide-border">
                        {accounts.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between gap-3 p-4"
                            >
                                <div>
                                    <p className="text-sm font-semibold">
                                        {item.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {item.type} · {item.currency}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-semibold">
                                        {formatEGP(
                                            Number(
                                                item.ledger_balance_egp ??
                                                    item.opening_balance_egp,
                                            ),
                                        )}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {item.reported_balance_egp == null
                                            ? 'No statement balance'
                                            : `Reported ${formatEGP(Number(item.reported_balance_egp))}`}
                                    </p>
                                </div>
                            </div>
                        ))}
                        {!accounts.length && (
                            <EmptyState
                                title="No accounts yet"
                                description="Add the accounts that own your cash and investments before importing transactions."
                            />
                        )}
                    </div>
                </Card>
                <Card>
                    <CardHeader
                        title="Confirmed ledger"
                        meta="Transfers are marked separately and never counted as income or spending."
                        action={
                            <Button
                                size="sm"
                                onClick={() => setTransactionOpen(true)}
                            >
                                Add transaction
                            </Button>
                        }
                    />
                    <div className="divide-y divide-border">
                        {transactions.map((item) => (
                            <div
                                key={item.id}
                                className="flex items-center justify-between gap-3 p-4"
                            >
                                <div>
                                    <div className="flex items-center gap-2">
                                        <p className="text-sm font-semibold">
                                            {item.description ||
                                                'Untitled transaction'}
                                        </p>
                                        <Badge variant="secondary">
                                            {item.review_state}
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {item.occurred_on} ·{' '}
                                        {item.transaction_type} ·{' '}
                                        {item.account?.name ?? 'Unassigned'}
                                        {item.purpose_bucket?.name
                                            ? ` · ${item.purpose_bucket.name}`
                                            : ''}
                                    </p>
                                </div>
                                <p className="text-sm font-semibold">
                                    {formatEGP(Number(item.amount_egp))}
                                </p>
                            </div>
                        ))}
                        {!transactions.length && (
                            <EmptyState
                                title="No transactions this month"
                                description="Add a confirmed entry or import a CSV for review."
                            />
                        )}
                    </div>
                </Card>
            </div>
            <Card className="mt-4">
                <CardHeader
                    title="Import review queue"
                    meta="Rows are pending until accepted or rejected."
                />
                {imports.length ? (
                    <div className="divide-y divide-border">
                        {imports.map((batch) => (
                            <div key={batch.id} className="p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold">
                                            {batch.file_name ||
                                                `Import batch ${batch.id}`}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {batch.rows.length} rows ·{' '}
                                            {batch.status}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        No silent posting
                                    </Badge>
                                </div>
                                <div className="mt-3 flex flex-col gap-2">
                                    {batch.rows
                                        .filter(
                                            (row) =>
                                                row.review_state ===
                                                    'pending' ||
                                                row.review_state ===
                                                    'duplicate',
                                        )
                                        .slice(0, 12)
                                        .map((row) => (
                                            <div
                                                key={row.id}
                                                className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-muted/40 p-3 text-sm"
                                            >
                                                <span>
                                                    Row {row.row_number}:{' '}
                                                    {row.description ||
                                                        'Untitled'}{' '}
                                                    ·{' '}
                                                    {formatEGP(
                                                        Number(row.amount || 0),
                                                    )}{' '}
                                                    {row.duplicate_of_id
                                                        ? '(duplicate)'
                                                        : ''}
                                                </span>
                                                <span className="flex gap-2">
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            router.post(
                                                                `/ledger/import-rows/${row.id}/accept`,
                                                            )
                                                        }
                                                        disabled={
                                                            row.duplicate_of_id !=
                                                            null
                                                        }
                                                    >
                                                        Accept
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            router.post(
                                                                `/ledger/import-rows/${row.id}/reject`,
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </Button>
                                                </span>
                                            </div>
                                        ))}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        title="No imports"
                        description="Paste a CSV to create a reviewable, deduplicated queue."
                    />
                )}
            </Card>
            {accountOpen && (
                <FormModal
                    title="Add account"
                    onClose={() => setAccountOpen(false)}
                >
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.post('/ledger/accounts', account, {
                                onSuccess: () => setAccountOpen(false),
                            });
                        }}
                    >
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="account-name">
                                    Name
                                </FieldLabel>
                                <Input
                                    id="account-name"
                                    required
                                    value={account.name}
                                    onChange={(event) =>
                                        updateAccountForm(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="account-type">
                                    Type
                                </FieldLabel>
                                <Input
                                    id="account-type"
                                    value={account.type}
                                    onChange={(event) =>
                                        updateAccountForm(
                                            'type',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="account-currency">
                                    Currency
                                </FieldLabel>
                                <Input
                                    id="account-currency"
                                    maxLength={3}
                                    value={account.currency}
                                    onChange={(event) =>
                                        updateAccountForm(
                                            'currency',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="account-opening">
                                    Opening balance (EGP)
                                </FieldLabel>
                                <Input
                                    id="account-opening"
                                    type="number"
                                    step="0.01"
                                    value={account.opening_balance_egp}
                                    onChange={(event) =>
                                        updateAccountForm(
                                            'opening_balance_egp',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </FieldGroup>
                        <Button type="submit">Save account</Button>
                    </form>
                </FormModal>
            )}
            {transactionOpen && (
                <FormModal
                    title="Add confirmed transaction"
                    description="Manual entries are explicitly marked confirmed; imported entries require queue review."
                    onClose={() => setTransactionOpen(false)}
                >
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.post('/ledger/transactions', transaction, {
                                onSuccess: () => setTransactionOpen(false),
                            });
                        }}
                    >
                        <FieldGroup>
                            <Field>
                                <FieldLabel htmlFor="transaction-type">
                                    Type
                                </FieldLabel>
                                <Input
                                    id="transaction-type"
                                    value={transaction.transaction_type}
                                    onChange={(event) =>
                                        updateTransactionForm(
                                            'transaction_type',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="transaction-date">
                                    Date
                                </FieldLabel>
                                <Input
                                    id="transaction-date"
                                    type="date"
                                    value={transaction.occurred_on}
                                    onChange={(event) =>
                                        updateTransactionForm(
                                            'occurred_on',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="transaction-description">
                                    Description
                                </FieldLabel>
                                <Input
                                    id="transaction-description"
                                    value={transaction.description}
                                    onChange={(event) =>
                                        updateTransactionForm(
                                            'description',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="transaction-amount">
                                    Amount
                                </FieldLabel>
                                <Input
                                    id="transaction-amount"
                                    required
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={transaction.amount}
                                    onChange={(event) =>
                                        updateTransactionForm(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor="transaction-purpose">
                                    Purpose bucket (optional)
                                </FieldLabel>
                                <Select
                                    value={transaction.purpose_bucket_id || 'auto'}
                                    onValueChange={(value) =>
                                        updateTransactionForm(
                                            'purpose_bucket_id',
                                            value === 'auto' ? '' : String(value ?? ''),
                                        )
                                    }
                                >
                                    <SelectTrigger id="transaction-purpose">
                                        <SelectValue placeholder="Auto-classify when possible" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="auto">
                                                Auto-classify when possible
                                            </SelectItem>
                                            {buckets.map((bucket) => (
                                                <SelectItem
                                                    key={bucket.id}
                                                    value={String(bucket.id)}
                                                >
                                                    {bucket.name}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                        </FieldGroup>
                        <Button type="submit">Record transaction</Button>
                    </form>
                </FormModal>
            )}
            {csvOpen && (
                <FormModal
                    title="Queue CSV import"
                    description="Expected columns: date, description, amount, currency, transaction_type. Rows are never posted automatically."
                    onClose={() => setCsvOpen(false)}
                >
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.post(
                                '/ledger/imports',
                                { file_name: 'pasted.csv', csv },
                                { onSuccess: () => setCsvOpen(false) },
                            );
                        }}
                    >
                        <Field>
                            <FieldLabel htmlFor="ledger-csv">
                                CSV data
                            </FieldLabel>
                            <Textarea
                                id="ledger-csv"
                                rows={10}
                                value={csv}
                                onChange={(event) => setCsv(event.target.value)}
                            />
                        </Field>
                        <Button type="submit">Queue for review</Button>
                    </form>
                </FormModal>
            )}
        </AppShell>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <Card>
            <div className="p-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="mt-2 text-xl font-semibold">{value}</p>
            </div>
        </Card>
    );
}
