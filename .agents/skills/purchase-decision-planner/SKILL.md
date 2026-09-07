---
name: purchase-decision-planner
description: "Evaluate purchase affordability, cash-versus-financing trade-offs, and the effect on liquidity, debt, goals, and investments using the local Financial MCP. Use for major purchases; never approve financing or mutate records without explicit confirmation."
---

# Purchase Decision Planner

Use this skill for “can I afford this?”, “should I buy now?”, cash-versus-finance comparisons, or purchases that could change monthly capacity.

## Workflow

1. Clarify price, currency, purchase date, cash or finance mode, down payment, interest rate, tenure, ongoing monthly cost, and the user's horizon.
2. Call 'evaluate_purchase' with the known inputs. Treat its configured checks as a decision input, not as a promise that the purchase is wise.
3. Read 'get_dashboard', 'get_decision_context', 'get_allocation_reconciliation', active goals, liabilities, and the monthly plan when the purchase competes with existing commitments.
4. Separate one-time price and financing cost from recurring ownership costs such as maintenance, insurance, subscriptions, fees, taxes, depreciation, and currency exposure. If unknown, show a range or verification task instead of hiding it.
5. Compare at least three options when material: buy later and preserve liquidity, buy with cash, and finance while preserving more cash. Show after-purchase immediate cash, emergency coverage, free-cash-flow effect, debt burden, delayed goals, and investment contribution effect.
6. Use the smallest reversible next step: verify missing quote/term, save a scenario, or prepare an exact confirmed change. Never treat a pass/fail calculator result as approval.

## Guardrails

- Do not count total assets or earmarked goal balances as spendable cash.
- Do not consume the emergency reserve or cancel a higher-priority goal without explicit discussion and confirmation.
- Do not recommend a lender, security, product, or guaranteed return without current authoritative data and suitability checks.
- Do not create a purchase transaction, loan, asset, or allocation as a side effect of analysis.

## Output

Return the inputs and as-of date, calculator result, liquidity and obligation snapshot, options with exact known amounts and labeled unknown costs, recommendation, main reason it could be wrong, approval state, and the review trigger.
