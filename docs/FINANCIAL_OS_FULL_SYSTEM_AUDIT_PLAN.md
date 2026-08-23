# Personal Finance OS — Full-System Audit and Rebuild Plan

Status: implementation complete; owner re-plan intentionally pending  
Owner data policy: preserve existing owner data; no reset or destructive rebuild without an explicit backup and approval.

## Objective

Bring the whole application into one coherent model:

```text
Income rules
  -> selected plan template (income + expense rules/categories + savings allocations)
  -> remaining cash
  -> asset target + purpose bucket (+ optional goal)
  -> monthly snapshot
  -> actual transactions
  -> month-end review and close
```

The dynamic monthly template is operational planning. Financial Policy remains a separate set of guardrails such as emergency coverage, debt limits, liquidity rules, and portfolio ranges.

## Non-destructive rules

- Never run `migrate:fresh`, `db:wipe`, reset, or demo reset against the owner account.
- Take and verify a backup before any owner-data migration.
- Demo data must be isolated by user and resettable without touching the owner.
- A planned amount never creates an actual transaction or moves an asset balance.
- A month is not silently marked closed if actuals are missing; closing must preserve a snapshot and provenance.
- Historical snapshots remain immutable except through an explicit revision flow.

## Audit matrix

| Area | Audit question | Current assessment | Decision |
|---|---|---|---|
| Database and migrations | Are all new records owner-scoped, reversible, and indexed? | Dynamic budget tables, template links, asset links, and snapshot fields are migrated and indexed. | Keep; do a separate fresh/rollback rehearsal before production. |
| Owner isolation | Can web, console, MCP, and demo users see only their own records? | Existing `BelongsToUser` boundary is present; new budget models need the same boundary tests. | Keep model, expand tests. |
| Income rules | Can recurring income be configured and copied into each monthly snapshot? | Multiple income rules are template-scoped and copied into monthly plan snapshots. | Keep. |
| Expense categories | Are default categories created per user and editable? | Four per-user defaults, CRUD UI, active/default flags, archive/restore, and rule selection exist. | Keep. |
| Expense plan | Are planned expenses separate from actual expenses? | Expense snapshot items are used by the monthly plan, review, dashboard planned categories, actual synchronization, and history. | Keep. |
| Savings allocation | Can a percentage calculate a planned amount for an asset target and bucket? | Template rules use percentage -> asset -> assigned bucket; monthly snapshots preserve the selected asset and bucket. | Keep. |
| Asset model | Does an asset represent what is actually owned? | Existing asset model is appropriate. | Keep. |
| Purpose bucket | Does a bucket represent purpose, with optional goal linkage? | Existing bucket/goal relation is appropriate. | Keep. |
| Actual ledger | Can actual income and expenses be categorized and synced without inventing data? | Confirmed-ledger flow exists; expense synchronization needs full UI verification. | Keep, verify and harden. |
| Month close | Does closing preserve a complete plan/actual snapshot and prepare the next month? | Close is protected, status/closed_at are stored, and monthly history exposes planned vs actual values. | Keep; actual entry remains an explicit user action. |
| Financial Policy | Is it limited to guardrails rather than operational monthly allocation? | Monthly allocation targets were removed from the UI and analytics; policy now supplies guardrails and thresholds only. | Keep. |
| Dashboard | Does it show the new plan, actuals, variance, and unassigned cash? | Saved monthly snapshots are the planned source for income sources, expenses, allocations, totals, variance, and dashboard planned ratios. | Keep; actuals remain ledger/review sourced. |
| Navigation | Can a user understand plan, tracking, portfolio, and analysis separately? | AppShell is grouped into Plan, Track, Portfolio, Analyze, and Settings. | Keep. |
| Demo seeder | Does demo data exercise every new workflow and remain isolated? | Demo seed now covers three templates, four categories, linked rules, twelve monthly plans, and expense snapshots. | Keep; reset is scoped to the demo user. |
| MCP | Can the agent manage the same domain objects and see the same projections as the UI? | MCP supports templates, rules, categories, allocation asset/bucket validation, monthly-plan close, and matching payload context. | Keep; add more parity tests as new fields are introduced. |
| Exports/context | Does exported context explain planned, actual, assets, buckets, goals, and income snapshots consistently? | Allocation plan exports and MCP payloads include income snapshots, expense snapshots, and allocation items. | Keep; add fields only when the domain grows. |
| Automation | Are scheduled actions safe and actually runnable? | Laravel scheduling exists; scheduler availability and close behavior need verification. | Verify; do not auto-create actuals. |
| Accessibility/UI | Are the new controls understandable and usable on small screens? | Allocation screen was extended; system-wide consistency is pending. | Audit after information architecture. |

## Canonical information architecture

### Plan

- Dashboard
- Monthly plan
- Monthly review

### Track

- Ledger / actual transactions
- Commitments
- Liabilities

### Portfolio

- Assets
- Purpose buckets
- Goals

### Analyze

- Reconciliation
- Scenarios
- Snapshots / history

### Settings

- Income and expense rules
- Budget categories
- Financial Policy / guardrails
- Data health and operations

The old static monthly allocation controls should not remain a second source of truth after the dynamic template is established.

## Page-by-page target behavior

### Dashboard

Show the current month first:

- planned income vs actual income;
- planned expenses vs actual expenses by category;
- available to allocate;
- planned savings allocation vs actual movement;
- unassigned remainder;
- month status and next required action.

Then show net worth, controlled vs held-elsewhere assets, buckets/goals, liabilities, and longer-term trends.

Remove or relabel any card that still presents the old starter split as if it were the user’s real policy.

### Plan templates

Templates are reusable, owner-scoped blueprints. Multiple templates are allowed, for example Normal month, Family car priority, or Investment-heavy. Each template contains income rules, expense categories/rules, and remaining savings rules: percentage -> asset target -> purpose bucket -> optional goal through the bucket.

Selecting a template for a month creates a separate monthly snapshot. Editing that snapshot never edits the template. A closed snapshot is protected from later template or commitment changes, while changes remain available through the audit/history layer.

### Monthly plan

Use two clear modes:

1. **Template rules** — recurring income, expense rules, and remaining allocation rules, managed on the template page.
2. **This month snapshot** — choose a template, calculate planned amounts, enter actuals, compare variance, and close safely.

Every savings line must expose:

```text
percentage -> calculated amount -> asset target -> bucket -> optional goal through bucket
```

### Monthly review

Use this as the month-end control center:

- enter or derive actuals;
- resolve uncategorized transactions;
- compare plan vs actual;
- record lessons/adjustments;
- explicitly close the month;
- prepare the next snapshot without changing the closed month.

### Ledger / actual transactions

Actual entries must support controlled expense categories and income sources. Confirmed transactions can synchronize actuals; planned records must never be treated as transactions.

### Assets, buckets, and goals

- Asset: where money actually exists, e.g. Bank Cash EGP, Bank Cash USD, Gold, ETF.
- Bucket: what the money is for.
- Goal: optional target attached to a bucket.
- Planned asset targets may refer to a future instrument without creating an owned asset prematurely.

### Financial Policy

Keep only guardrails and assumptions here. Move operational monthly rules to the monthly template/rules area.

## Execution phases

### Phase 0 — Freeze, backup, and inventory

- Verify the current owner and demo user IDs.
- Create and verify a backup.
- Export current owner context.
- Record migration status, route list, MCP tool list, and test/build baselines.
- Confirm no destructive command is used on owner data.

### Phase 1 — Data/model audit

- Verify every new model has `BelongsToUser`.
- Verify foreign keys, unique indexes, soft-delete behavior, and rollback paths.
- Verify plan snapshots do not mutate closed months.
- Verify actual sync is deterministic and idempotent.
- Decide whether budget categories and transaction categories should remain separate, and document the mapping.

### Phase 2 — Demo workspace

Rebuild/extend demo data to cover:

- income rule and multiple income sources;
- the four default expense categories;
- fixed and percentage expense rules;
- commitments;
- multiple reusable plan templates and a duplicated template;
- savings rules with Bank Cash EGP, Bank Cash USD, Gold, and an investment target;
- buckets with and without goals;
- planned amounts, actual amounts, an underfunded line, an over-budget line, and an unassigned remainder;
- a closed month and a prepared next month;
- confirmed ledger transactions and a pending import row.

Then test demo reset isolation.

### Phase 3 — Navigation and system-wide UI

- Reorganize AppShell into Plan, Track, Portfolio, Analyze, and Settings.
- Replace old dashboard cards with the canonical monthly plan/actual view.
- Consolidate duplicate income/expense entry paths.
- Add budget-rule/category management UI.
- Align Assets, Buckets, Goals, Reconciliation, Export, and Learn pages with the same vocabulary.
- Review responsive layout and empty states.

### Phase 4 — MCP and export parity

- Ensure MCP can create/update/list/archive/restore budget categories and rules.
- Ensure allocation-plan writes calculate percentage amounts server-side.
- Expose expense snapshots, asset targets, buckets, goals, actual sources, and variance in MCP context.
- Add contract tests comparing UI/API/MCP projections.

### Phase 5 — Owner account re-plan

Only after Phases 0–4 pass:

- restore or verify the owner backup;
- import/recreate owner facts without inventing values;
- create the four default categories/rules if missing;
- confirm income and recurring commitments;
- confirm assets, receivables, buckets, and goals;
- enter the owner’s actual monthly allocation percentages only after explicit confirmation;
- verify dashboard, monthly plan, monthly review, reconciliation, export, and MCP context.

## Acceptance criteria before owner re-plan

- Full test suite passes.
- TypeScript check and production build pass.
- Migration fresh test and rollback test pass.
- Demo reset changes only the demo owner.
- Owner export before and after is explainable; no unexplained deletion or balance change.
- Dashboard, monthly plan, monthly review, ledger, assets, buckets, goals, reconciliation, and export use the same planned/actual vocabulary.
- MCP and UI return the same totals for the same owner.
- No savings percentage or asset target is invented by the system.
- Closed month snapshots remain stable after future rule changes.

## Work log

- [x] Dynamic budget category/rule foundation added.
- [x] Expense plan snapshot storage added.
- [x] Percentage-based savings allocation foundation added.
- [x] MCP resource support extended for budget categories/rules.
- [x] Reusable plan templates with template-scoped rules and monthly snapshots.
- [x] Closed monthly plans protected from later template changes.
- [x] Recurring commitments synchronized into template expense rules.
- [x] Full implementation audit: migrations, routes, tests, typecheck, build, formatting, and diff checks.
- [x] Demo seeder parity for templates, rules, allocations, monthly history, and expense snapshots.
- [x] Navigation/dashboard redesign alignment.
- [ ] Owner re-plan.
