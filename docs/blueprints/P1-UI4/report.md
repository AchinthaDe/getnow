# P1-UI4 IMPLEMENTATION REPORT

**Step ID:** P1-UI4  
**Status:** Complete  
**Base Commit:** `a632274945d8b28cf33fb5e527d739818ea1b08b`  
**Head Commit:** `89ad7669cbbd802823860a9fd265e4941db0f42d`  
**Commits:**
1. `f6b9782` feat(content): add publish, archive, enable and disable policy abilities
2. `b893057` feat(testing): add assertViewPageRenders method to FilamentSmokeTest
3. `a278f4a` feat(filament): add content block metadata schema, view page and resource view route
4. `b3f9b2e` feat(filament): add shared content block transition actions and wire to view page
5. `a7ed85c` feat(filament): add view row action, grouped transition actions and row click to content block table
6. `4f3a0a5` test(filament): add state transition feature tests
7. `89ad766` docs(architecture): tick UI step 4 and document resource actions subfolder

---

## 1. Acceptance Criteria Verification

| AC | Requirement | Implementation (File:Line) | Verifying Test | Result |
|---|---|---|---|---|
| **AC-1** | Registers `index`, `create`, `edit`, `view`. Route key is `public_id`, numeric returns 404. UI3 assertions updated. Edit page header empty. | `ContentBlockResource.php:68-75` | `ContentBlockViewTest.php:40` (`it resolves view route with public_id and returns 404 for numeric id`), `ContentBlockEditTest.php:322` (`it exposes no header actions on edit page and registers index, create, edit and view routes`), `ContentBlockCreateTest.php:243` | **PASSED** |
| **AC-2** | View page renders for platform admin across draft, published, and archived blocks. Shows metadata, serving status with reason, schedule in admin timezone. | `ViewContentBlock.php:66-83`, `ContentBlockMetadataSchema.php:22-149` | `ContentBlockViewTest.php:57` (`it renders all metadata fields on view page across draft published and archived blocks`) | **PASSED** |
| **AC-3** | Payload rendered read-only via registered schema. No inputs editable. Link is plain text, not anchor. Forbidden scan passes. | `ViewContentBlock.php:73-82`, `BlockFormRegistry.php` | `ContentBlockViewTest.php:97` (`it renders announcement bar payload read only with plain text link`) | **PASSED** |
| **AC-4** | Broken payload view attempt gives danger notification and index redirect. Zero 500 error. No exception leakage. | `ViewContentBlock.php:43-64` | `ContentBlockViewTest.php:127` (`it redirects to index with danger notification when stored payload fails hydration`) | **PASSED** |
| **AC-5** | `view` ability allowed for platform admin (including archived), denied for non-admin (403). | `ContentBlockPolicy.php:24-27`, `ViewContentBlock.php` | `ContentBlockPolicyTest.php:28` (`it allows admin to view archived blocks and denies non admin`), `ContentBlockViewTest.php:148` (`it forbids non admin from viewing block`) | **PASSED** |
| **AC-6** | List table has View row action for every status. Smoke test loads View page. Row click opens View (OD-4). | `ContentBlockTable.php:142,152`, `FilamentSmokeTest.php:49-69` | `ContentBlockViewTest.php:27` (`ContentBlockViewSmokeTest::test_renders_view_page_for_platform_admin_via_smoke_test`), `ListContentBlocksTableTest.php:325` (`it configures row click url to view page`), `line 340` (`it renders view action for draft published and archived rows`) | **PASSED** |
| **AC-7** | Abilities `publish`, `archive`, `enable`, `disable` on `ContentBlockPolicy`: true for platform admin, false for non-admin. Zero state logic. | `ContentBlockPolicy.php:77-95` | `ContentBlockPolicyTest.php:84` (`it allows admin to transition blocks and denies non admin with zero state logic`) | **PASSED** |
| **AC-8** | Visible actions on View header and table row match Section 7.1 matrix. Hidden from non-admin. No visible action throws IllegalStateTransitionException (m2). | `ContentBlockTransitionActions.php:75,99,123,143`, `ContentBlockTable.php:144-150` | `ContentBlockStateActionsTest.php:78` (`it renders visible actions matching exact matrix across all statuses and enabled states`), `line 191` (`it forbids non-admin from list and view routes`) | **PASSED** |
| **AC-9** | Publish transitions draft block, writes activity entry with causer, shows success notification, asks confirmation. Page updates without reload (m3). | `ContentBlockTransitionActions.php:64-86`, `ContentBlockTable.php:145` | `ContentBlockStateActionsTest.php:208` (`it publishes draft block from view header and table row with confirmation modal and activity log`) | **PASSED** |
| **AC-10** | Publish and Enable on unservable payload leave state unchanged, write zero activity log, show danger notification with fixed copy. | `ContentBlockTransitionActions.php:37-45` | `ContentBlockStateActionsTest.php:267` (`it rejects publish and enable on unservable block payload without writing activity log`) | **PASSED** |
| **AC-11** | Archive calls Action once on both surfaces, sets archived and disabled, writes activity log with causer. Actions update to archived set. | `ContentBlockTransitionActions.php:88-110`, `ContentBlockTable.php:148` | `ContentBlockStateActionsTest.php:298` (`it archives block from view header and table row and updates visible actions`) | **PASSED** |
| **AC-12** | Enable and Disable flip `is_enabled`, write activity log. Disable and Archive succeed for broken payload from table row. | `ContentBlockTransitionActions.php:112-154`, `ContentBlockTable.php:146-147` | `ContentBlockStateActionsTest.php:381` (`it enables and disables published block from view header`), `line 435` (`it archives and disables broken payload block from table row without hydration`) | **PASSED** |
| **AC-13** | Stale state transition caught (`IllegalStateTransitionException`), shows danger notification with fixed copy, zero change, zero log, refreshes page. | `ContentBlockTransitionActions.php:46-54` | `ContentBlockStateActionsTest.php:469` (`it handles stale state transition gracefully with danger notification and record refresh`), `line 484` (`it probes modal race condition when record archived concurrently`), `line 518` (`it probes tab B disable click after tab A archives block`), `line 550` (`it succeeds idempotently without duplicate activity log when concurrent transition occurs`), `line 580` (`it throws AssertionFailedError when calling hidden action directly`) | **PARTIAL (ACCEPTED DEVIATION)** |

---

## 2. Raw Git Diff Status (`a632274...HEAD`)

**Command:** `git diff --name-status a632274...HEAD`
```
M	ARCHITECTURE.md
M	app/Domain/Content/Policies/ContentBlockPolicy.php
M	app/Filament/Admin/Resources/ContentBlockResource.php
A	app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php
A	app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php
A	app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockMetadataSchema.php
M	app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php
M	tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php
M	tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php
A	tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php
A	tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php
M	tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php
M	tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php
M	tests/Support/FilamentSmokeTest.php
```
*Total:* Exactly **14 files** (below the 15-file ceiling).

### File Reconciliation Table

| Planned File (from Plan Sec 4 & 12) | Planned Action | Actual Status | Notes |
|---|---|---|---|
| `app/Domain/Content/Policies/ContentBlockPolicy.php` | Modify | Done | Added `publish`, `archive`, `enable`, `disable` abilities |
| `tests/Support/FilamentSmokeTest.php` | Modify | Done | Added `assertViewPageRenders` helper method |
| `app/Filament/Admin/Resources/ContentBlockResource.php` | Modify | Done | Registered `view` route in `getPages()` |
| `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockMetadataSchema.php` | Add | Done | Read-only metadata schema with admin timezone & serving status |
| `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php` | Add | Done | View record page with header actions & payload hydration error handling |
| `tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php` | Modify | Done | Updated route-set assertion to include `view` (B1) |
| `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` | Modify | Done | Updated route-set assertion to include `view` (B1) |
| `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php` | Add | Done | View page render, smoke, hydration error, authorization tests |
| `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php` | Add | Done | Shared lifecycle transition actions (Publish, Archive, Enable, Disable) |
| `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` | Modify | Done | Added `ViewAction`, grouped transition actions, and row click `recordUrl` |
| `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php` | Modify | Done | Added row click, view action, archived edit hidden, reactive transition tests |
| `tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php` | Add | Done | State actions matrix, AC-9 to AC-13, M6 parity, A1 authorization tests |
| `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php` | Modify | Done | Policy unit tests for transition abilities across admin and non-admin |
| `ARCHITECTURE.md` | Modify | Done | Ticked UI Step 4, updated tree annotation, added actions folder convention |

*Unplanned Files:* 0  
*Not Done Files:* 0  

---

## 3. Scope & Purity Verifications

### Database & Composer Cleanliness
**Command:** `git diff a632274...HEAD --stat -- database composer.json composer.lock`
```
[Empty - 0 files touched]
```

### Domain Layer Isolation
**Command:** `git diff a632274...HEAD --stat -- app/Domain`
```
 app/Domain/Content/Policies/ContentBlockPolicy.php | 20 ++++++++++++++++++++
 1 file changed, 20 insertions(+)
```
Strictly 1 file modified under `app/Domain`, preserving domain layer isolation.

### Route Registrations
**Command:** `php artisan route:list --path=admin/content-blocks`
```
 GET|HEAD admin/content-blocks filament.admin.resources.content-blocks.index › App\Filament\Admin\Resources\Co…
 GET|HEAD admin/content-blocks/create filament.admin.resources.content-blocks.create › App\Filament\Admin\Reso…
 GET|HEAD admin/content-blocks/{record} filament.admin.resources.content-blocks.view › App\Filament\Admin\Reso…
 GET|HEAD admin/content-blocks/{record}/edit filament.admin.resources.content-blocks.edit › App\Filament\Admin…

 Showing [4] routes
```

### Forbidden Component Scan
**Command:** `Get-ChildItem -Path app/Filament -Recurse -Filter "*.php" | Select-String -Pattern "RichEditor|MarkdownEditor|CodeEditor|KeyValue|->url\(|DeleteAction|ForceDeleteAction|RestoreAction"`
```
[Empty - 0 matches]
```

### Notification Copy Scan
**Command:** `Get-ChildItem -Path app/Filament -Recurse -Filter "*.php" | Select-String -Pattern "->getMessage\(\)"`
```
[Empty - 0 matches]
```

### ARCHITECTURE.md Diff
**Command:** `git diff a632274...HEAD -- ARCHITECTURE.md`
```diff
diff --git a/ARCHITECTURE.md b/ARCHITECTURE.md
index aed00af..feedd9d 100644
--- a/ARCHITECTURE.md
+++ b/ARCHITECTURE.md
@@ -20,7 +20,7 @@ This document is the source of truth for structure and boundaries. If code and t
 - [x] UI Step 1 (prerequisites)
 - [x] UI Step 2 (policy, timezone config, read-only list + badges)
 - [x] UI Step 3 (BlockFormRegistry, Create/Edit forms)
-- [ ] UI Step 4 (View page, header/row actions)
+- [x] UI Step 4 (View page, header/row actions)
 - [ ] Menus
 - [ ] Content API v1
 - [ ] Seed data
@@ -79,7 +79,7 @@ app/
 ├── Filament/
 │   ├── Blocks/                 # BlockFormRegistry, plus form schemas per block type
 │   └── Admin/                  # panel `admin`
-│       ├── Resources/          # eg: Resources/ContentBlockResource/{Pages,Schemas,Tables}
+│       ├── Resources/          # eg: Resources/ContentBlockResource/{Actions,Pages,Schemas,Tables}
 │       ├── Pages/              # settings pages, custom pages
 │       └── Widgets/
 ├── Http/
@@ -213,6 +213,7 @@ Contract rules:
 ## 8. Filament conventions
 
 - Panel `admin` at `/admin`. Resources are grouped under `app/Filament/Admin/Resources/<Model>Resource/`.
+- Reusable resource actions (such as lifecycle transitions shared between page headers and table rows) live in `app/Filament/Admin/Resources/<Model>Resource/Actions/`.
 - Form and table definitions live in their own schema classes. Block form schemas live in `app/Filament/Blocks/` so a future retailer panel can reuse them.
 - Create, update, delete and bulk operations call Actions. Status is never a free form field. Use dedicated actions such as Publish, Archive, Enable and Disable.
 - Authorization uses Policies. Content policies extend `BaseContentPolicy`. Phase 1 admin access is a single `is_platform_admin` flag checked in `canAccessPanel`. Roles and permissions arrive with the retailer panel.
```

---

## 4. Review Amendments Tracking & Evidence

### A1: Authorization Enforced at Execution
- `executeTransition()` receives `string $ability` and enforces `abort_unless($authUser->can($ability, $record), 403)` after `User` type narrowing and prior to domain Action execution.
- Added test (1): `it('hides all transition actions from non-admins')` verifies that all four actions built with `->record($block)` report `isHidden() = true`.
- Added test (2): `it('aborts with 403 when non-admin executes transition action directly')` executes `$action->record($block)->call()` as non-admin, asserting that `HttpException` 403 is thrown, state is unchanged (`DRAFT`), and 0 activity rows are written.
- Added MUT-4 spot-check proving that removing `abort_unless` causes test (2) to fail.

### A2: Serving Status Entry Formulation
- In `ContentBlockMetadataSchema.php`, `serving_status` entry computes the final display string in `state()` via `GetBlockServingStatus` once:
  `$result->status->label() . ($result->healthOutcome !== null && $result->healthOutcome !== ServingHealthOutcome::Servable ? ' (' . self::humanServingReason($result->healthOutcome) . ')' : '')`.
- Badge color is resolved via `color(fn (?ContentBlock $record): string => $record === null ? 'gray' : ContentBlockTable::servingStatusColor(app(GetBlockServingStatus::class)($record)->status))`.
- Avoids DTO injection into `formatStateUsing($state)` and leverages array caching inside `GetBlockServingStatus` ($log = false).
- Verified: reason text is formatted by `humanServingReason` when non-servable; test `it renders all metadata fields on view page across draft published and archived blocks` in `ContentBlockViewTest` verifies status, serving status, and reason rendering.

### A3: Modal Race & Stale Tab Probe Observation & Evidence
- Probe (1) executed on Livewire component: `mountAction('publish')`, followed by concurrent database update `DB::table('content_blocks')->where('id', $block->id)->update(['status' => 'archived'])`, followed by `callMountedAction()`.
- **Observed Behavior:** Filament re-evaluates visibility during `callMountedAction()`. Because the action is no longer visible on the archived record, Filament drops the execution silently without running the action closure.
- **Probe Command Output:**
```
{"tool":"pest","result":"passed","tests":1,"passed":1,"assertions":3,"duration_ms":883}
```
- Assertions verified: zero state mutation from the action (remains `archived`), zero activity log rows written, no 500 error (`assertSuccessful()`).
- Probe (2) executed for stale tab B click (`it probes tab B disable click after tab A archives block`): Tab B has published/enabled block loaded. Tab A concurrently archives the record in the database. Tab B clicks `disable`.
- **Observed Behavior:** Filament re-evaluates `visible()` on the action prior to closure execution; finding it hidden in the archived state, the click is dropped as a silent no-op.
- **Probe (2) Command Output:**
```
{"tool":"pest","result":"passed","tests":1,"passed":1,"assertions":8,"duration_ms":1230}
```
- Assertions verified: Tab B receives no exception (`assertSuccessful()`), database status remains `archived` with `is_enabled = false`, 0 `disabled` activity log rows written, and Tab B re-renders on the Archived action set (all transition actions and edit action hidden: `disable`, `enable`, `publish`, `archive`, `edit`).

### m1: Hidden Action Assertion Class
- Direct call on hidden action (`callAction('publish')` on archived record) throws `\PHPUnit\Framework\AssertionFailedError::class` when asserted via `expect(fn () => ...)->toThrow(\PHPUnit\Framework\AssertionFailedError::class)`. Named and tested explicitly.

### m2: Visible Actions Never Throw IllegalStateTransitionException
- In dataset test `it('renders visible actions matching exact matrix across all statuses and enabled states')`, for each visible transition action, the domain Action behind it is directly invoked with `$admin` causer and asserted not to throw `IllegalStateTransitionException`.

### m3: Named Assertions for Updates Without Reload and Refreshed Record
- View header publish asserts `assertActionHidden('publish')` and `assertActionVisible('disable')`.
- Refreshed record asserted directly on component instance: `expect($view->instance()->getRecord()->status)->toBe(PublishStatus::PUBLISHED)`.

### m4: Commit 4 Checkpoint Status
- `composer check` was executed and confirmed fully green after Commit 4 (260 tests passed, 829 assertions, 0 phpstan errors).

---

## 5. Mutation Spot-Checks

### MUT-1: Removed `$user->isAdmin()` from `ContentBlockPolicy::publish()`
**Command:** `vendor/bin/pest tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php`
```
{"tool":"pest","result":"failed","tests":39,"passed":30,"assertions":255,"duration_ms":6030,"failed":9,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Policies\\ContentBlockPolicyTest::__pest_evaluable_it_allows_admin_to_transition_blocks_and_denies_non_admin_with_zero_state_logic","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Policies\\ContentBlockPolicyTest.php","line":94,"message":"Failed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Policies\\ContentBlockPolicyTest.php:94"]},{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest::__pest_evaluable_it_renders_visible_header_actions_matching_exact_matrix_across_all_statuses_and_enabled_states_on_view_page with data set \"dataset \"draft + enabled\"\"","file":"\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php","line":162,"message":"Failed asserting that an action with name [publish] is visible on the [App\\Filament\\Admin\\Resources\\ContentBlockResource\\Pages\\ViewContentBlock] component.\nFailed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php:162","C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php:140"]},{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest::__pest_evaluable_it_renders_visible_table_row_actions_matching_exact_matrix_across_all_statuses_and_enabled_states_on_list_page with data set \"dataset \"draft + enabled\"\"","file":"\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php","line":162,"message":"Failed asserting that an action with name [publish] is visible on the [App\\Filament\\Admin\\Resources\\ContentBlockResource\\Pages\\ListContentBlocks] component.\nFailed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php:162","C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php:168"]}]}
```
*Restoration:* `git checkout app/Domain/Content/Policies/ContentBlockPolicy.php`  
*Diff verification:* `git diff app/Domain/Content/Policies/ContentBlockPolicy.php` -> `[Empty - 0 files touched / clean]`

### MUT-2: Dropped status condition from Publish visibility in `ContentBlockTransitionActions.php`
**Command:** `vendor/bin/pest tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php`
```
{"tool":"pest","result":"failed","tests":25,"passed":13,"assertions":209,"duration_ms":4860,"failed":12,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest::__pest_evaluable_it_renders_visible_header_actions_matching_exact_matrix_across_all_statuses_and_enabled_states_on_view_page with data set \"dataset \"published + enabled\"\"","file":"\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php","line":162,"message":"Failed asserting that an action with name [publish] is hidden on the [App\\Filament\\Admin\\Resources\\ContentBlockResource\\Pages\\ViewContentBlock] component.\nFailed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php:162","C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php:143"]},{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest::__pest_evaluable_it_renders_visible_table_row_actions_matching_exact_matrix_across_all_statuses_and_enabled_states_on_list_page with data set \"dataset \"published + enabled\"\"","file":"\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php","line":162,"message":"Failed asserting that an action with name [publish] is hidden on the [App\\Filament\\Admin\\Resources\\ContentBlockResource\\Pages\\ListContentBlocks] component.\nFailed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\vendor\\filament\\actions\\src\\Testing\\TestsActions.php:162","C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php:171"]}]}
```
*Verification of matrix test surfaces:* Failures demonstrate explicit assertion of both View header actions (:143) and List table-row actions (:171).  
*Restoration:* `git checkout app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php`  
*Diff verification:* `git diff app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php` -> `[Empty - 0 files touched / clean]`

### MUT-3: Removed `IllegalStateTransitionException` catch in `executeTransition()`
**Command:** `vendor/bin/pest tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php`
```
{"tool":"pest","result":"failed","tests":25,"passed":24,"assertions":303,"duration_ms":4386,"errors":1,"error_details":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest::__pest_evaluable_it_handles_stale_state_transition_gracefully_with_danger_notification_and_record_refresh","file":"\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php","line":480,"message":"Cannot publish an archived block.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php:480","C:\\dev\\getnow-cms\\app\\Filament\\Admin\\Resources\\ContentBlockResource\\Actions\\ContentBlockTransitionActions.php:73","C:\\dev\\getnow-cms\\app\\Filament\\Admin\\Resources\\ContentBlockResource\\Actions\\ContentBlockTransitionActions.php:38"]}]}
```
*Restoration:* Restored `catch (IllegalStateTransitionException)`.  
*Diff verification:* `git diff app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php` -> `[Empty - clean]`

### MUT-4: Removed `abort_unless` authorization check from `executeTransition()`
**Command:** `vendor/bin/pest tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php --filter="aborts with 403 when non-admin executes transition action directly"`
```
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":868,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest::__pest_evaluable_it_aborts_with_403_when_non_admin_executes_transition_action_directly","file":"\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php","line":74,"message":"Failed asserting that false is true.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\ContentBlock\\ContentBlockStateActionsTest.php:74"]}]}
```
*Restoration:* Restored `abort_unless($authUser->can($ability, $record), 403);`.  
*Diff verification:* `git diff app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php` -> `[Empty - clean]`

---

## 6. Deviations & Gaps

### Accepted Deviation: AC-13 Stale Tab / Concurrent State Change Behavior (Accepted per VERIFY r1 F1)
- **Observation:** In Livewire / Filament, when an action is invoked (either after a confirmation modal has been mounted or from a stale browser tab B) and another session concurrently transitions the record to archived, Filament re-evaluates `visible()` on the action prior to executing the closure. Because all transition actions are hidden in the archived state, Filament drops the execution silently without calling the domain Action.
- **Observed & Tested Behavior:**
  - The click/execution is a silent no-op.
  - Zero state mutations occur to the database record (remains `archived`, `is_enabled = false`).
  - Zero activity log rows are created.
  - No 500 error or unhandled exception occurs.
  - The page/component re-renders with the Archived action set (all lifecycle actions and edit action hidden: `disable`, `enable`, `publish`, `archive`, `edit`).
  - No danger notification is displayed in this UI race scenario because the domain Action is not reached.
- **In-Request Concurrency:** When a stale transition occurs during in-request execution where the domain Action is reached, the `IllegalStateTransitionException` is caught as designed, displaying the danger notification and refreshing the record (verified by test `it handles stale state transition gracefully with danger notification and record refresh`).
- **Amended AC-13 & Manual Step 6 Expectation:**
  - *Manual Step 6:* Open block in Tab A and Tab B. Tab A archives the block. Tab B clicks Disable (or Publish). Expected: Click is silently dropped, zero DB changes, zero activity log entries, and Tab B refreshes to show the Archived action set (all transitions and edit hidden).
  - *AC-13 Status:* **PARTIAL (ACCEPTED DEVIATION)** per VERIFY r1 F1 closeout note.

### Gaps
- None.

---

## 7. Rule Register Self-Audit

| ID | Description | Compliance Evidence |
|---|---|---|
| **E1** | Windows/PowerShell native execution | All commands run one at a time via native PowerShell. Zero bash syntax. |
| **S1** | Business logic in Domain Actions | All lifecycle state changes invoke `PublishContentBlock`, `ArchiveContentBlock`, `EnableContentBlock`, `DisableContentBlock`. Zero write logic in Filament. |
| **S2** | Thin pages | Pages assemble schemas, handle UI notifications/redirects, and delegate to domain Actions. |
| **S3** | Domain never imports Filament or Http | Verified via `tests/Arch/DomainArchTest.php` passing. |
| **S4** | Filament never calls `Model::create/update/delete` directly | All writes go through invokable domain Actions. Enforced by `tests/Arch/FilamentWritesTest.php`. |
| **S5** | Block schemas in `app/Filament/Blocks/` | Reuses existing registered `AnnouncementBarForm` schema via `BlockFormRegistry`. |
| **S6** | Panel code under `app/Filament/Admin/` | All pages, schemas, actions, and tables reside under `app/Filament/Admin/Resources/ContentBlockResource/`. |
| **S7** | Put code in correct place | Reusable actions in `Actions/`, metadata schema in `Schemas/`, view page in `Pages/`. |
| **C1** | `declare(strict_types=1);` in every file | Declared at line 1 of every file. |
| **C2** | Strict types on properties, parameters, returns; final classes | All classes typed and marked `final`. |
| **C3** | Use enums and Data classes | `Placement`, `PublishStatus`, `ServingStatus`, `Tone` used. |
| **C4** | Status & `is_enabled` changed only via Actions | Neither field is an editable form field on View page. Transitions occur strictly through Actions. |
| **C5** | Action rule applies to all state persistence | All model updates delegate to Actions. |
| **C6** | Validation and Policies | Policy checks enforced across all abilities (`view`, `publish`, `archive`, `enable`, `disable`). |
| **C7** | Follow Pint and Larastan without baselines | `pint` passed and `phpstan` passed with 0 errors. Zero baseline entries added. |
| **C8** | Comments explain why, not what | Code comments explain non-obvious rationale; zero commented-out code. |
| **K1** | Block type keys permanent | Key `'announcement_bar'` preserved. |
| **K2** | Payload schema versioning | Managed via `GetUpgradedBlockPayload` in `ViewContentBlock::mutateFormDataBeforeFill`. |
| **K3** | No raw HTML/CSS/JS fields | Validated text, enums, and icons only. |
| **K4** | Validated links (`/` or `https://`) | Rendered as read-only plain text, never as clickable anchor tags. |
| **K5** | Placements & types defined in code | Options loaded from code definitions and enums. |
| **K6** | Explicit block registration | Registration via `ContentServiceProvider` and `BlockFormRegistry`. |
| **K7** | Server-side visibility | Maintained in domain layer. |
| **K8** | Content API contract | Contract untouched in this UI step. |
| **K9** | Checklist in Architecture sec 6 followed | Checklist respected. |
| **DB1** | PostgreSQL only | Tests run against Postgres `phone_store_test`. |
| **DB2** | Append-only migrations | Zero migrations modified or added. |
| **DB3** | No destructive DB changes | Zero DB schema changes. |
| **DB4** | DB constraints respected | Model and Action constraints honored. |
| **DB5** | Expose `public_id` externally | Routes and URLs use ULID; raw integer IDs return 404. |
| **DB6** | Idempotent seeders | No seeder changes. |
| **SEC1** | No secrets in repo | Verified clean. |
| **SEC2** | Authorization never bypassed | Policies enforced across all pages and actions. |
| **SEC3** | Do not log sensitive data | Hydration errors log only class name and `public_id`. |
| **SEC4** | Admin access checked server-side | Policy authorization checked at mount and at action execution (`abort_unless`). |
| **SEC5** | Sensitive state logged | Activity log entries asserted in tests for publish, archive, enable, disable. |
| **T1** | Pest tests for Actions & Filament | 296 Pest tests passing. |
| **T2** | Resource smoke tests | `FilamentSmokeTest::assertViewPageRenders` loads View page for platform admin. |
| **T3** | Block type tests | Preserved. |
| **T4** | Factories used | Test data generated via Eloquent factories. |
| **T5** | No skipped or incomplete tests | 296 tests passed, 0 skipped, 0 incomplete. |
| **G1** | Branch from main | Branch `feat/ui-content-block-view-and-transitions`. |
| **G2** | Conventional Commits | All commits follow Conventional Commits. |
| **G3** | Never push directly to main | Preserved. |
| **G4** | PR descriptions complete | Ready for PR creation. |
| **X1** | Do not add dependencies | Zero dependencies added (`composer.json` untouched). |
| **X2** | Do not introduce Docker | Preserved native Windows environment. |
| **X3** | Do not add deployment pipelines | Preserved. |
| **X4** | Stay within phase scope | Only Phase 1 CMS artifacts touched. |
| **X5** | Do not modify storefront repo | Storefront untouched. |
| **X6** | `composer check` passes | 296 tests passed (1148 assertions), Pint passed, Larastan passed (0 errors). |
| **X7** | Docs updated where relevant | Implementation report documented; `ARCHITECTURE.md` ticked UI step 4 and documented actions folder. |

---

## 8. Full Quality Suite Output (`composer check`)

**Command:** `composer check`
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":296,"passed":296,"assertions":1148,"duration_ms":38644}
```
*Static Analysis (`composer stan`):*
Passed with **0 errors** at Level 8 without baseline entries or suppressions.

---

## 9. Layer A Raw Command Verification Output

### Git Status
**Command:** `git status --short`
```
[Empty - clean working tree]
```

### Git Log
**Command:** `git log --oneline main..HEAD`
```
f9cb5b5 docs(blueprints): add P1-UI4 blueprint and report with file reconciliation, architecture diff and A3 fallback
89ad766 docs(architecture): tick UI step 4 and document resource actions subfolder
4f3a0a5 test(filament): add state transition feature tests
a7ed85c feat(filament): add view row action, grouped transition actions and row click to content block table
b3f9b2e feat(filament): add shared content block transition actions and wire to view page
a278f4a feat(filament): add content block metadata schema, view page and resource view route
b893057 feat(testing): add assertViewPageRenders method to FilamentSmokeTest
f6b9782 feat(content): add publish, archive, enable and disable policy abilities
a632274 docs(blueprints): organize phase 1 blueprints, record P1-UI3 closeout, and tick UI step 3
b355c7e fix(filament): span payload group full width and mirror smoke test class pattern
361843d test(content): add UnknownBlockTypeException catch test and tighten assertions
5326215 test(content): remove redundant RefreshDatabase and TestBlocks from admin tests
7894a34 fix(testing): restore instance methods and route-key lookup in FilamentSmokeTest
425de2d feat(filament): add content block create page, header action and create tests
6ca4649 feat(filament): add content block edit page, table action and edit tests
c31fa2b feat(filament): add block form contract, registry and announcement bar form schema
d62b5fd feat(content): add announcement bar text max length and disallow editing archived blocks in policy
8e8f68f docs(architecture): track phase 1 progress and reconcile discrepancies
40d9fce docs(content): fix formatting artifacts in ui step 2 verification report
f25d66e docs(content): finalise ui step 2 verification report
2b0f043 test(content): cleanup and rename ui step 2 verification tests
0f98a6e test(content): add strict types to ServingHealthIntegrityTest
43de6b2 chore(docs): restore ui_step_1_report.md
a5c3d61 test(content): add navigation visibility test
58aedd8 test(content): add hide_archived interaction test
90909f8 chore(content): move AdminTimezoneResolver binding to AdminPanelProvider
ae5b7c7 fix(content): resolve ui step 2 verify blockers
262d9be chore: add ADMIN_TIMEZONE to .env.example
9b0d736 feat(content): scaffold Filament Resource and Table for Content Blocks
a5d89ce feat(content): implement ui prerequisites and test updates
494c028 Merge pull request #3 from AchinthaDe/feat/content-blocks-backend
ddfb7f2 feat(content): implement ui prerequisites and test updates
eaefedb test(content): add serving health test coverage
8fbcb88 feat(content): integrate serving health into content actions
2972a13 feat(content): implement serving health domain layer
e2962b7 docs(content): add adrs for content block serving health and concurrency
da5ab93 chore: normalize line endings
78d17f0 Merge pull request #2 from AchinthaDe/feat/content-blocks-backend
71047f4 fix(content): resolve 2a correctness and test gaps
cab32b5 fix(content): resolve minor 2a review feedback
e0cb538 Fix remaining defects in 2a backend
b4b35fc fix(content): resolve architectural blockers in 2a backend
9d389bc feat(content): implement block registry and announcement bar backend
```

### Git Merge-Base
**Command:** `git merge-base main HEAD`
```
784c8aa67fbc6de735fbf78bb6785ed9182e766b
```
*(Note: Step P1-UI4 base commit is `a632274`, branching off `b355c7e` / UI3 closeout).*

### Baseline Diff
**Command:** `git diff main...HEAD --stat -- '*baseline*'`
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
**Command:** `Get-ChildItem -Path app/Domain/Content/Policies/ContentBlockPolicy.php, app/Filament/Admin/Resources/ContentBlockResource -Recurse -Filter *.php | Where-Object { -not (Get-Content $_.FullName -TotalCount 5 | Select-String -SimpleMatch 'declare(strict_types=1);') }`
```
[Empty - 0 matches / 100% compliant]
```

### T5 Scan (Skipped or Incomplete Tests)
**Command:** `Get-ChildItem -Path tests -Recurse -Filter *.php | Select-String -Pattern "->skip\(|markTestIncomplete"`
```
[Empty - 0 matches]
```

### X2 Scan (No Docker Introduced)
**Command:** `git ls-files "*docker*"`
```
[Empty - 0 matches]
```

### Environment Files Check
**Command:** `git ls-files .env`
```
[Empty - 0 matches]
```

### Modal Descriptions Check (Brief Trap 3)
**Command:** `Get-ChildItem -Path app/Filament -Recurse -Filter *.php | Select-String -Pattern 'modalDescription'`
```
app\Filament\Admin\Resources\ContentBlockResource\Actions\ContentBlockTransitionActions.php:72:            ->modalDescription('This content block will become eligible to show on the storefront within its schedule.')
app\Filament\Admin\Resources\ContentBlockResource\Actions\ContentBlockTransitionActions.php:96:            ->modalDescription('Archiving cannot be undone from the panel. The block will be removed from display and cannot be edited.')
app\Filament\Admin\Resources\ContentBlockResource\Actions\ContentBlockTransitionActions.php:120:            ->modalDescription('This content block will become eligible to show on the storefront within its schedule.')
```
