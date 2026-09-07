# Local Financial MCP Contract

This reference describes the app's current financial control plane. Use the tool names exposed by the running MCP; do not invent a tool because a future roadmap mentions one.

## Read-first tools

- `get_dashboard`: current projection, policy, freshness, source status, and limitations.
- `get_decision_context`: exportable context. Use `scope=decision_context` for most decisions; use `full_financial_context` only when detailed assets, accounts, valuations, transactions, and commitments are needed.
- `get_financial_settings`: configured base currency, reserve months, asset-class targets, decision guardrails, and goal-funding policy.
- `get_allocation_reconciliation`: total assets, goal purpose balance, non-goal purpose balance, and genuinely unallocated balance.
- `propose_cash_allocation`: read-only protection-first, balanced, and growth-after-protection options for a proposed EGP amount. It does not save a plan or move money.
- `list_goals`: goals and funding feasibility exposed by the current server.
- `get_monthly_review`, `get_reconciliation`, `list_allocation_plans`: monthly plan/review and actual-vs-planned evidence.
- `list_assets`, `list_liabilities`, `list_recurring_commitments`: holdings, debt, and known obligations when the decision needs them.
- `evaluate_purchase`: purchase/financing analysis. Use it instead of reproducing payment, liquidity, coverage, and confidence math in prose.

## Write tools

Writes include asset/bucket/goal/allocation/plan/review/ledger mutations and archive/restore operations. They are not implied by a recommendation. Before a write, state:

1. exact entity or entities;
2. exact amount, currency, and destination;
3. effect on reserve, goals, investments, and monthly plan;
4. whether the operation posts a transaction, changes an earmark, or only saves a plan;
5. what can be undone or reviewed.

Only proceed after explicit confirmation. Afterward call `get_dashboard` or `get_dashboard_delta`, and report the returned audit/version/freshness information.

## Truth and freshness rules

- A dashboard snapshot is a projection as of its returned date, not a promise about today.
- Current asset values may be manual/current-state values and may not be historical valuations.
- A recurring commitment is a projection until a confirmed ledger event or review says otherwise.
- A plan allocation is not an actual movement of money.
- A missing, stale, demo, or incomplete source changes the confidence of the recommendation; it does not justify silently filling the gap.
