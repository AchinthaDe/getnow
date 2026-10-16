# P1-S2 IMPLEMENTATION REPORT

**Step ID:** P1-S2  
**Status:** Completed (Fixes Applied per VERIFY r1 & r2)  
**Base Commit:** `03becb18ad52f2df2d5c72379683326d59814ab4` (`03becb1 docs(blueprints): add P1-S1 plan, migration notes, cr-01, report, and code diff`)  
**Tracking Upstream:** `origin/main` (`03becb18ad52f2df2d5c72379683326d59814ab4`)  
**Branch:** `feat/homepage-cms-blocks`  
**Head Commit:** `782c615` (`782c615 test(admin): add HomepageAdminWorkflowTest, drop history 404 test, and remove committed diff files`)  
**Commits on Branch (`base..HEAD`):**
1. `58c8cee` feat(content): upgrade announcement bar block to schema v2 with v1 upgrader
2. `427598e` feat(content): add sort order support to content block actions and filament forms
3. `08c9c28` feat(content): add homepage content block definitions, data classes, and service icon enum
4. `65a98ff` feat(content): register homepage blocks, expand placements, and wire filament forms
5. `c812cb9` feat(content): seed all 9 homepage placements idempotently in ContentSeeder
6. `c279321` feat(storefront): wire homepage placements with full-bar and full-card clickable link targets
7. `1cb8050` docs(architecture): document homepage CMS block types and placements
8. `f921c39` docs(blueprints): add P1-S2 plan, change requests, report, and code diff
9. `bc4fd28` fix(content): make table filters dynamic, add sort order bounds, and harden seeder and views
10. `d3cfae2` docs(blueprints): update P1-S2 plan v4.1, implementation report, and code diff for r1 fixes
11. `186eb61` fix(content): refine sort order bounds, placement labels, seeder exceptions, and view tests
12. `5051cc8` docs(blueprints): update plan v4.1 manifests, teardown debt, and synchronized diffs
13. `cfb8c74` test(admin): add Layer C click-path test covering filters, creation, views, and sort order
14. `fe6d4a0` docs(blueprints): finalize report with Layer C click-path results, MUT-F1, and synchronized diffs
15. `782c615` test(admin): add HomepageAdminWorkflowTest, drop history 404 test, and remove committed diff files

**Approved Change Requests:**
- `CR-P1-S2-01`: Announcement Bar Schema v2 Field Count Expansion (3 to 5 fields) — Approved
- `CR-P1-S2-02`: Factory & Update Test Collisions (`ContentBlockFactory`, `UpdateContentBlockTest`) — Approved
- `CR-P1-S2-03`: Unit Test Suite Laravel Container Binding (`tests/Pest.php`) — Approved
- `CR-P1-S2-04`: Dynamic List Table Placement and Type Filters (`ContentBlockTable`, `ListContentBlocksTableTest`) — Implemented in Commit 8

---

## 1. Commit Log (`git log --oneline 03becb1...HEAD`)

```text
782c615 test(admin): add HomepageAdminWorkflowTest, drop history 404 test, and remove committed diff files
fe6d4a0 docs(blueprints): finalize report with Layer C click-path results, MUT-F1, and synchronized diffs
cfb8c74 test(admin): add Layer C click-path test covering filters, creation, views, and sort order
5051cc8 docs(blueprints): update plan v4.1 manifests, teardown debt, and synchronized diffs
186eb61 fix(content): refine sort order bounds, placement labels, seeder exceptions, and view tests
d3cfae2 docs(blueprints): update P1-S2 plan v4.1, implementation report, and code diff for r1 fixes
bc4fd28 fix(content): make table filters dynamic, add sort order bounds, and harden seeder and views
f921c39 docs(blueprints): add P1-S2 plan, change requests, report, and code diff
1cb8050 docs(architecture): document homepage CMS block types and placements
c279321 feat(storefront): wire homepage placements with full-bar and full-card clickable link targets
c812cb9 feat(content): seed all 9 homepage placements idempotently in ContentSeeder
65a98ff feat(content): register homepage blocks, expand placements, and wire filament forms
08c9c28 feat(content): add homepage content block definitions, data classes, and service icon enum
427598e feat(content): add sort order support to content block actions and filament forms
58c8cee feat(content): upgrade announcement bar block to schema v2 with v1 upgrader
```

---

## 2. File Reconciliation (`git diff --name-status 03becb1...HEAD`)

```text
M	ARCHITECTURE.md
M	app/Domain/Content/Actions/CreateContentBlock.php
M	app/Domain/Content/Actions/UpdateContentBlock.php
M	app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php
M	app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php
A	app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarV1ToV2Upgrader.php
A	app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php
A	app/Domain/Content/Blocks/BrandStrip/BrandStripData.php
A	app/Domain/Content/Blocks/BrandStrip/BrandStripItemData.php
A	app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php
A	app/Domain/Content/Blocks/FeatureBanner/FeatureBannerData.php
A	app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php
A	app/Domain/Content/Blocks/PromoSlide/PromoSlideData.php
A	app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php
A	app/Domain/Content/Blocks/SectionHeader/SectionHeaderData.php
A	app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php
A	app/Domain/Content/Blocks/ServiceItem/ServiceItemData.php
A	app/Domain/Content/Contracts/BlockPayloadUpgrader.php
M	app/Domain/Content/Data/CreateContentBlockData.php
M	app/Domain/Content/Data/UpdateContentBlockData.php
M	app/Domain/Content/Enums/Placement.php
A	app/Domain/Content/Enums/PromoTheme.php
A	app/Domain/Content/Enums/ServiceIcon.php
M	app/Domain/Content/Models/ContentBlock.php
M	app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php
M	app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php
M	app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php
M	app/Filament/Blocks/AnnouncementBarForm.php
M	app/Filament/Blocks/BlockFormRegistry.php
A	app/Filament/Blocks/BrandStripForm.php
A	app/Filament/Blocks/Concerns/HasDemoProductImages.php
A	app/Filament/Blocks/FeatureBannerForm.php
A	app/Filament/Blocks/PromoSlideForm.php
A	app/Filament/Blocks/SectionHeaderForm.php
A	app/Filament/Blocks/ServiceItemForm.php
M	app/Http/Controllers/Storefront/DemoHomeController.php
M	app/Providers/ContentServiceProvider.php
M	database/factories/ContentBlockFactory.php
M	database/seeders/ContentSeeder.php
A	docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md
M	docs/blueprints/P1-S1/DEMO-TEARDOWN.md
A	docs/blueprints/P1-S2/brief.md
A	docs/blueprints/P1-S2/cr-01-legacy-test-manifest-collisions.md
A	docs/blueprints/P1-S2/cr-02-factory-and-update-test-collisions.md
A	docs/blueprints/P1-S2/cr-03-pest-unit-testcase-binding.md
A	docs/blueprints/P1-S2/cr-04-list-table-placement-and-type-filters.md
A	docs/blueprints/P1-S2/plan.md
A	docs/blueprints/P1-S2/report.md
M	docs/contracts/content-api.md
M	resources/css/storefront/carousel.css
M	resources/css/storefront/layout.css
M	resources/css/storefront/pages.css
M	resources/views/components/promo-carousel.blade.php
M	resources/views/layouts/storefront.blade.php
M	resources/views/storefront/home.blade.php
M	tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php
M	tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php
A	tests/Feature/Admin/ContentBlock/HomepageAdminWorkflowTest.php
A	tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php
M	tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php
M	tests/Feature/Content/BlockFormRegistryTest.php
M	tests/Feature/Database/ContentSeederTest.php
M	tests/Feature/Domain/Content/Actions/CreateContentBlockTest.php
M	tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php
M	tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php
M	tests/Feature/Storefront/DemoHomeTest.php
M	tests/Fixtures/contracts/content-layout-v1.json
M	tests/Pest.php
A	tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php
A	tests/Unit/Domain/Content/Blocks/BrandStripDataTest.php
A	tests/Unit/Domain/Content/Blocks/FeatureBannerDataTest.php
A	tests/Unit/Domain/Content/Blocks/PromoSlideDataTest.php
A	tests/Unit/Domain/Content/Blocks/SectionHeaderDataTest.php
A	tests/Unit/Domain/Content/Blocks/ServiceItemDataTest.php
M	tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php
M	tests/Unit/Domain/Content/Enums/PlacementTest.php
A	tests/Unit/Domain/Content/Enums/ServiceIconTest.php
```

Total changed files against base `03becb1`: **77 files** (68 codebase/test/contract files, 9 documentation / blueprint files).

### File Reconciliation Notes (Review Findings F7 & G2)
1. **`AnnouncementBarDataTest`**: The existing test file `tests/Feature/Domain/Content/Blocks/AnnouncementBarDataTest.php` was authored during Step 2a (`9d389bc`) and was NOT modified or added in P1-S2. Its earlier listing under `tests/Unit/Domain/Content/Blocks/` in report r1 was an agent reporting typo; `git ls-files tests/Unit/Domain/Content/Blocks` confirms only the 6 newly added unit tests exist under Unit.
2. **Diff Cleanup (`code_diff.txt`)**: Per finding G2, committed diff files (`docs/blueprints/P1-S2/code_diff.txt` deleted, root `code_diff.txt` restored to base) have been removed from tracking to eliminate self-referential git recursion. Diffs are generated on-demand outside repository tracking.
3. **`docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md`**: Team workflow protocol document formally recorded under unplanned documentation.
4. **`HomepageAdminWorkflowTest.php`**: Replaces `LayerCClickPathTest.php`, asserting real admin workflow actions without the dummy 404 `/history` assertion.
5. **`GetLayoutContentEndpointTest.php`**: Omitted from Modify manifest because `GetLayoutContent` already dynamically resolves all `Placement::cases()`, and contract verification tests are encapsulated in `HomepageBlocksCreateAndServeTest.php` and `ContentSeederTest.php`.

### Reconciliation Table

| Planned and Done | Planned and Not Done | Unplanned (with Reason) |
|---|---|---|
| All 65 code/test files matching Plan v4.1 manifests | None | `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` (CR-04: made filters dynamic) |
| | | `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php` (CR-04: added drift guard tests) |
| | | `tests/Feature/Admin/ContentBlock/LayerCClickPathTest.php` (F6: added automated Layer C click-path test suite) |
| | | `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php` (F6: added view hydration tests for promo_slide & brand_strip) |
| | | `tests/Feature/Content/BlockFormRegistryTest.php` (CR-P1-S2-01: updated expected schema field count 3 to 5) |
| | | `tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php` (CR-P1-S2-01: added `sort_order` parameter assertion) |
| | | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` (CR-P1-S2-01: updated schema v2 payload assertions) |
| | | `database/factories/ContentBlockFactory.php` (CR-P1-S2-02: updated announcement bar default payload to v2) |
| | | `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php` (CR-P1-S2-02: updated v2 announcement bar payload + m1 sort_order range and boundary tests) |
| | | `tests/Feature/Domain/Content/Actions/CreateContentBlockTest.php` (m1: added sort_order boundary acceptance tests) |
| | | `tests/Pest.php` (CR-P1-S2-03: bound `TestCase` to `Unit/` for Spatie Data validator resolution) |
| | | `tests/Feature/Database/ContentSeederTest.php` (m4: added environment guard RuntimeException test and archived bypass test) |
| | | `app/Domain/Content/Models/ContentBlock.php` (m1: defined MIN_SORT_ORDER and MAX_SORT_ORDER constants) |
| | | `app/Domain/Content/Enums/Placement.php` (m-placement-label: defined Placement::label() helper) |

---

## 3. Acceptance Criteria Results (Strict Alignment with Frozen Plan §9)

| AC ID | Part | Requirement Summary | Verifying Test Name (`it('...')`) | Test File & Line | Result |
|---|---|---|---|---|---|
| **AC-1** | A | `AnnouncementBarBlock` is at `schema_version = 2` with `secondary_text` and `locale_text`. Stored v1 payloads upgrade automatically via `AnnouncementBarV1ToV2Upgrader` to v2 in `GetUpgradedBlockPayload` and `GetLayoutContent` (`Rule K2`). | `it('upgrades stored v1 announcement bar payloads to v2 with null defaults')` | `tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php:11` | **PASS** |
| **AC-1** | A | Upgrader preserves existing secondary/locale text if present | `it('preserves existing secondary and locale text if already present during upgrade')` | `tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php:24` | **PASS** |
| **AC-1** | A | Serving action executes payload upgrade before returning | `it('upgrades announcement_bar v1 stored payload to v2 before serving')` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php:79` | **PASS** |
| **AC-2** | A | All 5 new block types are registered in `ContentServiceProvider` and `BlockFormRegistry`, validate payloads (`NoHtml`, `SafeLink`, max lengths, enums), and can be created and edited in Filament while observing draft, disabled, and schedule visibility rules (`Rule K6`, `K9`, `T3`). Schedule serving windows also verified by existing tests. | `it('creates and serves each allowed block type for every homepage placement in Filament while excluding non-live blocks')` (includes M2 future `starts_at` omission) | `tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php:144` | **PASS** |
| **AC-2** | A | Filament repeater and sort order editing preserves list format | `it('edits block payloads and sort_order preserving repeater list structure')` | `tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php:228` | **PASS** |
| **AC-2** | A | Schedule serving window evaluated server-side | `it('evaluates published scheduled block as not live before starts_at and live at starts_at without database write')` | `tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php:18` | **PASS** |
| **AC-2** | A | Drift guards enforce dynamic placement and type filters | `it('drift-guard: placement filter offers every Placement case and filters correctly')`, `it('drift-guard: type filter offers registered block types and filters correctly')` | `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php:396, 437` | **PASS** |
| **AC-3** | A | All 9 `Placement` cases are present in `data.placements` on `GET /api/v1/content/layout` and documented in `docs/contracts/content-api.md` and `tests/Fixtures/contracts/content-layout-v1.json` (`Rule K8`). | `it('verifies allowed block types and max counts for all 9 placements')` | `tests/Unit/Domain/Content/Enums/PlacementTest.php:12` | **PASS** |
| **AC-3** | A | Layout API returns exact 9-placement structure matching contract fixture | `it('enables GET /api/v1/content/layout to return the seeded storefront layout')` | `tests/Feature/Database/ContentSeederTest.php:257` | **PASS** |
| **AC-4** | B | `php artisan db:seed` populates all 9 placements (14 published `ContentBlock` rows total). Running `db:seed` twice produces the exact same row count (`14` blocks) with zero duplicates. On re-runs, existing single-block placements are reset to their intended seed state (`published`, `is_enabled = true`, `sort_order = 0`), while multi-block placements with existing non-archived blocks are skipped to prevent duplicate rows (`Rule DB6`). | `it('seeds all 9 homepage placements matching exact storefront demo content')` | `tests/Feature/Database/ContentSeederTest.php:64` | **PASS** |
| **AC-4** | B | Seeder idempotency on repeat runs preserving ULIDs | `it('is strictly idempotent on repeat execution preserving row counts across multi-block placements')` | `tests/Feature/Database/ContentSeederTest.php:117` | **PASS** |
| **AC-4** | B | Resetting single-block placements while preserving multi-block slides | `it('resets single-block placements to published and enabled seeded state while skipping populated multi-block placements on re-run')` | `tests/Feature/Database/ContentSeederTest.php:141` | **PASS** |
| **AC-4** | B | Restoring disabled single-block placement (`home_brand_strip`) | `it('restores a disabled home_brand_strip to its intended seeded state upon seeder rerun')` | `tests/Feature/Database/ContentSeederTest.php:180` | **PASS** |
| **AC-4** | B | Seeder aborts outside local/testing environments (m4) | `it('aborts when executed outside local or testing environments')` | `tests/Feature/Database/ContentSeederTest.php:283` | **PASS** |
| **AC-5** | B | Full-bar link UX on Announcement Bar: when `announcement_bar.link_url` is set, `GET /` renders the outer `.announcement` bar itself as an `<a href="..." class="announcement ... announcement--link">` spanning the whole bar, and renders `text`, `secondary_text`, and `locale_text` dynamically. | `it('renders full-bar clickable link target when announcement link_url is present')` | `tests/Feature/Storefront/DemoHomeTest.php:46` | **PASS** |
| **AC-5** | B | Dynamic rendering of secondary and locale text | `it('renders secondary and locale text dynamically on announcement bar')` | `tests/Feature/Storefront/DemoHomeTest.php:61` | **PASS** |
| **AC-6** | B | Full-card link UX & Dynamic Homepage Rendering: `GET /` renders hero promo carousel, hero side banner, 4 service strip items, 3 section headers, collection feature banner, and brand strip dynamically from `GetLayoutContent`, with full-card clickable links on banners, slides, and linked service items. | `it('dynamically renders all 8 homepage CMS placements from GetLayoutContent')` | `tests/Feature/Storefront/DemoHomeTest.php:70` | **PASS** |
| **AC-6** | B | Stretched link targets on banners and service items | `it('renders full-card clickable links on banners and linked service items')` | `tests/Feature/Storefront/DemoHomeTest.php:92` | **PASS** |
| **AC-7** | B | Dynamic CMS Mutation & Empty-State Resilience (`Rule K7`): editing any homepage block in the CMS immediately updates `GET /`, and disabling or deleting all blocks in any placement omits that section cleanly on `GET /` without crashing. | `it('demonstrates admin mutations propagate dynamically to storefront homepage without deployment')` | `tests/Feature/Storefront/DemoHomeTest.php:123` | **PASS** |
| **AC-7** | B | Placements omitted cleanly without crashing | `it('omits placements cleanly without crashing when blocks are disabled or empty')` | `tests/Feature/Storefront/DemoHomeTest.php:101` | **PASS** |
| **AC-7** | B | Graceful rendering when all placements and menus are empty | `it('renders gracefully when placements and menus are empty')` | `tests/Feature/Storefront/DemoHomeTest.php:145` | **PASS** |
| **AC-8** | B | `DEMO-TEARDOWN.md` is updated to clearly delineate permanent CMS deliverables from demo-only frontend assets, and `composer check` (Pint, PHPStan Level 8, Pest including `tests/Arch`) passes with zero errors and zero baseline additions. | `it('verifies demo teardown documentation accurately delineates permanent cms code from temporary demo assets')` | `tests/Feature/Storefront/DemoHomeTest.php:173` | **PASS** |
| **AC-8** | B | Verification check suite | `composer check` (Pint, PHPStan Level 8, Pest) | 429 passed tests, 0 failures, 0 errors | **PASS** |

---

## 4. Deviations from Frozen Plan

1. **Gate Discipline (STOP Gates CP-A1 & CP-A2)**:
   In the initial execution, Commits 1 through 7 were executed in a continuous sequence locally rather than waiting for explicit reviewer acceptance strings (`CP-A1 ACCEPTED` and `CP-A2 ACCEPTED`). All commits arrived together at Checkpoint CP-B. This deviation is recorded here per reviewer instruction.
2. **Review Feedback & Findings Evolution**:
   - **Finding F1 / CR-04**: The list table placement filter in `ContentBlockTable.php` initially retained a hardcoded option set. Resolved via CR-04 in Commit 8 (`bc4fd28`), building placement options from `Placement::cases()` and type options from `BlockRegistry::all()`, with drift-guard tests in `ListContentBlocksTableTest.php`.
   - **Finding Grouping in Commit `bc4fd28`**: Commit 8 bundled F1, m1, m4, and m5. Following review r2, subsequent fixes were split into strict single-concern commits (`186eb61`, `5051cc8`, `cfb8c74`, `fe6d4a0`).
   - **Finding G2 (Diff File Self-Referential Recursion)**: Committed diff files (`docs/blueprints/P1-S2/code_diff.txt` deleted, root `code_diff.txt` restored to base commit `03becb1`) were removed from git tracking to eliminate self-referential git recursion. Diffs are generated on-demand outside repository tracking.
   - **Finding G5 (Process Name Leak & History Step)**: `LayerCClickPathTest.php` was renamed to `HomepageAdminWorkflowTest.php`, test descriptions stripped of review step tags, and the dummy 404 `/history` assertion dropped in favor of substantive workflow assertions.
3. **Change Requests Reconciled**:
   - **CR-P1-S2-01**: Expanded expected schema fields count from 3 fields (`text`, `link_url`, `tone`) to 5 fields (adding `secondary_text` and `locale_text`) in `AnnouncementBarForm`.
   - **CR-P1-S2-02**: Updated `ContentBlockFactory` announcement bar default payload to schema v2, updating `UpdateContentBlockTest` accordingly.
   - **CR-P1-S2-03**: Bound `TestCase` globally to `Unit/` in `tests/Pest.php` to resolve Spatie Data validator container resolution in unit test suites.
   - **CR-P1-S2-04**: Made list table placement and type filters dynamic using `Placement::cases()` and `BlockRegistry::all()`.
4. **Unplanned Files / New Supporting Code**:
   | File | Layer | Reason for Addition |
   |---|---|---|
   | `app/Domain/Content/Models/ContentBlock.php` | Domain | Defined `MIN_SORT_ORDER = 0` and `MAX_SORT_ORDER = 2147483647` constants to prevent duplicate literals across actions and forms (Finding m1 refinement). |
   | `app/Domain/Content/Enums/Placement.php` | Domain | Added `Placement::label(): string` helper to eliminate duplicate title-casing across `ContentBlockTable` and `ContentBlockForm`. |
   | `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php` | Tests | Verified Filament `ViewContentBlock` renders with HTTP 200 for `promo_slide` and `brand_strip` with nested repeater arrays. |
   | `tests/Feature/Admin/ContentBlock/HomepageAdminWorkflowTest.php` | Tests | Automated end-to-end admin workflow test replacing `LayerCClickPathTest.php` (Finding G5). |
   | `docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md` | Docs | Team step protocol, roles, and evidence rules document governing step execution and reviews. |

---

## 5. Raw Unedited Verification Evidence

### 5.1 `composer check` (Full Raw Output)
```text
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":429,"passed":429,"assertions":2602,"duration_ms":43835}
```

### 5.2 `git status --short` (Clean Working Tree)
```powershell
PS C:\dev\getnow-cms> git status --short
# (Clean working tree - zero unstaged, modified, or untracked files)
```

### 5.3 `php artisan migrate:status`
```text
+------+-------------------------------------------------------+-------+
| Ran? | Migration                                             | Batch |
+------+-------------------------------------------------------+-------+
| Yes  | 0001_01_01_000000_create_users_table                  | 1     |
| Yes  | 0001_01_01_000001_create_cache_table                  | 1     |
| Yes  | 0001_01_01_000002_create_jobs_table                   | 1     |
| Yes  | 2022_12_14_083707_create_settings_table               | 1     |
| Yes  | 2024_01_01_000000_create_passkeys_table               | 1     |
| Yes  | 2025_08_14_170933_add_two_factor_columns_to_users_... | 1     |
| Yes  | 2026_10_07_064006_create_activity_log_table           | 1     |
| Yes  | 2026_10_07_070410_add_is_platform_admin_to_users_... | 1     |
| Yes  | 2026_10_07_091043_create_storefront_settings          | 1     |
| Yes  | 2026_10_07_101138_add_batch_uuid_column_to_activit... | 1     |
| Yes  | 2026_10_08_185740_create_content_blocks_table         | 1     |
| Yes  | 2026_10_14_000000_create_menus_and_menu_items_tables  | 1     |
+------+-------------------------------------------------------+-------+
```

---

## 6. Rule Self-Audit (Workflow §10A Mechanical Verification with Verbatim Raw Outputs)

### Command 1: `git status --short`
```powershell
PS C:\dev\getnow-cms> git status --short
# (Clean working tree - zero unstaged or untracked files)
```

### Command 2: `git log --oneline 03becb1...HEAD`
```powershell
PS C:\dev\getnow-cms> git log --oneline 03becb1...HEAD
782c615 test(admin): add HomepageAdminWorkflowTest, drop history 404 test, and remove committed diff files
fe6d4a0 docs(blueprints): finalize report with Layer C click-path results, MUT-F1, and synchronized diffs
cfb8c74 test(admin): add Layer C click-path test covering filters, creation, views, and sort order
5051cc8 docs(blueprints): update plan v4.1 manifests, teardown debt, and synchronized diffs
186eb61 fix(content): refine sort order bounds, placement labels, seeder exceptions, and view tests
d3cfae2 docs(blueprints): update P1-S2 plan v4.1, implementation report, and code diff for r1 fixes
bc4fd28 fix(content): make table filters dynamic, add sort order bounds, and harden seeder and views
f921c39 docs(blueprints): add P1-S2 plan, change requests, report, and code diff
1cb8050 docs(architecture): document homepage CMS block types and placements
c279321 feat(storefront): wire homepage placements with full-bar and full-card clickable link targets
c812cb9 feat(content): seed all 9 homepage placements idempotently in ContentSeeder
65a98ff feat(content): register homepage blocks, expand placements, and wire filament forms
08c9c28 feat(content): add homepage content block definitions, data classes, and service icon enum
427598e feat(content): add sort order support to content block actions and filament forms
58c8cee feat(content): upgrade announcement bar block to schema v2 with v1 upgrader
```

### Command 3: `git diff --name-status 03becb1...HEAD`
```powershell
PS C:\dev\getnow-cms> git diff --name-status 03becb18ad52f2df2d5c72379683326d59814ab4...HEAD
M	ARCHITECTURE.md
M	app/Domain/Content/Actions/CreateContentBlock.php
M	app/Domain/Content/Actions/UpdateContentBlock.php
M	app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php
M	app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php
A	app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarV1ToV2Upgrader.php
A	app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php
A	app/Domain/Content/Blocks/BrandStrip/BrandStripData.php
A	app/Domain/Content/Blocks/BrandStrip/BrandStripItemData.php
A	app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php
A	app/Domain/Content/Blocks/FeatureBanner/FeatureBannerData.php
A	app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php
A	app/Domain/Content/Blocks/PromoSlide/PromoSlideData.php
A	app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php
A	app/Domain/Content/Blocks/SectionHeader/SectionHeaderData.php
A	app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php
A	app/Domain/Content/Blocks/ServiceItem/ServiceItemData.php
A	app/Domain/Content/Contracts/BlockPayloadUpgrader.php
M	app/Domain/Content/Data/CreateContentBlockData.php
M	app/Domain/Content/Data/UpdateContentBlockData.php
M	app/Domain/Content/Enums/Placement.php
A	app/Domain/Content/Enums/PromoTheme.php
A	app/Domain/Content/Enums/ServiceIcon.php
M	app/Domain/Content/Models/ContentBlock.php
M	app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php
M	app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php
M	app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php
M	app/Filament/Blocks/AnnouncementBarForm.php
M	app/Filament/Blocks/BlockFormRegistry.php
A	app/Filament/Blocks/BrandStripForm.php
A	app/Filament/Blocks/Concerns/HasDemoProductImages.php
A	app/Filament/Blocks/FeatureBannerForm.php
A	app/Filament/Blocks/PromoSlideForm.php
A	app/Filament/Blocks/SectionHeaderForm.php
A	app/Filament/Blocks/ServiceItemForm.php
M	app/Http/Controllers/Storefront/DemoHomeController.php
M	app/Providers/ContentServiceProvider.php
M	database/factories/ContentBlockFactory.php
M	database/seeders/ContentSeeder.php
A	docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md
M	docs/blueprints/P1-S1/DEMO-TEARDOWN.md
A	docs/blueprints/P1-S2/brief.md
A	docs/blueprints/P1-S2/cr-01-legacy-test-manifest-collisions.md
A	docs/blueprints/P1-S2/cr-02-factory-and-update-test-collisions.md
A	docs/blueprints/P1-S2/cr-03-pest-unit-testcase-binding.md
A	docs/blueprints/P1-S2/cr-04-list-table-placement-and-type-filters.md
A	docs/blueprints/P1-S2/plan.md
A	docs/blueprints/P1-S2/report.md
M	docs/contracts/content-api.md
M	resources/css/storefront/carousel.css
M	resources/css/storefront/layout.css
M	resources/css/storefront/pages.css
M	resources/views/components/promo-carousel.blade.php
M	resources/views/layouts/storefront.blade.php
M	resources/views/storefront/home.blade.php
M	tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php
M	tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php
A	tests/Feature/Admin/ContentBlock/HomepageAdminWorkflowTest.php
A	tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php
M	tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php
M	tests/Feature/Content/BlockFormRegistryTest.php
M	tests/Feature/Database/ContentSeederTest.php
M	tests/Feature/Domain/Content/Actions/CreateContentBlockTest.php
M	tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php
M	tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php
M	tests/Feature/Storefront/DemoHomeTest.php
M	tests/Fixtures/contracts/content-layout-v1.json
M	tests/Pest.php
A	tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php
A	tests/Unit/Domain/Content/Blocks/BrandStripDataTest.php
A	tests/Unit/Domain/Content/Blocks/FeatureBannerDataTest.php
A	tests/Unit/Domain/Content/Blocks/PromoSlideDataTest.php
A	tests/Unit/Domain/Content/Blocks/SectionHeaderDataTest.php
A	tests/Unit/Domain/Content/Blocks/ServiceItemDataTest.php
M	tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php
M	tests/Unit/Domain/Content/Enums/PlacementTest.php
A	tests/Unit/Domain/Content/Enums/ServiceIconTest.php
```

### Raw Evidence G3.1: `git ls-files tests/Unit/Domain/Content/Blocks`
```powershell
PS C:\dev\getnow-cms> git ls-files tests/Unit/Domain/Content/Blocks
tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php
tests/Unit/Domain/Content/Blocks/BrandStripDataTest.php
tests/Unit/Domain/Content/Blocks/FeatureBannerDataTest.php
tests/Unit/Domain/Content/Blocks/PromoSlideDataTest.php
tests/Unit/Domain/Content/Blocks/SectionHeaderDataTest.php
tests/Unit/Domain/Content/Blocks/ServiceItemDataTest.php
```

### Raw Evidence G3.2: `git log --oneline -1 -- tests/Feature/Domain/Content/Blocks/AnnouncementBarDataTest.php`
```powershell
PS C:\dev\getnow-cms> git log --oneline -1 -- tests/Feature/Domain/Content/Blocks/AnnouncementBarDataTest.php
cab32b5 fix(content): resolve minor 2a review feedback
```

### Command 4: `git diff 03becb1...HEAD -- composer.json composer.lock package.json`
```powershell
PS C:\dev\getnow-cms> git diff 03becb18ad52f2df2d5c72379683326d59814ab4...HEAD -- composer.json composer.lock package.json
# (Output empty - zero dependencies added or modified)
```

### Command 5: `git diff 03becb1...HEAD --stat -- '*baseline*'`
```powershell
PS C:\dev\getnow-cms> git diff 03becb18ad52f2df2d5c72379683326d59814ab4...HEAD --stat -- '*baseline*'
# (Output empty - zero PHPStan baseline modifications)
```

### Command 6: Direct Mutation Inspection in Filament
```powershell
PS C:\dev\getnow-cms> Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '::create(','->update(','->delete(','->save(','->forceDelete('
# (Output empty - zero direct model mutations in Filament layer)
```

### Command 7: Prohibited Imports in Domain Layer
```powershell
PS C:\dev\getnow-cms> Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Filament','App\Http'
# (Output empty - zero prohibited imports in Domain layer)
```

### Command 8: `declare(strict_types=1);` Compliance
```powershell
PS C:\dev\getnow-cms> Get-ChildItem app,tests,database -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName
C:\dev\getnow-cms\tests\TestCase.php
C:\dev\getnow-cms\tests\Feature\DashboardTest.php
C:\dev\getnow-cms\tests\Feature\Auth\AuthenticationTest.php
C:\dev\getnow-cms\tests\Feature\Auth\EmailVerificationTest.php
C:\dev\getnow-cms\tests\Feature\Auth\PasswordConfirmationTest.php
C:\dev\getnow-cms\tests\Feature\Auth\PasswordResetTest.php
C:\dev\getnow-cms\tests\Feature\Auth\RegistrationTest.php
C:\dev\getnow-cms\tests\Feature\Auth\TwoFactorChallengeTest.php
C:\dev\getnow-cms\tests\Feature\Settings\ProfileUpdateTest.php
C:\dev\getnow-cms\tests\Feature\Settings\SecurityTest.php
C:\dev\getnow-cms\database\factories\UserFactory.php
C:\dev\getnow-cms\database\migrations\0001_01_01_000000_create_users_table.php
C:\dev\getnow-cms\database\migrations\0001_01_01_000001_create_cache_table.php
C:\dev\getnow-cms\database\migrations\0001_01_01_000002_create_jobs_table.php
C:\dev\getnow-cms\database\migrations\2022_12_14_083707_create_settings_table.php
C:\dev\getnow-cms\database\migrations\2024_01_01_000000_create_passkeys_table.php
C:\dev\getnow-cms\database\migrations\2025_08_14_170933_add_two_factor_columns_to_users_table.php
C:\dev\getnow-cms\database\migrations\2026_10_07_064006_create_activity_log_table.php
C:\dev\getnow-cms\database\migrations\2026_10_07_070410_add_is_platform_admin_to_users_table.php
```
*(Analysis: Only pre-existing Laravel starter-kit and migration boilerplate files lack the declaration. Every single file created or modified in P1-S2 strictly declares strict_types=1)*

### Command 9: Skipped or Incomplete Tests
```powershell
PS C:\dev\getnow-cms> Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'
tests\TestCase.php:13:            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
tests\Arch\FilamentWritesTest.php:8:        $this->markTestSkipped('Filament directory does not exist yet.');
```
*(Analysis: Only pre-existing starter-kit helper and initial architecture placeholder contain skip markers. Zero skipped or incomplete tests introduced in P1-S2)*

### Command 10: Docker / Sail / Container Search
```powershell
PS C:\dev\getnow-cms> git ls-files | Select-String -SimpleMatch 'docker','compose','sail'
# (Zero container files tracked)
```

### Command 11: `.env` Tracking Check
```powershell
PS C:\dev\getnow-cms> git ls-files .env
# (Empty - .env is strictly untracked)
```

### Command 12: `composer check`
```powershell
PS C:\dev\getnow-cms> composer check
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":429,"passed":429,"assertions":2602,"duration_ms":43835}
```

---

## 7. Layer B Mutation Spot-Checks Evidence (MUT-1 to MUT-8)

### MUT-1: Announcement Bar V1 to V2 Null Defaults
- **File:** `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarV1ToV2Upgrader.php`
- **Mutation:** `$payload['secondary_text'] = $payload['secondary_text'] ?? 'DEFAULT';`
- **Command:** `php artisan test tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php --filter="upgrades stored v1 announcement bar payloads"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":297,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Blocks\\AnnouncementBarV1ToV2UpgraderTest::__pest_evaluable_it_upgrades_stored_v1_announcement_bar_payloads_to_v2_with_null_defaults","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\AnnouncementBarV1ToV2UpgraderTest.php","line":19,"message":"Failed asserting that 'DEFAULT' is null.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\AnnouncementBarV1ToV2UpgraderTest.php:19"]}]}
```

### MUT-2: Announcement Bar Full-Bar Clickable Link Target
- **File:** `resources/views/layouts/storefront.blade.php`
- **Mutation:** Wrapped only first `<span>` with `<a>` tag instead of outer `.announcement` element.
- **Command:** `php artisan test tests/Feature/Storefront/DemoHomeTest.php --filter="renders full-bar clickable link target"`
- **Raw Failing Output:**
```text
Failed asserting that false is true: contains "<a href=\"/shop?deals=1\" class=\"announcement announcement--info announcement--link\">"
```

### MUT-3: ContentSeeder Multi-Block Placement Idempotency Guard
- **File:** `database/seeders/ContentSeeder.php`
- **Mutation:** Bypassed `hasExisting` check in `seedMultiBlock`.
- **Command:** `php artisan test tests/Feature/Database/ContentSeederTest.php --filter="is strictly idempotent"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":1200,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Database\\ContentSeederTest::__pest_evaluable_it_is_strictly_idempotent_on_repeat_execution_preserving_row_counts_across_multi_block_placements","file":"\\dev\\getnow-cms\\tests\\Feature\\Database\\ContentSeederTest.php","line":134,"message":"Failed asserting that 21 is identical to 14.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Database\\ContentSeederTest.php:134"]}]}
```

### MUT-4: Service Item Icon Whitelist
- **File:** `resources/views/components/icon.blade.php`
- **Mutation:** Renamed `'truck' =>` to `'truck_mutated' =>`.
- **Command:** `php artisan test tests/Unit/Domain/Content/Enums/ServiceIconTest.php --filter="verifies every ServiceIcon case exists"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":282,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Enums\\ServiceIconTest::__pest_evaluable_it_verifies_every_ServiceIcon_case_exists_in_components_icon_blade_php","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\ServiceIconTest.php","line":13,"message":"Expected ServiceIcon case 'truck' to exist in resources/views/components/icon.blade.php\nFailed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\ServiceIconTest.php:13"]}]}
```

### MUT-5: Placement MaxCount Constraint
- **File:** `app/Domain/Content/Enums/Placement.php`
- **Mutation:** Changed `HOME_HERO_CAROUSEL` max count from `5` to `1`.
- **Command:** `php artisan test tests/Unit/Domain/Content/Enums/PlacementTest.php --filter="verifies allowed block types and max counts"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":5,"duration_ms":275,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Enums\\PlacementTest::__pest_evaluable_it_verifies_allowed_block_types_and_max_counts_for_all_9_placements","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\PlacementTest.php","line":36,"message":"Failed asserting that 1 is identical to 5.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\PlacementTest.php:36"]}]}
```

### MUT-6 (m8 - Mutation A): SafeLink Removal on PromoSlideData `cta_url`
- **File:** `app/Domain/Content/Blocks/PromoSlide/PromoSlideData.php`
- **Mutation:** Removed `#[Rule(new SafeLink)]` attribute from `public string $cta_url`.
- **Command:** `php artisan test tests/Unit/Domain/Content/Blocks/PromoSlideDataTest.php`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":11,"duration_ms":312,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Blocks\\PromoSlideDataTest::__pest_evaluable_it_validates_promo_slide_payload_attributes_and_link_formats","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\PromoSlideDataTest.php","line":38,"message":"Exception \"Illuminate\\Validation\\ValidationException\" not thrown.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\PromoSlideDataTest.php:38"]}]}
```

### MUT-7 (m8 - Mutation B): `array_values` Removal on BrandStripData Repeater Keys
- **File:** `app/Domain/Content/Blocks/BrandStrip/BrandStripData.php`
- **Mutation:** Changed `$brands = array_values($payload['brands']);` to `$brands = $payload['brands'];`.
- **Command:** `php artisan test tests/Unit/Domain/Content/Blocks/BrandStripDataTest.php`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":311,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Blocks\\BrandStripDataTest::__pest_evaluable_it_validates_brand_strip_item_collection_constraints_and_normalizes_array_keys","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\BrandStripDataTest.php","line":24,"message":"Failed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\BrandStripDataTest.php:24"]}]}
```

### MUT-8 (m8 - Mutation C): Block Removal from `BlockFormRegistry`
- **File:** `app/Filament/Blocks/BlockFormRegistry.php`
- **Mutation:** Removed `'promo_slide' => PromoSlideForm::class,` from `FORMS` array.
- **Command:** `php artisan test tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":10,"passed":9,"assertions":141,"duration_ms":2359,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\HomepageBlocksCreateAndServeTest::__pest_evaluable_it_creates_and_serves_each_allowed_block_type_for_every_homepage_placement_in_Filament_while_excluding_non_live_blocks with data set \"dataset \"home_hero_carousel\"\"","file":"\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\HomepageBlocksCreateAndServeTest.php","line":166,"message":"Failed asserting that two arrays are identical.\n--- Expected\n+++ Actual\n@@ @@\n-Array &0 [\n- 0 => 'promo_slide',\n-]\n+Array &0 []","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\HomepageBlocksCreateAndServeTest.php:166"]}]}
```
*(Also verified in `BlockFormRegistryTest.php`: line 20 failed asserting bidirectional equality with domain block types)*

### MUT-F1 (Review Finding F8): Placement Filter Drift Guard Failure
- **File:** `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`
- **Mutation:** Re-hardcoded placement filter options to `['announcement_bar' => 'Announcement Bar']`.
- **Command:** `php artisan test tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php --filter="drift-guard: placement filter offers every Placement case"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":2,"duration_ms":998,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ListContentBlocksTableTest::__pest_evaluable_it_drift_guard__placement_filter_offers_every_Placement_case_and_filters_correctly","file":"\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ListContentBlocksTableTest.php","line":423,"message":"Failed asserting that an array has the key 'home_hero_carousel'","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ListContentBlocksTableTest.php:423"]}]}
```
*(Analysis: Confirms that if any placement option is missing or hardcoded, the drift-guard test immediately fails)*

### MUT-F1 Clean Restoration Proof
```powershell
PS C:\dev\getnow-cms> git diff app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php
# (Output empty - clean working tree restored via git checkout)
PS C:\dev\getnow-cms> git status --short
# (Output empty - zero modified files)
```

---

## 8. Layer C Verification Evidence (Review Findings F6 & G5: Dedicated Workflow Verification)

The Layer C admin workflow was verified through end-to-end execution of `tests/Feature/Admin/ContentBlock/HomepageAdminWorkflowTest.php` covering the 5 substantive workflow actions (with the dummy 404 `/history` assertion dropped per Finding G5):

### Workflow Step Execution Results

| Step | Action | Assertion / Verification Target | Result | Evidence / Details |
|---|---|---|---|---|
| **Step 1** | Filter by each of the 9 placements | `tableFilters.placement.value` offers all 9 cases and returns only matching records | **PASS** | Evaluated all 9 `Placement::cases()`. Filter option key parity verified. Matching records visible, non-matching records hidden. |
| **Step 2** | Create 1 block of each new type in Filament | `CreateContentBlock` page fills form and calls `create` | **PASS** | Created `promo_slide`, `feature_banner`, `service_item`, `section_header`, `brand_strip`. All 5 saved as `DRAFT` status with exact hydrated payloads. |
| **Step 3** | Open View page for `promo_slide` | `ViewContentBlock` renders read-only form and hydrates payload | **PASS** | HTTP 200 on `/admin/content-blocks/{public_id}`. Form `isDisabled() === true`. Payload fields (`eyebrow`, `title_line_1`, `label`, `cta_url`) hydrated properly. |
| **Step 4** | Open View page for `brand_strip` | `ViewContentBlock` hydrates nested `brands` repeater array | **PASS** | HTTP 200 on `/admin/content-blocks/{public_id}`. Form `isDisabled() === true`. Eyebrow and title match. Nested `brands` array hydrated into Repeater components with `label` and `url` for Apple, Samsung, Anker, JBL, Marshall, Sony. |
| **Step 5** | Edit slide `sort_order` and confirm homepage order changes | Move last slide to `sort_order = 0` and first to `sort_order = 10` | **PASS** | Initial HTML on `GET /` showed first slide before last slide. After sort_order edit, `GET /` rendered the promoted slide before the demoted slide. |

### Raw Test Execution Output (`HomepageAdminWorkflowTest.php`)
```powershell
PS C:\dev\getnow-cms> php artisan test tests/Feature/Admin/ContentBlock/HomepageAdminWorkflowTest.php
{"tool":"pest","result":"passed","tests":5,"passed":5,"assertions":235,"duration_ms":3061}
```

### Note on Browser Interaction & Environment Constraint (Finding G5)
An automated browser subagent was dispatched against a local server instance (`http://127.0.0.1:8001`) to capture visual screenshots of the placement-filtered table and the `brand_strip` View page. However, browser automation could not initialize because the Playwright browser driver download failed with `404 Not Found` from external CDNs (`playwright.azureedge.net`, `playwright-akamai.azureedge.net`) due to sandbox network restrictions. Full DOM state, read-only form disabling, filter evaluation, and nested repeater array hydration are strictly proven and guaranteed by `HomepageAdminWorkflowTest.php` and `ContentBlockViewTest.php`.

---

## 9. Reviewer Required Inspections & Greps

### 9.1 Verbatim Class Paste: `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Tables;

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Actions\ContentBlockTransitionActions;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;

final class ContentBlockTable
{
    public static function servingStatusColor(?ServingStatus $state): string
    {
        if ($state === null) {
            return 'gray';
        }

        return match ($state) {
            ServingStatus::Live => 'success',
            ServingStatus::NotLive => 'gray',
            ServingStatus::PayloadError => 'danger',
        };
    }

    public static function publishStatusColor(?PublishStatus $state): string
    {
        if ($state === null) {
            return 'gray';
        }

        return match ($state) {
            PublishStatus::PUBLISHED => 'success',
            PublishStatus::DRAFT => 'gray',
            PublishStatus::ARCHIVED => 'warning',
        };
    }

    public static function table(Table $table): Table
    {
        $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('placement')->orderBy('sort_order'))
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('placement')
                    ->label('Placement')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(function ($state) {
                        $registry = app(BlockRegistry::class);
                        $definition = $registry->get($state);

                        if ($definition !== null && method_exists($definition, 'label')) {
                            return $definition->label();
                        }

                        return $state;
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?PublishStatus $state) => self::publishStatusColor($state))
                    ->sortable(),

                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label("Starts at ({$timezone})")
                    ->dateTime()
                    ->timezone($timezone)
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label("Ends at ({$timezone})")
                    ->dateTime()
                    ->timezone($timezone)
                    ->sortable(),

                TextColumn::make('serving_status')
                    ->label('Serving Status')
                    ->getStateUsing(fn (ContentBlock $record) => app(GetBlockServingStatus::class)($record)->status)
                    ->badge()
                    ->formatStateUsing(fn (?ServingStatus $state) => $state?->label())
                    ->color(fn (?ServingStatus $state) => self::servingStatusColor($state))
                    ->sortable(false)
                    ->searchable(false),
            ])
            ->filters([
                Filter::make('hide_archived')
                    ->label('Hide Archived')
                    ->query(function (Builder $query, Component $livewire) {
                        $status = data_get($livewire, 'tableFilters.status.value');
                        if ($status !== 'archived') {
                            $query->where('status', '!=', 'archived');
                        }
                    })
                    ->default(true),

                SelectFilter::make('placement')
                    ->options(collect(Placement::cases())->mapWithKeys(fn (Placement $p) => [$p->value => $p->label()])->all()),

                SelectFilter::make('type')
                    ->options(collect(app(BlockRegistry::class)->all())->mapWithKeys(fn ($def, string $key) => [$key => Str::headline($key)])->all()),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),

                TernaryFilter::make('is_enabled')
                    ->label('Enabled'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    ContentBlockTransitionActions::publish(),
                    ContentBlockTransitionActions::enable(),
                    ContentBlockTransitionActions::disable(),
                    ContentBlockTransitionActions::archive(),
                ]),
            ])
            ->recordUrl(fn (ContentBlock $record): string => ContentBlockResource::getUrl('view', ['record' => $record]))
            ->bulkActions([]);
    }
}
```

### 9.2 Verbatim Grep: F1 (`'announcement_bar'`, `'ANNOUNCEMENT_BAR'`, `'Placement::'`)
```powershell
PS C:\dev\getnow-cms> Get-ChildItem app,resources -Recurse -Include *.php | Select-String -SimpleMatch 'announcement_bar','ANNOUNCEMENT_BAR','Placement::'

app\Domain\Content\Actions\GetLayoutContent.php:52:        foreach (Placement::cases() as $placement) {
app\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarBlock.php:13:        return 'announcement_bar';
app\Domain\Content\Enums\Placement.php:11:    case ANNOUNCEMENT_BAR = 'announcement_bar';
app\Domain\Content\Enums\Placement.php:27:            self::ANNOUNCEMENT_BAR => ['announcement_bar'],
app\Domain\Content\Enums\Placement.php:44:            self::ANNOUNCEMENT_BAR,
app\Domain\Content\Models\ContentBlock.php:49:        'placement' => TolerantEnum::class.':'.Placement::class,
app\Filament\Admin\Resources\ContentBlockResource\Pages\Concerns\InteractsWithContentBlockActions.php:25:        $placement = is_string($placementVal) ? Placement::tryFrom($placementVal) : null;
app\Filament\Admin\Resources\ContentBlockResource\Schemas\ContentBlockForm.php:32:                ->options(collect(Placement::cases())->mapWithKeys(fn (Placement $p) => [$p->value => $p->label()])->all())
app\Filament\Admin\Resources\ContentBlockResource\Schemas\ContentBlockForm.php:48:                    $placement = Placement::tryFrom((string) $placementVal);
app\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable.php:129:                    ->options(collect(Placement::cases())->mapWithKeys(fn (Placement $p) => [$p->value => $p->label()])->all()),
app\Filament\Blocks\BlockFormRegistry.php:16:        'announcement_bar' => AnnouncementBarForm::class,
resources\views\layouts\storefront.blade.php:17:@php($announcement = $layout->placements['announcement_bar'][0] ?? null)
```

### 9.3 Verbatim Grep: m2 (`maxCount`)
```powershell
PS C:\dev\getnow-cms> Get-ChildItem app -Recurse -Include *.php | Select-String -SimpleMatch 'maxCount'

app\Domain\Content\Actions\GetLayoutContent.php:55:            $maxCount = $placement->maxCount();
app\Domain\Content\Actions\GetLayoutContent.php:83:                if (count($selectedBlocks) >= $maxCount) {
app\Domain\Content\Enums\Placement.php:37:    public function maxCount(): int
```
*(Analysis: `maxCount` is strictly enforced at layout output serving layer in `GetLayoutContent.php`, capping the returned blocks to the lowest `sort_order` first, matching Decision D8 and D-i)*

### 9.4 Verbatim Grep: m3 (`free_delivery_minor`)
```powershell
PS C:\dev\getnow-cms> Get-ChildItem app,resources,config,tests -Recurse -Include *.php | Select-String -SimpleMatch 'free_delivery_minor'

config\storefront.php:11:    'free_delivery_minor' => (int) env('STOREFRONT_FREE_DELIVERY_MINOR', 2500000),
```
*(Analysis: Zero references in `app`, `resources`, or `tests`. The storefront announcement bar reads dynamic CMS copy directly from block payload `secondary_text` matching Decision D-j)*

### 9.5 Verbatim Grep: m6 (`promotions`)
```powershell
PS C:\dev\getnow-cms> Get-ChildItem app,resources,tests -Recurse -Include *.php,*.blade.php | Select-String "promotions"
# (Output empty - zero references across app, resources, or tests)
```
*(Analysis: `config/promotions.php` is legacy static config retained as reference; carousel autoplay and interval are declared on `<x-promo-carousel>` component)*
