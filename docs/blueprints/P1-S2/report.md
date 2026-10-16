# P1-S2 IMPLEMENTATION REPORT

**Step ID:** P1-S2  
**Status:** Completed (Ready for Final Review)  
**Base Commit:** `03becb18ad52f2df2d5c72379683326d59814ab4` (`03becb1 docs(blueprints): add P1-S1 plan, migration notes, cr-01, report, and code diff`)  
**Tracking Upstream:** `origin/main` (`03becb18ad52f2df2d5c72379683326d59814ab4`)  
**Branch:** `feat/homepage-cms-blocks`  
**Commits on Branch (`base..HEAD`):**
1. `58c8cee` feat(content): upgrade announcement bar block to schema v2 with v1 upgrader
2. `427598e` feat(content): add sort order support to content block actions and filament forms
3. `08c9c28` feat(content): add homepage content block definitions, data classes, and service icon enum
4. `65a98ff` feat(content): register homepage blocks, expand placements, and wire filament forms
5. `c812cb9` feat(content): seed all 9 homepage placements idempotently in ContentSeeder
6. `c279321` feat(storefront): wire homepage placements with full-bar and full-card clickable link targets
7. `1cb8050` docs(architecture): document homepage CMS block types and placements

**Approved Change Requests:**
- `CR-P1-S2-01`: Legacy Test Manifest Collisions (`BlockFormRegistryTest`, `UpdateContentBlockDataTest`, `ContentBlockEditTest`) — Approved
- `CR-P1-S2-02`: Factory & Update Test Collisions (`ContentBlockFactory`, `UpdateContentBlockTest`) — Approved
- `CR-P1-S2-03`: Unit Test Suite Laravel Container Binding (`tests/Pest.php`) — Approved

---

## 1. Commit Log (`git log --oneline 03becb1...HEAD`)

```text
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
M	app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php
M	app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php
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
M	docs/blueprints/P1-S1/DEMO-TEARDOWN.md
M	docs/contracts/content-api.md
M	resources/css/storefront/carousel.css
M	resources/css/storefront/layout.css
M	resources/css/storefront/pages.css
M	resources/views/components/promo-carousel.blade.php
M	resources/views/layouts/storefront.blade.php
M	resources/views/storefront/home.blade.php
M	tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php
A	tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php
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

### Reconciliation Table

| Planned and Done | Planned and Not Done | Unplanned (with Reason) |
|---|---|---|
| `ARCHITECTURE.md` | None | `tests/Feature/Content/BlockFormRegistryTest.php` (CR-P1-S2-01: updated expected types count) |
| `app/Domain/Content/Actions/CreateContentBlock.php` | None | `tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php` (CR-P1-S2-01: added `sort_order` parameter assertion) |
| `app/Domain/Content/Actions/UpdateContentBlock.php` | None | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` (CR-P1-S2-01: updated schema v2 payload assertions) |
| `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php` | None | `database/factories/ContentBlockFactory.php` (CR-P1-S2-02: updated announcement bar default payload to v2) |
| `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php` | None | `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php` (CR-P1-S2-02: updated v2 announcement bar payload) |
| `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarV1ToV2Upgrader.php` | None | `tests/Pest.php` (CR-P1-S2-03: bound `TestCase` to `Unit/` for Spatie Data validator resolution) |
| `app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php` | None | |
| `app/Domain/Content/Blocks/BrandStrip/BrandStripData.php` | None | |
| `app/Domain/Content/Blocks/BrandStrip/BrandStripItemData.php` | None | |
| `app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php` | None | |
| `app/Domain/Content/Blocks/FeatureBanner/FeatureBannerData.php` | None | |
| `app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php` | None | |
| `app/Domain/Content/Blocks/PromoSlide/PromoSlideData.php` | None | |
| `app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php` | None | |
| `app/Domain/Content/Blocks/SectionHeader/SectionHeaderData.php` | None | |
| `app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php` | None | |
| `app/Domain/Content/Blocks/ServiceItem/ServiceItemData.php` | None | |
| `app/Domain/Content/Contracts/BlockPayloadUpgrader.php` | None | |
| `app/Domain/Content/Data/CreateContentBlockData.php` | None | |
| `app/Domain/Content/Data/UpdateContentBlockData.php` | None | |
| `app/Domain/Content/Enums/Placement.php` | None | |
| `app/Domain/Content/Enums/PromoTheme.php` | None | |
| `app/Domain/Content/Enums/ServiceIcon.php` | None | |
| `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php` | None | |
| `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` | None | |
| `app/Filament/Blocks/AnnouncementBarForm.php` | None | |
| `app/Filament/Blocks/BlockFormRegistry.php` | None | |
| `app/Filament/Blocks/BrandStripForm.php` | None | |
| `app/Filament/Blocks/Concerns/HasDemoProductImages.php` | None | |
| `app/Filament/Blocks/FeatureBannerForm.php` | None | |
| `app/Filament/Blocks/PromoSlideForm.php` | None | |
| `app/Filament/Blocks/SectionHeaderForm.php` | None | |
| `app/Filament/Blocks/ServiceItemForm.php` | None | |
| `app/Http/Controllers/Storefront/DemoHomeController.php` | None | |
| `app/Providers/ContentServiceProvider.php` | None | |
| `database/seeders/ContentSeeder.php` | None | |
| `docs/blueprints/P1-S1/DEMO-TEARDOWN.md` | None | |
| `docs/contracts/content-api.md` | None | |
| `resources/css/storefront/carousel.css` | None | |
| `resources/css/storefront/layout.css` | None | |
| `resources/css/storefront/pages.css` | None | |
| `resources/views/components/promo-carousel.blade.php` | None | |
| `resources/views/layouts/storefront.blade.php` | None | |
| `resources/views/storefront/home.blade.php` | None | |
| `tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php` | None | |
| `tests/Feature/Database/ContentSeederTest.php` | None | |
| `tests/Feature/Domain/Content/Actions/CreateContentBlockTest.php` | None | |
| `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | None | |
| `tests/Feature/Storefront/DemoHomeTest.php` | None | |
| `tests/Fixtures/contracts/content-layout-v1.json` | None | |
| `tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php` | None | |
| `tests/Unit/Domain/Content/Blocks/BrandStripDataTest.php` | None | |
| `tests/Unit/Domain/Content/Blocks/FeatureBannerDataTest.php` | None | |
| `tests/Unit/Domain/Content/Blocks/PromoSlideDataTest.php` | None | |
| `tests/Unit/Domain/Content/Blocks/SectionHeaderDataTest.php` | None | |
| `tests/Unit/Domain/Content/Blocks/ServiceItemDataTest.php` | None | |
| `tests/Unit/Domain/Content/Enums/PlacementTest.php` | None | |
| `tests/Unit/Domain/Content/Enums/ServiceIconTest.php` | None | |

---

## 3. Acceptance Criteria Results

| Acceptance Criteria | Verification File & Line | Test Name | Result |
|---|---|---|---|
| **AC-1**: Announcement Bar v2 & Upgrader | `tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php:11` | `it('upgrades stored v1 announcement bar payloads to v2 with null defaults')` | **PASS** |
| **AC-1**: Announcement Bar v2 & Upgrader | `tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php:24` | `it('preserves existing secondary and locale text if already present during upgrade')` | **PASS** |
| **AC-1**: Announcement Bar v2 & Upgrader | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php:79` | `it('upgrades announcement_bar v1 stored payload to v2 before serving')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Unit/Domain/Content/Blocks/PromoSlideDataTest.php:12` | `it('validates promo slide payload attributes and link formats')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Unit/Domain/Content/Blocks/FeatureBannerDataTest.php:12` | `it('validates feature banner payload attributes')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Unit/Domain/Content/Blocks/ServiceItemDataTest.php:12` | `it('validates service item payload attributes')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Unit/Domain/Content/Blocks/SectionHeaderDataTest.php:12` | `it('validates section header payload attributes')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Unit/Domain/Content/Blocks/BrandStripDataTest.php:12` | `it('validates brand strip payload attributes and repeater limits')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Unit/Domain/Content/Enums/PlacementTest.php:12` | `it('verifies allowed block types and max counts for all 9 placements')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Unit/Domain/Content/Enums/ServiceIconTest.php:10` | `it('verifies every ServiceIcon case exists in components/icon.blade.php')` | **PASS** |
| **AC-2**: 5 Homepage Block Types & Placements | `tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php:14` | `it('creates, edits, and serves all 6 homepage block types via Filament forms and layout action')` | **PASS** |
| **AC-3**: Deterministic Sort Order Plumbing | `tests/Feature/Domain/Content/Actions/CreateContentBlockTest.php:20` | `it('creates a content block with custom sort order')` | **PASS** |
| **AC-3**: Deterministic Sort Order Plumbing | `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php:25` | `it('updates a content block with custom sort order')` | **PASS** |
| **AC-3**: Deterministic Sort Order Plumbing | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php:57` | `it('prefills and updates sort_order via Filament edit page')` | **PASS** |
| **AC-4**: Idempotent ContentSeeder (9 Placements) | `tests/Feature/Database/ContentSeederTest.php:21` | `it('seeds storefront settings, announcement bar, and navigation menus matching storefront values')` | **PASS** |
| **AC-4**: Idempotent ContentSeeder (9 Placements) | `tests/Feature/Database/ContentSeederTest.php:58` | `it('seeds all 9 homepage placements matching exact storefront demo content')` | **PASS** |
| **AC-4**: Idempotent ContentSeeder (9 Placements) | `tests/Feature/Database/ContentSeederTest.php:109` | `it('is strictly idempotent on repeat execution preserving row counts across multi-block placements')` | **PASS** |
| **AC-4**: Idempotent ContentSeeder (9 Placements) | `tests/Feature/Database/ContentSeederTest.php:132` | `it('resets single-block placements to published and enabled seeded state while skipping populated multi-block placements on re-run')` | **PASS** |
| **AC-4**: Idempotent ContentSeeder (9 Placements) | `tests/Feature/Database/ContentSeederTest.php:171` | `it('restores a disabled home_brand_strip to its intended seeded state upon seeder rerun')` | **PASS** |
| **AC-4**: Idempotent ContentSeeder (9 Placements) | `tests/Feature/Database/ContentSeederTest.php:188` | `it('does not re-seed multi-block placement when an individual slide is archived')` | **PASS** |
| **AC-4**: Idempotent ContentSeeder (9 Placements) | `tests/Feature/Database/ContentSeederTest.php:202` | `it('upgrades an existing v1 announcement bar block to v2 in-place during seed run')` | **PASS** |
| **AC-5**: Full-Bar & Full-Card Link UX | `tests/Feature/Storefront/DemoHomeTest.php:44` | `it('renders full-bar clickable link target when announcement link_url is present')` | **PASS** |
| **AC-5**: Full-Bar & Full-Card Link UX | `tests/Feature/Storefront/DemoHomeTest.php:59` | `it('renders secondary and locale text dynamically on announcement bar')` | **PASS** |
| **AC-5**: Full-Bar & Full-Card Link UX | `tests/Feature/Storefront/DemoHomeTest.php:90` | `it('renders full-card clickable links on banners and linked service items')` | **PASS** |
| **AC-6**: Dynamic Homepage Wiring & Empty State | `tests/Feature/Storefront/DemoHomeTest.php:68` | `it('dynamically renders all 8 homepage CMS placements from GetLayoutContent')` | **PASS** |
| **AC-6**: Dynamic Homepage Wiring & Empty State | `tests/Feature/Storefront/DemoHomeTest.php:99` | `it('omits placements cleanly without crashing when blocks are disabled or empty')` | **PASS** |
| **AC-6**: Dynamic Homepage Wiring & Empty State | `tests/Feature/Storefront/DemoHomeTest.php:130` | `it('reflects admin mutations and omits disabled announcement bar without crashing')` | **PASS** |
| **AC-6**: Dynamic Homepage Wiring & Empty State | `tests/Feature/Storefront/DemoHomeTest.php:154` | `it('renders gracefully when placements and menus are empty')` | **PASS** |
| **AC-7**: Contract Documentation & Canonical Fixture | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php:20` | `it('returns exact JSON structure matching canonical contract fixture')` | **PASS** |
| **AC-8**: Teardown Docs & Architecture Closeout | `tests/Feature/Storefront/DemoHomeTest.php:164` | `it('has valid teardown guide documenting all temporary demo files')` | **PASS** |

---

## 4. Deviations from Frozen Plan

- **CR-P1-S2-01**: Accommodated pre-existing tests expecting `BlockFormRegistry` type count 1, `UpdateContentBlockData` constructor without `sort_order`, and `ContentBlockEditTest` asserting v1 announcement bar fields. Approved by reviewer.
- **CR-P1-S2-02**: Updated `ContentBlockFactory` announcement bar default payload to schema v2, updating `UpdateContentBlockTest` accordingly. Approved by reviewer.
- **CR-P1-S2-03**: Bound `TestCase` in `tests/Pest.php` for `Unit/` folder to allow Spatie Data validator container resolution in unit test suites. Approved by reviewer.
- No other deviations occurred.

---

## 5. Raw Unedited Verification Evidence

### 5.1 `composer check` (Full Output)
```text
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":412,"passed":412,"assertions":2238,"duration_ms":90130}
```

### 5.2 `git status --short` (Prior to committing blueprints)
```text
 M code_diff.txt
?? "docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md"
?? docs/blueprints/P1-S2/
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

## 6. Rule Self-Audit (Workflow §10A Mechanical Verification)

### Command 1: `git status --short`
All working tree changes cleanly committed.

### Command 2: `git log --oneline 03becb1...HEAD`
7 atomic Conventional Commits matching Phase P1-S2a, P1-S2b, and P1-S2c sequence.

### Command 3: `git diff --name-status 03becb1...HEAD`
Zero migration modifications. All changes strictly append-only or additive.

### Command 4: `git diff 03becb1...HEAD -- composer.json composer.lock package.json`
Output: (Empty — no new dependencies introduced)

### Command 5: `git diff 03becb1...HEAD --stat -- '*baseline*'`
Output: (Empty — no PHPStan baselines added or weakened)

### Command 6: `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '::create(','->update(','->delete(','->save(','->forceDelete('`
Output: (Empty — Filament classes never directly mutate Eloquent models)

### Command 7: `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Filament','App\Http'`
Output: (Empty — zero prohibited imports in Domain layer)

### Command 8: `Get-ChildItem app,tests,database -Recurse -Filter *.php | Where-Object { -not (Select-String -Path $_.FullName -SimpleMatch 'declare(strict_types=1);' -Quiet) } | Select-Object -ExpandProperty FullName`
Output: (All newly authored and modified files strictly declare strict types)

### Command 9: `Get-ChildItem tests -Recurse -Filter *.php | Select-String -SimpleMatch '->skip(','->todo(','markTestIncomplete','markTestSkipped'`
Output: (Zero skipped or incomplete tests introduced)

### Command 10: `git ls-files | Select-String -SimpleMatch 'docker','compose','sail'`
Output: `composer.json`, `composer.lock` (No Docker, compose, or Sail files exist)

### Command 11: `git ls-files .env`
Output: (Empty — .env is not tracked)

### Command 12: `composer check`
Passed with 0 errors across 412 tests and 2238 assertions.

---

## 7. Layer B Mutation Spot-Checks Evidence (MUT-1 to MUT-5)

### MUT-1: Announcement Bar V1 to V2 Null Defaults
- **File:** `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarV1ToV2Upgrader.php`
- **Mutation:** `$payload['secondary_text'] = $payload['secondary_text'] ?? 'DEFAULT';`
- **Command:** `php artisan test tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php --filter="upgrades stored v1 announcement bar payloads"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":297,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Blocks\\AnnouncementBarV1ToV2UpgraderTest::__pest_evaluable_it_upgrades_stored_v1_announcement_bar_payloads_to_v2_with_null_defaults","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\AnnouncementBarV1ToV2UpgraderTest.php","line":19,"message":"Failed asserting that 'DEFAULT' is null.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Blocks\\AnnouncementBarV1ToV2UpgraderTest.php:19"]}]}
```
- **Reversion:** Cleanly reverted via `git checkout`. Diff verified empty.

### MUT-2: Announcement Bar Full-Bar Clickable Link Target
- **File:** `resources/views/layouts/storefront.blade.php`
- **Mutation:** Wrapped only first `<span>` with `<a>` tag instead of outer `.announcement` element.
- **Command:** `php artisan test tests/Feature/Storefront/DemoHomeTest.php --filter="renders full-bar clickable link target"`
- **Raw Failing Output:**
```text
Failed asserting that false is true: contains "<a href=\"/shop?deals=1\" class=\"announcement announcement--info announcement--link\">"
```
- **Reversion:** Cleanly reverted via `git checkout`. Diff verified empty.

### MUT-3: ContentSeeder Multi-Block Placement Idempotency Guard
- **File:** `database/seeders/ContentSeeder.php`
- **Mutation:** Bypassed `hasExisting` check in `seedMultiBlock`.
- **Command:** `php artisan test tests/Feature/Database/ContentSeederTest.php --filter="is strictly idempotent"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":1200,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Database\\ContentSeederTest::__pest_evaluable_it_is_strictly_idempotent_on_repeat_execution_preserving_row_counts_across_multi_block_placements","file":"\\dev\\getnow-cms\\tests\\Feature\\Database\\ContentSeederTest.php","line":134,"message":"Failed asserting that 21 is identical to 14.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Database\\ContentSeederTest.php:134"]}]}
```
- **Reversion:** Cleanly reverted via `git checkout`. Diff verified empty.

### MUT-4: Service Item Icon Whitelist
- **File:** `resources/views/components/icon.blade.php`
- **Mutation:** Renamed `'truck' =>` to `'truck_mutated' =>`.
- **Command:** `php artisan test tests/Unit/Domain/Content/Enums/ServiceIconTest.php --filter="verifies every ServiceIcon case exists"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":282,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Enums\\ServiceIconTest::__pest_evaluable_it_verifies_every_ServiceIcon_case_exists_in_components_icon_blade_php","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\ServiceIconTest.php","line":13,"message":"Expected ServiceIcon case 'truck' to exist in resources/views/components/icon.blade.php\nFailed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\ServiceIconTest.php:13"]}]}
```
- **Reversion:** Cleanly reverted via `git checkout`. Diff verified empty.

### MUT-5: Placement MaxCount Constraint
- **File:** `app/Domain/Content/Enums/Placement.php`
- **Mutation:** Changed `HOME_HERO_CAROUSEL` max count from `5` to `1`.
- **Command:** `php artisan test tests/Unit/Domain/Content/Enums/PlacementTest.php --filter="verifies allowed block types and max counts"`
- **Raw Failing Output:**
```text
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":5,"duration_ms":275,"failed":1,"failures":[{"test":"P\\Tests\\Unit\\Domain\\Content\\Enums\\PlacementTest::__pest_evaluable_it_verifies_allowed_block_types_and_max_counts_for_all_9_placements","file":"\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\PlacementTest.php","line":36,"message":"Failed asserting that 1 is identical to 5.","trace":["C:\\dev\\getnow-cms\\tests\\Unit\\Domain\\Content\\Enums\\PlacementTest.php:36"]}]}
```
- **Reversion:** Cleanly reverted via `git checkout`. Diff verified empty.

---

## 8. Layer C Manual Verification Evidence

1. `php artisan migrate:fresh --seed --no-interaction`:
   - Prepared database, ran all migrations, executed `ContentSeeder` in 250 ms. Status: **SUCCESS**
2. Repeat `php artisan db:seed --no-interaction`:
   - Executed `ContentSeeder` in 142 ms with zero errors and zero duplicate rows. Status: **SUCCESS**
3. `php artisan tinker --execute="echo 'Total blocks: ' . \App\Domain\Content\Models\ContentBlock::count() . PHP_EOL;"`:
   - Output: `Total blocks: 14`. Status: **SUCCESS**
4. `npm run build`:
   - Built production bundles in 1.78s cleanly. Status: **SUCCESS**
5. HTTP Endpoint Verification:
   - `GET http://127.0.0.1:8000/`: Returned HTTP status `200`
   - `GET http://127.0.0.1:8000/api/v1/content/layout`: Returned HTTP status `200` with complete payload and revision hash.
   - All temporary serve background tasks terminated cleanly. Status: **SUCCESS**

---

## 9. Gaps & Discrepancies

None. All 8 acceptance criteria, 7 commits, 5 mutation spot-checks, and Layer C verifications completed without gaps.
