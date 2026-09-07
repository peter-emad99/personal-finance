---
name: cash-allocation-planner
description: "Plan how idle cash or monthly free cash flow should be split across obligations, emergency reserve, goals, debt, and long-term investing using the local Financial MCP. Use for allocation proposals; do not execute transfers or reallocate assets without confirmation."
---

# Cash Allocation Planner

Use this skill when the user asks where to put spare money, how to allocate a monthly surplus, how to fund goals, or how to redirect a completed emergency-fund contribution.

## Core rule

Allocate only money that is actually available for the stated horizon. Never allocate total net worth as if it were cash. Keep four concepts separate:

1. Cash needed for known obligations and essential spending.
2. Emergency reserve required by the configured policy.
3. Goal money with a deadline and purpose.
4. Long-term investable surplus that can tolerate market volatility.

## Workflow

1. Read `get_decision_context` with `scope=decision_context`, then `get_dashboard`, `get_financial_settings`, and `get_allocation_reconciliation`.
2. Identify the amount and currency being allocated. Confirm whether it is new monthly free cash flow, an already-held unallocated asset balance, or a proposed future contribution. Do not mix these.
3. Reserve known commitments, minimum debt payments, essential near-term spending, and the emergency-reserve gap before considering long-term investing.
4. Inspect goals by priority, deadline, target, current funding, and required monthly pace. Use `$goal-funding-planner` for detailed goal math.
5. Treat debt payoff as an option with a risk-free interest saving; use `$debt-payoff-planner` to compare it against the user's policy and liquidity needs rather than assuming one universal debt rule.
6. Direct only the remaining amount to long-term investments. Use `$investment-wealth-planner` for risk, horizon, asset-class, currency, and wealth-path analysis.
7. Produce at least two feasible options when the decision is material: protection-first and growth-balanced. Show exact amounts, percentages, what remains unallocated, and the consequence for each goal.

## Monthly-plan rules

- A monthly plan is an intended allocation snapshot; it is not proof that money moved.
- Planned income minus planned expenses is the ceiling for planned allocations. Flag over-allocation and unassigned surplus.
- Confirmed ledger transactions are the actuals. Do not rewrite actuals to make the plan look successful.
- Prefer a proposed next-month plan from a closed review. Never overwrite an existing plan silently.
- If the reserve is complete, show the released monthly amount and ask whether it should go to goals or investments; do not choose silently.

## Output

Return: available amount and source, protected amount, emergency gap, debt/obligation impact, goal allocations, investment allocation, unallocated remainder, assumptions, risks, and the exact confirmation needed for any write. Use `propose_cash_allocation` for the deterministic options; if a requested decision needs a dimension that tool does not model, state the limitation instead of inventing a precise number.
