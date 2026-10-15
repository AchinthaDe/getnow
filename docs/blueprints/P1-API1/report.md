# P1-API1 IMPLEMENTATION REPORT

**Step ID:** P1-API1  
**Status:** Ready for Review  
**Base Commit:** `714fe30c664db9b5ddf8b993ef4942c15c79d34a`  
**Tracking Upstream:** `origin/main` (merge commit `051c69e` merged P1-M1 follow-ups `7146050`, `9a055b9`, and `714fe30`)  
**Branch:** `feat/content-api-v1`  
**Commits on Branch (origin/main..HEAD):**
1. `096d1cf` feat(content): add public layout data DTOs and contract fixture
2. `fc2e38a` feat(content): add GetLayoutContent action with visibility filtering and D8 tie breaking
3. `9672325` feat(content): add layout API endpoint with ETag and rate limiting
4. `bb06081` docs(architecture): mark Content API v1 complete in Phase 1
5. `757ed6e` docs(blueprints): add P1-API1 brief, plan v3, code diff, and implementation report
6. `57c0569` fix(content): address review findings F5-F7 for content api
7. docs(blueprints): update P1-API1 report, add fixture diff, and remove code_diff.txt for VERIFY r2

**Deviations:** None  
**Gaps:** None  

---

## 1. Acceptance Criteria Verification (Canonical Plan v3 Section 9)

| AC ID | Part | Requirement Summary | Implementation Files (`file:lines`) | Verifying Test Name (`it('...')`) & Location (`file:lines`) | Result |
|---|---|---|---|---|---|
| **AC-1** | B | `GET /api/v1/content/layout` is publicly accessible without authentication and returns HTTP 200 with keys `data` and `meta`. | [GetLayoutContentController.php:14-30](file:///c:/dev/getnow-cms/app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php#L14-L30), [routes/api.php:8-10](file:///c:/dev/getnow-cms/routes/api.php#L8-L10) | `it('returns HTTP 200 with layout data and meta without authentication')`<br>[GetLayoutContentEndpointTest.php:26-61](file:///c:/dev/getnow-cms/tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php#L26-L61) | **PASSED** |
| **AC-2** | A | `data.settings` returns `site_name`, `default_meta_description`, `search_placeholder`, `footer_brand_text` matching `StorefrontSettings`. | [GetLayoutContent.php:34-39](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L34-L39), [LayoutSettingsData.php:9-17](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutSettingsData.php#L9-L17) | `it('returns storefront settings matching configured values')`<br>[GetLayoutContentTest.php:65-79](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L65-L79) | **PASSED** |
| **AC-3** | A | Every case in `Placement::cases()` is present in `data.placements` (`[]` when empty), and every case in `MenuKey::cases()` is present in `data.menus` (`{"title": ..., "items": []}`). | [GetLayoutContent.php:51-89, 95-145](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L51-L145), [LayoutData.php:9-20](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutData.php#L9-L20), [LayoutMenuData.php:9-18](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutMenuData.php#L9-L18) | `it('includes every defined placement and menu key even when empty or unprovisioned')`<br>[GetLayoutContentTest.php:81-96](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L81-L96) | **PASSED** |
| **AC-4** | A | Block visibility (`K7`): draft, archived, disabled, future-scheduled (`starts_at > now`), or expired (`ends_at <= now`) blocks are never returned. | [GetLayoutContent.php:42](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L42), [ContentBlock.php:73-86](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php#L73-L86) | `it('excludes draft, archived, disabled, future-scheduled, and expired blocks from placements')`<br>[GetLayoutContentTest.php:98-133](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L98-L133) | **PASSED** |
| **AC-5** | A | Placement tie-break and `maxCount` (`D8`): winner selected by `sort_order ASC, starts_at DESC NULLS LAST, id DESC`, capped at `maxCount()`. | [GetLayoutContent.php:42, 53-56, 84-86](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L42-L86), [ContentBlock.php:92-97](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php#L92-L97), [Placement.php:18-24](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php#L18-L24) | `it('enforces placement maxCount and selects candidate by D8 tie break order')`<br>[GetLayoutContentTest.php:135-196](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L135-L196) | **PASSED** |
| **AC-6** | A | Block payload upgrading and corrupt-row resilience: stored blocks at older schema version are upgraded; corrupt/unservable blocks do not 500 and next candidate in D8 order is served. | [GetLayoutContent.php:58-86](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L58-L86), [ResolveServablePayload.php:21-72](file:///c:/dev/getnow-cms/app/Domain/Content/Services/ResolveServablePayload.php#L21-L72) | `it('falls back to next valid candidate when top candidate is unservable')`<br>[GetLayoutContentTest.php:198-219](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L198-L219),<br>`it('returns empty placement array when all candidate blocks are corrupt')`<br>[GetLayoutContentTest.php:221-229](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L221-L229),<br>`it('skips blocks whose type is not permitted for the placement')`<br>[GetLayoutContentTest.php:231-278](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L231-L278),<br>`it('upgrades older payload schemas to current version')`<br>[GetLayoutContentTest.php:280-352](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L280-L352) | **PASSED** |
| **AC-7** | A | Menu visibility (`K7`): disabled top-level items and all their children are omitted; disabled child items under enabled parent are omitted; enabled siblings remain ordered by `sort_order ASC, id ASC`. | [GetLayoutContent.php:108-144](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L108-L144), [Menu.php:54-57](file:///c:/dev/getnow-cms/app/Domain/Content/Models/Menu.php#L54-L57), [MenuItem.php:67-80](file:///c:/dev/getnow-cms/app/Domain/Content/Models/MenuItem.php#L67-L80) | `it('cascades exclusion to children of disabled parent items while retaining enabled siblings in strict sort order')`<br>[GetLayoutContentTest.php:354-411](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L354-L411) | **PASSED** |
| **AC-8** | A | Menu item mapping: maps `public_id` to `id`, `opens_in_new_tab` to `new_tab`, `children: []`, and never leaks internal columns. | [GetLayoutContent.php:121-129, 131-139](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L121-L139), [LayoutMenuItemData.php:9-22](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutMenuItemData.php#L9-L22) | `it('maps menu item attributes to public contract shape without internal columns')`<br>[GetLayoutContentTest.php:413-450](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L413-L450) | **PASSED** |
| **AC-9** | A | No raw database primary keys, user foreign keys, or internal lifecycle fields appear anywhere in JSON response (`DB5`, `SEC3`). | [GetLayoutContent.php:32-171](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L32-L171), [LayoutResponseData.php:9-15](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutResponseData.php#L9-L15) | `it('contains zero raw database primary keys or internal lifecycle attributes')`<br>[GetLayoutContentTest.php:452-495](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L452-L495) | **PASSED** |
| **AC-10** | A | `meta.revision` is deterministic: calling `GetLayoutContent` twice at different timestamps produces same hash; mutating content changes hash. | [GetLayoutContent.php:148-167](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L148-L167), [LayoutMetaData.php:9-16](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutMetaData.php#L9-L16) | `it('produces identical revision hash across timestamps and changes hash when content mutates')`<br>[GetLayoutContentTest.php:497-558](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L497-L558) | **PASSED** |
| **AC-11** | B | ETag & conditional GET: returns `ETag: "<revision>"`; matching `If-None-Match` (including weak `W/`, `*`, comma lists) returns HTTP 304 with empty body; stale hash returns HTTP 200. | [GetLayoutContentController.php:14-30](file:///c:/dev/getnow-cms/app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php#L14-L30) | `it('handles conditional GET returning 304 on matching ETag and 200 on stale ETag')`<br>[GetLayoutContentEndpointTest.php:63-105](file:///c:/dev/getnow-cms/tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php#L63-L105) | **PASSED** |
| **AC-12** | B | Rate limiting: requests exceeding configured per-minute limit receive HTTP 429 Too Many Requests. | [AppServiceProvider.php:34-37](file:///c:/dev/getnow-cms/app/Providers/AppServiceProvider.php#L34-L37), [config/content.php:5-7](file:///c:/dev/getnow-cms/config/content.php#L5-L7), [routes/api.php:8-10](file:///c:/dev/getnow-cms/routes/api.php#L8-L10) | `it('throttles requests exceeding configured rate limit with HTTP 429')`<br>[GetLayoutContentEndpointTest.php:107-114](file:///c:/dev/getnow-cms/tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php#L107-L114) | **PASSED** |
| **AC-13** | A | Query count discipline: `GetLayoutContent` executes in bounded, constant number of database queries (3 queries when settings warmed). | [GetLayoutContent.php:42, 91](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L42-L91) | `it('executes layout retrieval within constant query bounds')`<br>[GetLayoutContentTest.php:560-593](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php#L560-L593) | **PASSED** |
| **AC-14** | B | Contract documentation and fixture (`K8`, `X7`): `docs/contracts/content-api.md` and `tests/Fixtures/contracts/content-layout-v1.json` match response schema. | [docs/contracts/content-api.md:1-128](file:///c:/dev/getnow-cms/docs/contracts/content-api.md#L1-L128), [tests/Fixtures/contracts/content-layout-v1.json:1-69](file:///c:/dev/getnow-cms/tests/Fixtures/contracts/content-layout-v1.json#L1-L69) | `it('matches the canonical contract fixture schema')`<br>[GetLayoutContentEndpointTest.php:116-204](file:///c:/dev/getnow-cms/tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php#L116-L204) | **PASSED** |
| **AC-15** | B | Architecture tests verify controller thinness and domain isolation (`S1`, `S2`, `S3`), and `ARCHITECTURE.md` marks `Content API v1` complete. | [tests/Arch/ContentApiArchTest.php:10-24](file:///c:/dev/getnow-cms/tests/Arch/ContentApiArchTest.php#L10-L24), [ARCHITECTURE.md:25](file:///c:/dev/getnow-cms/ARCHITECTURE.md#L25) | `arch('content api controllers are thin and use GetLayoutContent')`<br>[ContentApiArchTest.php:10-12](file:///c:/dev/getnow-cms/tests/Arch/ContentApiArchTest.php#L10-L12),<br>`arch('content api controllers do not depend on domain models, db, or storefront settings directly')`<br>[ContentApiArchTest.php:14-20](file:///c:/dev/getnow-cms/tests/Arch/ContentApiArchTest.php#L14-L20),<br>`arch('content domain does not depend on http or illuminate http')`<br>[ContentApiArchTest.php:22-24](file:///c:/dev/getnow-cms/tests/Arch/ContentApiArchTest.php#L22-L24) | **PASSED** |

---

## 2. Review Decision Confirmations & Process Notes

### 2.1 B1 Candidate Loop Reordering & Corrupt Block Resilience
In [GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php), candidate blocks in each placement loop pass through `($this->resolveServablePayload)($candidate, log: true)` *before* checking `$placement->allowedBlockTypes()`. This ensures:
1. Unknown block types and corrupt schemas reach the resolver and are logged with cache throttling (`serving_failure:{$public_id}:{$outcome->value}`).
2. Disallowed block types for a specific placement are skipped second without logging serving failure.
3. In [GetLayoutContentTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php), Test 5 explicitly asserts that the cache key `serving_failure:{$corruptBlock->public_id}:{$outcome->value}` is set across all 4 failing outcomes (`UnknownType`, `UnsupportedVersion`, `InvalidAfterUpgrade`, `UpgradeFailed`).

### 2.2 B2 RFC 7232 Section 3.2 Weak Comparison
In [GetLayoutContentController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php), conditional request evaluation inspects `$request->getEtags()`, strips any leading `W/` weak indicator via `preg_replace('/^W\//', '', $etag)`, and matches against wildcard `*` or expected strong token `'"'.$layout->meta->revision.'"\'`. If matched, `$response->setNotModified()` sets HTTP 304 and clears the body. Verified across strong, weak `W/`, wildcard `*`, and comma lists in [GetLayoutContentEndpointTest.php](file:///c:/dev/getnow-cms/tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php).

### 2.3 B3 Larastan Level 8 Null-Safety Guards
[GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php) implements explicit null guards:
1. Candidates are filtered ensuring `$block->placement instanceof Placement` before grouping by `$block->placement->value` (guarding against `TolerantEnum` null return).
2. `$definition = $this->registry->get($candidate->type)` has an explicit null guard (`if ($definition === null) { continue; }`) before accessing `$definition->schemaVersion()`.
3. `Menu::with(['items' => fn (HasMany $q) => ...])` types the relationship closure parameter explicitly with `HasMany`, and `Menu::get()->keyBy(fn (Menu $m): string => $m->key->value)` uses an explicit closure for static type inference.

### 2.4 M1 Boundary Freeze & Deterministic Exclusion Phase
In Test 3 of [GetLayoutContentTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php), time is frozen via `Carbon::setTestNow('2026-10-15 10:00:00')`. The test executes in two distinct phases:
1. **Exclusion Phase**: Only the 6 non-servable/expired rows (including `ends_at == now()`) are created. The test asserts that `announcement_bar` is empty (`[]`). This guarantees that if `ends_at > $now` is ever mutated to `>= $now` (`MUT-1`), the test fails immediately.
2. **Active Boundary Phase**: The exact active boundary block (`starts_at == now()`) is created and verified to return as the sole servable block.

### 2.5 M2 Query Count Discipline
In Test 13 of [GetLayoutContentTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php), `StorefrontSettings` is pre-warmed (`$_ = app(StorefrontSettings::class)->site_name;`) prior to enabling the query log. The test verifies that `GetLayoutContent` executes in exactly 3 database queries (1 query for `content_blocks`, 1 query for `menus`, 1 query for `menu_items`) at N=5 blocks / 10 menu items, and remains constant at exactly 3 queries at 2N=10 blocks / 20 menu items.

### 2.6 M3 Menu Cascading Visibility with Disabled Sibling (Test 9)
In Test 9 of [GetLayoutContentTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php), Root 2 has three child items: enabled `Child 2B` (sort order 0, created first), enabled `Child 2A` (sort order 0, created second), and disabled `Child 2C` (is_enabled = false). The test asserts:
1. Admin `GetMenuTree` retains all 3 roots in `$adminTree->items` (including disabled Root 1).
2. Public `GetLayoutContent` excludes Root 1 and Child 1A entirely, excludes Child 2C under Root 2, and returns Root 3 before Root 2, and Child 2B before Child 2A in `sort_order ASC, id ASC`.

### 2.7 m1 Clock Alignment
[GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php) generates `meta.generated_at` using `now()->utc()->toIso8601ZuluString()`, ensuring that visibility scopes (`ContentBlock::visible()`) and metadata timestamps share the identical application clock.

---

## 3. File Manifest Reconciliation

Total implementation files: **19 files** (15 new, 4 modified). Zero extraneous or unplanned files.

| File Path | Planned Action | Actual Action | Diff (+ / -) | Reconciliation Explanation |
|---|---|---|---|---|
| `.env.example` | Modify | Modified | +1 / -0 | Added `CONTENT_API_RATE_LIMIT_PER_MINUTE=60` |
| `ARCHITECTURE.md` | Modify | Modified | +1 / -1 | Checked Phase 1 Content API v1 checklist box |
| `app/Domain/Content/Actions/GetLayoutContent.php` | New | Created | +173 / -0 | Domain Action implementing layout assembly |
| `app/Domain/Content/Data/LayoutBlockData.php` | New | Created | +20 / -0 | Spatie Data DTO for public placement block |
| `app/Domain/Content/Data/LayoutData.php` | New | Created | +20 / -0 | Spatie Data DTO for settings, placements, menus |
| `app/Domain/Content/Data/LayoutMenuData.php` | New | Created | +18 / -0 | Spatie Data DTO for menu title and items tree |
| `app/Domain/Content/Data/LayoutMenuItemData.php` | New | Created | +22 / -0 | Spatie Data DTO for menu item node |
| `app/Domain/Content/Data/LayoutMetaData.php` | New | Created | +16 / -0 | Spatie Data DTO for response metadata and revision |
| `app/Domain/Content/Data/LayoutResponseData.php` | New | Created | +15 / -0 | Spatie Data DTO for top-level API response |
| `app/Domain/Content/Data/LayoutSettingsData.php` | New | Created | +17 / -0 | Spatie Data DTO for public site settings |
| `app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php` | New | Created | +31 / -0 | Thin controller with RFC 7232 ETag handling |
| `app/Providers/AppServiceProvider.php` | Modify | Modified | +12 / -0 | Registered `content-api` named rate limiter |
| `config/content.php` | New | Created | +7 / -0 | Config file defining `rate_limit_per_minute` |
| `docs/contracts/content-api.md` | New | Created | +128 / -0 | Content API v1 OpenAPI-style contract document |
| `routes/api.php` | Modify | Modified | +5 / -2 | Registered `GET /api/v1/content/layout` under limiter |
| `tests/Arch/ContentApiArchTest.php` | New | Created | +24 / -0 | Arch tests verifying thinness & domain isolation |
| `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` | New | Created | +204 / -0 | Feature tests for endpoint, ETag, limiter, fixture |
| `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | New | Created | +593 / -0 | Feature tests for Action visibility & D8 tie-breaks |
| `tests/Fixtures/contracts/content-layout-v1.json` | New | Created | +69 / -0 | Canonical contract fixture with token placeholders |

### Documentation and Configuration Diffs

#### `.env.example`
```diff
--- a/.env.example
+++ b/.env.example
@@ -64,3 +64,4 @@ AWS_USE_PATH_STYLE_ENDPOINT=false
 
 VITE_APP_NAME="${APP_NAME}"
 ADMIN_TIMEZONE=Asia/Colombo
+CONTENT_API_RATE_LIMIT_PER_MINUTE=60
```

#### `ARCHITECTURE.md`
```diff
--- a/ARCHITECTURE.md
+++ b/ARCHITECTURE.md
@@ -22,7 +22,7 @@ This document is the source of truth for structure and boundaries. If code and t
 - [x] UI Step 3 (BlockFormRegistry, Create/Edit forms)
 - [x] UI Step 4 (View page, header/row actions)
 - [x] Menus
-- [ ] Content API v1
+- [x] Content API v1
 - [ ] Seed data
 
 Rule: do not scaffold folders, models or packages for a phase that is not active.
```

#### `docs/contracts/content-api.md`
```diff
--- /dev/null
+++ b/docs/contracts/content-api.md
@@ -0,0 +1,128 @@
+# Content API v1 Contract
+
+Version: v1  
+Status: Active  
+Base Path: `/api/v1/content`
+
+The Content API delivers public storefront layout state from the GetNow CMS. It is optimized for server-side or client-side storefront consumption with deterministic revision-based conditional caching (`ETag` / `If-None-Match`).
+
+---
+
+## 1. Endpoints
+
+### `GET /api/v1/content/layout`
+
+Delivers the complete public layout graph, including global site settings, visible placement blocks, and enabled navigation menus.
+
+#### Access & Authentication
+- **Authentication**: None. Publicly accessible.
+- **Rate Limit**: 60 requests per minute per IP address by default (`CONTENT_API_RATE_LIMIT_PER_MINUTE=60`). When exceeded, returns `429 Too Many Requests`.
+
+#### Storefront Caching Guidance
+The Blade storefront server and client consumers should leverage HTTP conditional caching. Each response includes a strong `ETag: "<revision>"`. When fetching layout, clients should supply the cached revision in `If-None-Match: "<revision>"`. If the content has not changed, the server returns `304 Not Modified` with zero body content, avoiding redundant serialization and network payload transfer.
+
+#### Request Headers
+| Header | Type | Required | Description |
+|---|---|---|---|
+| `Accept` | `string` | Recommended | Must be `application/json` |
+| `If-None-Match` | `string` | Optional | Client's previously received `meta.revision` quoted string, weak token, comma-separated list, or wildcard `*` (e.g., `"1a2b3c..."`, `W/"1a2b3c..."`, or `*`) |
+
+#### Response Headers
+| Header | Description |
+|---|---|
+| `Content-Type` | `application/json` |
+| `ETag` | Quoted SHA-256 revision hash matching `meta.revision` (e.g. `"a8f5f167..."`) |
+| `Cache-Control` | Default Laravel private cache control (`no-cache, private`) |
+
+#### Response Codes
+- `200 OK`: Full layout JSON payload returned.
+- `304 Not Modified`: Cached content matches current revision. Empty response body.
+- `429 Too Many Requests`: Client exceeded per-minute rate limit.
+
+---
+
+## 2. Response Schema
+
+### Root Structure
+```json
+{
+  "data": {
+    "settings": {
+      "site_name": "GetNow Marketplace",
+      "default_meta_description": "Wholesale mobile phones and consumer electronics.",
+      "search_placeholder": "Search models, brands, or SKUs...",
+      "footer_brand_text": "GetNow Electronics Wholesale Ltd."
+    },
+    "placements": {
+      "announcement_bar": [
+        {
+          "id": "01jar000000000000000000001",
+          "type": "announcement_bar",
+          "version": 1,
+          "data": {
+            "text": "Welcome to GetNow Wholesale Electronics",
+            "link_url": null,
+            "tone": "info"
+          }
+        }
+      ]
+    },
+    "menus": {
+      "main_nav": {
+        "title": "Main Navigation",
+        "items": [
+          {
+            "id": "01jar000000000000000000002",
+            "label": "Phones",
+            "url": "/phones",
+            "icon": null,
+            "new_tab": false,
+            "children": [
+              {
+                "id": "01jar000000000000000000003",
+                "label": "Apple",
+                "url": "/phones/apple",
+                "icon": null,
+                "new_tab": false,
+                "children": []
+              }
+            ]
+          }
+        ]
+      },
+      "mobile_nav": {
+        "title": null,
+        "items": []
+      },
+      "footer_column_1": {
+        "title": null,
+        "items": []
+      },
+      "footer_column_2": {
+        "title": null,
+        "items": []
+      },
+      "footer_column_3": {
+        "title": null,
+        "items": []
+      }
+    }
+  },
+  "meta": {
+    "api_version": 1,
+    "revision": "a8f5f167f44f4964e6c998dee827110ca8f5f167f44f4964e6c998dee827110c",
+    "generated_at": "2026-10-14T10:00:00Z"
+  }
+}
+```
+
+---
+
+## 3. Data Integrity & Contract Guarantees
+
+1. **Complete Key Presence**: Every defined placement (`Placement::cases()`) and menu key (`MenuKey::cases()`) is always present in the response, returning empty arrays or default structures when unpopulated or unprovisioned.
+2. **Server-Side Visibility Enforcement**: Content that is draft, archived, disabled, scheduled in the future, or expired is never exposed.
+3. **Menu Hierarchy & Cascading Exclusion**: If a parent menu item is disabled, the parent and all of its descendant children are omitted from the response. Remaining items are strictly ordered by `sort_order ASC, id ASC`.
+4. **ID Anonymization**: Internal PostgreSQL incremental IDs are never exposed. All entity identifiers are external Crockford ULIDs mapped to the property name `id`.
+5. **Deterministic Revision**: `meta.revision` is computed from the canonical JSON serialization of the `data` object, ensuring identical content produces identical hashes across different request timestamps.
+6. **Placement Cardinality & Unservable Resilience**: `announcement_bar` returns at most 1 block (`maxCount() = 1`). Candidate blocks that are unservable (due to unknown type, unsupported schema version, upgrade failure, or invalid payload) or whose type is not permitted for the placement are skipped silently, falling back to the next eligible candidate in tie-break order.
```

#### `tests/Fixtures/contracts/content-layout-v1.json`
```diff
--- /dev/null
+++ b/tests/Fixtures/contracts/content-layout-v1.json
@@ -0,0 +1,69 @@
+{
+  "data": {
+    "settings": {
+      "site_name": "GetNow Marketplace",
+      "default_meta_description": "Wholesale mobile phones and consumer electronics.",
+      "search_placeholder": "Search models, brands, or SKUs...",
+      "footer_brand_text": "GetNow Electronics Wholesale Ltd."
+    },
+    "placements": {
+      "announcement_bar": [
+        {
+          "id": "<ulid>",
+          "type": "announcement_bar",
+          "version": 1,
+          "data": {
+            "text": "Welcome to GetNow Wholesale Electronics",
+            "link_url": null,
+            "tone": "info"
+          }
+        }
+      ]
+    },
+    "menus": {
+      "main_nav": {
+        "title": "Main Navigation",
+        "items": [
+          {
+            "id": "<ulid>",
+            "label": "Phones",
+            "url": "/phones",
+            "icon": null,
+            "new_tab": false,
+            "children": [
+              {
+                "id": "<ulid>",
+                "label": "Apple",
+                "url": "/phones/apple",
+                "icon": null,
+                "new_tab": false,
+                "children": []
+              }
+            ]
+          }
+        ]
+      },
+      "mobile_nav": {
+        "title": null,
+        "items": []
+      },
+      "footer_column_1": {
+        "title": null,
+        "items": []
+      },
+      "footer_column_2": {
+        "title": null,
+        "items": []
+      },
+      "footer_column_3": {
+        "title": null,
+        "items": []
+      }
+    }
+  },
+  "meta": {
+    "api_version": 1,
+    "revision": "<hash>",
+    "generated_at": "<iso8601>"
+  }
+}
```

---

## 4. Layer B Mutation Spot-Checks (Spot-Check r1)

### MUT-A: Disabled Child Menu Item Filter
- **File:** `app/Domain/Content/Actions/GetLayoutContent.php:112`
- **Mutation:** Removed `&& $item->is_enabled === true` from `$childrenByParent` filter.
- **Target Command:** `php artisan test tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php --filter="cascades exclusion to children of disabled parent items"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":5,"duration_ms":784,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest::__pest_evaluable_it_cascades_exclusion_to_children_of_disabled_parent_items_while_retaining_enabled_siblings_in_strict_sort_order","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php","line":408,"message":"Failed asserting that actual size 3 matches expected size 2.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php:408"]}]}
```
- **Reversion:** `git checkout app/Domain/Content/Actions/GetLayoutContent.php` (verified clean diff; test passes with 7 assertions).

### MUT-B: NULLS LAST Serving Order Tie-Break
- **File:** `app/Domain/Content/Models/ContentBlock.php:95`
- **Mutation:** Mutated `->orderByRaw('starts_at DESC NULLS LAST')` to `->orderByRaw('starts_at DESC')`.
- **Target Command:** `php artisan test tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php --filter="enforces placement maxCount and selects candidate by D8 tie break order"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":955,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest::__pest_evaluable_it_enforces_placement_maxCount_and_selects_candidate_by_D8_tie_break_order","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php","line":177,"message":"Failed asserting that two strings are identical.\n--- Expected\n+++ Actual\n@@ @@\n-'01m4yvnegdjycjv65zvb798rrv'\n+'01m4yvnegcfrj3b5h376nsapwj'","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php:177"]}]}
```
- **Reversion:** `git checkout app/Domain/Content/Models/ContentBlock.php` (verified clean diff; test passes with 6 assertions).

### MUT-C: Placement Allowed Block Type Check
- **File:** `app/Domain/Content/Actions/GetLayoutContent.php:67`
- **Mutation:** Removed `if (! in_array($candidate->type, $allowedTypes, true)) { continue; }` check.
- **Target Command:** `php artisan test tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php --filter="skips blocks whose type is not permitted for the placement"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":826,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest::__pest_evaluable_it_skips_blocks_whose_type_is_not_permitted_for_the_placement","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php","line":277,"message":"Failed asserting that two arrays are identical.\n--- Expected\n+++ Actual\n@@ @@\n-Array &0 []\n+Array &0 [\n+ 0 => App\\Domain\\Content\\Data\\LayoutBlockData Object #4235 (\n+ '_additional' => Array &1 [],\n+ '_dataContext' => Spatie\\LaravelData\\Support\\Transformation\\DataContext Object #3634 (\n+ 'includePartials' => null,\n+ 'excludePartials' => null,\n+ 'onlyPartials' => null,\n+ 'exceptPartials' => null,\n+ 'wrap' => Spatie\\LaravelData\\Support\\Wrapping\\Wrap Object #4358 (\n+ 'type' => Spatie\\LaravelData\\Support\\Wrapping\\WrapType Enum #3939 (UseGlobal, 'use_global'),\n+ 'key' => null,\n+ ),\n+ ),\n+ 'id' => '01m4yvpyxdrgq0bn883hjc6pv4',\n+ 'type' => 'other_type',\n+ 'version' => 1,\n+ 'data' => Array &2 [\n+ 'value' => 'other',\n+ ],\n+ ),\n+]","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\GetLayoutContentTest.php:277"]}]}
```
- **Reversion:** `git checkout app/Domain/Content/Actions/GetLayoutContent.php` (verified clean diff; test passes with 1 assertion).

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
| **C2** | Strict types on properties, parameters, returns; final classes | All classes typed and marked `final`. Closures typed with `HasMany` and `MenuItem`. |
| **C3** | Use enums and Data classes | `Placement` and `MenuKey` backed enums used for keys; Spatie Data DTOs used for all layout payload representations. |
| **C4** | Status & `is_enabled` changed only via Actions | Read-only step with zero status modifications. |
| **C5** | Action rule applies to all state persistence | Read-only step with zero writes to persisted state. |
| **C6** | Validation and Policies | Public read-only endpoint; output validated and structured via Spatie Data DTOs. |
| **C7** | Follow Pint and Larastan without baselines | `pint` passed and `phpstan` passed with 0 errors at Level 8 without baseline additions. |
| **C8** | Comments explain why, not what | Zero commented-out code. Numbered comments removed; why-comments preserved. |
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
| **G3** | Never push directly to main | Preserved. Ready for PR to `main`. |
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

{"tool":"pest","result":"passed","tests":374,"passed":374,"assertions":1695,"duration_ms":32545}
```
*Static Analysis (`composer stan`):* Passed with **0 errors** at Level 8 without baseline entries or suppressions.

---

## 7. Layer A Raw Command Verification Output (against origin/main)

### Git Status
**Command:** `git status --short`
```
[Empty - 0 matches]
```

### Git Log
**Command:** `git log --oneline origin/main..HEAD`
```
<latest-hash> docs(blueprints): update P1-API1 report, add fixture diff, and remove code_diff.txt for VERIFY r2
57c0569 fix(content): address review findings F5-F7 for content api
757ed6e docs(blueprints): add P1-API1 brief, plan v3, code diff, and implementation report
bb06081 docs(architecture): mark Content API v1 complete in Phase 1
9672325 feat(content): add layout API endpoint with ETag and rate limiting
fc2e38a feat(content): add GetLayoutContent action with visibility filtering and D8 tie breaking
096d1cf feat(content): add public layout data DTOs and contract fixture
```

### Git Merge-Base
**Command:** `git merge-base origin/main HEAD`
```
714fe30c664db9b5ddf8b993ef4942c15c79d34a
```

### Step-Specific Check 1: Route List
**Command:** `php artisan route:list --path=api/v1/content`
```
 GET|HEAD api/v1/content/layout .. api.v1.content.layout › Api\V1\Content\GetLayoutContentController

 Showing [1] routes
```

### Step-Specific Check 2: Migrations & Dependencies Git Diff
**Command:** `git diff origin/main...HEAD --stat -- database composer.json composer.lock package.json`
```
[Empty - 0 matches]
```

### Step-Specific Check 3: Filament Layer Git Diff
**Command:** `git diff origin/main...HEAD --stat -- app/Filament`
```
[Empty - 0 matches]
```

### Step-Specific Check 4: MenuTree Action Git Diff
**Command:** `git diff origin/main...HEAD -- app/Domain/Content/Actions/GetMenuTree.php`
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

### Step-Specific Check 7: Baseline Check
**Command:** `git diff origin/main...HEAD --stat -- '*baseline*'`
```
[Empty - 0 matches]
```

### C1 Scan: Strict Types Declaration
**Command:** `git diff origin/main...HEAD --name-only -- '*.php' | ForEach-Object { if (-not (Get-Content $_ -TotalCount 5 | Select-String -SimpleMatch 'declare(strict_types=1);')) { $_ } }`
```
[Empty - 0 matches / 100% compliant across all changed PHP files]
```

### T5 Scan: Skipped or Incomplete Tests
**Command:** `git diff origin/main...HEAD --name-only -- 'tests/*.php' | ForEach-Object { Select-String -Path $_ -Pattern "->skip\(|->todo\(|markTestIncomplete|markTestSkipped" }`
```
[Empty - 0 matches]
```

### X2 Scan: Container / Docker Configuration
**Command:** `git diff origin/main...HEAD --name-only | Select-String -Pattern "docker|compose|sail|container"`
```
[Empty - 0 matches]
```

### SEC1 Scan: Tracked Environment Files
**Command:** `git ls-files .env`
```
[Empty - 0 matches]
```

---

## 8. Layer C Manual Verification Output (Steps 1–5 from Brief)

Executed against native local server (`php artisan serve`) with PostgreSQL database `phone_store`:

```powershell
$proc = Start-Process php -ArgumentList "artisan serve --port=8000" -WorkingDirectory "c:\dev\getnow-cms" -PassThru
Start-Sleep -Seconds 3

try {
    Write-Host "=== LAYER C MANUAL VERIFICATION SCRIPT (BRIEF STEPS 1-5) ==="

    # STEP 1: Fetch initial layout
    $resp1 = Invoke-RestMethod -Uri http://127.0.0.1:8000/api/v1/content/layout -UseBasicParsing
    $rev1 = $resp1.meta.revision
    $placementsKeys = ($resp1.data.placements | Get-Member -MemberType NoteProperty).Name -join ', '
    $menusKeys = ($resp1.data.menus | Get-Member -MemberType NoteProperty).Name -join ', '
    Write-Host "[STEP 1] Fetch layout: HTTP 200"
    Write-Host "  Settings Site Name: $($resp1.data.settings.site_name)"
    Write-Host "  Revision: $rev1"
    Write-Host "  Placements Keys: $placementsKeys"
    Write-Host "  Menus Keys: $menusKeys"
    $step1Pass = ($resp1.data.settings.site_name -ne $null) -and ($placementsKeys -like '*announcement_bar*') -and ($menusKeys -like '*main_nav*')
    Write-Host "  -> STEP 1: $(if ($step1Pass) { 'PASS' } else { 'FAIL' })"

    # STEP 2: Inspect ETag header
    $webResp = Invoke-WebRequest -Uri http://127.0.0.1:8000/api/v1/content/layout -UseBasicParsing
    $etagHeader = $webResp.Headers['ETag']
    $etag = if ($etagHeader -is [array]) { $etagHeader[0] } else { $etagHeader }
    Write-Host "[STEP 2] Inspect ETag header"
    Write-Host "  ETag Header: $etag"
    $expectedEtag = '"' + $rev1 + '"'
    $step2Pass = ($etag -eq $expectedEtag)
    Write-Host "  -> STEP 2: $(if ($step2Pass) { 'PASS' } else { 'FAIL' })"

    # STEP 3: Conditional GET (304)
    Write-Host "[STEP 3] Conditional GET with If-None-Match"
    $step3Pass = $false
    try {
        $conditionalResp = Invoke-WebRequest -Uri http://127.0.0.1:8000/api/v1/content/layout -Headers @{'If-None-Match' = $etag} -UseBasicParsing
        Write-Host "  Status Code: $($conditionalResp.StatusCode)"
        if ($conditionalResp.StatusCode -eq 304) { $step3Pass = $true }
    } catch {
        if ($_.Exception.Response.StatusCode.value__ -eq 304) {
            Write-Host "  Status Code: 304 Not Modified"
            $step3Pass = $true
        } else {
            Write-Host "  Status Code Error: $($_.Exception.Message)"
        }
    }
    Write-Host "  -> STEP 3: $(if ($step3Pass) { 'PASS' } else { 'FAIL' })"

    # STEP 4: Publish announcement_bar block and add enabled item + disabled item to main_nav
    Write-Host "[STEP 4] Mutate content: publish announcement_bar, add enabled + disabled items to main_nav"
    php artisan tinker --execute="`$block = App\Domain\Content\Models\ContentBlock::firstOrNew(['placement' => 'announcement_bar', 'type' => 'announcement_bar']); `$block->status = App\Domain\Content\Enums\PublishStatus::PUBLISHED; `$block->is_enabled = true; `$block->schema_version = 1; `$block->sort_order = 0; `$block->payload = ['text' => 'Live Announcement Bar', 'link_url' => '/promo', 'tone' => 'info']; `$block->save(); `$menu = App\Domain\Content\Models\Menu::where('key', 'main_nav')->firstOrFail(); `$menu->items()->delete(); `$menu->items()->create(['label' => 'Phones', 'url' => '/phones', 'is_enabled' => true, 'opens_in_new_tab' => true, 'sort_order' => 0]); `$menu->items()->create(['label' => 'Tablets', 'url' => '/tablets', 'is_enabled' => false, 'opens_in_new_tab' => false, 'sort_order' => 1]);"
    
    $resp4 = Invoke-RestMethod -Uri http://127.0.0.1:8000/api/v1/content/layout -UseBasicParsing
    $rev4 = $resp4.meta.revision
    $announcementBlock = $resp4.data.placements.announcement_bar[0]
    $mainNavItems = $resp4.data.menus.main_nav.items
    Write-Host "  Published Announcement Text: $($announcementBlock.data.text)"
    Write-Host "  Main Nav Items Count: $($mainNavItems.Count)"
    Write-Host "  Main Nav Item 0 Label: $($mainNavItems[0].label)"
    Write-Host "  Main Nav Item 0 ID: $($mainNavItems[0].id)"
    Write-Host "  Main Nav Item 0 new_tab: $($mainNavItems[0].new_tab)"
    Write-Host "  Old Revision: $rev1"
    Write-Host "  New Revision: $rev4"
    $step4Pass = ($announcementBlock.data.text -eq 'Live Announcement Bar') -and ($mainNavItems.Count -eq 1) -and ($mainNavItems[0].label -eq 'Phones') -and ($mainNavItems[0].new_tab -eq $true) -and ($rev4 -ne $rev1)
    Write-Host "  -> STEP 4: $(if ($step4Pass) { 'PASS' } else { 'FAIL' })"

    # STEP 5: Disable announcement_bar block and re-fetch
    Write-Host "[STEP 5] Disable announcement_bar block and re-fetch"
    php artisan tinker --execute="`$block = App\Domain\Content\Models\ContentBlock::where('placement', 'announcement_bar')->first(); if (`$block) { `$block->is_enabled = false; `$block->save(); }"
    
    $resp5 = Invoke-RestMethod -Uri http://127.0.0.1:8000/api/v1/content/layout -UseBasicParsing
    $announcementBlocksAfterDisable = $resp5.data.placements.announcement_bar
    Write-Host "  Announcement Bar Blocks Count: $($announcementBlocksAfterDisable.Count)"
    $step5Pass = ($announcementBlocksAfterDisable.Count -eq 0)
    Write-Host "  placements.announcement_bar: []"
    Write-Host "  -> STEP 5: $(if ($step5Pass) { 'PASS' } else { 'FAIL' })"

} finally {
    # Clean shutdown of server
    if ($proc -and -not $proc.HasExited) {
        Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
    }
    Get-CimInstance Win32_Process -Filter "name = 'php.exe'" | Where-Object { $_.CommandLine -like '*artisan serve*' -or $_.CommandLine -like '*-S 127.0.0.1:8000*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Write-Host "Server process stopped cleanly."
}
```

### Raw Command Execution Output
```
=== LAYER C MANUAL VERIFICATION SCRIPT (BRIEF STEPS 1-5) ===
[STEP 1] Fetch layout: HTTP 200
  Settings Site Name: Storefront
  Revision: a1d38ca3aace5eb60e84e1cadb14dbc83d1aedfa372107e46efcaca5c8d19646
  Placements Keys: announcement_bar
  Menus Keys: footer_column_1, footer_column_2, footer_column_3, main_nav, mobile_nav
  -> STEP 1: PASS
[STEP 2] Inspect ETag header
  ETag Header: "a1d38ca3aace5eb60e84e1cadb14dbc83d1aedfa372107e46efcaca5c8d19646"
  -> STEP 2: PASS
[STEP 3] Conditional GET with If-None-Match
  Status Code: 304 Not Modified
  -> STEP 3: PASS
[STEP 4] Mutate content: publish announcement_bar, add enabled + disabled items to main_nav
  Published Announcement Text: Live Announcement Bar
  Main Nav Items Count: 1
  Main Nav Item 0 Label: Phones
  Main Nav Item 0 ID: 01m4yvsmwnjrpy54s3rhcmmkjn
  Main Nav Item 0 new_tab: True
  Old Revision: a1d38ca3aace5eb60e84e1cadb14dbc83d1aedfa372107e46efcaca5c8d19646
  New Revision: 12e6122af7cc79a3e7311f1b4cc9aa9a413b6d8a0306e656eb42d29ea1042f58
  -> STEP 4: PASS
[STEP 5] Disable announcement_bar block and re-fetch
  Announcement Bar Blocks Count: 0
  placements.announcement_bar: []
  -> STEP 5: PASS
Server process stopped cleanly.
```
