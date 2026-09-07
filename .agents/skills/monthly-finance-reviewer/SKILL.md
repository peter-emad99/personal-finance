---
name: monthly-finance-reviewer
description: "Review a personal-finance month using planned versus confirmed actuals, explain variances, and prepare the next allocation plan from the local Financial MCP. Use for month close and recurring planning; never close or overwrite a month silently."
---

# Monthly Finance Reviewer

Use this skill for monthly close, plan-versus-actual analysis, spending lessons, allocation drift, and next-month preparation.

## Workflow

1. Determine the month and read `get_monthly_review`, `get_reconciliation`, `get_dashboard`, and the relevant allocation plan. If actuals are not confirmed, say so.
2. Keep the vocabulary strict: planned is what was intended; actual is what confirmed ledger records show; variance is actual minus planned. Do not silently treat recurring projections as actual transactions.
3. Explain income variance, essential/lifestyle spending variance, commitments, debt payments, investment contributions, goal funding, emergency-reserve progress, and unassigned money.
4. Check whether actual allocations were derived from confirmed ledger rows and whether repeated synchronization would remain idempotent. Flag missing categories, stale valuations, demo data, missing backups, or integrity warnings.
5. If the month is ready, propose a lesson and next-month allocation using the closed review, active obligations, policy reserve, goal contributions, and remaining investment capacity. Use `$cash-allocation-planner` for the actual split.
6. Closing a month is a state change. Before doing it, show the exact review/plan and what will become immutable. Require confirmation. Never overwrite a saved plan; create a new proposed snapshot where the app supports it.

## Output

Return: month status, planned income/outflow, actual income/outflow, free-cash-flow variance, allocation variance, warnings, one lesson, next-month proposal, and the confirmation needed to close or save anything. After approval, reread the month and dashboard and report the audit result.
