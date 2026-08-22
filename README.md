# Personal Finance OS

A local, single-user personal finance workspace built with Laravel 13, Inertia, React, TypeScript, Tailwind, and shadcn/ui components backed by Base UI primitives.

## Included MVP

- Overview dashboard: net worth, investable net worth, liquidity, cash flow, emergency coverage, allocation, and rules-based signals.
- Assets: cash, USD, gold, equities, funds, deposits, current value, cost basis, liquidity, and bucket allocation.
- Buckets: separate ownership from purpose; one asset can be split across multiple buckets.
- Goals: target, deadline, funding pace, required monthly contribution, and on/off-track status.
- Cash flow: monthly income, expenses, obligations, and free cash flow.
- Monthly allocation: planned versus actual funding by bucket.
- Scenario planner: cash purchase versus partial financing, payment, interest, liquidity, and coverage.
- Historical snapshots and JSON/Markdown decision-context exports.
- Monthly financial reviews: editable month totals, history, planned direction, and invested amount.
- Recurring commitments: subscriptions, renewals, utilities, insurance, and other predictable obligations with monthly and annual equivalents.
- Liabilities: balances, rates, payments, payoff dates, and true net worth after debt.
- Daily market sync: USD/EGP and 24K gold spot estimate per gram in EGP, with automatic marking of USD/gold assets that have a quantity.
- Agent-ready local MCP owner control plane for financial context, calculations, and validated CRUD/archive/restore mutations with audit and dashboard reflection.

## UI system

The interface uses the generated shadcn/ui component source under `resources/js/components/ui`. The current Base UI-backed primitives include the responsive Sidebar, Button, Card, Dialog, Input, Label, Select, Checkbox, Progress, Badge, Table, Sheet, Scroll Area, Dropdown Menu, Tooltip, and related form/layout primitives. Shared application wrappers compose those primitives so pages keep a consistent visual language while remaining easy to customize.

To add another component, use the shadcn CLI from the project root:

```bash
npx shadcn@latest add <component>
```

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

On a fresh or upgraded database, bootstrap the owner before opening the
dashboard or starting MCP. Set `FINANCE_OWNER_EMAIL`, `FINANCE_OWNER_NAME`, and
`FINANCE_OWNER_PASSWORD` in `.env` before the first migration where possible,
or rotate/create the account interactively:

```bash
php artisan finance:bootstrap-owner owner@example.test --name="Your name"
```

The Phase 3 migration creates one local owner when none exists and backfills
that owner onto every existing financial record (including ledger/import,
allocation, valuation, policy, snapshot, and audit rows). It also changes
single-user unique keys to include `user_id`. Review the backfill owner before
hosting an existing database for multiple people; records with a shared legacy
account must be exported and reassigned deliberately rather than guessed.

With Herd, open the project through its local site. For a temporary server:

```bash
php artisan serve
```

The scheduler updates market rates daily at 04:00 Africa/Cairo time. Keep the
Laravel scheduler running in development or configure the standard scheduler
cron in production:

```bash
php artisan schedule:work
php artisan finance:update-market-rates
```

The USD/EGP feed uses ExchangeRate-API's open daily endpoint and the gold feed
uses Gold API's public XAU spot endpoint. The dashboard stores the latest
successful values locally and marks them stale after 48 hours. Gold is a 24K
spot estimate per gram; Egyptian dealer premiums, workmanship, and taxes are
not included.

## Guided personal-finance setup order

Use this checklist in order. Complete one step, verify the dashboard, then move
to the next step. Do not rely on the seeded demo figures for decisions.

- [x] 1. Configure the private local owner and environment in `.env`.
- [ ] 2. Confirm the financial policy: base currency, emergency reserve, liquidity rules, allocation targets, and purchase guardrails.
- [ ] 3. Add real accounts: banks, cash wallets, brokerages, cards, and investment accounts.
- [ ] 4. Add transaction categories for income, expenses, transfers, investments, fees, taxes, and debt payments.
- [ ] 5. Replace or archive demo assets, then add real assets with value, cost basis, currency, liquidity, account, and bucket allocations.
- [ ] 6. Add liabilities with current balance, interest rate, payment, due day, and payoff date.
- [ ] 7. Configure purpose buckets and goals, including target amounts, priorities, deadlines, and monthly contributions.
- [ ] 8. Add recurring commitments such as rent, utilities, insurance, subscriptions, and annual obligations.
- [ ] 9. Import transaction history through the pending-row review workflow; accept only verified rows.
- [ ] 10. Add dated asset valuations, liability histories, and FX rates for historical reporting.
- [ ] 11. Reconcile the month, derive the monthly review, and close it only after checking the source data.
- [ ] 12. Create and verify encrypted backups, then run regular integrity checks.
- [ ] 13. Connect the local MCP client using `.mcp.example.json` and keep the server local-only.

The guided setup will request data in the same order. Sensitive credentials belong
only in `.env`; never put passwords, bank credentials, or private account numbers
in this document or in MCP notes.

Session cookies default to `Secure` when `APP_ENV=production` and remain
usable over local HTTP in `local` or `testing`. Leave `SESSION_SECURE_COOKIE`
blank to use that environment-aware default; set it explicitly to `true` for
any HTTPS deployment and never override it to `false` in production.

The seed contains clearly labeled demo figures based on the product brief. Replace them with your actual data from Assets, Buckets, Goals, and Cash flow before relying on the outputs.

### Demo workspace

Local and testing seeds also create a separate demo account with six months of salary, expenses, emergency savings, a goal, investments, a liability, monthly plans, reviews, snapshots, and decision-journal context:

- Email: `demo@finance.local`
- Password: `demo-finance-2026`

The demo account is controlled by `FINANCE_SEED_DEMO`, `FINANCE_DEMO_EMAIL`, `FINANCE_DEMO_NAME`, and `FINANCE_DEMO_PASSWORD`. Disable demo seeding before any public or production deployment; these credentials are intentionally for local learning only.

## Verification

```bash
php artisan test
composer run lint:check
composer run types:check
npm run lint:check
npm run types:check
npm run build
```

The app intentionally does not connect to banks, execute trades, or give investment advice in this MVP. Market data is fetched only by the scheduled server-side sync and is used as an informational reference.

## Agent access through MCP

The project includes a local Model Context Protocol server over stdio. MCP clients launch it as a local subprocess and can inspect or manage the same owner-scoped financial records used by the dashboard. Mutations are explicit, validated, transactional, soft-archived by default, and return an audit id plus a reflected dashboard delta.

Run it manually to verify the server:

```bash
printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"local-test","version":"1"}}}' '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' | php artisan mcp:serve
```

Example client configuration is in `.mcp.example.json`. The server includes dashboard/context tools, explicit CRUD/archive/restore tools for current entities, CSV review-queue ingestion, asset and bucket allocation management, ledger-derived allocation actuals, monthly review close/reopen and next-month planning, historical snapshots, backups, integrity checks, purchase analysis, redacted context, and audit-log inspection. Historical `as_of` inputs are intentionally supported only when dated valuation, liability-history, and ledger sources are complete.

The MCP control plane is intentionally bounded to this local finance domain. It does not log users in, change application passwords, connect to banks, execute trades, or expose an unauthenticated network endpoint. Those boundaries are product and security constraints, not missing MCP tools.

MCP is intentionally local-only: `php artisan mcp:serve` resolves the
bootstrapped owner and refuses to start when it is missing. No unauthenticated
remote MCP endpoint is exposed. A hosted deployment must add an authenticated
transport and a review of agent scopes before enabling external access.

## Backups and operations

`/operations` (or the MCP `create_backup`, `verify_backup`, and
`validate_data_integrity` tools) creates encrypted SQLite backups under the
private local disk, records checksums/retention, and verifies decryption before
the archive is trusted. Keep `APP_KEY` backed up separately: without it an
encrypted archive cannot be restored. The scheduled
`finance:verify-operations` command checks integrity and the latest backup;
failed imports/backups are logged with a request id. SQLite backups are
supported by this workflow; configure a database-native dump and restore test
before production MySQL/PostgreSQL hosting. Expired archives are never purged
implicitly; review retention first, then run
`php artisan finance:purge-expired-backups --force` (or the MCP
`purge_expired_backups` tool with `confirm=true`).

## Future bank-statement analysis

PDF bank-statement analysis is intentionally not part of this enhancement. The planned extension is a separate ingestion pipeline: upload a PDF, extract transactions into a review queue, let the user confirm categories and recurring items, then use confirmed totals to update a monthly review. Raw PDFs should remain private and should not be exposed through the general MCP context unless explicitly requested.
