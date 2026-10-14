[P1-FIX1 | PLAN v4 | Gemini]

# Implementation Plan: P1-FIX1 (v4)

**Step ID**: P1-FIX1  
**Version**: v4  
**Branch**: `fix/serving-window-and-history-stacking` (branched from `main`)  
**Goal**: Resolve two defects identified during manual testing:
1. **Part A (Serving Window Bug)**: Correct the in-process serving status caching defect in `GetBlockServingStatus` by evaluating dynamic lifecycle `isLive()` outside the cache so memoized health reflects window transitions. Resolve the root cause of the manual browser testing symptom proven by database inspection (time entered as local clock time while `admin.timezone` was `UTC`) by adopting Achintha's chosen Option A: configure `ADMIN_TIMEZONE=Asia/Colombo` in `.env` and document it in `.env.example` (rules X7, SEC1), ensuring the form label and parser operate in the administrator's local timezone.
2. **Part B (Browser History Stacking Bug)**: Reduce avoidable browser history entries by ensuring table pagination uses history replacement (`history: false`) rather than history pushes, preventing intra-table pagination clicks from polluting browser history.

---

## Change Log (Review Feedback Mapping: v3 -> v4)

| Finding ID | Reviewer Issue | Resolution in Plan v4 | Section Changed |
|---|---|---|---|
| **M1** | §3.1.2 and §13 Q1 had static helper text adding no info and recorded unmade decision | Replaced static helper text with Achintha's chosen Option A: configure `ADMIN_TIMEZONE=Asia/Colombo` in `.env` and document in `.env.example`. Recorded Achintha's explicit choice in Section 13 Q1. | **Section 3.1.2, Section 13** |
| **M2** | Form/env change had no AC, no spec in §5, no flow in §7, no test, and crossed commit boundaries | Added AC-11 and test in `ContentBlockServingWindowTest` asserting field label renders `Starts at (Asia/Colombo)` under configured timezone. Added mutation M4 mutating the label. Added interface spec to Section 5.1 and flow to Section 7.1. Split commit 1 into domain cache fix (Commit 1) and admin timezone env/form test (Commit 2). Re-checked M3 mutation line reference at line 112. | **Section 1, Section 4, Section 5.1, Section 7.1, Section 9, Section 10.1, Section 10.2, Section 12** |
| **M3** | Section 10.3 had no Layer C line testing table pagination | Added Layer C line testing pagination navigation (pages 1 to 2 to 3, then Back) with expected result. Stated prerequisite: at least 21 content blocks in database based on default per-page size of 10. | **Section 10.3** |
| **M4** | Change log said "documented distinct page entries" but Q2 was unrecorded; no check for consecutive duplicates | Corrected Change Log for Q2. Recorded Achintha's answer ("No its the order in which i went but descending", confirming distinct pages). Added condition to AC-10 and Layer C line 10: if the same URL repeats consecutively, stop and raise a Change Request (CR). | **Change Log, Section 3.2, Section 9, Section 10.3, Section 13** |
| **m1** | Section 4 file counts mismatched (header said 5, list had 6, total said 8 instead of 9) | Fixed counts: Part A lists 6 files, Part B lists 3 files, total 9 files. | **Section 4** |
| **m2** | AC-9 said "Create/Edit/View" but only Edit and View are tested | Dropped "Create" from AC-9 summary, leaving "Mount failure (Edit/View) redirects to index". | **Section 9** |
| **m3** | Section 5.2 lacked proof that no Filament parent class overrides `queryStringHandlesPagination` | Quoted ripgrep search proving `vendor/filament` has only one implementation (`DatabaseNotifications.php:211`). No parent class in `ListRecords` hierarchy overrides it. | **Section 5.2** |
| **m4** | Section 10.3 Part A line 1 hardcoded "Starts at (UTC)" | Rewrote line 1 to reference `Starts at (Asia/Colombo)` per the chosen Option A. | **Section 10.3** |

---

## 1. Context & Scope

### Active Scope
- Active Phase: Phase 1 (Layout CMS).
- Scope boundaries:
  - **Part A**:
    - Root-cause diagnosis and correction of stale serving status caching in [GetBlockServingStatus](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php).
    - Ensure dynamic lifecycle evaluation (`isLive()`) is evaluated outside the cache so non-live states are never stored in `Cache::store('array')`.
    - Address the primary manual browser symptom (entry timezone interpretation mismatch) via Option A: document `ADMIN_TIMEZONE=Asia/Colombo` in [.env.example](file:///c:/dev/getnow-cms/.env.example) and configure in local `.env` (rules X7, SEC1), enabling [ContentBlockForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) to display `Starts at (Asia/Colombo)` and interpret inputs directly in local wall-clock time.
    - Regression tests verifying immediate liveness at `starts_at` boundary, non-liveness at `starts_at - 1 second`, retention of non-live state past `ends_at`, timezone label rendering, and UTC conversion integrity.
    - Documentation update to [0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md) recording dynamic lifecycle evaluation boundaries.
  - **Part B**:
    - Reduce avoidable browser history entries on the Content Blocks List page table.
    - Configure table pagination on [ListContentBlocks](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php) to use history replacement (`history: false`) via `queryStringHandlesPagination` override.
    - Characterize inherited Livewire defaults for `tableSearch`, `tableSort`, and `tableFilters` ensuring they retain `history: false`.
    - Assert defined redirect targets for Create save, Edit save, Publish, Archive, Enable, and Disable actions.
    - Assert that Edit and View mount failures issue redirects to index.
    - Documentation update to [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) Section 8 recording the panel browser history convention.

### Out of Scope
- Scheduler jobs, background commands, or cron tasks that flip content block status (rule C4).
- Creating new database statuses, database columns, or stored "live" flags.
- Introducing persistent caching stores or changing default cache drivers (rule B2).
- Rewriting browser history across distinct visited pages (Part B Option 1 accepted).
- Enabling panel-wide SPA mode (requires separate architectural step per rule X4).
- Introducing external browser testing packages or dependencies (rule X1).
- Modifying Menus, Content API, or Seed phases (rule X4).
- Modifying storefront repository code or assumptions (rule X5).

---

## 2. Sources Read

| Source File | What in it constrains P1-FIX1 |
|---|---|
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | Sections 5 (Dependency rules), 6 (Content model & Block rules: server-side visibility, no revisions in Phase 1), 7 (ETag and application caching sentence), 8 (Filament conventions), 11 (Testing), 12 (Decisions D7, D9: pessimistic locking for transitions, last-write-wins). |
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Strict typing, single concern per change, thin Filament layers delegating to Actions, zero direct model mutations in Filament, no leaked exception details, PostgreSQL test requirement, definition of done. |
| [rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | Canonical rule register: C4 (no status writes for time-based visibility), K7 (server-side visibility decision), S1/S2 (thin resources and pages), T1-T5 (deterministic time using `travelTo`/`setTestNow`), DB1 (Postgres only). |
| [0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md) | Section 3: `isLive()` remains a pure lifecycle check; serving health is resolved only for lifecycle-live rows, cached per request, and paginated. Section 4: Publish and Enable reject unservable payloads. |
| [0003-content-block-concurrency.md](file:///c:/dev/getnow-cms/docs/adr/0003-content-block-concurrency.md) | Last-write-wins for payloads, pessimistic locking (`lockForUpdate()`) strictly for atomic state transitions. |
| [ContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php) | Schema columns (`status`, `is_enabled`, `starts_at`, `ends_at`), query scopes `scopeVisible` (lines 72-86), and lifecycle methods `isLiveAt` (lines 99-118) and `isLive` (lines 120-123). |
| [GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php) | Current serving status resolution and caching implementation using `Cache::store('array')->rememberForever(...)` (lines 20-53). |
| [ResolveServablePayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Services/ResolveServablePayload.php) | Payload hydration and validation service (lines 24-70) and failure warning logging (lines 72-92). |
| [PublishContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php) | State transition action verifying payload servability via `ResolveServablePayload` (line 34). |
| [EnableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php) | State transition action verifying payload servability via `ResolveServablePayload` (line 34). |
| [DisableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/DisableContentBlock.php) | State transition action flipping `is_enabled` to false with pessimistic locking. |
| [ArchiveContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/ArchiveContentBlock.php) | State transition action setting status to `archived` and `is_enabled` to false. |
| [CreateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/CreateContentBlock.php) | Create action parsing dates to UTC with `startOfSecond()` (lines 40-41). |
| [UpdateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php) | Update action parsing dates to UTC with `startOfSecond()` (lines 30-31). |
| [ContentBlockResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource.php) | Route key configuration (`public_id`), `getUrl()` override, and page route registration. |
| [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) | Form layout, `starts_at` and `ends_at` DateTimePicker definitions with resolved `$timezone` (lines 110-118). |
| [ContentBlockMetadataSchema.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockMetadataSchema.php) | View page metadata entries; `serving_status` entry invoking `GetBlockServingStatus` (lines 75-97). |
| [ContentBlockTable.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php) | List page table configuration; `serving_status` column evaluating `GetBlockServingStatus` (lines 106-114). |
| [ContentBlockTransitionActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php) | Reusable transition actions (`publish`, `archive`, `enable`, `disable`) executing transitions and refreshing records. |
| [ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php) | List records page class inheriting from Filament `ListRecords`. |
| [CreateContentBlock.php (Page)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/CreateContentBlock.php) | Create record page defining `getRedirectUrl()` returning the edit route (lines 42-45). |
| [EditContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) | Edit record page with hydration error redirect to index (lines 60-65). |
| [ViewContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php) | View record page with hydration error redirect to index (lines 63-68). |
| [InteractsWithContentBlockActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php) | DateTime parsing helper `parseScheduleDatetime` (lines 69-77). |
| [AdminTimezoneResolver.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Services/AdminTimezoneResolver.php) | Timezone resolution service validating configured timezone identifier against PHP identifiers list. |
| [AdminPanelProvider.php](file:///c:/dev/getnow-cms/app/Providers/Filament/AdminPanelProvider.php) | Admin panel provider configuration. |
| [config/admin.php](file:///c:/dev/getnow-cms/config/admin.php) | Configuration reading `ADMIN_TIMEZONE` from environment with `UTC` fallback. |
| [config/app.php](file:///c:/dev/getnow-cms/config/app.php) | Application timezone configured as `UTC` (line 68). |
| [.env](file:///c:/dev/getnow-cms/.env) | Local environment configuration specifying `CACHE_STORE=database`. |
| [.env.example](file:///c:/dev/getnow-cms/.env.example) | Example environment configuration. |
| `vendor/filament/filament/src/Resources/Pages/ListRecords.php` | Base ListRecords defining URL-bound properties `tableSearch`, `tableSort`, `tableFilters`, `tableGrouping`, `isTableReordering`, `activeTab` (lines 33-56). |
| `vendor/filament/filament/src/Resources/Pages/CreateRecord.php` | Base CreateRecord redirection lifecycle and `getRedirectUrl()` defaults (lines 170-174, 331-358). |
| `vendor/filament/filament/src/Resources/Pages/EditRecord.php` | Base EditRecord redirection lifecycle and `getRedirectUrl()` returning null when authorized (lines 201-203, 390-415). |
| `vendor/filament/filament/src/Resources/Pages/ViewRecord.php` | Base ViewRecord mount and record resolution architecture. |
| `vendor/livewire/livewire/src/Features/SupportQueryString/BaseUrl.php` | Livewire `BaseUrl` attribute defining `$history = false` default and history mode mapping `'use' => $this->history ? 'push' : 'replace'` (lines 14-20, 143). |
| `vendor/livewire/livewire/src/Features/SupportPagination/HandlesPagination.php` | Livewire pagination trait defining `queryStringHandlesPagination` returning `['history' => true]` (lines 12-17). |
| `vendor/livewire/livewire/src/Features/SupportRedirects/HandlesRedirects.php` | Livewire redirect trait and `redirect($url, $navigate = false)` handling (lines 8-18). |
| `vendor/filament/forms/src/Components/DateTimePicker.php` | Form field component configuring timezone and `DateTimeStateCast` (lines 345-363, 507-512, 870-873). |
| `vendor/filament/schemas/src/Components/StateCasts/DateTimeStateCast.php` | DateTime state cast converting between form display timezone and storage timezone (lines 18-57, 59-92). |
| [GetBlockServingStatusTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php) | Existing feature tests for serving status computation and caching behavior. |
| [ContentBlockCreateTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php) | Create page feature tests including timezone persistence tests (lines 203-228). |
| [ContentBlockEditTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php) | Edit page feature tests including schedule hydration tests (lines 91-120). |
| [ListContentBlocksTableTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php) | List page table feature tests covering columns, filters, and row actions. |
| [ContentBlockViewTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php) | View page feature tests asserting metadata rendering and schema version formatting. |
| [ContentBlockStateActionsTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php) | Feature tests for state transition actions and wall-time parity assertions (lines 591-611). |

---

## 3. Verified Current State

### 3.1 Root Cause Diagnosis: Part A (Serving Window Bug)

#### 1. Lifecycle Implementation in ContentBlock.php
The relevant methods governing block visibility and lifecycle evaluation are:

**`scopeVisible()` (ContentBlock.php:72-86)**:
```php
public function scopeVisible(Builder $query): Builder
{
    $now = now();

    return $query->where('status', PublishStatus::PUBLISHED->value)
        ->where('is_enabled', true)
        ->where(function (Builder $q) use ($now) {
            $q->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $now);
        })
        ->where(function (Builder $q) use ($now) {
            $q->whereNull('ends_at')
                ->orWhere('ends_at', '>', $now);
        });
}
```

**`isLiveAt()` (ContentBlock.php:99-118)**:
```php
public function isLiveAt(CarbonInterface $time): bool
{
    if ($this->status !== PublishStatus::PUBLISHED) {
        return false;
    }

    if (! $this->is_enabled) {
        return false;
    }

    if ($this->starts_at !== null && $time->lt($this->starts_at)) {
        return false;
    }

    if ($this->ends_at !== null && $time->gte($this->ends_at)) {
        return false;
    }

    return true;
}
```

**`isLive()` (ContentBlock.php:120-123)**:
```php
public function isLive(): bool
{
    return $this->isLiveAt(now());
}
```

#### 2. Raw Evidence from Database Inspection
The test block referenced in manual testing was inspected on developer machine PostgreSQL database `phone_store` via native read-only tinker execution:

**Command 1**:
```powershell
php artisan tinker --execute="dump(App\Domain\Content\Models\ContentBlock::where('public_id', '01m4qa12fvt9c9458rnbfvyfa5')->first()->only(['status', 'is_enabled', 'starts_at', 'ends_at']));"
```
**Raw Output 1**:
```
array:4 [
  "status" => App\Domain\Content\Enums\PublishStatus {#8333
    +name: "PUBLISHED"
    +value: "published"
  }
  "is_enabled" => true
  "starts_at" => Carbon\CarbonImmutable @1791908006 {#8835
    date: 2026-10-13 16:13:26.0 +00:00
  }
  "ends_at" => null
]
```

**Command 2**:
```powershell
php artisan tinker --execute="dump(['now_utc' => now()->toIso8601String(), 'is_live' => App\Domain\Content\Models\ContentBlock::where('public_id', '01m4qa12fvt9c9458rnbfvyfa5')->first()->isLive()]);"
```
**Raw Output 2**:
```
array:2 [
  "now_utc" => "2026-10-14T03:27:06+00:00"
  "is_live" => true
]
```

**Command 3**:
```powershell
php artisan tinker --execute="dump(['admin_timezone' => config('admin.timezone'), 'app_timezone' => config('app.timezone')]);"
```
**Raw Output 3**:
```
array:2 [
  "admin_timezone" => "UTC"
  "app_timezone" => "UTC"
]
```

**Timestamps on the Record**:
```powershell
php artisan tinker --execute="dump(App\Domain\Content\Models\ContentBlock::where('public_id', '01m4qa12fvt9c9458rnbfvyfa5')->first()->only(['created_at', 'updated_at']));"
```
```
array:2 [
  "created_at" => Carbon\CarbonImmutable @1791783635 {#8333
    date: 2026-10-12 05:40:35.0 UTC (+00:00)
  }
  "updated_at" => Carbon\CarbonImmutable @1791888157 {#8836
    date: 2026-10-13 10:42:37.0 UTC (+00:00)
  }
]
```

#### 3. Value Trace and Defect Branching Analysis
1. **Trace Through Lifecycle Logic**:
   - At the time of edit (`updated_at`), local clock in Sri Lanka (UTC+05:30) was `16:12:37`. Actual UTC wall time was `10:42:37 UTC`.
   - The administrator intended "now + 49 seconds" and entered `16:13:26`.
   - In [ContentBlockForm.php:27, 110-113](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php#L27), `$timezone` resolved to `config('admin.timezone')`, which was `UTC`. The field label rendered `Starts at (UTC)`.
   - Because the form timezone was `UTC`, the input string `16:13:26` was parsed directly as `2026-10-13 16:13:26.0 +00:00` (UTC).
   - In [CreateContentBlock.php:40](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/CreateContentBlock.php#L40) / [UpdateContentBlock.php:30](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php#L30), `starts_at` was persisted in PostgreSQL as `2026-10-13 16:13:26+00`.
   - During manual verification 2 minutes later at local time `16:14:00`, actual UTC time was `10:44:00 UTC`.
   - Tracing through `isLiveAt(CarbonInterface $time)`:
     - `status === PublishStatus::PUBLISHED` -> passes line 101.
     - `is_enabled === true` -> passes line 105.
     - `$time->lt($this->starts_at)` compares `10:44:00 UTC` against `16:13:26 UTC`. Because `10:44:00 < 16:13:26`, line 109 returned `false`.
     - The block was not live because `starts_at` had been scheduled 5 hours and 29 minutes into the future relative to true UTC time.
   - When inspected on 2026-10-14 03:27:06 UTC, `$time->lt($this->starts_at)` is `false` (`03:27:06 (+1 day) > 16:13:26`), line 117 executed, and `isLive()` returned `true` (Raw Output 2).

2. **Branch on Evidence & Adopted Solution (Option A)**:
   - `starts_at in UTC is hours past the moment you intended: an entry or label problem. The code works as written.`
   - Achintha explicitly selected Option A: configure `ADMIN_TIMEZONE=Asia/Colombo` in `.env` and document `ADMIN_TIMEZONE=Asia/Colombo` in [.env.example](file:///c:/dev/getnow-cms/.env.example) (rules X7, SEC1).
   - Under Option A, `AdminTimezoneResolver` resolves `Asia/Colombo`. The field label in `ContentBlockForm` dynamically displays `Starts at (Asia/Colombo)`. When the administrator types local wall-clock times, Filament's `DateTimeStateCast` converts the local wall time to UTC for storage, aligning entered times with true UTC instants.

3. **In-Process Cache Defect (Confirmed Secondary Defect)**:
   In [GetBlockServingStatus.php:41](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php#L41), `$block->isLive()` is evaluated inside `Cache::store('array')->rememberForever($key)`. In-process (same long-lived worker, Octane process, or test execution), evaluating before `starts_at` stores `NotLive` indefinitely until memory reset. Moving `$block->isLive()` outside the cache resolves this in-process defect. The PR description (rule G4) will state that the reported manual symptom was an input timezone mismatch, and the cache change resolves a confirmed in-process lifecycle caching defect found during diagnosis.

### 3.2 Root Cause Diagnosis: Part B (Browser History Stacking Bug)

#### 1. Q2 Evidence Restatement
Achintha's evidence for Q2:
> "No its the order in which i went but descending"

This confirms the browser Back history consists of distinct visited pages in reverse chronological order, not repeated entries of the same page.

#### 2. History Push Analysis
1. **Inter-Page Navigation**:
   In [AdminPanelProvider.php:33-66](file:///c:/dev/getnow-cms/app/Providers/Filament/AdminPanelProvider.php#L33-L66), panel SPA mode is not enabled. Every link click (sidebar items, breadcrumbs, table row clicks to View or Edit) executes standard browser navigations (`<a href="...">`) that push an entry onto the browser history stack. Pressing browser Back traverses these distinct visited pages in reverse order.
2. **Table Query String Defaults**:
   In `vendor/filament/filament/src/Resources/Pages/ListRecords.php:39-55`, `tableSearch`, `tableSort`, and `tableFilters` are declared with `#[Url]`. In `vendor/livewire/livewire/src/Features/SupportQueryString/BaseUrl.php:16`, `BaseUrl::$history` defaults to `false`. These properties replace history rather than pushing entries.
3. **Table Pagination Push (Confirmed Avoidable Push)**:
   In `vendor/livewire/livewire/src/Features/SupportPagination/HandlesPagination.php:12-17` (Livewire `v4.4.7`):
   ```php
   public function queryStringHandlesPagination()
   {
       return collect($this->paginators)->mapWithKeys(function ($page, $pageName) {
           return ['paginators.'.$pageName => ['history' => true, 'as' => $pageName, 'keep' => false]];
       })->toArray();
   }
   ```
   Pagination explicitly sets `'history' => true`. Every table page click pushes an entry onto the browser history stack. Overriding `queryStringHandlesPagination` to return `'history' => false` stops pagination from pushing history entries.

---

## 4. File Manifest

The plan modifies 9 files total (6 for Part A, 3 for Part B), below the 10-file size guard. Part A and Part B touch zero common files.

### Part A Manifest (6 files)
1. [app/Domain/Content/Actions/GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php) (Modified: evaluate dynamic lifecycle `isLive()` outside the cache; cache only servable payload health outcomes for live rows in `Cache::store('array')`).
2. [.env.example](file:///c:/dev/getnow-cms/.env.example) (Modified: document `ADMIN_TIMEZONE=Asia/Colombo` per rules X7, SEC1).
3. [docs/adr/0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md) (Modified: document that lifecycle non-live states are never cached, and payload serving health caching applies strictly to live records).
4. [tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php) (Modified: update existing cache tests):
   - `calls the resolver at most once per block per request`: retained; asserts resolver memoization on live blocks.
   - `resolves freshly if starts_at or ends_at changes without updated_at changing`: updated to assert dynamic evaluation when time advances without requiring model attribute modifications.
   - New test: `evaluates live status immediately when time reaches starts_at without cache clearing`.
5. `tests/Feature/Domain/Content/ServingWindowTest.php` (New: domain feature tests covering AC-1, AC-2, AC-3, AC-6).
6. `tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php` (New: admin feature tests covering AC-4, AC-5, AC-11, M3 mutation, M4 mutation).

### Part B Manifest (3 files)
7. [app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php) (Modified: override `queryStringHandlesPagination` with `history: false`).
8. [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) (Modified: Section 8 update establishing panel browser history convention).
9. `tests/Feature/Admin/ContentBlock/ContentBlockHistoryTest.php` (New: feature tests asserting AC-7, AC-8, AC-9).

---

## 5. Architecture & Component Design

### 5.1 Part A: Interface Specification and Decoupled Lifecycle Evaluation

#### 1. GetBlockServingStatus Exact Signature
In [GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php):
```php
final class GetBlockServingStatus
{
    public function __construct(
        private readonly ResolveServablePayload $resolveServablePayload
    ) {}

    public function __invoke(ContentBlock $block): BlockServingStatusResult
    {
        if (! $block->isLive()) {
            return new BlockServingStatusResult(ServingStatus::NotLive);
        }

        $updatedAt = $block->updated_at !== null ? $block->updated_at->timestamp : 0;
        $payloadString = json_encode($block->payload);
        $key = sprintf(
            'serving_status_%d_%s_%d_%d_%s',
            $block->id ?? 0,
            $block->type,
            $updatedAt,
            $block->schema_version ?? 1,
            md5($payloadString ?: '')
        );

        return Cache::store('array')->rememberForever($key, function () use ($block): BlockServingStatusResult {
            $healthResult = ($this->resolveServablePayload)($block, false);

            $status = ($healthResult->outcome === ServingHealthOutcome::Servable)
                ? ServingStatus::Live
                : ServingStatus::PayloadError;

            return new BlockServingStatusResult($status, $healthResult->outcome);
        });
    }
}
```

#### 2. Exact Cache Key Parameters
The cache key is formatted as `serving_status_%d_%s_%d_%d_%s` with five parameters:
1. `(int) ($block->id ?? 0)`
2. `(string) $block->type`
3. `(int) $updatedAt`
4. `(int) ($block->schema_version ?? 1)`
5. `(string) md5($payloadString ?: '')`

The cache store is strictly `Cache::store('array')`. Non-live states are never stored in cache.

#### 3. ContentBlockForm Timezone Interface Specification
In [ContentBlockForm.php:27, 110-118](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php#L27):
```php
$timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

DateTimePicker::make('starts_at')
    ->label("Starts at ({$timezone})")
    ->timezone($timezone)
    ->nullable(),

DateTimePicker::make('ends_at')
    ->label("Ends at ({$timezone})")
    ->timezone($timezone)
    ->nullable(),
```
Under Option A, `config('admin.timezone')` is set to `Asia/Colombo`. The resolved `$timezone` string evaluates to `Asia/Colombo`, producing field labels `Starts at (Asia/Colombo)` and `Ends at (Asia/Colombo)`. The `DateTimePicker` component converts dates entered by administrators in `Asia/Colombo` into UTC instants prior to saving.

### 5.2 Part B: Interface Specification for ListContentBlocks
In [ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php):

#### Exact Class Declaration
```php
final class ListContentBlocks extends ListRecords
{
    protected static string $resource = ContentBlockResource::class;

    /**
     * @return array<string, array{history: bool, as: string, keep: bool}>
     */
    public function queryStringHandlesPagination(): array
    {
        return collect($this->paginators)->mapWithKeys(function ($page, $pageName): array {
            return ['paginators.'.$pageName => ['history' => false, 'as' => $pageName, 'keep' => false]];
        })->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

#### Grep Proof for queryStringHandlesPagination Override
A ripgrep search for `queryStringHandlesPagination` across `vendor/filament` reveals only a single occurrence:
`vendor\filament\notifications\src\Livewire\DatabaseNotifications.php:211`
No parent class in the `ListRecords` inheritance chain (`ListRecords`, `ManageRecords`, `Filament\Pages\Page`, `Livewire\Component`) defines or overrides `queryStringHandlesPagination`. Overriding `queryStringHandlesPagination` on `ListContentBlocks` directly and safely overrides the implementation provided by Livewire's `HandlesPagination` trait.

### 5.3 Redirection Target Matrix (AC-8 & AC-9)

| Action / Event | Invocation Surface | Defined Redirection Target | Assertion Method | History Impact |
|---|---|---|---|---|
| **Create Save** | Create page form submit | Edit page (`ContentBlockResource::getUrl('edit', ['record' => $record])`) | `assertRedirect(ContentBlockResource::getUrl('edit', ['record' => $block]))` | Navigates forward to Edit page |
| **Edit Save** | Edit page form submit | Stays on Edit page (`null` redirect) | `assertNoRedirect()` | Updates current page; 0 history push |
| **Publish** | List table row action | Stays on List page (`null` redirect; row refresh) | `assertNoRedirect()` | 0 history change |
| **Publish** | View page header action | Stays on View page (`null` redirect; record refresh) | `assertNoRedirect()` | 0 history change |
| **Archive** | List table row action | Stays on List page (`null` redirect; row refresh) | `assertNoRedirect()` | 0 history change |
| **Archive** | View page header action | Stays on View page (`null` redirect; record refresh) | `assertNoRedirect()` | 0 history change |
| **Enable** | List table row action | Stays on List page (`null` redirect; row refresh) | `assertNoRedirect()` | 0 history change |
| **Enable** | View page header action | Stays on View page (`null` redirect; record refresh) | `assertNoRedirect()` | 0 history change |
| **Disable** | List table row action | Stays on List page (`null` redirect; row refresh) | `assertNoRedirect()` | 0 history change |
| **Disable** | View page header action | Stays on View page (`null` redirect; record refresh) | `assertNoRedirect()` | 0 history change |
| **Mount Failure** | Edit / View page mount | Index page (`ContentBlockResource::getUrl('index')`) | `assertRedirect(ContentBlockResource::getUrl('index'))` | HTTP 302 redirect on initial GET |

---

## 6. Data and Migrations

- **Database Changes**: Zero migrations. No database tables, columns, indexes, or constraints are added, altered, or dropped.
- **Data Integrity**: Conforms strictly to rule DB1 (PostgreSQL dev/test environment) and DB2 (zero destructive changes).

---

## 7. Behavior & Flows

### 7.1 Serving Window Progression Flow
1. **Creation**: An administrator in Sri Lanka creates a content block. The form field label displays `Starts at (Asia/Colombo)`. The administrator enters `starts_at = 2026-10-15 17:30:00` (which is `12:00:00 UTC`) and `ends_at = 2026-10-15 19:30:00` (which is `14:00:00 UTC`). The dates are converted by Filament and persisted as UTC in PostgreSQL.
2. **Pre-window State (`now() < starts_at`)**:
   - `ContentBlock::isLive()` evaluates to `false`.
   - `GetBlockServingStatus` returns `ServingStatus::NotLive` directly. No cache key is populated.
   - List table displays `Not live` badge.
   - View page displays `Not live` badge.
   - Scope `scopeVisible` excludes the row from API resolution.
   - Activity log: none (read-only); Concurrency: no lock (D9).
3. **Window Arrival (`now() == starts_at`)**:
   - Time reaches `2026-10-15 12:00:00 UTC` without any database write (`updated_at` unchanged, zero activity log entry).
   - `ContentBlock::isLive()` evaluates to `true`.
   - `GetBlockServingStatus` executes `ResolveServablePayload`. If payload is valid, returns `ServingStatus::Live`.
   - List table displays `Live` badge.
   - View page displays `Live` badge.
   - Activity log: none (read-only); Concurrency: no lock (D9).
4. **Post-window State (`now() >= ends_at`)**:
   - Time reaches `2026-10-15 14:00:00 UTC`.
   - `ContentBlock::isLive()` evaluates to `false`.
   - `GetBlockServingStatus` returns `ServingStatus::NotLive`.
   - List table and View page display `Not live` badge.
   - Activity log: none (read-only); Concurrency: no lock (D9).

### 7.2 Panel Navigation History Flow
1. User lands on `/admin/content-blocks` (Entry 1 in history).
2. User types in table search: URL updates to `/admin/content-blocks?search=sale` via `window.history.replaceState()`. Entry 1 is replaced.
   - Activity log: none (read-only); Concurrency: no lock (D9).
3. User sorts by type: URL updates to `/admin/content-blocks?search=sale&sort=type` via `replaceState()`. Entry 1 is replaced.
   - Activity log: none (read-only); Concurrency: no lock (D9).
4. User changes page to 2: URL updates to `/admin/content-blocks?search=sale&sort=type&page=2` via `replaceState()`. Entry 1 is replaced.
   - Activity log: none (read-only); Concurrency: no lock (D9).
5. User clicks row to View a block: Navigates to `/admin/content-blocks/01J...` (Entry 2 in history).
   - Activity log: none (read-only); Concurrency: no lock (D9).
6. User clicks browser Back: Browser navigates directly back to Entry 1 (`/admin/content-blocks?search=sale&sort=type&page=2`). No stepping through intermediate search or sort states.

---

## 8. Rule Compliance Matrix

| Rule ID | Rule Requirement Text | How P1-FIX1 Complies |
|---|---|---|
| **E1** | Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`. | All commands specified one at a time using PowerShell syntax without chaining. |
| **S1** | Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too. | `GetBlockServingStatus` remains an invokable Domain Action housing serving status logic. |
| **S2** | Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output. | `ListContentBlocks`, `ContentBlockTable`, and `ContentBlockMetadataSchema` remain thin delegates calling Actions. |
| **S3** | `app/Domain` must never import from `App\Filament` or `App\Http`. | Zero imports from `App\Filament` or `App\Http` inside `app/Domain`. |
| **S4** | Filament never calls `Model::create/update/delete` directly. It calls Actions. | Zero direct model mutations added to Filament pages or tables. |
| **S5** | Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`. | Zero changes to block definitions or form schemas. |
| **S6** | Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths. | All panel changes are strictly located in `app/Filament/Admin/Resources/ContentBlockResource/`. |
| **S7** | Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder. | Code modifications placed strictly in existing designated locations. |
| **C1** | `declare(strict_types=1);` in every PHP file. | Strict types declared on all modified and new PHP files. |
| **C2** | Type every parameter, return value and property. Prefer `final` classes. | Full native type declarations on all classes, methods, and properties; classes declared `final`. |
| **C3** | Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers. | Existing enums (`ServingStatus`, `PublishStatus`, `ServingHealthOutcome`) and Data classes used throughout. |
| **C4** | Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field. | Visibility window progression creates zero status or flag mutations. Status remains `published`. |
| **C5** | The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action. | Zero settings mutations in this step. |
| **C6** | Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API. | Authorization gates on `ContentBlockPolicy` remain enforced. |
| **C7** | Follow Pint and Larastan. Do not add baseline entries without approval. | Code formatted with Pint; Larastan passes at current level without baseline entries. |
| **C8** | Comments explain why, not what. No commented-out code. | Zero commented-out code; documentation explains design rationales. |
| **K1** | Block type keys are permanent. Never rename one. | Zero block types renamed. |
| **K2** | Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load. | Schema versions and upgraders untouched. |
| **K3** | No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans. | Zero raw HTML/CSS/JS fields introduced. |
| **K4** | Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme. | Link validation rules untouched. |
| **K5** | Placements, menu keys and block types are defined in code. Admins never create them. | Code definitions untouched. |
| **K6** | Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery. | Registration list untouched. |
| **K7** | Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content. | Dynamic `isLive()` check server-side decides serving visibility. |
| **K8** | The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change. | Public API contract untouched. |
| **K9** | Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps. | Zero block types added. |
| **DB1** | PostgreSQL only. Tests run against Postgres, never SQLite. | All automated tests run against native PostgreSQL database `phone_store_test`. |
| **DB2** | Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration. | Zero migrations modified or added. |
| **DB3** | No destructive changes (drop or rename column or table) in the same release that stops using them. | Zero schema alterations. |
| **DB4** | Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers. | Existing schema constraints untouched. |
| **DB5** | Expose `public_id` (ULID) externally, never raw incremental IDs. | Public ULIDs preserved across all routes. |
| **DB6** | Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases. | Database seeds untouched. |
| **SEC1** | Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`. | `ADMIN_TIMEZONE` documented in `.env.example`; no secrets committed. |
| **SEC2** | Do not disable authorization, policies, strict types or static analysis rules to make code pass. | Exact rule text followed; zero gates bypassed or weakened. |
| **SEC3** | Do not log sensitive data. | Zero sensitive data logged. |
| **SEC4** | Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization. | Server-side panel authentication and authorization preserved. |
| **SEC5** | Write sensitive content changes (publish, disable, menu and settings edits) to the activity log. | Sensitive transition logging preserved; read-only operations write zero activity entries. |
| **T1** | Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`. | All new tests written in Pest. |
| **T2** | Every Filament resource has a smoke test that loads list, create and edit pages. | Resource smoke tests pass untouched. |
| **T3** | Every block type has tests for payload validation, API output and visibility rules. | Existing visibility tests preserved and extended. |
| **T4** | Use factories, not hand-built rows. Tests must be independent and deterministic. | ContentBlock factory states used across all test cases. |
| **T5** | Do not lower coverage, skip tests or mark them incomplete to pass checks. | Zero tests skipped or marked incomplete. |
| **G1** | Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`. | Work developed on branch `fix/serving-window-and-history-stacking`. |
| **G2** | Conventional Commits: `feat(content): add announcement bar block`. | Conventional commit messages formatted per standard. |
| **G3** | Never push to `main` directly or bypass git hooks. | Branch commits only; pre-push checks respected. |
| **G4** | PR description: what changed, why, how it was tested, and risks (migrations, API contract, security). | PR description covers changes and verification evidence. |
| **X1** | Do not add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval. | Zero dependencies added. |
| **X2** | Do not introduce Docker, Sail, docker-compose or any container configuration. | Native environment preserved. |
| **X3** | Do not add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments. | Verified zero scheduler entries via `php artisan schedule:list`. |
| **X4** | Nothing outside the active phase was added. Do not create folders, contexts or tables for later phases. | Scoped strictly to Phase 1 fixes. |
| **X5** | Do not modify the storefront repository or assume its internals. The API contract is the only interface. | Storefront untouched. |
| **X6** | Definition of done: `composer check` passes and the output was read. | `composer check` executed and verified green. |
| **X7** | Definition of done: `ARCHITECTURE.md`, `docs/contracts/content-api.md`, `.env.example` updated where relevant. | `ARCHITECTURE.md`, ADR 0002, and `.env.example` updated. |

---

## 9. Acceptance Criteria Traceability

| AC ID | Acceptance Criteria Summary | Implementation Mechanism | Test Assertion |
|---|---|---|---|
| **AC-1** | Published + enabled, `starts_at = now+1h`: not live in `GetBlockServingStatus`, absent from servable layout | Dynamic check in `GetBlockServingStatus` returns `NotLive`. Scope `visible` excludes row. | Assert `GetBlockServingStatus` returns `NotLive`. Assert `ContentBlock::visible()->pluck('id')` excludes block. |
| **AC-2** | Same block at `travelTo(starts_at)` is live; at `starts_at - 1s` not live; `updated_at` unchanged, 0 activity log | In-memory time comparison against `starts_at`. | Assert `NotLive` at `starts_at - 1s`; assert `Live` at `travelTo(starts_at)`; assert `updated_at` equals original; assert zero activity log rows. |
| **AC-3** | Resolver called before `starts_at` (cache primed), then time passes: block is live with no cache flush on array cache store | Non-live state is never cached; array cache priming does not store `NotLive`. | Prime array cache before `starts_at`, advance time, call again, assert `Live` without cache flush in `tests/Feature/Domain/Content/ServingWindowTest.php`. |
| **AC-4** | Value entered in form as non-UTC timezone stored as converted UTC instant and compared against UTC `now` | `DateTimePicker` with `->timezone('Asia/Colombo')` converts to UTC. | Assert form filled with `'2026-10-15 09:00:00'` under `Asia/Colombo` yields DB `starts_at` `'2026-10-15 03:30:00+00'`. |
| **AC-5** | List and View badges show Live after `starts_at` with zero write | Columns and schemas dynamically invoke `GetBlockServingStatus` on each render. | Mount List and View at `travelTo(starts_at)`; assert `Live` badge rendered. |
| **AC-6** | Past `ends_at` is still not live (regression) | `isLive()` evaluates `$time->gte($this->ends_at)`. | Travel to `ends_at + 1s`; assert `GetBlockServingStatus` returns `NotLive`. |
| **AC-7** | Table pagination on ListContentBlocks uses history replacement | `queryStringHandlesPagination` override sets `'history' => false`. | Assert `queryStringHandlesPagination()` returns `['history' => false]`. Characterize search, sort, filters inheriting `history === false`. |
| **AC-8** | Create/Edit save and transitions redirect to defined targets | Explicit redirection configuration per action. | Assert `getRedirectUrl()` on Create returns edit URL via `assertRedirect`; assert Edit save returns null via `assertNoRedirect`; assert transitions return null via `assertNoRedirect`. |
| **AC-9** | Mount failure (Edit/View) redirects to index | Unhydrateable payloads return 302 to index. | Request unhydrateable edit/view routes; assert 302 to index; assert session error notification. |
| **AC-10** | Layer C: Back lands on expected page for every line of history script; stop if URLs repeat consecutively | Manual verification script executed in browser. | Execution report records URL transitions and back navigation paths; stop and raise CR if consecutive duplicate URLs occur. |
| **AC-11** | Field label reflects configured admin timezone | `ContentBlockForm` dynamically resolves `$timezone` and injects it into field labels. | Assert Create form field label rendered for `starts_at` equals `Starts at (Asia/Colombo)` under `admin.timezone = Asia/Colombo`. |

---

## 10. Testing Strategy & Fixtures

### 10.1 Layer A: Automated Feature Tests

#### Part A Domain Test Suite (`tests/Feature/Domain/Content/ServingWindowTest.php`)
1. `it('evaluates published scheduled block as not live before starts_at and live at starts_at without database write')`:
   - Creates block with `starts_at = now()->addHour()`.
   - Asserts `GetBlockServingStatus` returns `NotLive`.
   - Asserts `ContentBlock::visible()->pluck('id')` excludes block.
   - Travels to `starts_at - 1 second`: asserts `NotLive`.
   - Travels to `starts_at`: asserts `Live`.
   - Asserts `ContentBlock::visible()->pluck('id')` includes block.
   - Asserts `updated_at` timestamp is untouched.
   - Asserts zero activity log rows created for subject.
   - *Status pre-fix*: **Fails pre-fix**. Invoking `GetBlockServingStatus` before `starts_at` primes the array cache with `NotLive`; advancing time on the same block without clearing cache causes the second call to return stale `NotLive`.
2. `it('evaluates serving status correctly across time without cache flush under array cache store')`:
   - Primes `GetBlockServingStatus` before `starts_at` (returns `NotLive`).
   - Advances time to `starts_at` without calling `Cache::flush()`.
   - Invokes `GetBlockServingStatus` again: asserts returns `Live`.
   - *Status pre-fix*: **Fails pre-fix**. Returns stale `NotLive` from array cache.
3. `it('evaluates block as not live once ends_at timestamp has passed')`:
   - Creates block with `starts_at = now()->subHour()` and `ends_at = now()->addHour()`.
   - Asserts `Live`.
   - Travels to `ends_at + 1 second`.
   - Asserts `GetBlockServingStatus` returns `NotLive`.
   - *Status pre-fix*: **Fails pre-fix**. Priming `Live` before `ends_at` returns stale `Live` from array cache after `ends_at`.

#### Part A Admin Test Suite (`tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php`)
1. `it('renders field label displaying resolved admin timezone under non-UTC configuration')`:
   - Sets `config(['admin.timezone' => 'Asia/Colombo'])`.
   - Mounts `CreateContentBlock` Livewire component.
   - Asserts form field label for `starts_at` is `'Starts at (Asia/Colombo)'`.
   - Asserts form field label for `ends_at` is `'Ends at (Asia/Colombo)'`.
   - *Status pre-fix*: Characterization test (passes pre-fix).
2. `it('persists entered schedule converted to UTC under non-UTC admin timezone')`:
   - Sets `config(['admin.timezone' => 'Asia/Colombo'])`.
   - Submits Create form with `starts_at = 2026-10-15 09:00:00`.
   - Asserts model `starts_at` in database equals `2026-10-15 03:30:00 UTC`.
   - *Status pre-fix*: Characterization test (passes pre-fix).
3. `it('renders Live badge on List and View pages when window opens without database write')`:
   - Creates block with `starts_at = now()->addMinutes(5)`.
   - Travels to `now()->addMinutes(6)`.
   - Tests `ListContentBlocks` Livewire component: asserts table column `serving_status` formatted state is `Live`.
   - Tests `ViewContentBlock` Livewire component: asserts metadata entry `serving_status` state is `Live`.
   - *Status pre-fix*: **Passes pre-fix**. In independent Livewire component mounts where `GetBlockServingStatus` was not previously primed before `starts_at`, fresh execution returns `Live`.

#### Part B Test Suite (`tests/Feature/Admin/ContentBlock/ContentBlockHistoryTest.php`)
1. `it('characterizes tableSearch, tableSort, and tableFilters properties as having history false')`:
   - Inspects `ListContentBlocks` properties `tableSearch`, `tableSort`, `tableFilters`.
   - Asserts each property possesses a `Livewire\Attributes\Url` attribute where `history === false`.
   - *Status pre-fix*: Characterization test (passes pre-fix due to inherited Livewire default).
2. `it('configures table pagination on ListContentBlocks with history false')`:
   - Invokes `queryStringHandlesPagination()` on `ListContentBlocks` instance.
   - Asserts each paginator array contains `'history' => false`.
   - *Status pre-fix*: **Fails pre-fix** (`HandlesPagination` defaults to `'history' => true`).
3. `it('redirects Create form save to edit page using assertRedirect')`:
   - Creates block on `CreateContentBlock` page, calls `create`.
   - Asserts `assertRedirect(ContentBlockResource::getUrl('edit', ['record' => $block]))`.
4. `it('retains Edit form save on current page with assertNoRedirect')`:
   - Saves block on `EditContentBlock` page, calls `save`.
   - Asserts `assertNoRedirect()`.
5. `it('executes Publish table row action without redirect using assertNoRedirect')`:
   - Calls `publish` table action on `ListContentBlocks`.
   - Asserts `assertNoRedirect()`.
6. `it('executes Archive table row action without redirect using assertNoRedirect')`:
   - Calls `archive` table action on `ListContentBlocks`.
   - Asserts `assertNoRedirect()`.
7. `it('executes Enable table row action without redirect using assertNoRedirect')`:
   - Calls `enable` table action on `ListContentBlocks`.
   - Asserts `assertNoRedirect()`.
8. `it('executes Disable table row action without redirect using assertNoRedirect')`:
   - Calls `disable` table action on `ListContentBlocks`.
   - Asserts `assertNoRedirect()`.
9. `it('executes Publish header action on View page without redirect using assertNoRedirect')`:
   - Calls `publish` action on `ViewContentBlock`.
   - Asserts `assertNoRedirect()`.
10. `it('executes Archive header action on View page without redirect using assertNoRedirect')`:
    - Calls `archive` action on `ViewContentBlock`.
    - Asserts `assertNoRedirect()`.
11. `it('executes Enable header action on View page without redirect using assertNoRedirect')`:
    - Calls `enable` action on `ViewContentBlock`.
    - Asserts `assertNoRedirect()`.
12. `it('executes Disable header action on View page without redirect using assertNoRedirect')`:
    - Calls `disable` action on `ViewContentBlock`.
    - Asserts `assertNoRedirect()`.
13. `it('redirects to index on View or Edit mount failure')`:
    - Creates block with unhydrateable payload (`unknown_type`).
    - Tests `Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])->assertRedirect(ContentBlockResource::getUrl('index'))`.
    - Tests `Livewire::test(EditContentBlock::class, ['record' => $block->public_id])->assertRedirect(ContentBlockResource::getUrl('index'))`.

### 10.2 Layer B: Mutation Spot-Checks

Each mutation is verified by temporarily modifying the codebase, running the targeted test, confirming failure, reverting the mutation, and verifying a clean git diff:

- **M1 (Boundary Comparison Mutation)**:
  - Modify [ContentBlock.php:109](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php#L109) from `$time->lt($this->starts_at)` to `$time->lte($this->starts_at)`.
  - Target test: `tests/Feature/Domain/Content/ServingWindowTest.php` (`evaluates published scheduled block as not live before starts_at and live at starts_at without database write`).
  - Expected failure: test fails at exact `starts_at` timestamp because `lte` marks it not live.
- **M2 (Stale Cache Key / TTL Mutation)**:
  - Modify [GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php) to evaluate `isLive()` inside `Cache::store('array')->rememberForever`.
  - Target test: `tests/Feature/Domain/Content/ServingWindowTest.php` (`evaluates serving status correctly across time without cache flush under array cache store`).
  - Expected failure: test fails after advancing time because cached `NotLive` is returned.
- **M3 (Timezone Conversion Removal Mutation)**:
  - Remove `->timezone($timezone)` from `starts_at` in [ContentBlockForm.php:112](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php#L112).
  - Target test: `tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php` (`persists entered schedule converted to UTC under non-UTC admin timezone`).
  - Expected failure: test fails because non-UTC input `09:00:00` is saved as `09:00:00 UTC` instead of converted to `03:30:00 UTC`.
- **M4 (Timezone Label Omission Mutation)**:
  - Modify [ContentBlockForm.php:111](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php#L111) from `->label("Starts at ({$timezone})")` to `->label('Starts at')`.
  - Target test: `tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php` (`renders field label displaying resolved admin timezone under non-UTC configuration`).
  - Expected failure: test fails because label renders `'Starts at'` rather than `'Starts at (Asia/Colombo)'`.

### 10.3 Layer C: Manual UI Script Execution
Executed against native local server (`php artisan serve`):
- **Part A**:
  1. Create a block with Starts at (Asia/Colombo) = now + 2 minutes. Publish, then Enable. Verify `Not live` badge on List and View.
  2. After 2 minutes pass, reload List. Expected result: badge transitions to `Live`.
  3. Open View page. Expected result: badge displays `Live`.
  4. Record whether open page updated without manual reload.
- **Part B**:
  5. On List, open block View, press Back. Expected result: browser navigates directly back to List page (single history step).
  6. On List, open Edit, modify a field, Save, press Back. Expected result: browser navigates back to List page.
  7. On List, search, sort, and filter multiple times, then press Back. Expected result: browser navigates back to previous distinct page; intermediate search/sort/filter changes are replaced in history and not stepped through.
  8. From List, publish, archive, enable, and disable via row actions, then press Back. Expected result: row actions execute via Livewire wire:click without pushing history; Back navigates directly to previous page.
  9. Click sidebar link for Content Blocks three times, then press Back. Expected result: observe whether clicking active nav item pushed redundant entries.
  10. Prerequisite: ensure at least 21 content blocks exist in database (ContentBlockTable default per-page size is 10; 21 rows produce 3 pages). From List, change table page from 1 to 2 to 3, then press browser Back. Expected result: browser navigates back to the previous distinct page or state prior to pagination, without stepping backwards through page 2 and page 1.
  11. Press Back repeatedly until exiting admin. Expected result: URL history sequence steps through distinct visited routes without intermediate table state entries. If the same URL repeats consecutively, stop and raise a Change Request (CR).

---

## 11. Documentation Updates

1. **[0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md)**:
   - Clarify Section 3 to document that `isLive()` is evaluated dynamically on every request and lifecycle non-live states are never stored in cache.
   - Document that serving health outcome caching applies strictly to records that have passed lifecycle validation.
2. **[ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md)**:
   - Update Section 8 (Filament conventions) to document the history convention: table state interactions (search, sort, filters inherit `history: false`, pagination configured with `history: false`) replace the current browser history entry, while inter-page navigation pushes an entry.
3. **[.env.example](file:///c:/dev/getnow-cms/.env.example)**:
   - Add `ADMIN_TIMEZONE=Asia/Colombo` documentation entry per rules X7 and SEC1.

---

## 12. Commit Order & Checkpoints

Each commit combines the implementation and its corresponding tests, leaving `composer check` fully green at every checkpoint.

### Part A Commits
1. **Commit 1 (Part A Domain Cache Test + Fix)**:
   - Command to prove failure pre-fix: `php artisan test tests/Feature/Domain/Content/ServingWindowTest.php`
   - Files:
     - `tests/Feature/Domain/Content/ServingWindowTest.php`
     - [tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php)
     - [app/Domain/Content/Actions/GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php)
   - Commit message: `fix(content): evaluate dynamic lifecycle before serving status memoization`
2. **Commit 2 (Part A Admin Timezone Configuration & Characterization)**:
   - Files:
     - [.env.example](file:///c:/dev/getnow-cms/.env.example)
     - `tests/Feature/Admin/ContentBlock/ContentBlockServingWindowTest.php`
   - Commit message: `feat(filament): document admin timezone in env and characterize schedule form timezone labels`
3. **Commit 3 (Part A Documentation)**:
   - File: [docs/adr/0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md)
   - Commit message: `docs(adr): update ADR 0002 for dynamic lifecycle serving status resolution`

### Part B Commits
4. **Commit 4 (Part B Test + Fix)**:
   - Command to prove failure pre-fix: `php artisan test tests/Feature/Admin/ContentBlock/ContentBlockHistoryTest.php --filter=pagination`
   - Files:
     - `tests/Feature/Admin/ContentBlock/ContentBlockHistoryTest.php`
     - [app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php)
   - Commit message: `fix(filament): configure history replacement for table query strings and pagination`
5. **Commit 5 (Part B Documentation)**:
   - File: [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md)
   - Commit message: `docs(architecture): document admin panel browser history convention in section 8`

---

## 13. Risks, Assumptions & Conflicts

### Questions for Achintha (Recorded Responses & Decisions)
1. **Q1 Evidence for Part A**: Raw tinker outputs executed on block `01m4qa12fvt9c9458rnbfvyfa5`:
   - `status`: `published`, `is_enabled`: `true`, `starts_at`: `2026-10-13 16:13:26+00`.
   - `updated_at`: `2026-10-13 10:42:37 UTC` (Local time `16:12:37` UTC+05:30).
   - Time entered was local clock time entered into a field labeled `Starts at (UTC)`.
   - **Decision on Part A Timezone Fix**: Achintha selected Option A: configure `ADMIN_TIMEZONE=Asia/Colombo` in `.env` and document it in [.env.example](file:///c:/dev/getnow-cms/.env.example) (rules X7, SEC1).
2. **Q2 Evidence for Part B**: Achintha confirmed that Back traverses panels visited in descending order:
   - *Achintha's answer*: "No its the order in which i went but descending"
   - Confirms browser Back traverses distinct visited routes in reverse order, not repeated entries of the same page.
3. **Q3 Admin List Auto-refresh**: Defaulted to `no`. A page load shows the truth.
4. **Q4 History Convention**: Defaulted to `yes`. Table state replaces current entry; inter-page navigation pushes one entry.
5. **Q5 Browser-test Dependency**: Defaulted to `no`. Layer C manual testing only.
6. **B2 Persistent Caching Question**: Achintha responded conditionally:
   - *Achintha's answer*: "If its correctly live on the database side i have no issue with it."
   - *Resolution*: Array store `Cache::store('array')` is retained because ADR 0002 Section 3 mandates per-request array caching and ARCHITECTURE Section 7 forbids persistent caching without measurement.
7. **Part B Navigation Decision**:
   - *Options*:
     1. **Option 1 (Default)**: Accept normal Back behavior across distinct pages. Part B only stops pagination from adding entries.
     2. **Option 2**: Option 1 plus replace-navigation after Create, so Back from the new Edit page does not land on the empty Create form.
     3. **Option 3**: Enable SPA mode (separate architectural step per rule X4).
   - *Resolution*: Option 1 adopted as default per brief guidance.

### Risks
1. **Timezone Configuration**:
   If a new development environment is created without setting `ADMIN_TIMEZONE` in `.env`, it will fall back to `UTC`. Documenting `ADMIN_TIMEZONE=Asia/Colombo` in `.env.example` provides explicit setup guidance.
2. **Inter-Page History Stacking**:
   Standard hyperlink navigation across distinct pages pushes history entries in multi-page web applications. Part B eliminates avoidable intra-table pagination pushes while preserving standard browser navigation history across distinct URLs.

### Assumptions
1. Administrator input into DateTimePicker is interpreted according to `config('admin.timezone')` (configured as `Asia/Colombo`), and converted accurately to UTC before persistence in PostgreSQL.
2. Browser history testing for AC-10 is verified via manual Layer C script because Pest feature tests run server-side without a JavaScript DOM engine.

---

## 14. Dependencies

1. **Prerequisite Step**: P1-UI4 (Accepted and marked complete in [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md)).
2. **Pinned Package Versions** (sourced from `composer.lock`):
   - `filament/filament`: `v5.10.0`
   - `filament/forms`: `v5.10.0`
   - `filament/tables`: `v5.10.0`
   - `livewire/livewire`: `v4.4.7`
   - PHP: `^8.3`
3. **Database**: Native PostgreSQL instance running on developer machine with database `phone_store_test`. Zero container or Docker dependencies.
