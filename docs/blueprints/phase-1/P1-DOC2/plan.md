# [P1-DOC2 | PLAN v2 | Gemini]

Change log:
- B1 > Section 11.3, Section 13 (reworded D8 in ARCHITECTURE.md to match code and ADR 0001; resolved Section 13)
- B2 > Section 11.1, Section 11.4, Section 13 (placed X1, X2, X3, X4, X5 under `## Do not` and X6, X7 under `## Definition of done` in AGENTS.md; resolved Section 13)
- m1 > Section 11.2 (removed literal `(a)` and `(b)` prefixes from items 12 and 13 in Section 10A of `docs/workflow.md`)

---

### 1. Understanding

Integrate [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md), [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md), and the team workflow document with the normative area-guide documentation system introduced in [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md) and `docs/areas/`. [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) becomes the sole canonical home for rule IDs (S1–S7, C1–C8, K1–K9, DB1–DB6, SEC1–SEC5, T1–T5, G1–G3, X1–X7, E1, DOC1–DOC4) and gains documentation rules. The workflow document stops maintaining an independent rule register table, embeds guide reading, guide updates, and trigger checks into the step lifecycle, and is renamed via `git mv` to [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md). [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) adds pointers to guides and [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md), updates the section 5 docs path, and aligns decisions D8 and D9 with active code and ADRs.

Out of scope: modifying the Phase 1 progress checklist, section 4 demo harness note, section 5 directory tree, or slimming [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) (deferred to P1-DOC3); modifying [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md), any file in `docs/areas/`, any ADR, any file in `docs/blueprints/` ([docs/blueprints/rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) retained until P1-DOC3), or `.gitignore`; generalizing rules across contexts (deferred to P1-SCALE1); changing the meaning of any rule; changing application code, configuration, database migrations, tests, or dependencies; and modifying workflow section 3 artifact locations or section 11 checkbox bullets.

---

### 2. Sources read

| File or doc section | What in it constrains this step |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Quality gates, Conventional Commits, PowerShell command constraints, phase boundaries, and current rule bullet text. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | Header text, section 5 folder structure, Decision table §12 (D8 and D9), and phase boundaries. |
| [docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md](file:///c:/dev/getnow-cms/docs/GetNow%20CMS%20Step%20Workflow%20%28Claude,%20Achintha,%20Gemini%29.md) | Current workflow roles, remainder table, rule register table, step lifecycle, templates, and prompts to be updated and renamed to [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md). |
| [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md) | Documentation system architecture, area map table, guide structure, and pull request update rule. |
| [docs/areas/content-blocks.md](file:///c:/dev/getnow-cms/docs/areas/content-blocks.md) | Content blocks normative guide, cited rule IDs (K1, K2, K3, K4, K7, DB4, SEC4, C6, D2, D3, D5, D7, D8), and lock conventions. |
| [docs/areas/menus.md](file:///c:/dev/getnow-cms/docs/areas/menus.md) | Menus normative guide, cited rule IDs (K4, D2, D4, D9), and whole-tree atomic reconciliation under row lock. |
| [docs/areas/site-settings.md](file:///c:/dev/getnow-cms/docs/areas/site-settings.md) | Site settings normative guide, cited rule IDs (K3, SEC4, SEC5, C5, D5, D6), and Spatie settings repository. |
| [docs/areas/content-api.md](file:///c:/dev/getnow-cms/docs/areas/content-api.md) | Content API normative guide, cited decisions (D1, D3, D8), and rate limiting. |
| [docs/adr/0001-content-api-tie-break.md](file:///c:/dev/getnow-cms/docs/adr/0001-content-api-tie-break.md) | Decision D8 context: tie-breaking sort order (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`), read-time in-memory maxCount slice, and removal of write-time publish locking. |
| [docs/adr/0003-content-block-concurrency.md](file:///c:/dev/getnow-cms/docs/adr/0003-content-block-concurrency.md) | Decision D9 context: last-write-wins payload updates and pessimistic locking (`lockForUpdate()`) strictly for lifecycle transitions. |
| [docs/blueprints/rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | Historical register reference mapping rule IDs S1–S7, C1–C8, K1–K9, DB1–DB6, SEC1–SEC5, T1–T5, G1–G4, X1–X7, E1. |
| [app/Domain/Content/Actions/PublishContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php) | Verified active code: uses `DB::transaction()` and row `lockForUpdate()`; no write-time capacity check or inline advisory lock. |
| [app/Domain/Content/Actions/EnableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php) | Verified active code: uses `DB::transaction()` and row `lockForUpdate()`; no write-time capacity check or inline advisory lock. |
| [app/Domain/Content/Actions/UpdateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php) | Verified active code: uses `DB::transaction()` and row `lockForUpdate()`; payload update is last-write-wins with no version integer check. |
| [app/Domain/Content/Actions/GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php) | Verified active code: orders by `servingOrder()`, pre-indexes by placement, and slices to `Placement::maxCount()` in memory. |
| [app/Domain/Content/Actions/UpdateMenu.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) | Verified active code: executes `DB::transaction()` with row `lockForUpdate()`, updates items with matching `public_id` in place, inserts new items, deletes omitted items, last-write-wins. |

---

### 3. Verified current state

#### 3.1 Edit Locations in [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md)

1. **Intro (lines 1–3)**:
```markdown
# AGENTS.md

Instructions for AI coding agents working in this repository. Read `ARCHITECTURE.md` before any structural change. If this file and `ARCHITECTURE.md` disagree, stop and ask.
```

2. **Workflow item 1 (line 35)**:
```markdown
1. Read the relevant part of `ARCHITECTURE.md` and the nearby code before editing.
```

3. **Workflow item 5 (line 39)**:
```markdown
5. Update `ARCHITECTURE.md` when you change structure, flows, tables or the API contract. Add an ADR in `docs/adr/` for significant decisions.
```

4. **Section insertion point after Workflow (line 41)**:
```markdown
## Where code goes
```

5. **`## Where code goes` bullets (lines 44–50)**:
```markdown
- Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too.
- Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output.
- `app/Domain` must never import from `App\Filament` or `App\Http`.
- Filament never calls `Model::create/update/delete` directly. It calls Actions.
- Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`.
- Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths.
- Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder.
```

6. **`## Coding standards` bullets (lines 54–61)**:
```markdown
- `declare(strict_types=1);` in every PHP file.
- Type every parameter, return value and property. Prefer `final` classes.
- Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers.
- Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field.
- The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action.
- Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API.
- Follow Pint and Larastan. Do not add baseline entries without approval.
- Comments explain why, not what. No commented-out code.
```

7. **`## Content rules (critical)` bullets (lines 65–73)**:
```markdown
- Block type keys are permanent. Never rename one.
- Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load.
- No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans.
- Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme.
- Placements, menu keys and block types are defined in code. Admins never create them.
- Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery.
- Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content.
- The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change.
- Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps.
```

8. **`## Database rules` bullets (lines 77–82)**:
```markdown
- PostgreSQL only. Tests run against Postgres, never SQLite.
- Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration.
- No destructive changes (drop or rename column or table) in the same release that stops using them.
- Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers.
- Expose `public_id` (ULID) externally, never raw incremental IDs.
- Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases.
```

9. **`## Security` bullets (lines 86–90)**:
```markdown
- Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`.
- Do not disable authorization, policies, strict types or static analysis rules to make code pass.
- Do not log sensitive data.
- Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization.
- Write sensitive content changes (publish, disable, menu and settings edits) to the activity log.
```

10. **`## Testing` bullets (lines 94–98)**:
```markdown
- Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`.
- Every Filament resource has a smoke test that loads list, create and edit pages.
- Every block type has tests for payload validation, API output and visibility rules.
- Use factories, not hand-built rows. Tests must be independent and deterministic.
- Do not lower coverage, skip tests or mark them incomplete to pass checks.
```

11. **`## Git` bullets (lines 102–105)**:
```markdown
- Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`.
- Conventional Commits: `feat(content): add announcement bar block`.
- Never push to `main` directly or bypass git hooks.
- PR description: what changed, why, how it was tested, and risks (migrations, API contract, security).
```

12. **`## Do not` bullets (lines 109–116)**:
```markdown
- Add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval.
- Introduce Docker, Sail, docker-compose or any container configuration.
- Add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments.
- Change deployment or infrastructure configuration as a side effect of a feature.
- Put business logic in controllers, Filament resources, model boot methods or observers.
- Modify the storefront repository or assume its internals. The API contract is the only interface.
- Create folders, contexts or tables for later phases.
- Make destructive file or database operations outside migrations and the local reset command above.
```

13. **`## Definition of done` line 124 (lines 118–125)**:
```markdown
- [ ] `ARCHITECTURE.md`, `docs/contracts/content-api.md`, `.env.example` updated where relevant
```

14. **`## Step protocol` (lines 127–133)**:
```markdown
## Step protocol

- Work only from an approved plan identified by step ID and version. No approved plan, no code.
- If the plan is wrong or incomplete, stop and write a Change Request. Never deviate silently.
- A step ends with an IMPLEMENTATION REPORT containing raw, unedited command output. Summaries are not evidence.
- Do not mark a step done. Only the reviewer accepts it.
```

15. **Environment bullet (line 13)**:
```markdown
- Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`.
```

---

#### 3.2 Edit Locations in [docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md](file:///c:/dev/getnow-cms/docs/GetNow%20CMS%20Step%20Workflow%20%28Claude,%20Achintha,%20Gemini%29.md)

1. **Header (lines 1–4)**:
```markdown
# GetNow CMS: Step Workflow

Claude, Achintha and Gemini. Applies from UI Step 3 onward. Built on AGENTS.md and ARCHITECTURE.md. If this document conflicts with either, they win and this one gets fixed.
```

2. **Section 2 remainder table and trailing line (lines 28–43)**:
```markdown
Proposed remainder of Phase 1:

| Step | Scope | Depends on |
| --- | --- | --- |
| P1-UI3a | BlockFormRegistry, AnnouncementBar form schema in app/Filament/Blocks, arch and unit tests. No pages | UI2 |
| P1-UI3b | Create and Edit pages wired to Actions, validation, link rules, smoke tests | UI3a |
| P1-UI4 | View page, header and row actions Publish, Archive, Enable, Disable through Actions, activity log, policy abilities | UI3b |
| P1-SET | StorefrontSettings page saving through an Action. This is in Phase 1 scope but has no line on the progress list: confirm whether it exists | none |
| P1-MENU1 | Menu and MenuItem models, migrations, Actions (depth 2 enforced), policy, tests | UI4 |
| P1-MENU2 | Menus admin UI with nested items | MENU1 |
| P1-API1 | GetLayoutContent Action, Data classes, visibility, D8 tie-break, content-api.md and fixture | MENU1, SET |
| P1-API2 | Controller, route, rate limit, ETag, contract test | API1 |
| P1-SEED | Idempotent ContentSeeder mirroring app.blade.php | API1 |
| P1-CLOSE | Phase exit review (section 11) | all |

Order can change. Dependencies cannot.
```

3. **Section 4 rule register table (lines 64–121)**:
```markdown
## 4. Rule register

Plans and reviews cite these IDs. All come from AGENTS.md.

| ID | Rule |
| --- | --- |
| S1 | Business logic only in app/Domain/Content/Actions, one invokable per use case, reads included |
| S2 | Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return |
| S3 | App\Domain never imports App\Filament or App\Http |
| S4 | Filament never calls Model::create, update or delete directly |
| S5 | Block definitions in Domain/Content/Blocks/Name, form schemas in app/Filament/Blocks |
| S6 | Panel code under app/Filament/Admin only, nothing in default Filament paths |
| S7 | No new context or top-level folder without asking |
| C1 | declare(strict_types=1) in every PHP file |
| C2 | Typed parameters, returns, properties. Prefer final classes |
| C3 | Enums for fixed sets, spatie/laravel-data for structured input and output between layers |
| C4 | Status and is_enabled change only through Actions (Publish, Archive, Enable, Disable), never a form field |
| C5 | Every write to persisted state, Spatie settings included, goes through an Action |
| C6 | Form Request or Data validation for input, Policies for authorization on every exposed model |
| C7 | Pint and Larastan clean, no baseline entries without approval |
| C8 | Comments explain why, no commented-out code |
| K1 | Block type keys are permanent |
| K2 | schema_version on every payload, shape change means bump plus upgrader, old payloads still load |
| K3 | No raw HTML, CSS or JS fields. Short text with max lengths, enums, validated links, booleans |
| K4 | Links are a relative path starting with / or an https:// URL, every other scheme rejected |
| K5 | Placements, menu keys, block types defined in code, admins never create them |
| K6 | Block registration is an explicit list in ContentServiceProvider |
| K7 | Visibility decided server-side, API never returns draft, archived, disabled or out-of-window content |
| K8 | API v1 additive only, content-api.md and fixture updated in the same change |
| K9 | New block type follows the ARCHITECTURE.md section 6 checklist |
| DB1 | PostgreSQL only, tests never on SQLite |
| DB2 | Never edit a merged migration, add a new one |
| DB3 | No destructive change in the same release that stops using the thing |
| DB4 | Foreign keys, indexes for query paths, CHECK constraints |
| DB5 | public_id (ULID) external, never raw IDs |
| DB6 | Seeders idempotent, destructive commands only on phone_store and phone_store_test |
| SEC1 | No secrets or .env committed, new variables in .env.example |
| SEC2 | Never disable authorization, policies, strict types or static analysis rules to pass |
| SEC3 | No sensitive data in logs |
| SEC4 | Admin access checked server-side in canAccessPanel |
| SEC5 | Publish, disable, menu and settings edits written to the activity log |
| T1 | Pest. Feature tests for Actions, API, Filament resources. Unit tests for pure logic. Arch tests in tests/Arch |
| T2 | Every Filament resource has a smoke test for list, create and edit |
| T3 | Every block type tests payload validation, API output, visibility |
| T4 | Factories, not hand-built rows. Tests independent and deterministic |
| T5 | No lowered coverage, skipped or incomplete tests |
| G1 | Branch feat/, fix/ or chore/ from main |
| G2 | Conventional Commits |
| G3 | Never push to main or bypass hooks |
| X1 | No dependency outside ARCHITECTURE.md section 3 without approval |
| X2 | No Docker, Sail or container config |
| X3 | No deployment pipeline or infrastructure change as a side effect |
| X4 | Nothing for later phases |
| X5 | Storefront repo untouched |
| X6 | composer check passes and its output was read |
| X7 | ARCHITECTURE.md, content-api.md, .env.example updated where relevant |
| E1 | PowerShell, one command at a time, no && or bash syntax, no servers left running |
```

4. **Section 5 Step Brief template (lines 130–131)**:
```markdown
Read first:           ARCHITECTURE.md sections, AGENTS.md sections, existing files (paths)
Decisions made:       Gemini must not re-decide these
```

5. **Section 6 Plan template (lines 150–162, 179–182, 192–195)**:
```markdown
2. Sources read
   Table: file or doc section | what in it constrains this step.
   Includes every existing file this step will call, extend or copy a pattern from.
   Files are read, not recalled.
...
4. File manifest
   Table: path | new or modify | layer | one-line responsibility | rule IDs it must satisfy.
   Total file count. Placement checked against AGENTS.md 'Where code goes'.
...
8. Rule compliance matrix
   One row per ID in the rule register: Applies Y or N | how satisfied, or why N/A.
   No row skipped.
...
11. Docs and contract updates
   Exact edits: ARCHITECTURE.md (section and new text), content-api.md, fixture,
   .env.example, ADRs. Or: None, with reason.
```

6. **Section 7 Plan review item 8 (line 221)**:
```markdown
8. Docs and contract updates listed
```

7. **Section 9 Implementation Report item 7 (line 269)**:
```markdown
7. Docs diffs: ARCHITECTURE.md, content-api.md, fixture, .env.example, ADRs
```

8. **Section 10A Layer A items 11–13 (lines 289–291)**:
```markdown
11. `git ls-files .env` (expect empty, SEC1)
12. `composer check` (read all of it)
13. Any step-specific commands from the brief
```

9. **Section 10B Layer B (lines 295–302)**:
```markdown
Achintha pastes `git diff main...HEAD`, or the changed files in manifest order. Claude checks:

- manifest against the diff: missing files, unplanned files, hidden edits to config, composer files, baseline
- signatures against plan section 5
- every rule in the register that applies
- test quality: assertions that prove behavior (not only a 200 status), failure, authorization and boundary cases, factories used
- docs and contract edits match plan section 11
```

10. **Section 11 Closeout and phase exit (lines 327–332)**:
```markdown
Step closeout:

- tick the checkbox in ARCHITECTURE.md Phase 1 progress
- closeout note, 5 lines: what shipped, deviations accepted, debt and follow-ups, what the next step depends on, ADRs added
- PR description: what changed, why, how tested, risks (migrations, API contract, security)
```

11. **Section 12 Prompts to paste to Gemini (lines 347–364)**:
```markdown
Plan request:

```text
Read AGENTS.md and ARCHITECTURE.md, then the Step Brief below. Produce a plan using the Plan template exactly: all 14 sections, in order, none skipped. Read the files you list in section 2 before writing section 3. Do not recall signatures from memory. Do not write code. Do not use: etc., as needed, appropriate, similar to, TBD. If something is ambiguous or conflicts, put it in section 13 and stop there; do not choose. Start with the tag [<STEP ID> | PLAN v1 | Gemini].
```
...
Implementation:

```text
Implement plan <STEP ID> v<N> exactly. Follow the section 12 commit order. One PowerShell command at a time, no &&. No refactors, extra files, dependencies or improvements. If the plan is wrong or incomplete, stop and send a CR; do not work around it. When finished, run composer check, then fill the IMPL-REPORT template with raw unedited outputs. A summary in place of raw output is a failed report.
```
```

12. **Section 13 Suggested AGENTS.md addition (lines 371–380)**:
```markdown
## 13. Suggested AGENTS.md addition (your call)

```text
## Step protocol
- Work only from an approved plan identified by step ID and version. No approved plan, no code.
- If the plan is wrong or incomplete, stop and write a Change Request. Never deviate silently.
- A step ends with an IMPLEMENTATION REPORT containing raw, unedited command output. Summaries are not evidence.
- Do not mark a step done. Only the reviewer accepts it.
```
```

---

#### 3.3 Edit Locations in [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md)

1. **Header (lines 5–8)**:
```markdown
This repository starts as the **content management backend**: admins edit storefront content in Filament, and storefronts read it through a versioned API. It will grow into the full marketplace backend, one phase at a time.

This document is the source of truth for structure and boundaries. If code and this document disagree, fix one of them in the same PR. Only the **active phase** is described in detail; later phases are sketched so names stay consistent, not so they get built early.
```

2. **Section 5 docs line (line 99)**:
```markdown
docs/{adr,contracts}/           # contracts/content-api.md is the API contract
```

3. **Decision D8 (line 275)**:
```markdown
| D8 | MaxCount is enforced at API output limit, not publish lock. Tie-break: `sort_order ASC, starts_at DESC NULLS LAST, id DESC`. One block = one message. |
```

4. **Decision D9 (line 276)**:
```markdown
| D9 | Concurrency & Locks: Content blocks use last-write-wins for payloads, pessimistic locking for state transitions. Menus use whole-tree atomic replacement under a row lock, last-write-wins. |
```

---

### 4. File manifest

| Path | New or modify | Layer | One-line responsibility | Rule IDs it must satisfy |
|---|---|---|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Modify | Governance / Docs | Adds rule IDs, documentation section, guide reading rules, and workflow references. | X4, G1, G2, G3, DOC1–DOC4 |
| [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md) (renamed from `docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md`) | Modify / Rename | Docs | Replaces embedded register table with AGENTS.md reference, integrates guide lifecycle, updates prompts, and removes section 13. | X4, G1, G2, G3, DOC1–DOC4 |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | Modify | Architecture / Docs | Adds guide and workflow pointers to header, updates section 5 docs line, and updates D8 and D9. | X4, G1, G2, G3, DOC1–DOC4 |

**Total file count**: 3 files (2 modifications, 1 git rename with modifications). Zero application code, test, configuration, or database files modified.  
**Placement check**: All files are placed strictly in root governance and [docs/](file:///c:/dev/getnow-cms/docs). No domain, application, or provider namespaces are touched.

---

### 5. Interface specs

N/A, docs only.

---

### 6. Data and migrations

N/A, docs only.

---

### 7. Behavior and flows

N/A, docs only.

---

### 8. Rule compliance matrix

| ID | Rule | Applies | How satisfied |
|---|---|---|---|
| G1 | Branch feat/, fix/ or chore/ from main | Y | Executed on branch `chore/docs-process-integration` branched from `main`. |
| G2 | Conventional Commits | Y | Commits follow format `docs(<scope>): <description>` using type `docs`. |
| G3 | Never push to main or bypass hooks | Y | Work remains on the feature branch; pre-commit and pre-push hooks execute without bypass flags. |
| X4 | Nothing for later phases | Y | No text, folders, or constructs added for Catalog, Retail, or subsequent phases. |
| X6 | composer check passes and its output was read | Y | Full `composer check` will be executed and output read prior to completing the implementation report. |
| X7 | Owning area guides, docs/contracts/content-api.md and .env.example updated where relevant | Y | Preserved unchanged; no area files, contract specifications, or environment variables modified. |
| E1 | PowerShell, one command at a time, no && or bash syntax, no servers left running | Y | Commands executed individually via native PowerShell without chaining or persistent server processes. |
| DOC1 | Before changing files in an area, read its guide | Y | Area guides and documentation system map read prior to planning; no area-owned files modified. |
| DOC2 | A change to files matching a guide's paths updates that guide in the same PR | Y | Manifest files (`AGENTS.md`, `ARCHITECTURE.md`, `docs/workflow.md`) are governance and process docs outside any area's `paths:` globs. |
| DOC3 | Guides state only what is true now: no step IDs, plans, or copies of code, schemas or contract payloads | Y | No step IDs or temporary implementation prose added to documentation deliverables. |
| DOC4 | ARCHITECTURE.md changes only for map-level facts: phases, stack, code structure, dependency rules, decisions | Y | Edits to `ARCHITECTURE.md` restricted to header pointers, section 5 directory index, and decisions D8 and D9. |

---

### 9. AC traceability

| AC | Files | Test names / verification commands | How verified |
|---|---|---|---|
| AC-1 | [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md), [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md), [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md) | `git status --short`, `git diff --name-status main...HEAD` | Proves exactly `M AGENTS.md`, `M ARCHITECTURE.md`, and `R docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md -> docs/workflow.md`. |
| AC-2 | [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | PowerShell script comparing extracted bold IDs against git show on main plus DOC1–DOC4 | Verifies all rule bullets carry IDs, matches main's register set plus DOC1–DOC4, and confirms zero duplicate IDs. |
| AC-3 | [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | `git diff -U0 main...HEAD -- AGENTS.md` | Verifies line-by-line that removed rule lines reappear with only an ID prefix, except X7 and specified additions. |
| AC-4 | [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | `(Get-Content AGENTS.md).Count`, inspection of A1–A7 text blocks | Confirms A1–A7 applied with exact text and total line count is at most 160 lines. |
| AC-5 | [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md) | `(Get-Content docs/workflow.md).Count`, `Select-String` assertions for removals and additions | Confirms old register table, remainder table, and section 13 removed; W1–W11 applied; line count at most 365 lines. |
| AC-6 | [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | `git diff -U0 main...HEAD -- ARCHITECTURE.md` | Confirms exactly 4 hunks (R1–R4); quotes D8 and D9 against code lines and ADRs. |
| AC-7 | [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md), [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md), [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md), `docs/areas/*.md` | PowerShell path resolution script checking all markdown links and rule ID references | Confirms all referenced file paths resolve on disk and rule IDs K1, K2, K3, K4, K7, K8, SEC4, SEC5, DB4, C5, C6, S2 exist in AGENTS.md. |
| AC-8 | Repository root | `git status --short` | Confirms zero changes outside the 3 planned files; only pre-existing untracked files remain. |
| AC-9 | Codebase | `composer check` | Runs Pint, PHPStan, and Pest; verifies all checks pass and output is read. |

---

### 10. Test plan

N/A, docs only.

---

### 11. Docs and contract updates

#### 11.1 Exact Edits to [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md)

1. **A1 (Intro)**:
```markdown
# AGENTS.md

Instructions for AI coding agents working in this repository. Read `ARCHITECTURE.md` before any structural change. Before changing an area, read its guide (see Documentation). If this file and `ARCHITECTURE.md` disagree, stop and ask.
```

2. **A2 (Workflow Item 1)**:
```markdown
1. Read the relevant part of `ARCHITECTURE.md`, the owning area guide and the nearby code before editing.
```

3. **A3 (Workflow Item 5)**:
```markdown
5. Update the owning area guide when behavior, invariants, tables or limits change. Update ARCHITECTURE.md only for map-level changes. Add an ADR in `docs/adr/` for significant decisions.
```

4. **A4 (New Documentation Section after Workflow)**:
```markdown
## Documentation

Map: ARCHITECTURE.md (phases, stack, structure, decisions), docs/README.md (area map), docs/areas/<area>.md (one guide per area), docs/adr/, docs/contracts/, docs/workflow.md (step process).

- **DOC1** Before changing files in an area, read its guide. docs/README.md maps areas to guides.
- **DOC2** A change to files matching a guide's `paths:` updates that guide in the same PR. If a `triggers:` entry matches, answer its checklist in the PR description.
- **DOC3** Guides state only what is true now: no step IDs, plans, or copies of code, schemas or contract payloads. Each invariant names the test that enforces it, or `Review only`.
- **DOC4** ARCHITECTURE.md changes only for map-level facts: phases, stack, code structure, dependency rules, decisions. If a guide and the code disagree, the code wins and the guide is fixed. If a guide conflicts with a rule here or in ARCHITECTURE.md, stop and ask.
```

5. **A5 and A6 (X6, X7 and Definition of Done)**:
```markdown
## Definition of done

- [ ] **X6** `composer check` passes and the output was read
- [ ] Tests added or updated, including failure and authorization cases
- [ ] `tests/Arch` passes
- [ ] Migrations are backward compatible
- [ ] **X7** Owning area guide(s) updated and trigger checklists answered; ARCHITECTURE.md only for map-level changes; docs/contracts/content-api.md and .env.example where relevant (DOC1 to DOC4)
- [ ] Nothing outside the active phase was added
```

6. **A7 (Step Protocol Addition)**:
```markdown
## Step protocol

- Work only from an approved plan identified by step ID and version. No approved plan, no code.
- If the plan is wrong or incomplete, stop and write a Change Request. Never deviate silently.
- A step ends with an IMPLEMENTATION REPORT containing raw, unedited command output. Summaries are not evidence.
- Do not mark a step done. Only the reviewer accepts it.
- Step artifacts (brief, plan, reports) are working files, not product docs. Lasting facts go into the owning guide. The step process is in docs/workflow.md.
```

7. **Rule Prefixes Added to AGENTS.md Bullets**:
- Environment:
  `- **E1** Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`.`
- Where code goes:
  `- **S1** Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too.`  
  `- **S2** Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output.`  
  `- **S3** `app/Domain` must never import from `App\Filament` or `App\Http`.`  
  `- **S4** Filament never calls `Model::create/update/delete` directly. It calls Actions.`  
  `- **S5** Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`.`  
  `- **S6** Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths.`  
  `- **S7** Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder.`
- Coding standards:
  `- **C1** `declare(strict_types=1);` in every PHP file.`  
  `- **C2** Type every parameter, return value and property. Prefer `final` classes.`  
  `- **C3** Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers.`  
  `- **C4** Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field.`  
  `- **C5** The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action.`  
  `- **C6** Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API.`  
  `- **C7** Follow Pint and Larastan. Do not add baseline entries without approval.`  
  `- **C8** Comments explain why, not what. No commented-out code.`
- Content rules (critical):
  `- **K1** Block type keys are permanent. Never rename one.`  
  `- **K2** Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load.`  
  `- **K3** No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans.`  
  `- **K4** Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme.`  
  `- **K5** Placements, menu keys and block types are defined in code. Admins never create them.`  
  `- **K6** Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery.`  
  `- **K7** Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content.`  
  `- **K8** The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change.`  
  `- **K9** Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps.`
- Database rules:
  `- **DB1** PostgreSQL only. Tests run against Postgres, never SQLite.`  
  `- **DB2** Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration.`  
  `- **DB3** No destructive changes (drop or rename column or table) in the same release that stops using them.`  
  `- **DB4** Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers.`  
  `- **DB5** Expose `public_id` (ULID) externally, never raw incremental IDs.`  
  `- **DB6** Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases.`
- Security:
  `- **SEC1** Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`.`  
  `- **SEC2** Do not disable authorization, policies, strict types or static analysis rules to make code pass.`  
  `- **SEC3** Do not log sensitive data.`  
  `- **SEC4** Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization.`  
  `- **SEC5** Write sensitive content changes (publish, disable, menu and settings edits) to the activity log.`
- Testing:
  `- **T1** Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`.`  
  `- **T2** Every Filament resource has a smoke test that loads list, create and edit pages.`  
  `- **T3** Every block type has tests for payload validation, API output and visibility rules.`  
  `- **T4** Use factories, not hand-built rows. Tests must be independent and deterministic.`  
  `- **T5** Do not lower coverage, skip tests or mark them incomplete to pass checks.`
- Git:
  `- **G1** Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`.`  
  `- **G2** Conventional Commits: `feat(content): add announcement bar block`.`  
  `- **G3** Never push to `main` directly or bypass git hooks.`  
  `- PR description: what changed, why, how it was tested, and risks (migrations, API contract, security).` (No register ID).
- Do not:
  `- **X1** Add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval.`  
  `- **X2** Introduce Docker, Sail, docker-compose or any container configuration.`  
  `- **X3** Add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments.`  
  `- Change deployment or infrastructure configuration as a side effect of a feature.` (Unmapped in register).  
  `- Put business logic in controllers, Filament resources, model boot methods or observers.` (No register ID).  
  `- **X5** Modify the storefront repository or assume its internals. The API contract is the only interface.`  
  `- **X4** Create folders, contexts or tables for later phases.`  
  `- Make destructive file or database operations outside migrations and the local reset command above.` (No register ID).

---

#### 11.2 Exact Edits to [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md)

1. **W1 (Header)**:
```markdown
# GetNow CMS: Step Workflow

Claude, Achintha and Gemini. Applies from UI Step 3 onward. Built on AGENTS.md and ARCHITECTURE.md. Product docs: docs/README.md. Read the guide of every area a step touches. If this document conflicts with either, they win and this one gets fixed.
```

2. **W2 (Section 2 Table Removal)**:
Lines 28–43 (the table "Proposed remainder of Phase 1" and the line "Order can change. Dependencies cannot.") are deleted. The ID format paragraph remains:
```markdown
ID format: `P<phase>-<AREA><n>`, with a letter for sub-steps, for example `P1-UI3a`.
```

3. **W3 (Section 4 Rule Register Replacement)**:
Replace Section 4 content with:
```markdown
## 4. Rule register

Plans and reviews cite the rule IDs defined in AGENTS.md (S, C, K, DB, SEC, T, G, X, E, DOC). The plan's rule matrix has one row per ID in AGENTS.md.
```

4. **W4 (Section 5 Step Brief Template)**:
```markdown
Read first:           ARCHITECTURE.md sections, AGENTS.md sections, docs/README.md, owning area guides, existing files (paths)
Guides to update:     guide files and what changes, or None
Decisions made:       Gemini must not re-decide these
```

5. **W5 (Section 6 Plan Template)**:
- Section 2 description:
```markdown
2. Sources read
   Table: file or doc section | what in it constrains this step.
   Includes the owning guide of every manifest file (docs/README.md map).
   Includes every existing file this step will call, extend or copy a pattern from.
   Files are read, not recalled.
```
- Section 4 description:
```markdown
4. File manifest
   Table: path | new or modify | layer | owning guide | one-line responsibility | rule IDs it must satisfy.
   Total file count. Placement checked against AGENTS.md 'Where code goes'.
```
- Section 11 description:
```markdown
11. Docs and contract updates
   Exact edits: each owning guide (section and new text); trigger checklists answered for every matching `triggers:` entry; ARCHITECTURE.md only for map-level changes; content-api.md, fixture, .env.example, ADRs; or None with reason.
```

6. **W6 (Section 7 Plan Review Item 8)**:
```markdown
8. Docs and contract updates listed: owning guides identified, exact guide edits listed, trigger checklists answered, no step IDs or plan prose destined for a guide
```

7. **W7 (Section 9 Implementation Report Item 7)**:
```markdown
7. Docs diffs: each owning guide, ARCHITECTURE.md (map-level only), content-api.md, fixture, .env.example, ADRs; raw output of every edited guide's tests: commands; trigger checklist answers
```

8. **W8 (Section 10A Layer A Additions)**:
After item 11, insert:
```markdown
12. `Select-String -Path docs/areas/*.md -Pattern 'P1-[A-Z]+[0-9]|blueprint|\.php:\d+'` (expect none, DOC3)
13. every changed file that matches a guide's paths has that guide in `git diff --name-status` (DOC2)
14. `composer check` (read all of it)
15. Any step-specific commands from the brief
```

9. **W9 (Section 10B Layer B Addition)**:
Under checklist bullets, add:
```markdown
- guides changed in the diff are reviewed as deliverables: statements checked against code, Enforced by names a test or Review only, no plan prose, D numbers and rule IDs resolve
```

10. **W10 (Section 11 Closeout Addition)**:
In the `Step closeout:` bullet list, add:
```markdown
- guides updated in the step's PR (DOC2); lasting facts from the closeout note go into the guide's Known limits or History
```

11. **W11 (Section 12 Prompts and Section 13 Deletion)**:
- Plan request prompt:
```text
Read AGENTS.md, ARCHITECTURE.md and docs/README.md, then the guides the brief names, then the Step Brief below. Produce a plan using the Plan template exactly: all 14 sections, in order, none skipped. Read the files you list in section 2 before writing section 3. Do not recall signatures from memory. Do not write code. Do not use: etc., as needed, appropriate, similar to, TBD. If something is ambiguous or conflicts, put it in section 13 and stop there; do not choose. Start with the tag [<STEP ID> | PLAN v1 | Gemini].
```
- Implementation prompt:
```text
Implement plan <STEP ID> v<N> exactly. Follow the section 12 commit order. One PowerShell command at a time, no &&. No refactors, extra files, dependencies or improvements. Update the guides named in plan section 11 in the same PR. If the plan is wrong or incomplete, stop and send a CR; do not work around it. When finished, run composer check, then fill the IMPL-REPORT template with raw unedited outputs. A summary in place of raw output is a failed report.
```
- Section 13 (`## 13. Suggested AGENTS.md addition (your call)`) is completely deleted.
- Rename file via `git mv "docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md" docs/workflow.md`.
- References to old file name: zero tracked occurrences outside `docs/blueprints/` verified via `git grep "GetNow CMS Step Workflow"`.

---

#### 11.3 Exact Edits to [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md)

1. **R1 (Header Pointer)**:
```markdown
This document is the source of truth for structure and boundaries. If code and this document disagree, fix one of them in the same PR. Only the **active phase** is described in detail; later phases are sketched so names stay consistent, not so they get built early. Area detail lives in the guides under docs/areas/ (index: docs/README.md). Rules and their IDs live in AGENTS.md. The step process is in docs/workflow.md.
```

2. **R2 (Decision D8)**:
```markdown
| D8 | MaxCount is enforced at API output via in-memory slice, not publish lock (ADR 0001). Deterministic tie-break: `sort_order ASC, starts_at DESC NULLS LAST, id DESC`. One block = one message. |
```

3. **R3 (Decision D9)**:
```markdown
| D9 | Concurrency & Locks: Content blocks use last-write-wins for payloads, pessimistic locking for state transitions (ADR 0003). Menus: the submitted tree is reconciled in one transaction under a row lock. Items matched by public_id update in place, new items are inserted, omitted items are deleted. Last-write-wins. |
```

4. **R4 (Section 5 Docs Path Line 99)**:
```markdown
docs/{adr,areas,contracts}/     # areas = area guides (index docs/README.md); contracts/content-api.md is the API contract
```

---

#### 11.4 Rule ID Mapping Table

| ID | Old register text (workflow on main) | AGENTS.md bullet text (quoted) |
|---|---|---|
| **S1** | "Business logic only in app/Domain/Content/Actions, one invokable per use case, reads included" | "Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too." |
| **S2** | "Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return" | "Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output." |
| **S3** | "App\Domain never imports App\Filament or App\Http" | "`app/Domain` must never import from `App\Filament` or `App\Http`." |
| **S4** | "Filament never calls Model::create, update or delete directly" | "Filament never calls `Model::create/update/delete` directly. It calls Actions." |
| **S5** | "Block definitions in Domain/Content/Blocks/Name, form schemas in app/Filament/Blocks" | "Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`." |
| **S6** | "Panel code under app/Filament/Admin only, nothing in default Filament paths" | "Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths." |
| **S7** | "No new context or top-level folder without asking" | "Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder." |
| **C1** | "declare(strict_types=1) in every PHP file" | "`declare(strict_types=1);` in every PHP file." |
| **C2** | "Typed parameters, returns, properties. Prefer final classes" | "Type every parameter, return value and property. Prefer `final` classes." |
| **C3** | "Enums for fixed sets, spatie/laravel-data for structured input and output between layers" | "Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers." |
| **C4** | "Status and is_enabled change only through Actions (Publish, Archive, Enable, Disable), never a form field" | "Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field." |
| **C5** | "Every write to persisted state, Spatie settings included, goes through an Action" | "The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action." |
| **C6** | "Form Request or Data validation for input, Policies for authorization on every exposed model" | "Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API." |
| **C7** | "Pint and Larastan clean, no baseline entries without approval" | "Follow Pint and Larastan. Do not add baseline entries without approval." |
| **C8** | "Comments explain why, not what. No commented-out code" | "Comments explain why, not what. No commented-out code." |
| **K1** | "Block type keys are permanent" | "Block type keys are permanent. Never rename one." |
| **K2** | "schema_version on every payload, shape change means bump plus upgrader, old payloads still load" | "Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load." |
| **K3** | "No raw HTML, CSS or JS fields. Short text with max lengths, enums, validated links, booleans" | "No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans." |
| **K4** | "Links are a relative path starting with / or an https:// URL, every other scheme rejected" | "Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme." |
| **K5** | "Placements, menu keys, block types defined in code, admins never create them" | "Placements, menu keys and block types are defined in code. Admins never create them." |
| **K6** | "Block registration is an explicit list in ContentServiceProvider" | "Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery." |
| **K7** | "Visibility decided server-side, API never returns draft, archived, disabled or out-of-window content" | "Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content." |
| **K8** | "API v1 additive only, content-api.md and fixture updated in the same change" | "The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change." |
| **K9** | "New block type follows the ARCHITECTURE.md section 6 checklist" | "Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps." |
| **DB1** | "PostgreSQL only, tests never on SQLite" | "PostgreSQL only. Tests run against Postgres, never SQLite." |
| **DB2** | "Never edit a merged migration, add a new one" | "Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration." |
| **DB3** | "No destructive change in the same release that stops using the thing" | "No destructive changes (drop or rename column or table) in the same release that stops using them." |
| **DB4** | "Foreign keys, indexes for query paths, CHECK constraints" | "Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers." |
| **DB5** | "public_id (ULID) external, never raw IDs" | "Expose `public_id` (ULID) externally, never raw incremental IDs." |
| **DB6** | "Seeders idempotent, destructive commands only on phone_store and phone_store_test" | "Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases." |
| **SEC1** | "No secrets or .env committed, new variables in .env.example" | "Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`." |
| **SEC2** | "Never disable authorization, policies, strict types or static analysis rules to pass" | "Do not disable authorization, policies, strict types or static analysis rules to make code pass." |
| **SEC3** | "No sensitive data in logs" | "Do not log sensitive data." |
| **SEC4** | "Admin access checked server-side in canAccessPanel" | "Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization." |
| **SEC5** | "Publish, disable, menu and settings edits written to the activity log" | "Write sensitive content changes (publish, disable, menu and settings edits) to the activity log." |
| **T1** | "Pest. Feature tests for Actions, API, Filament resources. Unit tests for pure logic. Arch tests in tests/Arch" | "Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`." |
| **T2** | "Every Filament resource has a smoke test for list, create and edit" | "Every Filament resource has a smoke test that loads list, create and edit pages." |
| **T3** | "Every block type tests payload validation, API output, visibility" | "Every block type has tests for payload validation, API output and visibility rules." |
| **T4** | "Factories, not hand-built rows. Tests independent and deterministic" | "Use factories, not hand-built rows. Tests must be independent and deterministic." |
| **T5** | "No lowered coverage, skipped or incomplete tests" | "Do not lower coverage, skip tests or mark them incomplete to pass checks." |
| **G1** | "Branch feat/, fix/ or chore/ from main" | "Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`." |
| **G2** | "Conventional Commits" | "Conventional Commits: `feat(content): add announcement bar block`." |
| **G3** | "Never push to main or bypass hooks" | "Never push to `main` directly or bypass git hooks." |
| **X1** | "No dependency outside ARCHITECTURE.md section 3 without approval" | "Add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval." |
| **X2** | "No Docker, Sail or container config" | "Introduce Docker, Sail, docker-compose or any container configuration." |
| **X3** | "No deployment pipeline or infrastructure change as a side effect" | "Add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments." |
| **X4** | "Nothing for later phases" | "Create folders, contexts or tables for later phases." |
| **X5** | "Storefront repo untouched" | "Modify the storefront repository or assume its internals. The API contract is the only interface." |
| **X6** | "composer check passes and its output was read" | "`composer check` passes and the output was read" |
| **X7** | "ARCHITECTURE.md, content-api.md, .env.example updated where relevant" | "Owning area guide(s) updated and trigger checklists answered; ARCHITECTURE.md only for map-level changes; docs/contracts/content-api.md and .env.example where relevant (DOC1 to DOC4)" |
| **E1** | "PowerShell, one command at a time, no && or bash syntax, no servers left running" | "Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`." |
| **DOC1** | *(New rule)* | "Before changing files in an area, read its guide. docs/README.md maps areas to guides." |
| **DOC2** | *(New rule)* | "A change to files matching a guide's `paths:` updates that guide in the same PR. If a `triggers:` entry matches, answer its checklist in the PR description." |
| **DOC3** | *(New rule)* | "Guides state only what is true now: no step IDs, plans, or copies of code, schemas or contract payloads. Each invariant names the test that enforces it, or `Review only`." |
| **DOC4** | *(New rule)* | "ARCHITECTURE.md changes only for map-level facts: phases, stack, code structure, dependency rules, decisions. If a guide and the code disagree, the code wins and the guide is fixed. If a guide conflicts with a rule here or in ARCHITECTURE.md, stop and ask." |

---

### 12. Implementation order

1. **Commit 1**: `docs(agents): add rule IDs, documentation section, and process references`
   - Files: [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md)
   - Scope: Apply A1–A7, add bold rule ID prefixes (S1–S7, C1–C8, K1–K9, DB1–DB6, SEC1–SEC5, T1–T5, G1–G3, X1–X7, E1, DOC1–DOC4), insert `## Documentation` section.
   - Verification: `composer check` passes; `(Get-Content AGENTS.md).Count` <= 160; AC-2 ID extraction script passes.

2. **Commit 2**: `docs(workflow): integrate area guides and rename workflow to docs/workflow.md`
   - Files: [docs/workflow.md](file:///c:/dev/getnow-cms/docs/workflow.md) (via `git mv "docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md" docs/workflow.md`)
   - Scope: Apply W1–W11, replace register table with AGENTS.md pointer, remove remainder table, update templates and prompts, delete section 13.
   - Verification: `composer check` passes; `(Get-Content docs/workflow.md).Count` <= 365; `git status` shows rename `R`.

3. **Commit 3**: `docs(architecture): add docs and workflow pointers and align D8 and D9 with code`
   - Files: [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md)
   - Scope: Apply R1–R4, update header pointers, update section 5 docs line, update D8 and D9 text.
   - Verification: `composer check` passes; `git diff -U0 main...HEAD -- ARCHITECTURE.md` shows exactly 4 hunks.

---

### 13. Risks, assumptions, conflicts

None.

---

### 14. Dependencies

N/A, docs only.
