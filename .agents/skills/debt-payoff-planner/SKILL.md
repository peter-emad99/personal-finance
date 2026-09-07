---
name: debt-payoff-planner
description: "Analyze debt payoff choices, interest savings, liquidity impact, and obligation risk from the local Financial MCP. Use for extra payments, refinancing questions, and debt-versus-investing decisions; never post a payment or change liabilities without explicit confirmation."
---

# Debt Payoff Planner

Use this skill when the owner asks whether to pay down debt early, how to prioritize liabilities, whether a loan is affordable, or whether extra cash should go to debt or investments.

## Workflow

1. Read 'get_decision_context', 'get_dashboard', 'list_liabilities', and 'get_reconciliation' as needed. Confirm the as-of date, source, active status, and freshness.
2. For each relevant liability capture balance, interest rate, minimum payment, due date, projected payoff, estimated interest, fees, and any missing fields. A missing rate or prepayment rule is a verification gap, not permission to guess.
3. Protect essential spending, minimum payments, and the configured emergency reserve before proposing an extra payment. Do not call a debt balance liquid cash.
4. Compare choices: minimum payments only, an extra monthly amount, a lump-sum payoff, and debt-versus-investing. Show the guaranteed interest saving as an estimate, while labeling taxes, fees, penalties, and investment uncertainty.
5. Check the effect on free cash flow, goals, liquidity coverage, debt burden, and investment capacity. A mathematically attractive payoff can still be wrong if it leaves the owner unable to meet near-term obligations.
6. Prefer the app's liability payoff projection and policy thresholds over reimplementing formulas in prose. If the user asks about a specific lender or local legal/tax rule, mark it for current authoritative verification.

## Guardrails

- Never recommend missing-data debt prioritization as if it were exact.
- Never use the emergency reserve for payoff unless the user explicitly changes policy and confirms the consequence.
- Do not imply that paying a liability creates a guaranteed investment return; describe avoided interest separately from uncertain market returns.
- Do not post a payment transaction, alter a liability balance, archive a liability, or change an allocation as a side effect of analysis.

## Output

Return a debt table with balance, rate, minimum payment, payoff estimate, and data gaps; options with extra amount, liquidity effect, interest saving, goal/investment trade-off, and downside; one recommendation; the exact confirmation required for any write; and a review trigger such as a rate change, income change, or reserve completion.
