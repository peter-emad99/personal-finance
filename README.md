# Personal Finance OS

A local, single-user personal finance workspace built with Laravel 13, Inertia, React, TypeScript, Tailwind, and the Laravel React starter kit's shadcn-style foundation.

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
- Agent-ready read-only MCP server for financial context, monthly reviews, goals, assets, commitments, liabilities, purchase analysis, and decision exports.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

With Herd, open the project through its local site. For a temporary server:

```bash
php artisan serve
```

The seed contains clearly labeled demo figures based on the product brief. Replace them with your actual data from Assets, Buckets, Goals, and Cash flow before relying on the outputs.

## Verification

```bash
php artisan test
composer run lint:check
composer run types:check
npm run lint:check
npm run types:check
npm run build
```

The app intentionally does not connect to banks, fetch live market prices, execute trades, or give investment advice in this MVP.

## Agent access through MCP

The project includes a read-only Model Context Protocol server over stdio. MCP clients launch it as a local subprocess and can then discuss the same financial context used by the dashboard.

Run it manually to verify the server:

```bash
printf '%s\n' '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"local-test","version":"1"}}}' '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}' | php artisan mcp:serve
```

Example client configuration is in `.mcp.example.json`. The available read-only tools include `get_financial_overview`, `get_monthly_review`, `list_assets`, `list_goals`, `list_recurring_commitments`, `list_liabilities`, `evaluate_purchase`, and `get_decision_context`.

The MCP server deliberately has no write tools yet. This prevents an agent from changing financial records without an explicit, separately designed approval flow.

## Future bank-statement analysis

PDF bank-statement analysis is intentionally not part of this enhancement. The planned extension is a separate ingestion pipeline: upload a PDF, extract transactions into a review queue, let the user confirm categories and recurring items, then use confirmed totals to update a monthly review. Raw PDFs should remain private and should not be exposed through the general MCP context unless explicitly requested.
