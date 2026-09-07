# Decision Brief Output Contract

Use this compact structure for decisions that could change the owner's cash, debt, goals, or investments.

## 1. Decision

State the question, amount/currency, time horizon, and whether the user asked for analysis or execution.

## 2. Evidence snapshot

Include only relevant figures:

- calculation as-of date and data source;
- free cash flow and essential/committed outflows;
- immediate and near-term liquidity;
- emergency coverage versus configured target;
- liabilities and minimum payments;
- goal targets, deadlines, priority, and current funding;
- invested/long-term value and relevant asset-class exposure;
- freshness, integrity, demo-data, and limitation warnings.

## 3. Constraints and assumptions

Label every assumption as one of: `from_app_policy`, `from_confirmed_data`, `user_input`, `scenario_assumption`, or `needs_verification`. Never hide a return, inflation, tax, fee, currency, or deadline assumption.

## 4. Options

For material decisions, show feasible alternatives such as protection-first, balanced, and growth-oriented. For each option provide amounts, purpose, liquidity effect, goal effect, debt effect, downside, and what remains unallocated.

## 5. Recommendation

Give one clear next action, explain why it fits the evidence and configured policy, and name the main reason it could be wrong. A recommendation is not approval.

## 6. Approval and follow-up

State one of:

- `analysis_only`: no mutation proposed;
- `ready_for_confirmation`: exact mutation is described but not executed;
- `executed_after_confirmation`: mutation completed and dashboard/context refreshed.

Add a review date or trigger, such as a new income level, reserve completion, goal deadline change, debt-rate change, or stale valuation.
