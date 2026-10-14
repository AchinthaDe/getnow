# P1-API1 IMPLEMENTATION REPORT

**Step ID:** P1-API1  
**Status:** Complete  
**Base Commit:** `714fe30c664db9b5ddf8b993ef4942c15c79d34a`  
**Head Commit:** `bb06081`  
**Branch:** `feat/content-api-v1`  
**Commits:**
1. `096d1cf` feat(content): add public layout data DTOs and contract fixture
2. `fc2e38a` feat(content): add GetLayoutContent action with visibility filtering and D8 tie breaking
3. `9672325` feat(content): add layout API endpoint with ETag and rate limiting
4. `bb06081` docs(architecture): mark Content API v1 complete in Phase 1

---

## 1. Acceptance Criteria Verification (Canonical Plan v3 Section 9)

| AC ID | Part | Requirement Summary | Implementation Files | Verifying Test Name (`it('...')`) | Test File | Result |
|---|---|---|---|---|---|---|
| **AC-1** | B | `GET /api/v1/content/layout` is publicly accessible without authentication and returns HTTP 200 with keys `data` and `meta`. | `GetLayoutContentController.php`, `routes/api.php` | `it('returns HTTP 200 with layout data and meta without authentication')` | `GetLayoutContentEndpointTest.php` | **PASSED** |
| **AC-2** | A | `data.settings` returns `site_name`, `default_meta_description`, `search_placeholder`, `footer_brand_text` matching `StorefrontSettings`. | `GetLayoutContent.php`, `LayoutSettingsData.php` | `it('returns storefront settings matching configured values')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-3** | A | Every case in `Placement::cases()` is present in `data.placements` (`[]` when empty), and every case in `MenuKey::cases()` is present in `data.menus` (`{"title": ..., "items": []}`). | `GetLayoutContent.php`, `LayoutData.php`, `LayoutMenuData.php` | `it('includes every defined placement and menu key even when empty or unprovisioned')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-4** | A | Block visibility (`K7`): draft, archived, disabled, future-scheduled (`starts_at > now`), or expired (`ends_at <= now`) blocks are never returned. | `GetLayoutContent.php`, `ContentBlock.php` | `it('excludes draft, archived, disabled, future-scheduled, and expired blocks from placements')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-5** | A | Placement tie-break and `maxCount` (`D8`): winner selected by `sort_order ASC, starts_at DESC NULLS LAST, id DESC`, capped at `maxCount()`. | `GetLayoutContent.php`, `ContentBlock.php`, `Placement.php` | `it('enforces placement maxCount and selects candidate by D8 tie break order')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-6** | A | Block payload upgrading and corrupt-row resilience: stored blocks at older schema version are upgraded; corrupt/unservable blocks do not 500 and next candidate in D8 order is served. | `GetLayoutContent.php`, `ResolveServablePayload.php` | `it('upgrades older payload schemas to current version')`, `it('falls back to next valid candidate when top candidate is unservable')`, `it('returns empty placement array when all candidate blocks are corrupt')`, `it('skips blocks whose type is not permitted for the placement')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-7** | A | Menu visibility (`K7`): disabled top-level items and all their children are omitted; disabled child items under enabled parent are omitted; enabled siblings remain ordered by `sort_order ASC, id ASC`. | `GetLayoutContent.php`, `Menu.php`, `MenuItem.php` | `it('cascades exclusion to children of disabled parent items while retaining enabled siblings in strict sort order')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-8** | A | Menu item mapping: maps `public_id` to `id`, `opens_in_new_tab` to `new_tab`, `children: []`, and never leaks internal columns. | `GetLayoutContent.php`, `LayoutMenuItemData.php` | `it('maps menu item attributes to public contract shape without internal columns')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-9** | A | No raw database primary keys, user foreign keys, or internal lifecycle fields appear anywhere in JSON response (`DB5`, `SEC3`). | `GetLayoutContent.php`, `LayoutResponseData.php` | `it('contains zero raw database primary keys or internal lifecycle attributes')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-10** | A | `meta.revision` is deterministic: calling `GetLayoutContent` twice at different timestamps produces same hash; mutating content changes hash. | `GetLayoutContent.php`, `LayoutMetaData.php` | `it('produces identical revision hash across timestamps and changes hash when content mutates')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-11** | B | ETag & conditional GET: returns `ETag: "<revision>"`; matching `If-None-Match` (including weak `W/`) returns HTTP 304 with empty body; stale hash returns HTTP 200. | `GetLayoutContentController.php` | `it('handles conditional GET returning 304 on matching ETag and 200 on stale ETag')` | `GetLayoutContentEndpointTest.php` | **PASSED** |
| **AC-12** | B | Rate limiting: requests exceeding configured per-minute limit receive HTTP 429 Too Many Requests. | `AppServiceProvider.php`, `config/content.php`, `routes/api.php` | `it('throttles requests exceeding configured rate limit with HTTP 429')` | `GetLayoutContentEndpointTest.php` | **PASSED** |
| **AC-13** | A | Query count discipline: `GetLayoutContent` executes in bounded, constant number of database queries (3 queries when settings warmed). | `GetLayoutContent.php` | `it('executes layout retrieval within constant query bounds')` | `GetLayoutContentTest.php` | **PASSED** |
| **AC-14** | B | Contract documentation and fixture (`K8`, `X7`): `docs/contracts/content-api.md` and `tests/Fixtures/contracts/content-layout-v1.json` match response schema. | `docs/contracts/content-api.md`, `tests/Fixtures/contracts/content-layout-v1.json` | `it('matches the canonical contract fixture schema')` | `GetLayoutContentEndpointTest.php` | **PASSED** |
| **AC-15** | B | Architecture tests verify controller thinness and domain isolation (`S1`, `S2`, `S3`), and `ARCHITECTURE.md` marks `Content API v1` complete. | `tests/Arch/ContentApiArchTest.php`, `ARCHITECTURE.md` | `it('verifies content api controller thinness and domain isolation')` | `ContentApiArchTest.php` | **PASSED** |

---

## 2. Review Decision Confirmations & Process Notes

### 2.1 B1 Candidate Loop Reordering & Corrupt Block Resilience
In `GetLayoutContent.php`, candidates in each placement are passed to `ResolveServablePayload` with `log: true` before placement-allowed-type filtering. This ensures:
1. Unknown block types and corrupt schemas are captured and logged to the application log with cache throttling (`serving_failure:{$public_id}:{$outcome->value}`).
2. Disallowed block types for a specific placement are skipped silently without logging serving failure.
3. In `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php`, Test 5 explicitly asserts that the cache key `serving_failure:{$corruptBlock->public_id}:{$outcome->value}` is set across all 4 failing outcomes (`UnknownType`, `UnsupportedVersion`, `InvalidAfterUpgrade`, `UpgradeFailed`).

### 2.2 B2 RFC 7232 Section 3.2 Weak Comparison
In `GetLayoutContentController.php`, conditional evaluation inspects `$request->getEtags()`, strips any leading `W/` weak indicator via `preg_replace('/^W\//', '', $etag)`, and matches against wildcard `*` or expected quoted strong token `'"'.$layout->meta->revision.'"\'`. If matched, `$response->setNotModified()` sets HTTP 304 and empties the response body. Verified across strong, weak `W/`, `*`, and comma lists in `GetLayoutContentEndpointTest.php`.

### 2.3 B3 Larastan Level 8 Null-Safety Guards
`GetLayoutContent.php` implements explicit null guards:
1. Candidates are filtered ensuring `$block->placement instanceof Placement` before grouping by `$block->placement->value` (guarding against `TolerantEnum` null return).
2. `$definition = $this->registry->get($candidate->type)` has an explicit null guard (`if ($definition === null) { continue; }`) before accessing `$definition->schemaVersion()`.
3. `Menu::with(['items'])->get()->keyBy(fn (Menu $m): string => $m->key->value)` uses an explicit closure for PHPStan type safety.

### 2.4 M1 Boundary Freeze & Deterministic Exclusion Phase
In Test 3 of `GetLayoutContentTest.php`, time is frozen via `Carbon::setTestNow('2026-10-15 10:00:00')`. The test executes in two distinct phases:
1. **Exclusion Phase**: Only the 6 non-servable/expired rows (including `ends_at == now()`) are created. The test asserts that `announcement_bar` is empty (`[]`). This guarantees that if `ends_at > $now` is ever mutated to `>= $now` (`MUT-1`), the test fails immediately.
2. **Active Boundary Phase**: The exact active boundary block (`starts_at == now()`) is created and verified to return as the sole servable block.

### 2.5 M2 Query Count Discipline
In Test 13 of `GetLayoutContentTest.php`, `StorefrontSettings` is pre-warmed (`$_ = app(StorefrontSettings::class)->site_name;`) prior to enabling the query log. The test verifies that `GetLayoutContent` executes in exactly 3 database queries (1 query for `content_blocks`, 1 query for `menus`, 1 query for `menu_items`) at N=5 blocks / 10 menu items, and remains constant at exactly 3 queries at 2N=10 blocks / 20 menu items.

### 2.6 M3 Menu Cascading Visibility with Disabled Sibling
In Test 9 of `GetLayoutContentTest.php`, Root 2 has three child items: enabled `Child 2B` (sort order 0, created first), enabled `Child 2A` (sort order 0, created second), and disabled `Child 2C` (is_enabled = false). The test asserts:
1. Admin `GetMenuTree` retains disabled Root 1, Child 1A, and Child 2C (preserving admin editing capabilities).
2. Public `GetLayoutContent` excludes Root 1 and Child 1A entirely, excludes Child 2C under Root 2, and returns Root 3 before Root 2, and Child 2B before Child 2A in `sort_order ASC, id ASC`.

### 2.7 m1 Clock Alignment
`GetLayoutContent.php` generates `meta.generated_at` using `now()->utc()->toIso8601ZuluString()`, ensuring that visibility scopes (`ContentBlock::visible()`) and metadata timestamps share the identical application clock.

---

## 3. Raw Git Diff Status (`714fe30...HEAD`)

**Command:** `git diff 714fe30...HEAD --name-status`
```
M	.env.example
M	ARCHITECTURE.md
A	app/Domain/Content/Actions/GetLayoutContent.php
A	app/Domain/Content/Data/LayoutBlockData.php
A	app/Domain/Content/Data/LayoutData.php
A	app/Domain/Content/Data/LayoutMenuData.php
A	app/Domain/Content/Data/LayoutMenuItemData.php
A	app/Domain/Content/Data/LayoutMetaData.php
A	app/Domain/Content/Data/LayoutResponseData.php
A	app/Domain/Content/Data/LayoutSettingsData.php
A	app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php
M	app/Providers/AppServiceProvider.php
A	config/content.php
A	docs/contracts/content-api.md
M	routes/api.php
A	tests/Arch/ContentApiArchTest.php
A	tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php
A	tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php
A	tests/Fixtures/contracts/content-layout-v1.json
```

**Command:** `git diff 714fe30...HEAD --stat`
```
 .env.example                                       |   1 +
 ARCHITECTURE.md                                    |   2 +-
 app/Domain/Content/Actions/GetLayoutContent.php    | 173 ++++++
 app/Domain/Content/Data/LayoutBlockData.php        |  20 +
 app/Domain/Content/Data/LayoutData.php             |  20 +
 app/Domain/Content/Data/LayoutMenuData.php         |  18 +
 app/Domain/Content/Data/LayoutMenuItemData.php     |  22 +
 app/Domain/Content/Data/LayoutMetaData.php         |  16 +
 app/Domain/Content/Data/LayoutResponseData.php     |  15 +
 app/Domain/Content/Data/LayoutSettingsData.php     |  17 +
 .../Api/V1/Content/GetLayoutContentController.php  |  31 ++
 app/Providers/AppServiceProvider.php               |  12 +
 config/content.php                                 |   7 +
 docs/contracts/content-api.md                      | 127 +++++
 routes/api.php                                     |   7 +-
 tests/Arch/ContentApiArchTest.php                  |  24 +
 .../V1/Content/GetLayoutContentEndpointTest.php    | 214 ++++++++
 .../Content/Actions/GetLayoutContentTest.php       | 594 +++++++++++++++++++++
 tests/Fixtures/contracts/content-layout-v1.json    |  69 +++
 19 files changed, 1386 insertions(+), 3 deletions(-)
```

---

## 4. Layer B Mutation Spot-Checks

### MUT-1: Boundary Semantics at `ends_at`
- **File:** `app/Domain/Content/Models/ContentBlock.php:84`
- **Mutation:** Mutated `->orWhere('ends_at', '>', $now);` to `->orWhere('ends_at', '>=', $now);`.
- **Target Command:** `php artisan test tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php --filter="excludes draft, archived, disabled, future-scheduled, and expired blocks"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":948,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest::__pest_evaluable_it_excludes_draft__archived__disabled__future_scheduled__and_expired_blocks_from_placements","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php","line":120,"message":"Failed asserting that two arrays are identical.\n--- Expected\n+++ Actual\n@@ @@\n-Array &0 []\n+Array &0 [\n+ 0 => App\\Domain\\Content\\Data\\LayoutBlockData Object #4246 (\n+ '_additional' => Array &1 [],\n+ '_dataContext' => Spatie\\LaravelData\\Support\\Transformation\\DataContext Object #3694 (\n+ 'includePartials' => null,\n+ 'excludePartials' => null,\n+ 'onlyPartials' => null,\n+ 'exceptPartials' => null,\n+ 'wrap' => Spatie\\LaravelData\\Support\\Wrapping\\Wrap Object #4439 (\n+ 'type' => Spatie\\LaravelData\\Support\\Wrapping\\WrapType Enum #4235 (UseGlobal, 'use_global'),\n+ 'key' => null,\n+ ),\n+ ),\n+ 'id' => '01m4x0yfsf2rhh8mk0jggs4m9q',\n+ 'type' => 'announcement_bar',\n+ 'version' => 1,\n+ 'data' => Array &2 [\n+ 'text' => 'Ea et reiciendis praesentium doloremque quasi laborum.',\n+ 'link_url' => null,\n+ 'tone' => 'info',\n+ ],\n+ ),\n+]","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php:120"]}]}
```
- **Reversion:** `git checkout app/Domain/Content/Models/ContentBlock.php` (verified clean diff; test passes with 3 assertions).

### MUT-2: Disabled Menu Parent Cascade
- **File:** `app/Domain/Content/Actions/GetLayoutContent.php:109`
- **Mutation:** Removed `&& $item->is_enabled === true` from root items filter.
- **Target Command:** `php artisan test tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php --filter="cascades exclusion to children of disabled parent items"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":2,"duration_ms":951,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest::__pest_evaluable_it_cascades_exclusion_to_children_of_disabled_parent_items_while_retaining_enabled_siblings_in_strict_sort_order","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php","line":403,"message":"Failed asserting that actual size 3 matches expected size 2.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php:403"]}]}
```
- **Reversion:** `git checkout app/Domain/Content/Actions/GetLayoutContent.php` (verified clean diff; test passes with 7 assertions).

### MUT-3: Deterministic Revision Hashing
- **File:** `app/Domain/Content/Actions/GetLayoutContent.php:160`
- **Mutation:** Appended `$generatedAt` into hash input: `hash('sha256', $canonicalJson.$generatedAt)`.
- **Target Command:** `php artisan test tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php --filter="produces identical revision hash across timestamps"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":869,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest::__pest_evaluable_it_produces_identical_revision_hash_across_timestamps_and_changes_hash_when_content_mutates","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php","line":521,"message":"Failed asserting that two strings are identical.\n--- Expected\n+++ Actual\n@@ @@\n-'436515c89ecd72d08940cda8dcaebfb55ce2aecbc928f7ab60232bc3453f38e1'\n+'526cf19b56eda0f4842fdc85a87f0434a99df50d22c77dc91620b6fedf3326df'","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php:521"]}]}
```
- **Reversion:** `git checkout app/Domain/Content/Actions/GetLayoutContent.php` (verified clean diff; test passes with 7 assertions).

### MUT-4: ETag Header Quotes
- **File:** `app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php:18`
- **Mutation:** Set unquoted header `$response->headers->set('ETag', $layout->meta->revision)` instead of `setEtag`.
- **Target Command:** `php artisan test tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php --filter="returns HTTP 200 with layout data and meta without authentication"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":15,"duration_ms":860,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Api\\V1\\Content\\GetLayoutContentEndpointTest::__pest_evaluable_it_returns_HTTP_200_with_layout_data_and_meta_without_authentication","file":"\\dev\\getnow-cms\\vendor\\laravel\\framework\\src\\Illuminate\\Testing\\TestResponseAssert.php","line":46,"message":"Header [ETag] was found, but value [38d97cab7f7c46c6f08eda5a41b980fec65573a02f49a404b235d59db444b864] does not match [\"38d97cab7f7c46c6f08eda5a41b980fec65573a02f49a404b235d59db444b864\"].\nFailed asserting that two strings are equal.\n--- Expected\n+++ Actual\n@@ @@\n-'\"38d97cab7f7c46c6f08eda5a41b980fec65573a02f49a404b235d59db444b864\"'\n+'38d97cab7f7c46c6f08eda5a41b980fec65573a02f49a404b235d59db444b864'","trace":["C:\\dev\\getnow-cms\\vendor\\laravel\\framework\\src\\Illuminate\\Testing\\TestResponseAssert.php:46","C:\\dev\\getnow-cms\\tests\\Feature\\Api\\V1\\Content\\GetLayoutContentEndpointTest.php:62"]}]}
```
- **Reversion:** `git checkout app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php` (verified clean diff; test passes with 15 assertions).

### MUT-5: Public Column Name Mapping
- **File:** `app/Domain/Content/Actions/GetLayoutContent.php:136`
- **Mutation:** Mutated `new_tab: (bool) $root->opens_in_new_tab` to `new_tab: false`.
- **Target Command:** `php artisan test tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php --filter="maps menu item attributes to public contract shape without internal columns"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":5,"duration_ms":829,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest::__pest_evaluable_it_maps_menu_item_attributes_to_public_contract_shape_without_internal_columns","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php","line":439,"message":"Failed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php:439"]}]}
```
- **Reversion:** `git checkout app/Domain/Content/Actions/GetLayoutContent.php` (verified clean diff; test passes with 21 assertions).

---

## 5. Rule Register Self-Audit

| ID | Description | Compliance Evidence |
|---|---|---|
| **E1** | Windows/PowerShell native execution | All commands run one at a time via native PowerShell without bash syntax or `&&`. |
| **S1** | Business logic in Domain Actions | All querying, filtering, upgrading, D8 tie-break, menu cascading, and revision hashing logic is strictly encapsulated in `GetLayoutContent`. |
| **S2** | Thin controllers | `GetLayoutContentController` only invokes `GetLayoutContent`, evaluates RFC 7232 `If-None-Match`, and returns HTTP 200/304 JSON response. Enforced by `ContentApiArchTest`. |
| **S3** | Domain never imports Filament or Http | Verified via S3 scan (0 matches) and `DomainArchTest` / `ContentApiArchTest` passing. |
| **S4** | Filament never calls `Model::create/update/delete` directly | Unchanged; zero Filament modifications in this step. |
| **S5** | Block schemas in `app/Filament/Blocks/` | Unchanged; zero new block types created in this step. |
| **S6** | Panel code under `app/Filament/Admin/` | Zero panel code modifications in this step. |
| **S7** | Put code in correct place | Actions in `app/Domain/Content/Actions/`, DTOs in `app/Domain/Content/Data/`, controller in `app/Http/Controllers/Api/V1/Content/`. |
| **C1** | `declare(strict_types=1);` in every file | Declared at line 1 of every new/modified PHP file. Verified via C1 scan (0 violations). |
| **C2** | Strict types on properties, parameters, returns; final classes | All classes typed and marked `final`. |
| **C3** | Use enums and Data classes | `Placement` and `MenuKey` backed enums used for keys; Spatie Data DTOs used for all layout payload representations. |
| **C4** | Status & `is_enabled` changed only via Actions | Read-only step with zero status modifications. |
| **C5** | Action rule applies to all state persistence | Read-only step with zero writes to persisted state. |
| **C6** | Validation and Policies | Public read-only endpoint; output validated and structured via Spatie Data DTOs. |
| **C7** | Follow Pint and Larastan without baselines | `pint` passed and `phpstan` passed with 0 errors at Level 8 without baseline additions. |
| **C8** | Comments explain why, not what | Zero commented-out code. |
| **K1** | Block type keys permanent | Preserves permanent `announcement_bar` block key. |
| **K2** | Payload schema versioning | Stored block payloads upgraded through `GetUpgradedBlockPayload` via `ResolveServablePayload`. |
| **K3** | No raw HTML/CSS/JS fields | Public response exposes validated strings, booleans, enums, and safe links. |
| **K4** | Validated links (`/` or `https://`) | Reuses existing link safety validations persisted on block payloads and menu items. |
| **K5** | Placements & types defined in code | Driven by `Placement::cases()` and `MenuKey::cases()`. |
| **K6** | Explicit block registration | Explicit registry in `ContentServiceProvider` preserved. |
| **K7** | Server-side visibility | Verified server-side via `scopeVisible()`, schedule boundary checks, and menu `is_enabled` cascade filtering. |
| **K8** | Content API contract | `docs/contracts/content-api.md` created, fixture `content-layout-v1.json` added, verified by automated test. |
| **K9** | Checklist in Architecture sec 6 followed | Respected. |
| **DB1** | PostgreSQL only | Tests run against Postgres `phone_store_test`. |
| **DB2** | Append-only migrations | Zero migrations added or edited. |
| **DB3** | No destructive DB changes | Zero dropped or renamed columns. |
| **DB4** | DB constraints respected | Leverages existing database constraints and index prefixes. |
| **DB5** | Expose `public_id` externally | Public JSON exposes `public_id` mapped to `"id"`; zero internal incremental `id` values leaked. |
| **DB6** | Idempotent seeders | Zero database seeders executed outside test transactions. |
| **SEC1** | No secrets in repo | `CONTENT_API_RATE_LIMIT_PER_MINUTE=60` added to `.env.example`. Zero `.env` files tracked. |
| **SEC2** | Authorization never bypassed | Public read-only endpoint without authorization bypasses. |
| **SEC3** | Do not log sensitive data | Error logs in `ResolveServablePayload` record only public ID, type, schema version, and failure reason. |
| **SEC4** | Admin access checked server-side | Public layout API does not require admin authentication. |
| **SEC5** | Sensitive state logged | Read-only endpoint generates zero audit log entries. |
| **T1** | Pest tests for Actions & API | 374 Pest tests passing. |
| **T2** | Resource smoke tests | Existing Filament resource smoke tests passing. |
| **T3** | Block type tests | Announcement bar API output, visibility, and fallback tested. |
| **T4** | Factories used | `ContentBlockFactory`, `MenuFactory`, and `MenuItemFactory` used across tests. |
| **T5** | No skipped or incomplete tests | 374 tests passed, 0 skipped, 0 incomplete. |
| **G1** | Branch from main | Work developed on feature branch `feat/content-api-v1`. |
| **G2** | Conventional Commits | Commits follow conventional commit specification. |
| **G3** | Never push directly to main | Preserved. |
| **G4** | PR descriptions complete | Ready for PR creation. |
| **X1** | Do not add dependencies | Zero dependencies added (`composer.json` untouched). |
| **X2** | Do not introduce Docker | Native Windows environment preserved. |
| **X3** | Do not add deployment pipelines | Preserved. |
| **X4** | Stay within phase scope | Only Phase 1 Content API v1 touched. |
| **X5** | Do not modify storefront repo | Storefront untouched. |
| **X6** | `composer check` passes | 374 tests passed (1695 assertions), Pint passed, PHPStan passed (0 errors). |
| **X7** | Docs updated where relevant | `ARCHITECTURE.md` updated with completed Content API v1 checklist item; `docs/contracts/content-api.md` and `.env.example` created/updated. |

---

## 6. Full Quality Suite Output (`composer check`)

**Command:** `composer check`
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":374,"passed":374,"assertions":1695,"duration_ms":33745}
```
*Static Analysis (`composer stan`):* Passed with **0 errors** at Level 8 without baseline entries or suppressions.

---

## 7. Layer A Raw Command Verification Output

### Git Status
**Command:** `git status --short`
```
?? docs/blueprints/P1-API1/
```
*(Only blueprint artifacts in `docs/blueprints/P1-API1/` present prior to final blueprint commit).*

### Git Log
**Command:** `git log -n 10 --oneline`
```
bb06081 docs(architecture): mark Content API v1 complete in Phase 1
9672325 feat(content): add layout API endpoint with ETag and rate limiting
fc2e38a feat(content): add GetLayoutContent action with visibility filtering and D8 tie breaking
096d1cf feat(content): add public layout data DTOs and contract fixture
714fe30 Merge remote-tracking branch 'origin/main' into feat/storefront-navigation-menus
9a055b9 docs(blueprints): record manual ui run, lowercase ulids, and review evidence in p1-m1 report
7146050 fix(content): lowercase ulids, prune unused getmenulist, touch menu updated_at, and extend ac-8 tests
7aed3d4 Merge pull request #9 from AchinthaDe/feat/storefront-navigation-menus
9857b5f docs(blueprints): restore canonical Plan v2 AC table and audit details in P1-M1 report
2b9c6c0 test(content): add boundary, disabled items, and mid-transaction rollback tests
```

### Git Merge-Base
**Command:** `git merge-base main HEAD`
```
2e3ca781f305d8613e85944c4d25c79219e322e1
```

### Step-Specific Check 1: Route List
**Command:** `php artisan route:list --path=api/v1/content`
```
 GET|HEAD api/v1/content/layout .. api.v1.content.layout › Api\V1\Content\GetLayoutContentController

 Showing [1] routes
```

### Step-Specific Check 2: Migrations & Dependencies Git Diff
**Command:** `git diff 714fe30...HEAD --stat -- database composer.json composer.lock package.json`
```
[Empty - 0 matches]
```

### Step-Specific Check 3: Filament Layer Git Diff
**Command:** `git diff 714fe30...HEAD --stat -- app/Filament`
```
[Empty - 0 matches]
```

### Step-Specific Check 4: MenuTree Action Git Diff
**Command:** `git diff 714fe30...HEAD -- app/Domain/Content/Actions/GetMenuTree.php`
```
[Empty - 0 matches]
```

### Step-Specific Check 5: Domain Layer Isolation Scan
**Command:** `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Filament','App\Http','Illuminate\Http'`
```
[Empty - 0 matches]
```

### Step-Specific Check 6: Controller Thinness Scan
**Command:** `Get-ChildItem app/Http/Controllers/Api -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Domain\Content\Models','DB::','StorefrontSettings'`
```
[Empty - 0 matches]
```

### C1 Scan: Strict Types Declaration
**Command:** `git diff 714fe30...HEAD --name-only -- '*.php' | ForEach-Object { if (-not (Get-Content $_ -TotalCount 5 | Select-String -SimpleMatch 'declare(strict_types=1);')) { $_ } }`
```
[Empty - 0 matches / 100% compliant across all changed PHP files]
```

### T5 Scan: Skipped or Incomplete Tests
**Command:** `git diff 714fe30...HEAD --name-only -- 'tests/*.php' | ForEach-Object { Select-String -Path $_ -Pattern "->skip\(|->todo\(|markTestIncomplete|markTestSkipped" }`
```
[Empty - 0 matches]
```

### X2 Scan: Container / Docker Configuration
**Command:** `git diff 714fe30...HEAD --name-only | Select-String -Pattern "docker|compose|sail|container"`
```
[Empty - 0 matches]
```

### SEC1 Scan: Tracked Environment Files
**Command:** `git ls-files .env`
```
[Empty - 0 matches]
```

---

## 8. Layer C Manual Verification Output

Executed against native local server (`php artisan serve`) with PostgreSQL database `phone_store`:

```powershell
$proc = Start-Process php -ArgumentList "artisan serve --port=8000" -WorkingDirectory "c:\dev\getnow-cms" -PassThru
Start-Sleep -Seconds 3

try {
    # 2. Fetch layout
    $resp = Invoke-RestMethod -Uri http://127.0.0.1:8000/api/v1/content/layout -UseBasicParsing
    Write-Host "Site Name: $($resp.data.settings.site_name)"
    Write-Host "Revision: $($resp.meta.revision)"

    # 3. Inspect ETag header
    $webResp = Invoke-WebRequest -Uri http://127.0.0.1:8000/api/v1/content/layout -UseBasicParsing
    $etagHeader = $webResp.Headers['ETag']
    $etag = if ($etagHeader -is [array]) { $etagHeader[0] } else { $etagHeader }
    Write-Host "ETag: $etag"

    # 4. Conditional GET (304)
    try {
        $conditionalResp = Invoke-WebRequest -Uri http://127.0.0.1:8000/api/v1/content/layout -Headers @{'If-None-Match' = $etag} -UseBasicParsing
        Write-Host "Status: $($conditionalResp.StatusCode)"
    } catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 304) {
            Write-Host "Status: 304 Not Modified"
        } else {
            throw $_
        }
    }
} finally {
    # 5. Stop server process cleanly
    if ($proc -and -not $proc.HasExited) {
        Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
    }
    Get-CimInstance Win32_Process -Filter "name = 'php.exe'" | Where-Object { $_.CommandLine -like '*artisan serve*' -or $_.CommandLine -like '*-S 127.0.0.1:8000*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}
```

### Raw Command Output
```
Site Name: Storefront
Revision: a1d38ca3aace5eb60e84e1cadb14dbc83d1aedfa372107e46efcaca5c8d19646
ETag: "a1d38ca3aace5eb60e84e1cadb14dbc83d1aedfa372107e46efcaca5c8d19646"
Status: 304 Not Modified
```
Server process cleanly terminated after execution (0 running `artisan serve` processes remaining).
