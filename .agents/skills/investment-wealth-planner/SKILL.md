---
name: investment-wealth-planner
description: "Analyze investment capacity, risk, horizon, diversification, and long-term wealth progress from the local Financial MCP. Use for portfolio policy and financial-independence planning; do not make unsupported security-specific recommendations or execute trades."
---

# Investment & Wealth Planner

Use this skill for investing, asset mix, idle money after reserves, retirement/FI planning, wealth stage, and long-term growth questions.

## Order of analysis

1. Read `get_decision_context`, `get_dashboard`, `get_financial_settings`, assets/asset types, liabilities, goals, and allocation reconciliation as needed.
2. Separate risk capacity from risk tolerance. Capacity comes from horizon, emergency liquidity, stable cash flow, debt, goal deadlines, and ability to absorb a loss; tolerance is the user's behavioral comfort. The lower of the two controls the plan.
3. Separate money by time horizon: immediate/near-term, medium-term goals, and long-term capital. Do not recommend volatile assets for money needed soon.
4. Check concentration, liquidity, currency exposure, fees, inflation, taxes, and valuation freshness. If local Egyptian tax or product rules are required, mark them as a verification task rather than importing US rules.
5. Use the app's configured asset classes, target ranges, and rebalancing tolerance. Do not invent a universal percentage split or treat historical returns as expected outcomes.
6. For financial independence, use the app's `financialFreedom` estimate as a planning indicator only. It currently excludes investment growth, taxes, fees, inflation, and sequence-of-returns risk; present it with conservative/base/optimistic scenarios when inputs support them.
7. Prefer diversified, understandable policy choices over security picking. A specific instrument requires current, authoritative data, suitability checks, and explicit user intent; it is never implied by this skill.

## Output

Return: investable amount, protected amount, horizon map, policy fit, current exposure, risks, scenario range, contribution pace, and the next review trigger. Distinguish “investing policy” from “specific product choice.” Never claim a return is guaranteed.

## Execution boundary

This skill is analysis-only unless the user explicitly confirms an exact allocation change. Never call a trade, transfer, reallocation, or mutation as a side effect of a recommendation. After an approved app mutation, refresh the context and record the decision assumptions and review date.
