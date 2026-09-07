---
name: goal-funding-planner
description: "Evaluate personal financial goals, deadlines, priority conflicts, and monthly funding pace from the local Financial MCP. Use for goal affordability and sequencing; never create or change a goal plan without explicit confirmation."
---

# Goal Funding Planner

Use this skill for questions such as “can I afford this goal?”, “which goal comes first?”, “how much should I save monthly?”, and “why is a goal behind?”

## Workflow

1. Read `get_decision_context` and `get_dashboard`; confirm base currency, as-of date, freshness, and limitations.
2. Read the goals and their purpose buckets. For each active goal capture target amount, deadline, priority, current allocated balance, and configured/planned monthly contribution.
3. Calculate the gap and required pace using the remaining time. Keep current allocated balance separate from monthly contributions and from assets held for other purposes.
4. Check portfolio-wide feasibility: available monthly free cash flow, emergency-reserve gap, obligations, liabilities, and higher-priority goals. Do not promise every goal can be funded simultaneously.
5. Respect the configured goal-funding policy. If it is priority order, allocate higher-priority goals first and show the conflict. If it is manual contributions, evaluate the user's entered contributions and flag infeasible totals.
6. Classify the goal by horizon. Near-term goals need capital preservation and liquidity; long-term goals may tolerate investment volatility only when the user has sufficient risk capacity and the goal can survive a loss.
7. Provide a feasible baseline, a faster/less flexible option, and a delayed/safer option when trade-offs exist. Identify the smallest change that closes the gap: contribution, deadline, target, priority, or spending.

## Guardrails

- A goal allocation is earmarking existing asset value, not a new asset and not a guaranteed return.
- Never fund a goal by consuming the emergency reserve unless the user explicitly changes policy and confirms the consequence.
- Never count the same asset balance toward multiple purposes.
- Do not use an assumed investment return to claim a deadline is affordable; if a scenario needs growth, label the return, volatility, fees, taxes, and inflation assumptions and show a no-growth case.

## Output

Return a compact goal table with target, funded, gap, deadline, required pace, current pace, feasibility, priority conflict, and next action. Separate recommendation from any pending mutation and ask for exact confirmation before changing goal or bucket allocations.
