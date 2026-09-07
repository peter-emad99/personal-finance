# Finance Agent Skills Plan

## Objective

Make the local agent a reliable personal-finance decision partner over the app's Financial MCP. It should understand the owner's current financial context, answer in plain language, show the math and assumptions, propose feasible choices, and require confirmation before changing records.

The agent is a planning and explanation layer. Laravel services remain the source of truth for balances, ledger totals, liquidity, policy checks, goal feasibility, purchase math, and audit history.

## Skills now installed in this project

All are project-local under `.agents/skills/` so the workspace can discover them without changing global agent configuration.

| Skill | Responsibility | Typical triggers |
| --- | --- | --- |
| `personal-finance-agent` | Primary router, evidence preflight, output contract, approval boundary | Any money decision or broad financial question |
| `cash-allocation-planner` | Split idle cash or monthly surplus across protection, goals, debt, and investing | “Where do I put this money?”, monthly allocation, unallocated cash |
| `goal-funding-planner` | Goal gap, deadline, priority conflicts, required monthly pace | “Can I afford this goal?”, goal behind/on track |
| `investment-wealth-planner` | Risk capacity, horizon, diversification, investment policy, FI/wealth path | Investing, portfolio mix, retirement, long-term wealth |
| `debt-payoff-planner` | Debt priority, interest savings, payoff versus liquidity/goals/investing | Extra payment, debt burden, payoff or refinance |
| `purchase-decision-planner` | Purchase affordability, cash versus finance, ownership-cost impact | “Can I afford this?”, major purchase, financing |
| `monthly-finance-reviewer` | Planned vs actual, variance explanation, close/review, next month | Month close, spending review, prepare next plan |

## Agent operating model

1. Identify the decision, amount, currency, horizon, and whether the user wants analysis or a record change.
2. Read the smallest sufficient MCP context, normally `get_decision_context(scope=decision_context)` plus `get_dashboard` and `get_financial_settings`.
3. Check freshness, source, demo-data warning, integrity, limitations, liquidity reconciliation, obligations, goals, and the current monthly plan.
4. Route to the relevant domain skill. Use existing deterministic tools, especially `evaluate_purchase`; do not reproduce business math in prose.
5. Return a decision brief: evidence, constraints, assumptions, feasible options, recommendation, risks, approval state, and review trigger.
6. Treat every mutation as a separate confirmed step. After confirmation, execute only the named actions, refresh the dashboard/context, and report audit/version/freshness.

## Correct monthly allocation model

The monthly plan must distinguish intended allocation from actual money movement:

`planned income - planned expenses = planned free cash flow`

The planned free cash flow is the maximum amount that can be allocated. The sequence is:

1. Known essential outflows, recurring commitments, and minimum debt payments.
2. Emergency-reserve gap until the configured reserve-month policy is met.
3. Goal contributions ordered by configured priority, or explicit manual contributions when that policy is selected.
4. Long-term investment contribution only from the remaining amount and only when the horizon/risk capacity supports it.
5. Leave any genuinely unassigned remainder visible; never force 100% allocation by inventing a purpose.

Confirmed ledger transactions drive actuals. A plan row, purpose bucket, or earmark does not prove that money moved. Closed plans/reviews are historical snapshots and must not be silently overwritten.

## Current app strengths

- Versioned decision/full/redacted context exports.
- Liquidity tiers and emergency eligibility.
- Purchase/financing calculator with configured guardrails.
- Goal feasibility and priority conflict signals.
- Asset-purpose allocation reconciliation.
- Monthly plan/review and confirmed-ledger actual tracking.
- Wealth stage and financial-freedom estimate with explicit limitations.
- Audited owner-scoped MCP mutations with soft archive/restore behavior.

## Gaps to close before high-confidence automation

### P0 — read-only allocation proposal — DONE

Added `propose_cash_allocation`. It accepts an EGP amount/source and returns protection-first, balanced, and growth-after-protection proposals, including the emergency gap, goal pace, investment capacity, unallocated remainder, freshness, warnings, and limitations. It does not mutate assets, buckets, goals, or plans. Feature coverage protects the reserve, applies goal priority, shows horizon warnings, and verifies no financial records change.

### P1 — scenario engine

Add a persisted or preview-only scenario model for goal deadline changes, contribution changes, purchase decisions, and conservative/base/upside wealth paths. Keep nominal return, inflation, fees, taxes, and currency assumptions explicit; do not present a single forecast as a promise.

### P1 — risk and suitability profile

Capture horizon, loss capacity, liquidity needs, concentration, currency needs, and behavioral risk tolerance. The lower of risk capacity and tolerance controls investment policy. This should inform asset-class ranges, not pick securities automatically.

### P1 — local Egypt reference layer

Keep Egypt-specific tax, inflation, rates, investment products, and legal/regulatory facts in dated, source-linked references or data adapters. Never import US-only Roth, wash-sale, IRS, or 1099 rules into an EGP decision.

### P2 — decision feedback loop

When a decision's review date arrives, compare assumptions with actual outcomes and store the lesson in the decision journal. This improves future planning without rewriting the original decision.

## Definition of done

- Every financial answer cites the app's as-of date/source and states important limitations.
- No answer counts total net worth as spendable cash or double-counts bucket allocations.
- Monthly plans never exceed planned free cash flow and clearly show unassigned money.
- Goal conflicts are explicit and priority-ordered.
- Investment answers separate policy from security selection and include horizon/risk/fees/inflation caveats.
- No mutation or transaction posting occurs without explicit confirmation.
- Every approved mutation is refreshed, audited, and reviewable.
- The P0 allocation proposal has feature tests for reserve protection, goal priority, no-growth feasibility, zero/negative surplus, and no mutation side effects.
