# P1-FIX1 IMPLEMENTATION REPORT

**Step ID:** P1-FIX1  
**Status:** Complete  
**Base Commit:** `da51c3f9de50be4d3aae51e996fd436ee2708576`  
**Head Commit:** `dca6e451b6a7a03073d82089eb170d18bc3306c5`  
**Commits:**
1. `8c94524` fix(content): evaluate dynamic lifecycle before serving status memoization
2. `1cabb2e` chore(env): document admin timezone in env and characterize schedule form timezone labels
3. `f659d87` docs(adr): update ADR 0002 for dynamic lifecycle serving status resolution
4. `9a17abc` fix(filament): configure history replacement for table pagination
5. `dca6e45` docs(architecture): document admin panel browser history convention in section 8

---

## 1. Acceptance Criteria Verification

| AC | Requirement | Implementation (File:Line) | Verifying Test | Result |
|---|---|---|---|---|
| **AC-1** | Published + enabled, `starts_at = now+1h`: not live in `GetBlockServingStatus`, absent from servable layout. | `GetBlockServingStatus.php:22-24` | `ServingWindowTest.php:19` (`it evaluates published scheduled block as not live before starts_at and live at starts_at without database write`) | **PASSED** |
| **AC-2** | Same block at `travelTo(starts_at)` is live; at `starts_at - 1s` not live; `updated_at` unchanged, 0 activity log. | `GetBlockServingStatus.php:22-24` | `ServingWindowTest.php:19` | **PASSED** |
| **AC-3** | Resolver called before `starts_at` (cache primed), then time passes: block is live with no cache flush on array cache store. | `GetBlockServingStatus.php:22-24` | `ServingWindowTest.php:50` (`it evaluates serving status correctly across time without cache flush under array cache store`) | **PASSED** |
| **AC-4** | Value entered in form as non-UTC timezone stored as converted UTC instant and compared against UTC `now`. | `ContentBlockForm.php:110-118` | `ContentBlockServingWindowTest.php:51` (`it persists entered schedule converted to UTC under non-UTC admin timezone`) | **PASSED** |
| **AC-5** | List and View badges show Live after `starts_at` with zero write. | `ContentBlockTable.php:106-114`, `ContentBlockMetadataSchema.php:75-97` | `ContentBlockServingWindowTest.php:77` (`it renders Live badge on List and View pages when window opens without database write`) | **PASSED** |
| **AC-6** | Past `ends_at` is still not live (regression). | `ContentBlock.php:113-115` | `ServingWindowTest.php:71` (`it evaluates block as not live once ends_at timestamp has passed`) | **PASSED** |
| **AC-7** | Table pagination on `ListContentBlocks` uses history replacement. | `ListContentBlocks.php:19-32` | `ContentBlockHistoryTest.php:30` (`it characterizes tableSearch, tableSort, and tableFilters properties as having history false`), `line 44` (`it configures table pagination on ListContentBlocks with history false`) | **PASSED** |
| **AC-8** | Create/Edit save and transitions redirect to defined targets. | `CreateContentBlock.php:42-45`, `EditContentBlock.php:71`, `ContentBlockTransitionActions.php:64-154` | `ContentBlockHistoryTest.php:63` (`it redirects Create form save to edit page using assertRedirect`), `line 86` (`it retains Edit form save on current page with assertNoRedirect`), `lines 110-216` (row and header actions assertNoRedirect) | **PASSED** |
| **AC-9** | Mount failure (Edit/View) redirects to index. | `EditContentBlock.php:62`, `ViewContentBlock.php:65` | `ContentBlockHistoryTest.php:218` (`it redirects to index on View or Edit mount failure`) | **PASSED** |
| **AC-10** | Layer C: Back lands on expected page for every line of history script; stop if URLs repeat consecutively. | Manual verification script (§10.3) | Layer C browser verification script executed natively; 0 consecutive duplicate URLs | **PASSED** |
| **AC-11** | Field label reflects configured admin timezone. | `ContentBlockForm.php:27, 110-118` | `ContentBlockServingWindowTest.php:28` (`it renders field label displaying resolved admin timezone under non-UTC configuration`) | **PASSED** |

---

## 2. Review Conditions Verification (C1 - C6)

### Condition C1: `.env.example` and CI Workflow State
Current state of `ADMIN_TIMEZONE` in `.env.example`:
```
VITE_APP_NAME="${APP_NAME}"
ADMIN_TIMEZONE=Asia/Colombo
```
Workflow `.github/workflows/tests.yml` copies `.env.example` at lines 53-54:
```yaml
      - name: Prepare Environment
        run: cp .env.example .env
```
Because the GitHub Actions workflow copies `.env.example`, the repository example becomes the CI environment configuration. No duplicate `ADMIN_TIMEZONE` lines were added; the existing line was updated in place.

### Condition C2: Full Suite Passing with `ADMIN_TIMEZONE=Asia/Colombo`
Running `composer check` with `ADMIN_TIMEZONE=Asia/Colombo` set in `.env`:
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":316,"passed":316,"assertions":1237,"duration_ms":29836}
```
All existing tests including `ContentBlockStateActionsTest` and `ContentBlockEditTest` passed without modification. Pinning in `phpunit.xml` was not required.

### Condition C3: Part B Test 2 Non-Vacuous Pre-Fix Failure
Mounted `ListContentBlocks` through `Livewire::test(ListContentBlocks::class)` with 15 content blocks in database (exceeding table per-page size of 10).
Raw inspection of `$instance->queryStringHandlesPagination()` pre-fix:
```
array:2 [
  "paginators" => array:1 [
    "page" => 1
  ]
  "queryStrings" => array:1 [
    "paginators.page" => array:3 [
      "history" => true
      "as" => "page"
      "keep" => false
    ]
  ]
]
```
Raw failure output pre-fix:
```
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":3,"duration_ms":1211,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockHistoryTest::__pest_evaluable_it_configures_table_pagination_on_ListContentBlocks_with_history_false","file":"\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockHistoryTest.php","line":58,"message":"Failed asserting that true is false.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockHistoryTest.php:58"]}]}
```

### Condition C4: Layer C Local Environment & Pagination
- Local `.env` has `ADMIN_TIMEZONE=Asia/Colombo` (uncommitted, SEC1).
- Config cache cleared via `php artisan config:clear`.
- Field label renders `Starts at (Asia/Colombo)` (verified by `ContentBlockServingWindowTest.php:28`).
- Confirmed table per-page default from `ContentBlockTable.php:61`: `->paginated([10, 25, 50])` (default is 10). With 21 rows in database, exactly 3 pages are produced.
- Line 10 expected result verified: pressing Back after traversing table pagination lands directly on the page visited before List, without stepping backward through page 2 and page 1.

### Condition C5: Commit Messages
- Commit 2 scoped to chore: `chore(env): document admin timezone in env and characterize schedule form timezone labels`
- Commit 4 scoped to table pagination: `fix(filament): configure history replacement for table pagination`

### Condition C6: PR Description
PR description explicitly documents that bug 1 was an input timezone interpretation mismatch resolved by local configuration (`ADMIN_TIMEZONE=Asia/Colombo`), while the cache change resolves a separate in-process lifecycle caching defect found during diagnosis. The `.env` edit is an uncommitted local step.

---

## 3. Raw Git Diff Status (`da51c3f...HEAD`)

**Command:** `git diff --name-status da51c3f...HEAD`
```
M	.env.example
M	ARCHITECTURE.md
M	app/Domain/Content/Actions/GetBlockServingStatus.php
M	app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php
docs/adr/0002-content-block-serving-health.md
A	tests/Feature/Admin/ContentBlock/ContentBlockHistoryTest.php
A	tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php
M	tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php
A	tests/Feature/Domain/Content/ServingWindowTest.php
```
*Total codebase files:* Exactly **9 files** (below the 10-file ceiling).  
*Blueprint documentation files:* Blueprint docs (`plan.md`, `report.md`) do not count against the 10-file codebase manifest.

**Command:** `git diff da51c3f...HEAD --stat`
```
 .env.example                                       |   2 +-
 ARCHITECTURE.md                                    |   1 +
 .../Content/Actions/GetBlockServingStatus.php      |  27 +--
 .../Pages/ListContentBlocks.php                    |  18 ++
 docs/adr/0002-content-block-serving-health.md      |   4 +-
 .../Admin/ContentBlock/ContentBlockHistoryTest.php | 234 +++++++++++++++++++++
 .../ContentBlock/ContentBlockServingWindowTest.php |  98 +++++++++
 .../Content/Actions/GetBlockServingStatusTest.php  |  13 ++
 tests/Feature/Domain/Content/ServingWindowTest.php |  86 ++++++++
 9 files changed, 463 insertions(+), 20 deletions(-)
```

### File Reconciliation Table

| Planned File (from Plan Sec 4 & 12) | Planned Action | Actual Status | Notes |
|---|---|---|---|
| `app/Domain/Content/Actions/GetBlockServingStatus.php` | Modify | Done | Evaluates `isLive()` outside array cache; memoizes servable health for live records only |
| `.env.example` | Modify | Done | Documents `ADMIN_TIMEZONE=Asia/Colombo` |
| `docs/adr/0002-content-block-serving-health.md` | Modify | Done | Clarifies dynamic lifecycle serving status resolution in Section 3 |
| `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php` | Modify | Done | Updated cache tests and added dynamic evaluation test |
| `tests/Feature/Domain/Content/ServingWindowTest.php` | Add | Done | Domain feature tests for AC-1, AC-2, AC-3, AC-6 |
| `tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php` | Add | Done | Admin feature tests for AC-4, AC-5, AC-11, M3, M4 |
| `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php` | Modify | Done | Overrides `queryStringHandlesPagination` with `history: false` |
| `ARCHITECTURE.md` | Modify | Done | Section 8 updated with panel browser history convention |
| `tests/Feature/Admin/ContentBlock/ContentBlockHistoryTest.php` | Add | Done | Feature tests for AC-7, AC-8, AC-9 |

---

## 4. Pre-Fix Test Failure Outputs

### Part A Pre-Fix Failure Output
**Command:** `php artisan test tests/Feature/Domain/Content/ServingWindowTest.php`
```
{"tool":"pest","result":"failed","tests":3,"passed":0,"assertions":2,"duration_ms":724,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\ServingWindowTest::__pest_evaluable_it_evaluates_published_scheduled_block_as_not_live_before_starts__at_and_live_at_starts__at_without_database_write","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\ServingWindowTest.php","line":39,"message":"Failed asserting that two variables reference the same object.\n--- Expected\n+++ Actual\n@@ @@\n-App\\Domain\\Content\\Enums\\ServingStatus Enum #3814 (Live, 'live')\n+App\\Domain\\Content\\Enums\\ServingStatus Enum #3815 (NotLive, 'not_live')","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\ServingWindowTest.php:39"]}]}
```

### Part B Pre-Fix Failure Output
**Command:** `php artisan test tests/Feature/Admin/ContentBlock/ContentBlockHistoryTest.php --filter=pagination`
```
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":3,"duration_ms":1211,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockHistoryTest::__pest_evaluable_it_configures_table_pagination_on_ListContentBlocks_with_history_false","file":"\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockHistoryTest.php","line":58,"message":"Failed asserting that true is false.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockHistoryTest.php:58"]}]}
```

---

## 5. Layer B Mutation Spot-Checks

### M1: Boundary Comparison Mutation
- **Change:** Changed `$time->lt($this->starts_at)` to `$time->lte($this->starts_at)` in `app/Domain/Content/Models/ContentBlock.php:109`.
- **Target Test:** `php artisan test tests/Feature/Domain/Content/ServingWindowTest.php --filter="evaluates published scheduled block as not live before starts_at and live at starts_at without database write"`
- **Raw Failure Output:**
```
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":4,"duration_ms":748,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\ServingWindowTest::__pest_evaluable_it_evaluates_published_scheduled_block_as_not_live_before_starts__at_and_live_at_starts__at_without_database_write","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\ServingWindowTest.php","line":39,"message":"Failed asserting that two variables reference the same object.\n--- Expected\n+++ Actual\n@@ @@\n-App\\Domain\\Content\\Enums\\ServingStatus Enum #3814 (Live, 'live')\n+App\\Domain\\Content\\Enums\\ServingStatus Enum #3815 (NotLive, 'not_live')","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\ServingWindowTest.php:39"]}]}
```
- **Reversion:** `git checkout -- app/Domain/Content/Models/ContentBlock.php` (verified clean diff).

### M2: Stale Cache Key / TTL Mutation
- **Change:** Moved `$block->isLive()` inside `Cache::store('array')->rememberForever` in `app/Domain/Content/Actions/GetBlockServingStatus.php:37`.
- **Target Test:** `php artisan test tests/Feature/Domain/Content/ServingWindowTest.php --filter="evaluates serving status correctly across time without cache flush under array cache store"`
- **Raw Failure Output:**
```
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":2,"duration_ms":772,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\ServingWindowTest::__pest_evaluable_it_evaluates_serving_status_correctly_across_time_without_cache_flush_under_array_cache_store","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\ServingWindowTest.php","line":66,"message":"Failed asserting that two variables reference the same object.\n--- Expected\n+++ Actual\n@@ @@\n-App\\Domain\\Content\\Enums\\ServingStatus Enum #3814 (Live, 'live')\n+App\\Domain\\Content\\Enums\\ServingStatus Enum #3851 (NotLive, 'not_live')","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\ServingWindowTest.php:66"]}]}
```
- **Reversion:** `git checkout -- app/Domain/Content/Actions/GetBlockServingStatus.php` (verified clean diff).

### M3: Timezone Conversion Removal Mutation
- **Change:** Removed `->timezone($timezone)` from `starts_at` in `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php:112`.
- **Target Test:** `php artisan test tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php --filter="persists entered schedule converted to UTC under non-UTC admin timezone"`
- **Raw Failure Output:**
```
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":3,"duration_ms":1122,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockServingWindowTest::__pest_evaluable_it_persists_entered_schedule_converted_to_UTC_under_non_UTC_admin_timezone","file":"\\dev\\getnow-cms\\vendor\\livewire\\livewire\\src\\Features\\SupportValidation\\TestsValidation.php","line":127,"message":"Component has errors: \"data.ends_at\" => [\"The ends at date must be after the starts at date.\"]\nFailed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\vendor\\livewire\\livewire\\src\\Features\\SupportValidation\\TestsValidation.php:127","C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockServingWindowTest.php:68"]}]}
```
- **Reversion:** `git checkout -- app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` (verified clean diff).

### M4: Timezone Label Omission Mutation
- **Change:** Changed `->label("Starts at ({$timezone})")` to `->label('Starts at')` in `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php:111`.
- **Target Test:** `php artisan test tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php --filter="renders field label displaying resolved admin timezone under non-UTC configuration"`
- **Raw Failure Output:**
```
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":1089,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockServingWindowTest::__pest_evaluable_it_renders_field_label_displaying_resolved_admin_timezone_under_non_UTC_configuration","file":"\\dev\\getnow-cms\\vendor\\livewire\\livewire\\src\\Features\\SupportTesting\\MakesAssertions.php","line":47,"message":"Failed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\vendor\\livewire\\livewire\\src\\Features\\SupportTesting\\MakesAssertions.php:47","C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockServingWindowTest.php:37"]}]}
```
- **Reversion:** `git checkout -- app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` (verified clean diff).

---

## 6. Rule Register Self-Audit

| ID | Description | Compliance Evidence |
|---|---|---|
| **E1** | Windows/PowerShell native execution | All commands run one at a time via native PowerShell. Zero bash syntax, zero `&&`. |
| **S1** | Business logic in Domain Actions | Serving status logic remains inside invokable `GetBlockServingStatus` Domain Action. Zero business logic in Filament. |
| **S2** | Thin pages | `ListContentBlocks` overrides `queryStringHandlesPagination` cleanly; remains thin delegate. |
| **S3** | Domain never imports Filament or Http | Verified via S3 scan (0 matches) and `DomainArchTest` passing. |
| **S4** | Filament never calls `Model::create/update/delete` directly | Verified via S4 scan (0 matches). |
| **S5** | Block schemas in `app/Filament/Blocks/` | Untouched; existing block form registries preserved. |
| **S6** | Panel code under `app/Filament/Admin/` | All panel edits strictly under `app/Filament/Admin/Resources/ContentBlockResource/`. |
| **S7** | Put code in correct place | All files placed in designated domain, resource, and test folders. |
| **C1** | `declare(strict_types=1);` in every file | Declared at line 1 of every file. Verified via C1 scan (0 violations). |
| **C2** | Strict types on properties, parameters, returns; final classes | All classes typed and marked `final`. |
| **C3** | Use enums and Data classes | `ServingStatus`, `PublishStatus`, `ServingHealthOutcome` used. |
| **C4** | Status & `is_enabled` changed only via Actions | Time-based window transitions involve zero database writes. Status remains `published`. |
| **C5** | Action rule applies to all state persistence | Zero settings mutations. |
| **C6** | Validation and Policies | Policy authorization maintained. |
| **C7** | Follow Pint and Larastan without baselines | `pint` passed and `phpstan` passed with 0 errors. Zero baseline entries added. |
| **C8** | Comments explain why, not what | Zero commented-out code. |
| **K1** | Block type keys permanent | Key `'announcement_bar'` preserved. |
| **K2** | Payload schema versioning | Maintained. |
| **K3** | No raw HTML/CSS/JS fields | Validated text and enums only. |
| **K4** | Validated links (`/` or `https://`) | Maintained. |
| **K5** | Placements & types defined in code | Maintained. |
| **K6** | Explicit block registration | Maintained. |
| **K7** | Server-side visibility | Decided server-side via `isLive()` and `scopeVisible`. |
| **K8** | Content API contract | Contract untouched in this fix. |
| **K9** | Checklist in Architecture sec 6 followed | Respected. |
| **DB1** | PostgreSQL only | Tests run against Postgres `phone_store_test`. |
| **DB2** | Append-only migrations | Zero migrations modified or added. |
| **DB3** | No destructive DB changes | Zero DB schema changes. |
| **DB4** | DB constraints respected | Model and schema constraints honored. |
| **DB5** | Expose `public_id` externally | Routes and URLs use ULID. |
| **DB6** | Idempotent seeders | No seeder changes. |
| **SEC1** | No secrets in repo | Verified clean; `.env` is not tracked. |
| **SEC2** | Authorization never bypassed | Policies enforced across all actions. |
| **SEC3** | Do not log sensitive data | Zero sensitive data logged. |
| **SEC4** | Admin access checked server-side | Server-side authorization preserved. |
| **SEC5** | Sensitive state logged | Zero activity logged for read-only serving evaluations. |
| **T1** | Pest tests for Actions & Filament | 316 Pest tests passing. |
| **T2** | Resource smoke tests | Resource smoke tests pass. |
| **T3** | Block type tests | Preserved. |
| **T4** | Factories used | Eloquent factories used across all tests. |
| **T5** | No skipped or incomplete tests | 316 tests passed, 0 skipped, 0 incomplete in PR files. |
| **G1** | Branch from main | Branch `fix/serving-window-and-history-stacking`. |
| **G2** | Conventional Commits | All 5 commits follow Conventional Commits per C5. |
| **G3** | Never push directly to main | Preserved. |
| **G4** | PR descriptions complete | Ready for PR creation per C6. |
| **X1** | Do not add dependencies | Zero dependencies added (`composer.json` untouched). |
| **X2** | Do not introduce Docker | Native Windows environment preserved. |
| **X3** | Do not add deployment pipelines | Preserved. |
| **X4** | Stay within phase scope | Only Phase 1 fixes touched. |
| **X5** | Do not modify storefront repo | Storefront untouched. |
| **X6** | `composer check` passes | 316 tests passed (1237 assertions), Pint passed, PHPStan passed (0 errors). |
| **X7** | Docs updated where relevant | `ARCHITECTURE.md`, `ADR 0002`, and `.env.example` updated. |

---

## 7. Full Quality Suite Output (`composer check`)

**Command:** `composer check`
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":316,"passed":316,"assertions":1237,"duration_ms":29836}
```
*Static Analysis (`composer stan`):* Passed with **0 errors** at Level 8 without baseline entries or suppressions.

---

## 8. Layer A Raw Command Verification Output

### Git Status
**Command:** `git status --short`
```
?? docs/blueprints/P1-FIX1/
```

### Git Log
**Command:** `git log -n 5 --oneline`
```
dca6e45 docs(architecture): document admin panel browser history convention in section 8
9a17abc fix(filament): configure history replacement for table pagination
f659d87 docs(adr): update ADR 0002 for dynamic lifecycle serving status resolution
1cabb2e chore(env): document admin timezone in env and characterize schedule form timezone labels
8c94524 fix(content): evaluate dynamic lifecycle before serving status memoization
```

### Git Merge-Base
**Command:** `git merge-base origin/main HEAD`
```
a632274c69f9627c5f18f3b37a1bbc9d0ab2e4c2
```

### Baseline Diff
**Command:** `git diff da51c3f...HEAD --stat -- '*baseline*'`
```
[Empty - 0 matches]
```

### S4 Scan (Forbidden Filament Eloquent Writes)
**Command:** `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '::create(','->update(','->delete(','->save(','->forceDelete('`
```
[Empty - 0 matches]
```

### S3 Scan (Domain Layer Isolation from Filament and Http)
**Command:** `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -Pattern "App\\Filament|App\\Http"`
```
[Empty - 0 matches]
```

### C1 Scan (Strict Types Declaration)
**Command:** `git diff da51c3f...HEAD --name-only -- '*.php' | ForEach-Object { if (-not (Get-Content $_ -TotalCount 5 | Select-String -SimpleMatch 'declare(strict_types=1);')) { $_ } }`
```
[Empty - 0 matches / 100% compliant across all changed PHP files]
```

### T5 Scan (Skipped or Incomplete Tests)
**Command:** `git diff da51c3f...HEAD --name-only -- 'tests/*.php' | ForEach-Object { Select-String -Path $_ -Pattern "->skip\(|->todo\(|markTestIncomplete|markTestSkipped" }`
```
[Empty - 0 matches]
```

### X2 Scan (No Docker / Container Introduced)
**Command:** `git diff da51c3f...HEAD --name-only | Select-String -Pattern "docker|compose|sail|container"`
```
[Empty - 0 matches]
```

### SEC1 Scan (Tracked Environment Files)
**Command:** `git ls-files .env`
```
[Empty - 0 matches]
```
