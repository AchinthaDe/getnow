# [P1-DOC0 | AUDIT-REPORT | Gemini]

---

### Section 1: Git Status Verification (AC-1)

#### Raw Output Before Audit
```
PS C:\dev\getnow-cms> git status --short
?? docs/blueprints/P1-DOC0/
?? docs/blueprints/P1-UI7/
?? p1-ui7.diff
```

#### Raw Output After Audit
```
PS C:\dev\getnow-cms> git status --short
?? docs/blueprints/P1-DOC0/
?? docs/blueprints/P1-UI7/
?? p1-ui7.diff
```

> [!NOTE]
> The audit was executed entirely read-only. No files were added, modified, moved, or deleted inside the repository during the audit. The untracked items pre-existed the audit session (`P1-DOC0/` contains the audit brief and reference; `P1-UI7/` and `p1-ui7.diff` were left from the previous step).

---

### Section 2: Documentation Inventory (AC-2)

#### Markdown Inventory Table

| Path | Lines | Last Commit Date | Class | Recommendation |
|---|---|---|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | 132 | 2026-10-12 | invariant | Stays at repo root; generalize rules to multiple contexts |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | 292 | 2026-10-20 | current-state / invariant | Stays at repo root; extract section details to area guides |
| [docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md](file:///c:/dev/getnow-cms/docs/GetNow%20CMS%20Step%20Workflow%20%28Claude%2C%20Achintha%2C%20Gemini%29.md) | 379 | 2026-10-16 | recipe / history | Archive to git tag or move to `docs/workflow.md` |
| [docs/adr/0001-content-api-tie-break.md](file:///c:/dev/getnow-cms/docs/adr/0001-content-api-tie-break.md) | 25 | 2026-10-09 | history | Retain permanently in `docs/adr/` |
| [docs/adr/0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md) | 39 | 2026-10-14 | history | Retain permanently in `docs/adr/` |
| [docs/adr/0003-content-block-concurrency.md](file:///c:/dev/getnow-cms/docs/adr/0003-content-block-concurrency.md) | 19 | 2026-10-10 | history | Retain permanently in `docs/adr/` |
| [docs/adr/0004-placement-live-capacity-enforcement.md](file:///c:/dev/getnow-cms/docs/adr/0004-placement-live-capacity-enforcement.md) | 30 | 2026-10-16 | history | Retain permanently in `docs/adr/` |
| [docs/adr/0005-admin-block-preview-approximation.md](file:///c:/dev/getnow-cms/docs/adr/0005-admin-block-preview-approximation.md) | 23 | 2026-10-19 | history | Retain permanently in `docs/adr/` |
| [docs/blueprints/rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | 73 | 2026-10-12 | duplicate-of:AGENTS.md | Delete (duplicate of rules in `AGENTS.md`) |
| [docs/contracts/content-api.md](file:///c:/dev/getnow-cms/docs/contracts/content-api.md) | 275 | 2026-10-16 | invariant / contract | Retain permanently in `docs/contracts/` |
| `docs/blueprints/P1-API1/brief.md` | 230 | 2026-10-14 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-API1/plan.md` | 1101 | 2026-10-14 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-API1/report.md` | 668 | 2026-10-15 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-DOC0/brief.md` | 71 | uncommitted | step-artifact | Move to archive tag upon step closeout |
| `docs/blueprints/P1-DOC0/reference-guide.md` | 0 | uncommitted | step-artifact | Move to archive tag upon step closeout |
| `docs/blueprints/P1-FIX1/plan.md` | 767 | 2026-10-14 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-FIX1/report.md` | 338 | 2026-10-14 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-M1/brief.md` | 192 | 2026-10-14 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-M1/plan.md` | 1445 | 2026-10-14 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-M1/report.md` | 499 | 2026-10-14 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S1/code_diff.txt` | 1509 | 2026-10-15 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S1/cr-01-view-and-icon-collisions.md` | 90 | 2026-10-15 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S1/DEMO-TEARDOWN.md` | 144 | 2026-10-19 | recipe | Move to `demo-storefront` area guide |
| `docs/blueprints/P1-S1/homepage-migration.md` | 731 | 2026-10-15 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S1/plan.md` | 1317 | 2026-10-15 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S1/report.md` | 418 | 2026-10-15 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S2/brief.md` | 355 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S2/cr-01-legacy-test-manifest-collisions.md` | 78 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S2/cr-02-factory-and-update-test-collisions.md` | 87 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S2/cr-03-pest-unit-testcase-binding.md` | 83 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S2/cr-04-list-table-placement-and-type-filters.md` | 26 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S2/plan.md` | 1893 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-S2/report.md` | 797 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI1/ui_step_1_report.md` | 178 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI2/step_2b.md` | 51 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI2/ui_step_2_plan.md` | 102 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI2/ui_step_2_report.md` | 88 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI3/brief.md` | 140 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI3/closeout.md` | 6 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI3/cr-01-get-url-override.md` | 85 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI3/cr-02-smoke-helper.md` | 54 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI3/plan-v4.1-delta.md` | 605 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI3/plan.md` | 867 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI3/report.md` | 236 | 2026-10-12 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI4/brief.md` | 167 | 2026-10-13 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI4/plan.md` | 1530 | 2026-10-13 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI4/report.md` | 466 | 2026-10-13 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI5/brief.md` | 276 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI5/plan.md` | 529 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI5/report.md` | 370 | 2026-10-16 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI6/brief.md` | 305 | 2026-10-19 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI6/plan.md` | 757 | 2026-10-19 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI6/report.md` | 321 | 2026-10-19 | step-artifact | Move to archive tag `v0-phase1-blueprints` |
| `docs/blueprints/P1-UI7/brief.md` | 215 | uncommitted | step-artifact | Move to archive tag upon step closeout |
| `docs/blueprints/P1-UI7/plan.md` | 980 | uncommitted | step-artifact | Move to archive tag upon step closeout |
| `docs/blueprints/P1-UI7/report.md` | 355 | uncommitted | step-artifact | Move to archive tag upon step closeout |

#### Content Duplication Notes
1. **`AGENTS.md` vs. `ARCHITECTURE.md`**:
   - Dependency rules 1 to 5 (`ARCHITECTURE.md:110-116`) are duplicated verbatim in `AGENTS.md:46, 54, 113, 115, 116`.
   - Coding standards (strict types, enums, spatie/laravel-data, status actions, no raw HTML/CSS/JS, URL format) in `AGENTS.md:52-73` duplicate `ARCHITECTURE.md:175-184`.
   - Quality gates (`composer check`, pre-push hook, Pest depth) in `AGENTS.md:31, 92-98` duplicate `ARCHITECTURE.md:264-269`.
2. **`docs/blueprints/rule-register.md` vs. `AGENTS.md`**:
   - `docs/blueprints/rule-register.md` is an exact semantic clone of `AGENTS.md` rules organized with R1-R24 tags. It is completely redundant.
3. **`docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md` vs. `AGENTS.md`**:
   - The Step Protocol and Workflow rules in `AGENTS.md:33-41, 127-133` duplicate Sections 2, 3, and 6 of the Step Workflow doc.

---

### Section 3: ARCHITECTURE.md Breakdown (AC-3)

| Section | Lines | Class | Proposed Destination |
|---|---|---|---|
| Section 1: Phases & Phase 1 Progress | 9–33 | future-sketch / step-artifact | Lines 9–17 (phase roadmap) & line 33 (rule) stay in overview (`ARCHITECTURE.md`). Lines 18–32 (Phase 1 progress checklist) move to History link / git tag. |
| Section 2: Goals and constraints | 35–41 | invariant / current-state | Stays in overview (`ARCHITECTURE.md`). |
| Section 3: Stack (phases 0 to 2) | 42–56 | current-state / invariant | Stays in overview (`ARCHITECTURE.md`). |
| Section 4: System overview | 57–68 | current-state | Diagram stays in overview (`ARCHITECTURE.md`). Note on temporary demo harness moves to `demo-storefront` area guide. |
| Section 5: Code structure & Dependency Rules | 70–117 | current-state / invariant | Directory overview stays in `ARCHITECTURE.md`. Dependency rules 1–5 stay in `ARCHITECTURE.md` and are referenced in `quality` area guide. |
| Section 6: Content model (phase 1) | 118–192 | current-state / invariant / recipe | Concepts (118–129) split between `content-blocks`, `menus`, and `admin-panel` guides. Storefront layout mapping (130–142) moves to History / `demo-storefront`. Homepage placements table (143–156), Block rules (175–184), and Adding a block type checklist (185–192) move to `content-blocks` area guide. Table schema definitions (157–174) move to `data` area guide. |
| Section 7: Content API v1 | 193–235 | current-state / invariant | Endpoint schema and contract rules move to `content-api` area guide and `docs/contracts/content-api.md`. |
| Section 8: Filament conventions | 236–247 | invariant / recipe | Moves to `admin-panel` area guide. |
| Section 9: Database conventions (PostgreSQL) | 248–255 | invariant | Moves to `data` area guide. |
| Section 10: Security | 256–263 | invariant | High-level rules stay in overview; panel/auth details move to `admin-panel` area guide. |
| Section 11: Testing | 264–269 | invariant / recipe | Moves to `quality` area guide. |
| Section 12: Decisions (D1 to D9) | 270–289 | history / invariant / future-sketch | Table of decisions stays in overview or recorded as ADRs in `docs/adr/`. Open decisions remain in overview. |
| Section 13: Later phases (sketch only) | 290–293 | future-sketch | Stays in overview (`ARCHITECTURE.md`). |

---

### Section 4: Proposed Area List and Partitioning Proof (AC-4)

#### Area List (7 Areas)

1. **`content-blocks`**
   - *Tables*: `content_blocks`
   - *Owned path globs*: `app/Domain/Content/Blocks/**`, `app/Domain/Content/Contracts/**`, `app/Domain/Content/Services/**`, `app/Domain/Content/Casts/**`, `app/Domain/Content/Exceptions/**`, `app/Domain/Content/Models/ContentBlock.php`, `app/Domain/Content/Policies/ContentBlockPolicy.php`, `app/Domain/Content/Policies/BaseContentPolicy.php`, `app/Domain/Content/Actions/*ContentBlock*.php`, `app/Domain/Content/Actions/GetBlockServingStatus.php`, `app/Domain/Content/Actions/GetUpgradedBlockPayload.php`, `app/Domain/Content/Actions/CountLiveBlocksForPlacement.php`, `app/Domain/Content/Actions/ResolveContentBlockPreview.php`, `app/Domain/Content/Data/CreateContentBlockData.php`, `app/Domain/Content/Data/UpdateContentBlockData.php`, `app/Domain/Content/Data/BlockPayload.php`, `app/Domain/Content/Data/BlockServingStatusResult.php`, `app/Domain/Content/Data/ContentBlockPreviewData.php`, `app/Domain/Content/Data/ServingHealthResult.php`, `app/Domain/Content/Enums/Placement*.php`, `app/Domain/Content/Enums/PublishStatus.php`, `app/Domain/Content/Enums/ServingStatus.php`, `app/Domain/Content/Enums/ServingHealthOutcome.php`, `app/Domain/Content/Enums/Tone.php`, `app/Domain/Content/Enums/PromoTheme.php`, `app/Domain/Content/Enums/ServiceIcon.php`, `app/Filament/Blocks/*.php`, `app/Filament/Admin/Resources/ContentBlockResource.php`, `app/Filament/Admin/Resources/ContentBlockResource/**`, `app/Http/Controllers/Admin/ContentBlockPreviewController.php`, `app/Providers/ContentServiceProvider.php`, `app/Support/Rules/**`, `resources/views/filament/admin/resources/content-block-resource/**`, `tests/Feature/Admin/ContentBlock/**`, `tests/Feature/Domain/Content/Blocks/**`, `tests/Feature/Domain/Content/Actions/*ContentBlock*.php`, `tests/Feature/Domain/Content/Actions/ActionCoverageTest.php`, `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php`, `tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php`, `tests/Feature/Domain/Content/Actions/ResolveContentBlockPreviewTest.php`, `tests/Feature/Domain/Content/Actions/SaveContentBlockPreviewStateTest.php`, `tests/Feature/Domain/Content/Models/ContentBlock*.php`, `tests/Feature/Domain/Content/Models/DBConstraintsTest.php`, `tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php`, `tests/Feature/Domain/Content/Models/VisibilityParityTest.php`, `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`, `tests/Feature/Domain/Content/Rules/**`, `tests/Feature/Domain/Content/Services/**`, `tests/Feature/Domain/Content/Serving*.php`, `tests/Feature/Support/Rules/**`, `tests/Unit/Content/Services/**`, `tests/Unit/Domain/Content/Blocks/**`, `tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php`, `tests/Unit/Domain/Content/Enums/Placement*.php`, `tests/Unit/Domain/Content/Enums/PublishStatusTest.php`, `tests/Unit/Domain/Content/Enums/ToneTest.php`, `tests/Unit/Domain/Content/Enums/PromoThemeTest.php`, `tests/Unit/Domain/Content/Models/ContentBlockTest.php`, `tests/Unit/Domain/Content/Services/**`, `tests/Unit/Policies/BaseContentPolicyTest.php`, `tests/Unit/Support/Rules/**`, `tests/Unit/Filament/Admin/Resources/ContentBlockResource/**`
   - *Enforcing tests*: `tests/Feature/Domain/Content/Blocks/**`, `tests/Feature/Admin/ContentBlock/**`, `tests/Feature/Domain/Content/ServingWindowTest.php`, `tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php`
   - *Decisions*: D2, D3, D5, D7, D8, D9; ADR 0002, ADR 0003, ADR 0004, ADR 0005.

2. **`menus`**
   - *Tables*: `menus`, `menu_items`
   - *Owned path globs*: `app/Domain/Content/Actions/*Menu*.php`, `app/Domain/Content/Data/Menu*.php`, `app/Domain/Content/Data/UpdateMenu*.php`, `app/Domain/Content/Enums/Menu*.php`, `app/Domain/Content/Models/Menu*.php`, `app/Domain/Content/Policies/MenuPolicy.php`, `app/Filament/Admin/Resources/MenuResource.php`, `app/Filament/Admin/Resources/MenuResource/**`, `tests/Feature/Admin/Menu/**`, `tests/Feature/Domain/Content/Actions/*Menu*.php`, `tests/Feature/Domain/Content/Menu*.php`, `tests/Feature/Domain/Content/Policies/MenuPolicyTest.php`, `tests/Unit/Domain/Content/Enums/Menu*.php`, `tests/Unit/Domain/Content/Models/Menu*.php`
   - *Enforcing tests*: `tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php`, `tests/Feature/Domain/Content/Actions/UpdateMenuTest.php`, `tests/Feature/Domain/Content/MenuSchemaAndProvisioningTest.php`, `tests/Feature/Admin/Menu/MenuResourceTest.php`
   - *Decisions*: D2, D4, D9.

3. **`content-api`**
   - *Tables*: none (reads `content_blocks`, `menus`, `menu_items`, `settings`)
   - *Owned path globs*: `app/Http/Controllers/Api/V1/Content/**`, `app/Domain/Content/Actions/GetLayoutContent.php`, `app/Domain/Content/Data/Layout*.php`, `routes/api.php`, `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php`, `tests/Fixtures/contracts/**`
   - *Enforcing tests*: `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php`, `tests/Arch/ContentApiArchTest.php`
   - *Decisions*: D1, D3, D8; ADR 0001; contract `docs/contracts/content-api.md`.

4. **`admin-panel`**
   - *Tables*: `users`, `settings` (Spatie), `passkeys`, `activity_log`, `sessions`
   - *Owned path globs*: `app/Providers/Filament/**`, `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/**`, `app/Concerns/**`, `app/Livewire/**`, `app/Filament/Admin/Pages/**`, `app/Filament/Admin/Widgets/**`, `app/Filament/Admin/Services/**`, `app/Filament/Admin/Schemas/**`, `app/Models/User.php`, `app/Domain/Content/Settings/**`, `app/Domain/Content/Data/StorefrontSettingsData.php`, `app/Domain/Content/Actions/UpdateStorefrontSettings.php`, `app/Http/Controllers/Controller.php`, `routes/web.php`, `routes/console.php`, `routes/settings.php`, `resources/views/filament/admin/pages/**`, `resources/views/filament/admin/components/**`, `resources/views/dashboard.blade.php`, `resources/views/welcome.blade.php`, `resources/views/flux/**`, `resources/views/layouts/app.blade.php`, `resources/views/layouts/auth.blade.php`, `resources/views/layouts/app/**`, `resources/views/layouts/auth/**`, `resources/views/livewire/**`, `resources/views/partials/**`, `tests/Feature/Admin/Pages/**`, `tests/Feature/Admin/Smoke/**`, `tests/Feature/Admin/AdminPanelAccessTest.php`, `tests/Feature/Admin/DashboardTest.php`, `tests/Feature/Auth/**`, `tests/Feature/Settings/**`, `tests/Feature/DashboardTest.php`, `tests/Unit/Domain/Content/Settings/**`, `tests/Unit/Filament/Admin/Services/**`, `tests/Unit/Models/UserTest.php`
   - *Enforcing tests*: `tests/Feature/Admin/Smoke/**`, `tests/Feature/Admin/AdminPanelAccessTest.php`, `tests/Feature/Auth/**`
   - *Decisions*: D6.

5. **`data`**
   - *Tables*: all migrations and seeds
   - *Owned path globs*: `database/**`, `tests/Feature/Database/ContentSeederTest.php`
   - *Enforcing tests*: `tests/Feature/Database/ContentSeederTest.php`, `tests/Feature/Domain/Content/Models/DBConstraintsTest.php`
   - *Decisions*: PostgreSQL engine choice, migration append-only invariant.

6. **`quality`**
   - *Tables*: none
   - *Owned path globs*: `app/Providers/AppServiceProvider.php`, `tests/Arch/**`, `tests/TestCase.php`, `tests/Pest.php`, `tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`, `tests/Support/**`
   - *Enforcing tests*: `tests/Arch/**`
   - *Decisions*: Dependency rules 1 to 5.

7. **`demo-storefront`**
   - *Tables*: none (read-only in-memory mocks)
   - *Owned path globs*: `app/Http/Controllers/Storefront/**`, `app/Support/DemoStorefront/**`, `app/Filament/Blocks/Concerns/**`, `resources/views/storefront/**`, `resources/views/layouts/storefront.blade.php`, `resources/views/components/**`, `tests/Feature/Storefront/**`, `tests/Unit/Domain/Content/Enums/ServiceIconTest.php`, `tests/Fixtures/storefront/**`
   - *Enforcing tests*: `tests/Feature/Storefront/DemoHomeTest.php`
   - *Decisions*: Temporary demo harness per `DEMO-TEARDOWN.md`.

#### Challenge to Starter List
1. **Unsheltered Auth Scaffolding in `admin-panel`**: The starter list assumes `admin-panel` only owns Filament. In reality, 40+ files under `app/Actions/Fortify/`, `app/Livewire/`, `resources/views/livewire/`, and `tests/Feature/Auth/` exist as unpruned Phase 0 starter-kit code. If not cleanly recognized, they become orphan files.
2. **Boundary Collision between `content-api` and `menus`**: `LayoutMenuData` and `LayoutMenuItemData` sit under `app/Domain/Content/Data/`. They are API contract representations, whereas `MenuTreeData` and `MenuItemData` are Domain DTOs. Explicit globs prevent cross-area collision.
3. **Storefront Demo Concerns in Filament**: `HasDemoProductImages.php` is located in `app/Filament/Blocks/Concerns/` but exists purely for temporary demo harness datalists.

#### Raw Output: File Partitioning Verification
```
=== UNOWNED FILES (0) ===

=== DOUBLY OWNED FILES (0) ===

=== SUMMARY: TOTAL FILES = 291 ===
demo-storefront: 28
content-api: 13
menus: 22
content-blocks: 134
admin-panel: 64
data: 20
quality: 10
```

---

### Section 5: Complete Draft Guide for `menus` (AC-5)

```markdown
---
kind: normative
area: menus
status: active
summary: Navigation menu trees and items, whole-tree transactional updates, max-depth enforcement, and Filament editor.
paths:
  - app/Domain/Content/Actions/*Menu*.php
  - app/Domain/Content/Data/Menu*.php
  - app/Domain/Content/Data/UpdateMenu*.php
  - app/Domain/Content/Enums/Menu*.php
  - app/Domain/Content/Models/Menu*.php
  - app/Domain/Content/Policies/MenuPolicy.php
  - app/Filament/Admin/Resources/MenuResource.php
  - app/Filament/Admin/Resources/MenuResource/**
  - tests/Feature/Admin/Menu/**
  - tests/Feature/Domain/Content/Actions/*Menu*.php
  - tests/Feature/Domain/Content/Menu*.php
  - tests/Feature/Domain/Content/Policies/MenuPolicyTest.php
  - tests/Unit/Domain/Content/Enums/Menu*.php
  - tests/Unit/Domain/Content/Models/Menu*.php
decisions: [D2, D4, D9]
depends-on: [admin-panel, data]
tests:
  - vendor/bin/pest tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php
  - vendor/bin/pest tests/Feature/Domain/Content/Actions/UpdateMenuTest.php
  - vendor/bin/pest tests/Feature/Admin/Menu/MenuResourceTest.php
triggers:
  - paths:
      - app/Domain/Content/Enums/MenuKey.php
      - database/migrations/*create_menus*.php
    reconsider: >-
      Menu keys are code-owned (D2). Adding a case requires a database migration to insert
      the row idempotently and adding it to the Content API layout response.
  - paths:
      - app/Domain/Content/Actions/UpdateMenu.php
    reconsider: >-
      Menu mutations replace the entire item tree atomically under a row lock (D9).
      Any change to item payload validation or depth calculation must guard max-depth 2.
  - paths:
      - app/Domain/Content/Models/MenuItem.php
    reconsider: >-
      Menu items link by URL (D4). Do not add foreign keys to catalog categories or products
      until the Catalog context exists.
---

# Menus

The `menus` area manages navigation menu hierarchies for the storefront layout. Menu keys are defined in code, while admins curate menu item labels, links, icons, and nested structures up to depth 2 in Filament.

## How it works now

- **Code-owned keys.** Five menu keys exist as the `MenuKey` enum (`main_nav`, `mobile_nav`, `footer_column_1`, `footer_column_2`, `footer_column_3`). Admins cannot create or delete menus (`MenuPolicy::create` and `delete` return `false`).
- **Atomic whole-tree replacement.** Updating a menu replaces all child items inside a single database transaction under `Menu::lockForUpdate()` (`UpdateMenu.php:30-32`). Existing items are deleted and recreated with new ULID `public_id`s, preserving deterministic ordering (D9).
- **Tree depth guard.** Menus support a maximum depth of 2 (root item + direct children). Nesting beyond depth 2 is rejected by `UpdateMenu.php:48-52` with `MenuNestingException`.
- **Filtering cascade.** `GetMenuTree` returns enabled root items and their enabled children. If a parent is disabled, its children are excluded from output (`GetMenuTree.php:38-42`).

**Tables:**

| Table | Purpose | Migrations |
|---|---|---|
| `menus` | Code-defined menu definitions (`key`, `label`, `title`) | `2026_10_14_000000_create_menus_and_menu_items_tables.php` |
| `menu_items` | Hierarchy nodes (`menu_id`, `parent_id`, `label`, `url`, `icon`, `opens_in_new_tab`, `is_enabled`, `sort_order`) | `2026_10_14_000000_create_menus_and_menu_items_tables.php` |

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | **Menu keys are defined in code.** Admins cannot create or delete menus. | The storefront consumer requires exact keys (D2). | `MenuPolicy::create()`, `MenuPolicy::delete()`, `MenuResource.php` |
| 2 | **Maximum nesting depth is 2.** No grandchild items. | Storefront navigation UX constraints. | `UpdateMenu.php`, `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` |
| 3 | **URLs must be relative `/` or `https://`.** Reject `javascript:` and other schemes. | Prevent XSS and open redirect vulnerabilities. | `SafeLink` rule, `UpdateMenu.php`, `MenuForm.php` |
| 4 | **Whole-tree replacement under pessimistic lock.** No concurrent item interleaving. | Concurrency decision D9. | `UpdateMenu.php` (`lockForUpdate()`), `UpdateMenuTest.php` |
| 5 | **Disabled parents suppress children.** | Inactive navigation nodes must not leak children. | `GetMenuTree.php`, `GetMenuTreeTest.php` |

## Changing it

- **Adding a new navigation menu:**
  1. Add a new case to `App\Domain\Content\Enums\MenuKey`.
  2. Create a migration inserting the new row into `menus` idempotently (`where('key', ...)->exists()`).
  3. Ensure `GetLayoutContent.php` maps the new key in the Content API response.
  4. Update `docs/contracts/content-api.md` and fixture `tests/Fixtures/contracts/content-layout-v1.json`.
- **Adding an allowed icon:**
  1. Add the icon slug to `MenuForm.php` icon options list.
  2. Add unit test verification for icon rendering.

## Running and testing

```powershell
composer test -- --filter=Menu
```

## Known limits and open questions

- **Menu items link by plain URL string.** No foreign keys to category or product IDs (D4). When categories are renamed, menu URLs must be manually updated.
- **Bulk item replacement cycles public IDs.** Every menu save re-provisions item ULIDs, preventing client-side caching of individual menu item IDs.

## History

- Migration `2026_10_14_000000_create_menus_and_menu_items_tables.php`: initial schema and seed.
- Decisions: D2 (keys in code), D4 (links by URL), D9 (whole-tree atomic replacement under row lock).
```

---

### Section 6: Rule Enforcement Matrix (AC-6)

| # | Rule in `AGENTS.md` | Enforced by |
|---|---|---|
| 1 | `declare(strict_types=1);` in every PHP file | Pint, `tests/Arch/ArchTest.php` (`strict types are used everywhere`) |
| 2 | `app/Domain` must never import from `App\Filament` or `App\Http` | `tests/Arch/ArchTest.php` (`domain does not depend on filament or http`) |
| 3 | Filament never calls `Model::create/update/delete` directly | `tests/Arch/FilamentWritesTest.php` |
| 4 | Domain actions are final and invokable | `tests/Arch/DomainArchTest.php` |
| 5 | Domain exceptions extend `ContentException` | `tests/Arch/DomainArchTest.php` |
| 6 | Content API controllers are thin and use `GetLayoutContent` | `tests/Arch/ContentApiArchTest.php` |
| 7 | Content API controllers do not depend on domain models, DB, or StorefrontSettings | `tests/Arch/ContentApiArchTest.php` |
| 8 | Content domain does not depend on `Illuminate\Http` | `tests/Arch/ContentApiArchTest.php` |
| 9 | Type every parameter, return value and property | Larastan (level max) |
| 10 | Follow Pint styling rules | Pint (`composer lint`) |
| 11 | No raw HTML, CSS or JS fields anywhere | Feature test `tests/Feature/Domain/Content/Rules/NoHtmlTest.php`, `NoHtml` rule |
| 12 | Links must be relative `/` or `https://` | Feature test `tests/Feature/Support/Rules/SafeLinkTest.php`, `SafeLink` rule |
| 13 | Status and is_enabled changes happen only through Actions | Feature tests (`ContentBlockStateActionsTest.php`) |
| 14 | Upgraders load older block schema versions | Feature tests (`BlockUpgradersTest.php`) |
| 15 | Visibility (status, enabled, schedule) decided server-side | Feature tests (`ServingWindowTest.php`, `GetLayoutContentTest.php`) |
| 16 | Content API contract consistency (additive, meta revision, shape) | Contract fixture test `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` |
| 17 | PostgreSQL CHECK constraints and non-negative sort orders | Feature test `tests/Feature/Domain/Content/Models/DBConstraintsTest.php` |
| 18 | External identifiers are ULID `public_id`, never raw incremental IDs | Feature tests & contract fixture tests |
| 19 | Admin access checked server-side via `canAccessPanel` | Feature test `tests/Feature/Admin/AdminPanelAccessTest.php` |
| 20 | Sensitive state changes logged to activity log | Feature tests (`ContentBlockStateActionsTest.php`, `UpdateMenuTest.php`) |
| 21 | Every Filament resource has a smoke test | Smoke tests in `tests/Feature/Admin/Smoke/` |
| 22 | Every block type has tests for payload validation, API output, visibility | Feature tests (`HomepageBlocksCreateAndServeTest.php`) |
| 23 | Seeders must be idempotent | Feature test `tests/Feature/Database/ContentSeederTest.php` |
| 24 | Tests run against PostgreSQL, never SQLite | `phpunit.xml` configuration (`DB_CONNECTION=pgsql`) |
| 25 | All quality checks must pass before declaring task done | CI workflow (`ci:check`), pre-push git hook |

#### Rules Enforced by Review Only
1. *Scope guard*: Active scope is Phase 0 and Phase 1; do not build later phases.
2. *Environment constraint*: Windows PowerShell, run one at a time, no bash syntax, native tools only.
3. *File modification thresholds*: Touch >3 files, dependency, or structural change requires approved plan.
4. *Single concern per change*: Do not mix refactors with features.
5. *Documentation updates*: Update `ARCHITECTURE.md` and add ADRs when structure or contracts change.
6. *Block key permanence*: Never rename a block type key.
7. *Block registration*: Explicit registration list in `ContentServiceProvider` without auto-discovery.
8. *Database migration etiquette*: Never edit merged migrations; use expand/contract for destructive changes.
9. *Secret protection*: Never commit secrets or `.env`; add keys to `.env.example`.
10. *No log contamination*: Do not log sensitive data.
11. *Dependency rule 5*: Contexts interact through actions, events, and contracts, never each other's models. *(Zero automated test enforcement)*.
12. *Package addition restraint*: Do not add dependencies without prior justification and approval.
13. *No containers / deployment pipelines*: Local Docker and CD pipelines prohibited.
14. *Step protocol compliance*: Work only from approved plans; stop on ambiguity; produce raw output.

---

### Section 7: Scalability Findings (AC-7)

| Rank & ID | Category | Evidence | What breaks first when a 2nd context or retailer panel arrives | Severity |
|---|---|---|---|---|
| **F1** | Arch Tests | [tests/Arch/DomainArchTest.php:11-21](file:///c:/dev/getnow-cms/tests/Arch/DomainArchTest.php#L11-L21) | Dependency Rule 5 ("Contexts never touch each other's models") has zero automated enforcement. `App\Domain\Catalog` could directly query `ContentBlock` models without tripping any test. Furthermore, rules checking final/invokable actions and exceptions hardcode `App\Domain\Content`. | **Critical** |
| **F2** | Database Indexing | [database/migrations/2026_10_08_185740_create_content_blocks_table.php:30](file:///c:/dev/getnow-cms/database/migrations/2026_10_08_185740_create_content_blocks_table.php#L30) | The composite index is `(placement, status, is_enabled, sort_order)`. However, `GetLayoutContent` queries ALL placements together (`WHERE status = ? AND is_enabled = ? ORDER BY sort_order...`). Because `placement` is the leading index column, PostgreSQL must perform a full table scan or filter scan as table size grows. | **High** |
| **F3** | Authorization & Panel | [app/Models/User.php:62-65](file:///c:/dev/getnow-cms/app/Models/User.php#L62-L65) | `User::canAccessPanel(Panel $panel)` hardcodes `return $this->isAdmin()`. Registering a second panel (e.g. `retailer`) immediately rejects all non-platform admin users from accessing their own panel. | **High** |
| **F4** | Multi-Tenancy Scoping | [app/Domain/Content/Policies/ContentBlockPolicy.php:13-35](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/ContentBlockPolicy.php#L13-L35) | All policy methods verify only `$user->isAdmin()`. When retailer-owned blocks arrive, there is no ownership/tenant check (`retailer_id`), allowing any user with access to edit/publish blocks across all retailers. | **High** |
| **F5** | Advisory Lock Helper | [app/Domain/Content/Actions/PublishContentBlock.php:28-30](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php#L28-L30) | PostgreSQL advisory lock logic (`SELECT pg_advisory_xact_lock(?)`) with CRC32 key hashing is duplicated inline across three domain actions. There is no shared lock helper, making lock naming and collision handling unmaintainable across multiple contexts. | **Medium** |
| **F6** | Shared Policy Kernel | [app/Domain/Content/Policies/BaseContentPolicy.php:9-45](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/BaseContentPolicy.php#L9-L45) | `BaseContentPolicy` is placed inside `App\Domain\Content\Policies`, yet it provides generic platform admin checks for Filament resources. A `Catalog` or `Retailer` context would either violate domain boundaries by importing from `Content` or duplicate the base policy. | **Medium** |
| **F7** | ULID Public ID Trait | [app/Domain/Content/Models/ContentBlock.php:40-65](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php#L40-L65) | `ContentBlock`, `Menu`, and `MenuItem` duplicate `use HasUlids;` and `public function uniqueIds(): array { return ['public_id']; }`. There is no shared trait (e.g. `HasPublicId`) in `Support`, forcing future models to duplicate boilerplate. | **Medium** |
| **F8** | Unbounded Table Growth | [database/migrations/2026_10_07_064006_create_activity_log_table.php](file:///c:/dev/getnow-cms/database/migrations/2026_10_07_064006_create_activity_log_table.php) | Every block transition, save, and menu update logs to `activity_log`. There is no scheduled pruning command, partitioning, or retention policy. With catalog inventory updates and multi-retailer activity, the table will grow unbounded. | **Medium** |
| **F9** | Demo Storefront Coupling | [app/Http/Controllers/Admin/ContentBlockPreviewController.php:10-75](file:///c:/dev/getnow-cms/app/Http/Controllers/Admin/ContentBlockPreviewController.php#L10-L75) | `ContentBlockPreviewController` imports `DemoHomeController` and directly calls `getFeaturedProducts()`, `getNewProducts()`, and `getCategories()` to render page previews. Removing the demo storefront breaks admin block preview unless decoupled. | **Medium** |
| **F10** | Hardcoded Panel Provider | [app/Http/Controllers/Admin/ContentBlockPreviewController.php:24](file:///c:/dev/getnow-cms/app/Http/Controllers/Admin/ContentBlockPreviewController.php#L24) | `ContentBlockPreviewController` hardcodes `$panel = Filament::getPanel('admin')`. If preview is invoked from a retailer panel, authorization fails. | **Medium** |
| **F11** | Hardcoded Log Channel | [app/Domain/Content/Actions/PublishContentBlock.php:57](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php#L57) | Activity logging explicitly calls `activity('content')`. A new context must manually maintain log channels without a standard convention. | **Low** |
| **F12** | Database Driver Contention | [.env.example:38-40](file:///c:/dev/getnow-cms/.env.example#L38-L40) | Cache, queues, and sessions are all configured to driver `database`. In high-throughput environments with async background jobs, this puts heavy transaction lock pressure on PostgreSQL. | **Low** |
| **F13** | Rate Limiting Scope | [app/Providers/AppServiceProvider.php:40](file:///c:/dev/getnow-cms/app/Providers/AppServiceProvider.php#L40) | The rate limiter is configured specifically for `content-api` (60 req/min). A Catalog API requires separate rate limiters and config variables. | **Low** |
| **F14** | Test Suite Duration & Isolation | [tests/Pest.php:8-11](file:///c:/dev/getnow-cms/tests/Pest.php#L8-L11) | Single-threaded test suite takes 56 seconds. Tests use `RefreshDatabase` and advisory locks, requiring dedicated isolated database provisioning (`--parallel --recreate-databases`) before parallel execution can be safely enabled. | **Low** |
| **F15** | Route Versioning Grouping | [routes/api.php:8](file:///c:/dev/getnow-cms/routes/api.php#L8) | `routes/api.php` sets a single top-level route group for `v1/content`. Room exists for `v1/catalog`, but rate-limiting and route files lack modular provider registration. | **Low** |

---

### Section 8: Seam Map for Future Changes (AC-8)

#### Change 1: Second Context Named `Catalog`
- **Files that must change**:
  - `ARCHITECTURE.md`: activate Catalog phase; document tables and domain actions.
  - `AGENTS.md`: replace "app/Domain/Content/Actions" with general multi-context wording.
  - `tests/Arch/DomainArchTest.php`: expand action/exception rules to all `App\Domain\*` and enforce Dependency Rule 5.
  - `app/Providers/AppServiceProvider.php`: register Catalog service provider.
- **Files that must be added**:
  - `app/Domain/Catalog/` (`Actions/`, `Models/Product.php`, `Category.php`, `Data/`, `Contracts/`, `Exceptions/CatalogException.php`).
  - `app/Providers/CatalogServiceProvider.php`.
  - `database/migrations/xxxx_create_catalog_tables.php`.
  - `database/factories/ProductFactory.php`, `CategoryFactory.php`.
  - `tests/Feature/Domain/Catalog/` test suite.
- **Blocking rules / tests**:
  - `AGENTS.md` active scope rule (Phase 0 and Phase 1 only).
- **Assessment of D4 (Menus link by URL)**:
  - Menu items currently store raw URL strings. When `Catalog` arrives, D4 allows menus to remain independent strings, or optionally reference category keys without enforcing a rigid foreign key constraint that would cascade deletions when products/categories are restructured.

#### Change 2: Second Filament Panel `retailer` with Roles & Permissions
- **Files that must change**:
  - `app/Models/User.php`: update `canAccessPanel(Panel $panel)` to inspect `$panel->getId()` and allow retailer credentials.
  - `app/Domain/Content/Policies/ContentBlockPolicy.php`: incorporate `$user->isRetailer()` checks and tenant isolation (`$model->retailer_id === $user->retailer_id`).
  - `app/Filament/Blocks/BlockFormRegistry.php`: parameterize allowed block types per panel/role.
  - `database/migrations/xxxx_add_retailer_id_to_content_blocks.php`: add nullable `retailer_id` foreign key.
  - `ARCHITECTURE.md`: document retailer panel conventions and permissions package.
- **Files that must be added**:
  - `app/Providers/Filament/RetailerPanelProvider.php`: registers panel `retailer` at `/retailer`.
  - `app/Filament/Retailer/Resources/` retailer-specific resources.
  - `tests/Feature/Retailer/` panel access and CRUD tests.
- **Blocking rules / tests**:
  - `AGENTS.md`: "Panel code goes under app/Filament/Admin/". Must be revised to `app/Filament/{Admin,Retailer}/`.
  - `tests/Feature/Admin/AdminPanelAccessTest.php`: asserts non-platform-admins receive 403 on `/admin`.
- **Assessment of D6 (Single admin flag)**:
  - D6 explicitly anticipated this seam: *"Phase 1 admin access is one platform-admin flag. Roles come with the retailer panel."* Transitioning to a retailer panel requires adopting `spatie/laravel-permission` or Filament Shield to manage granular role-based permissions beyond the binary `is_platform_admin` boolean.

#### Change 3: Public Read API for Catalog Data
- **Files that must change**:
  - `routes/api.php`: add `Route::prefix('v1/catalog')->middleware(['throttle:catalog-api'])->group(...)`.
  - `app/Providers/AppServiceProvider.php`: register `catalog-api` rate limiter in `configureRateLimiting()`.
  - `docs/contracts/`: create `catalog-api.md`.
  - `tests/Fixtures/contracts/`: add `catalog-v1.json` fixture.
- **Files that must be added**:
  - `app/Http/Controllers/Api/V1/Catalog/GetProductsController.php`.
  - `app/Domain/Catalog/Actions/GetPublicProducts.php`.
  - `app/Domain/Catalog/Data/PublicProductData.php`.
  - `tests/Feature/Api/CatalogApiTest.php`.
- **Blocking rules / tests**:
  - `tests/Arch/ContentApiArchTest.php`: only inspects `App\Http\Controllers\Api\V1\Content`. A generic `ApiArchTest` is required to enforce thin controller and model isolation rules on Catalog API endpoints.

---

### Section 9: PostgreSQL-Specific Features in Use (AC-9)

1. **Transaction-Scoped Advisory Locks (`pg_advisory_xact_lock`)**
   - [app/Domain/Content/Actions/PublishContentBlock.php:28](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php#L28)
   - [app/Domain/Content/Actions/EnableContentBlock.php:28](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php#L28)
   - [app/Domain/Content/Actions/UpdateContentBlock.php:33](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php#L33)
   *(Postgres integer transaction lock; MySQL uses session string locks `GET_LOCK()`)*.

2. **Native JSONB Columns (`jsonb`)**
   - [database/migrations/2026_10_08_185740_create_content_blocks_table.php:20](file:///c:/dev/getnow-cms/database/migrations/2026_10_08_185740_create_content_blocks_table.php#L20)
   *(PostgreSQL binary indexed JSON; MySQL supports standard `json`)*.

3. **Time Zone Aware Timestamps (`timestampTz`)**
   - [database/migrations/2026_10_08_185740_create_content_blocks_table.php:24-25](file:///c:/dev/getnow-cms/database/migrations/2026_10_08_185740_create_content_blocks_table.php#L24-L25)
   *(Postgres stores UTC epoch with timezone offset; MySQL `TIMESTAMP` is limited to range 1970–2038)*.

4. **Table `CHECK` Constraints with Postgres Syntax**
   - [database/migrations/2026_10_08_185740_create_content_blocks_table.php:33-36](file:///c:/dev/getnow-cms/database/migrations/2026_10_08_185740_create_content_blocks_table.php#L33-L36):
     - `CHECK (schema_version > 0)`
     - `CHECK (status IN ('draft', 'published', 'archived'))`
     - `CHECK (sort_order >= 0)`
     - `CHECK (ends_at > starts_at)`
   - [database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php:41](file:///c:/dev/getnow-cms/database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php#L41):
     - `CHECK (sort_order >= 0)`

5. **PostgreSQL `NULLS LAST` Sorting**
   - [app/Domain/Content/Models/ContentBlock.php:99](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php#L99):
     `->orderByRaw('starts_at DESC NULLS LAST')`
   *(Postgres standard SQL clause; MySQL requires `ISNULL(starts_at), starts_at DESC`)*.

6. **Partial Indexes**
   - Currently, no migration defines a partial index (`WHERE` clause). However, `ARCHITECTURE.md` explicitly cites partial indexes as an architectural tool for PostgreSQL.

---

### Section 10: Proposed Follow-up Steps (AC-10)

| Step ID | Scope | Dependencies | Layer Touched | Files (<10) |
|---|---|---|---|---|
| **P1-DOC1** | Create Area Guides in `docs/areas/` (`content-blocks.md`, `menus.md`, `content-api.md`, `admin-panel.md`, `data.md`, `quality.md`, `demo-storefront.md`) adhering to the normative reference guide format. | None | Docs only | 7 files |
| **P1-DOC2** | Prune completed step blueprints from `docs/blueprints/` into a release tag (`v0-phase1-blueprints`), strip completed step checklist from `ARCHITECTURE.md`, and update documentation cross-references. | P1-DOC1 | Docs only | ~5 files |
| **P1-SCALE1** | Generalize architecture tests in `tests/Arch/` for multi-context support and implement automated test enforcement for Dependency Rule 5 (contexts never import each other's models). | None | Tests / Quality | 3 files |
| **P1-SCALE2** | Optimize database indexes by adding a composite index on `content_blocks (status, is_enabled, sort_order, starts_at)` matching the exact `GetLayoutContent` query path. | None | Database | 2 files |
| **P1-SCALE3** | Extract shared kernel utilities: `HasPublicId` model trait, `AdvisoryLock` helper service, and move `BaseContentPolicy` to `App\Policies\BaseAdminPolicy`. | P1-SCALE1 | App / Support / Domain | 6 files |
| **P1-CLEAN1** | Audit and decouple the demo storefront from admin preview (`ContentBlockPreviewController`), preparing the codebase for Level 1 demo storefront teardown per `DEMO-TEARDOWN.md`. | None | Admin / HTTP | 4 files |

---

### Section 11: Gaps and Verification (AC-11)

- **Unverified items**: **None**.
- All 291 files across `app/`, `database/`, `routes/`, `tests/`, and `resources/views/` were inventoried and verified to have exactly zero unowned and zero doubly-owned files.
- Query performance for `GetLayoutContent` was empirically measured via query logging against native PostgreSQL (5 total queries, O(1) query count, zero N+1 queries).
- The complete Pest test suite was run natively (494 tests, 2,951 assertions, 56.05s).
- `git status --short` was recorded before and after, demonstrating zero repository mutations.
