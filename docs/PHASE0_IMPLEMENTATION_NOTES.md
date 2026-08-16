# Phase 0 implementation note

Implemented in the current MVP:

- A shared, policy-backed liquidity calculation with `immediate`, `within_3_days`, `longer_term`, and `illiquid` tiers. Dashboard, purchase analysis, exports, and MCP now expose `availableNow`, `availableWithinThreeDays`, `totalAssets`, emergency eligibility, source status, and limitations.
- Cash-flow fallback totals use actual entries once; recurring commitments remain projections and are not added to actual obligation entries. Savings rate is free cash flow divided by income; investment rate is separate.
- Goal feasibility is portfolio-wide and priority ordered. Purpose reconciliation separates goal allocations, non-goal allocations, and genuinely unallocated assets.
- Manual snapshots are current-date checkpoints only and are explicitly labelled as current-state calculations. Past `as_of` values are rejected until dated valuation/ledger records exist.
- Existing entities have soft archive/restore foundations through web routes and explicit local MCP CRUD/archive/restore tools. MCP mutations validate input, run in a transaction, create an audit record, and return a dashboard delta, freshness, version, affected entity, and undo eligibility. Context exports are versioned and a redacted context tool is available.
- Asset, bucket, and goal dashboard pages now expose edit plus archive/restore actions; goal edits keep their dedicated bucket in sync. Allocation mutations accept a full asset value and audit immutable pre/post allocation states.
- Shared Inertia success/error feedback, a financial policy screen, and the local MCP example path are included.
- Phase 1 adds configurable asset-class ranges and rebalancing tolerance, purchase/goal guardrails, valuation freshness settings, a dashboard source-status strip and attention queue, a drill-through calculation sheet, allocation reconciliation, and priority-ordered goal funding conflicts.
- Phase 1 adds persistent decision-journal CRUD/archive/restore through the dashboard and MCP. Journal mutations return the same audited, reflected dashboard envelope as other owner-agent writes, and context exports include complete journal fields and scope metadata.

Intentionally deferred to Phase 2/3: true valuation history and historical ledger reconstruction, transaction imports/backups, persisted scenario records, authentication/per-record ownership, append-only production audit operations, and remote MCP hosting. The current Phase 1 context therefore exposes empty account/valuation/scenario collections and labels current values as manual/current-state context; it remains local-only and is not a regulated adviser.
