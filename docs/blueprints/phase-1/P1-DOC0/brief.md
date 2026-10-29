[P1-DOC0 | BRIEF | Claude]

Read AGENTS.md and ARCHITECTURE.md fully, then this brief and the attached reference-guide.md. This step is a READ-ONLY AUDIT. Do not edit, create, move or delete any file. Do not write a plan. Do not write code. Produce the AUDIT-REPORT below in chat.

Goal
Prepare the repository to grow from a one-context content CMS into a multi-context marketplace (a Catalog context with categories and products, a retailer-facing panel, roles) without building any of it. Two concerns:
(1) Documentation is mostly finished-step blueprints and implementation detail. It must become small area guides holding only facts that are true now, invariants and recipes.
(2) Conventions and tests must be checked for anything that assumes Content is the only context.
This audit gives Claude and Achintha the evidence to write the follow-up step briefs.

Reference format
reference-guide.md comes from another project (Java/Spring/Liquibase). Take from it: front matter (kind, area, status, summary, paths owned, decisions, depends-on, tests, triggers with a "reconsider" checklist), "How it works now", an invariants table with Why and Enforced by columns, "Changing it" recipes, "Known limits and open questions", and a "History" section that links to ADRs and migration headers instead of copying them. Do not copy anything stack-specific (Liquibase, Gradle, WSL). Adapt to Laravel, Pest, Composer, PowerShell.

Decisions made (do not re-decide)
- ARCHITECTURE.md and AGENTS.md stay at the repo root.
- Finished step artifacts (docs/blueprints/<STEP>/) leave main after closeout and are preserved by a git tag. ADRs, docs/contracts and migration headers stay, because they hold the reasons.
- Guides state only what is true now. No step IDs, plans or "added in P1-UI5" outside History.
- No Catalog, Retail or Accounts folders, tables, packages or guides are created (X4). Later phases stay a sketch.
- No new dependency (X1).

In scope: read, measure, classify, propose.
Out of scope: any file change; drafting guides other than AC-5; implementing any finding; product/category design; the database engine choice.

Open decision (Achintha, not Gemini)
A senior's draft product schema is MySQL; this repo is PostgreSQL. AC-9 lists what the choice would affect. Do not recommend one.

Acceptance criteria
AC-1  git status --short is empty before and after (raw output of both).
AC-2  Doc inventory: every file under docs/, plus ARCHITECTURE.md, AGENTS.md and any other root *.md. Table: path | lines | last commit date (git log -1 --format=%cs -- <path>) | class | recommendation. Classes: current-state, invariant, recipe, history, step-artifact, future-sketch, duplicate-of:<path>. Note content duplicated between ARCHITECTURE.md, AGENTS.md and the step workflow doc (if it is in the repo).
AC-3  ARCHITECTURE.md sections 1 to 13: line range | class | proposed destination (stays in overview | named area guide | History link | delete).
AC-4  Proposed area list, 5 to 9 areas. Challenge my starter list: content-blocks, menus, content-api, admin-panel, data, quality, demo-storefront. For each: owned path globs, tables, enforcing tests, decisions (D-numbers and ADRs). Then raw proof that every file under app/, database/, routes/, tests/ and resources/views belongs to exactly one area. List unowned and doubly-owned files.
AC-5  One complete draft guide for the `menus` area in the reference format, shown in chat and not saved. Max 150 lines. Every claim traceable to a file path or ADR. Include 2 or 3 triggers.
AC-6  Rule enforcement table: every rule in AGENTS.md | enforced by (Pint, Larastan, named arch test, named feature test, git hook, CI, or review only). List the review-only rules separately.
AC-7  Scalability findings F1..Fn. Each: category | evidence (file:line) | what breaks first when a second context or a retailer panel arrives | severity. Check at least:
  a. tests/Arch rules hard-coded to App\Domain\Content, and whether dependency rule 5 (contexts never touch each other's models) is enforced by any test
  b. AGENTS.md and ARCHITECTURE.md wording that says "Content" where the rule is general (for example "Business logic lives in app/Domain/Content/Actions")
  c. what in Content is really shared kernel (BaseContentPolicy, ULID public_id handling, activity log usage, advisory lock helper, Support/Rules, Data base classes) and where each lives
  d. Filament: panel id and path, canAccessPanel and is_platform_admin, how a second panel would be registered, whether app/Filament/Blocks and the Schemas/Tables class convention can be reused across panels
  e. authorization: every Policy and Gate, and where an owner scope (retailer_id) would have to be applied
  f. database: tables, FKs, indexes against the real query paths of the Content API and admin lists, jsonb use, CHECK constraints, unbounded growth (activity log, preview cache)
  g. Content API: N+1 queries (measured query count per request), cache and ETag behavior, configured cache store and queue connection, rate limit
  h. routes and controllers: versioning layout and room for Api/V1/<Context>
  i. tests: count, suite time from composer test, parallel-capable or not, shared state or order dependence
  j. the temporary demo storefront: files coupled to it and what removing it touches
AC-8  Seam map for three future changes, no building: (1) a second context named Catalog, (2) a second Filament panel `retailer` with roles and permissions, (3) a public read API for catalog data. For each: files that must change, files that must be added, rules or tests that would block it (by ID or path). Assess D4 (menus link by URL) and D6 (single admin flag) explicitly.
AC-9  Every PostgreSQL-specific feature in use (advisory locks, jsonb, partial indexes, CHECK syntax, timestamptz, and any others found) with file:line.
AC-10 Proposed follow-up steps: ID (P1-DOC1, P1-SCALE1 ...), scope, dependencies, layer touched. One concern each, under 10 files each. Docs-only and code steps are separate. Do not implement.

Rules most at risk: X4 (scaffolding later phases), X1, E1, and the evidence rule (every claim needs file:line or raw output).

Known traps
- Do not start rewriting docs "while you are there".
- Do not summarise ADRs away. They are history and stay as they are.
- Classification needs cited lines, not impressions.
- "Future sketch" does not mean "plan to build".
- Stack-specific details from the reference guide must not leak in.

Size guard: no file limit (read-only), but the report must stay scannable: tables, not prose. If AC-7 exceeds 25 findings, rank them, keep the top 15 in full and give the rest one line each.

Commands (PowerShell, one at a time, no &&, nothing left running)
- git status --short
- Get-ChildItem docs -Recurse -File | Select-Object FullName, Length
- (Get-Content ARCHITECTURE.md).Count
- Get-ChildItem tests/Arch -Recurse -Filter *.php
- Get-ChildItem app -Recurse -File | Measure-Object
- composer test (record the duration)
- any others you need. Paste raw output for every claim that rests on one.

Report format
[P1-DOC0 | AUDIT-REPORT | Gemini]
Sections 1 to 10 matching AC-1 to AC-10, then section 11: gaps and anything you could not verify ("None" stated explicitly).