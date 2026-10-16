# [P1-UI5 | PLAN v3 | Gemini]

## Change Log

| Finding ID | Reviewer Issue | Resolution in Plan v3 | Section Changed |
|---|---|---|---|
| **B1** | Scope exceeded checkpoint CP-A; manifest and ACs contained CP-B (block preview) and CP-C (menus). | Scoped v3 strictly to Checkpoint CP-A (Commits 1 to 3). Removed all preview and menu components, controllers, routes, and partials. Reconciled Section 4 manifest with files modified in Commits 1 to 3 including `EditContentBlock.php` and `InteractsWithContentBlockActions.php`. Deferred CP-B to P1-UI6 and CP-C to P1-UI7 in Section 13. | **Section 1, Section 4, Section 5, Section 7, Section 9, Section 10, Section 11, Section 12, Section 13, Section 14** |
| **B2** | Concurrency race condition across singleton and multi-block capacity checks. | Enforced transaction-scoped PostgreSQL advisory locking (`pg_advisory_xact_lock`) keyed on placement CRC32 before row locks in `PublishContentBlock`, `EnableContentBlock`, and `UpdateContentBlock`. Defined key derivation, lock ordering, deadlock immunity, and `DB::listen` ordering test. | **Section 5.4, Section 5.5, Section 7, Section 10.3** |
| **M1** | Placement select on Create allowed fallback to all placements and bypass of Page selection. | Set Placement select on Create to disabled with empty options until Page is selected; options display `shortLabel()`. Removed `Placement::cases()` fallback, test 'preserves backward compatibility...', and MUT-3. Documented Livewire fill order (`page`, then `placement`, then `type`) and added tests for disabled placement state and cascade reset on page change. | **Section 5.7, Section 10.5** |
| **M2** | `defaultMaxLivePerPlacement` on `BlockDefinition` duplicated capacity logic across block definitions. | Removed `defaultMaxLivePerPlacement` from `BlockDefinition`, 6 block classes, and 4 test stubs. Designated `Placement::maxCount()` as sole source of truth. Replaced AC-1 test with unit test pinning `maxCount()` across all 9 placements. | **Section 5.2, Section 10.1, Section 11.1** |
| **M3** | `UpdateContentBlock` threw capacity exceptions on non-schedule edits for over-capacity legacy blocks. | Restricted capacity check in `UpdateContentBlock` to run only when `starts_at` or `ends_at` differs from stored values. Added tests for legacy over-capacity text edits succeeding, schedule edits throwing, and unchanged schedules passing. | **Section 5.5, Section 10.2** |
| **M4** | `GetBlockServingStatus` candidate selection ordering and health evaluation diverged from `GetLayoutContent`. | Aligned `GetBlockServingStatus` candidate ranking with `GetLayoutContent.php` lines 68-75 (evaluating servability before applying capacity cap). Restored `isLive()` check first for non-live blocks to preserve `NotLive` status for non-live corrupt blocks. Added parity test asserting `ServingStatus::Live` set matches `GetLayoutContent` output. | **Section 5.5, Section 10.4, Section 13** |
| **M5** | Missing architectural decision record for capacity enforcement; Section 13 reported "None". | Added ADR `docs/adr/0004-live-capacity-enforcement.md` amending D8. Added `ARCHITECTURE.md` Section 12 row D10. Documented D8 reversal in Section 13. | **Section 11, Section 13** |
| **m1** | Redundant `SelectFilter` 'page' and unbounded placement filter options on list table. | Removed `SelectFilter` 'page'. Scoped `placement` filter options dynamically to the active tab and reset value on tab switch. Specified adherence to `ARCHITECTURE.md` Section 8 browser history convention (`history: false`). | **Section 5.7, Section 10.5** |
| **m2** | Default parameter `= new EnsurePlacementCapacity()` in Action constructors bypassed DI container. | Removed default constructor argument `= new EnsurePlacementCapacity()` across `PublishContentBlock`, `EnableContentBlock`, and `UpdateContentBlock`. | **Section 5.5** |
| **m3** | Direct Eloquent query in Filament capacity hint violated domain separation. | Replaced Filament-layer query with invokable domain Action `CountLiveBlocksForPlacement`. | **Section 5.7, Section 10.5** |
| **m4** | Insufficient test coverage for complex multi-block concurrency and schedule windows. | Added tests in `EnsurePlacementCapacityTest` for staggered windows on max-4 placement, rejection when 5 overlap concurrently, expired window handling, exclusion of non-live statuses, and self-exclusion on update. | **Section 10.2** |

---

## 1. Understanding

The scope of P1-UI5 v3 is strictly confined to Checkpoint **CP-A**:
1. Introducing page-level grouping via `PlacementPage` enum (`GLOBAL_LAYOUT`, `HOME`), structuring the Content Block list table with page tabs (`all`, `home`, `global_layout`), filtering placement options by active tab, and providing a Page badge column.
2. Implementing a cascading Page -> Placement -> Type selection flow on block creation where the Placement select remains disabled until Page is selected, and displays concise placement names via `Placement::shortLabel()`.
3. Enforcing code-defined live capacity limits (`Placement::maxCount()`) across singleton and multi-block placements during `PublishContentBlock`, `EnableContentBlock`, and schedule-altering `UpdateContentBlock` calls. Execution is serialized per placement using PostgreSQL transaction-level advisory locks, preventing concurrent schedule overlaps beyond capacity via `PlacementCapacityExceededException`.
4. Aligning `GetBlockServingStatus` candidate selection with `GetLayoutContent` so shadowed blocks rank as `ServingStatus::NotLive`, while preserving `ServingStatus::NotLive` for non-live blocks with corrupt payloads.

### Out of Scope Restated
- Block preview features (isolated preview on View, split-screen preview on Edit, demo controller sample catalog extraction, preview routes, iframe messaging) are deferred to **P1-UI6**.
- Menu management redesign (collapsible summary cards, compact grids, hierarchical tree preview panel) is deferred to **P1-UI7**.
- Installing `safi/filament-atelier` or third-party packages (Rule X1).
- Adding database migrations or schema alterations (Rule DB2).
- Altering the public JSON response shape of `GET /api/v1/content/layout` (Rule K8).

---

## 2. Sources Read

| Source File or Document Section | What in it constrains P1-UI5 |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Business logic restricted to `app/Domain/Content/Actions/` and Services; thin Filament resources; zero direct model writes in Filament; strict types; final classes; no raw HTML/CSS/JS fields; no external packages without approval; definition of done. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) Sections 5, 6, 7, 8, 12 | Monolith structure; 9 Phase 1 placements and block definitions; D7 live edit semantics; D8 tie-breaking (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`); D9 pessimistic locks on transitions; admin panel routing conventions. |
| [rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | Canonical rule register (`E1, S1..S7, C1..C8, K1..K9, DB1..DB6, SEC1..SEC5, T1..T5, G1..G3, X1..X7`). |
| [Placement.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php) | Enum defining 9 placement cases, `allowedBlockTypes()`, `maxCount()`, and `label()`. Requires `page()`, `shortLabel()`, and `forPage()`. |
| [PublishStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/PublishStatus.php) | Enum defining `DRAFT`, `PUBLISHED`, `ARCHIVED`. |
| [ServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/ServingStatus.php) | Enum defining `Live`, `NotLive`, `PayloadError`. |
| [ContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php) | Eloquent model for blocks, casting `placement` with `TolerantEnum`, scopes `visible()` and `servingOrder()`, methods `isLiveAt()` and `isLive()`. |
| [PublishContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php) | Transition action locking block row, verifying servability, setting `PUBLISHED`. Requires placement advisory lock and capacity guard invocation. |
| [EnableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php) | Transition action locking block row, verifying servability, setting `is_enabled = true`. Requires placement advisory lock and capacity guard invocation. |
| [UpdateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php) | Domain action locking block row, validating and upgrading payload, updating schedule and sort order. Requires placement advisory lock and capacity guard when schedule changes on active block. |
| [GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php) | Evaluates block serving status; requires verification that block ranks within top `maxCount()` under D8 order before returning `Live`. |
| [GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php) | Assembles `LayoutResponseData` using visible blocks in D8 serving order bounded by `maxCount()`. Used as reference for serving status parity. |
| [ContentBlockResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource.php) | Resource registering `index`, `create`, `view`, and `edit` routes keyed by `public_id`. |
| [ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php) | List page component configuring pagination history. Requires `getTabs()` for page filtering. |
| [CreateContentBlock.php (Page)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/CreateContentBlock.php) | Create page component delegating record creation to domain Action. |
| [EditContentBlock.php (Page)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) | Edit page component handling hydration and updates. Requires catching capacity exceptions on schedule updates. |
| [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) | Form schema. Requires Page -> Placement -> Type cascading select, disabled placement state, and capacity hint. |
| [ContentBlockTable.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php) | Table schema. Requires Page badge column and tab-scoped placement filter. |
| [ContentBlockTransitionActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php) | Shared transition actions. Requires catching `PlacementCapacityExceededException` and dispatching danger notifications. |
| [InteractsWithContentBlockActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php) | Form data preparation helper converting datetime inputs and re-mapping validation errors. |
| [ContentException.php](file:///c:/dev/getnow-cms/app/Domain/Content/Exceptions/ContentException.php) | Base domain exception class required by `DomainArchTest`. |

---

## 3. Verified Current State

### 3.1 Placement Enum
- File: [Placement.php:12-68](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php#L12-L68)
- Defines 9 cases: `ANNOUNCEMENT_BAR`, `HOME_HERO_CAROUSEL`, `HOME_HERO_SIDE`, `HOME_SERVICE_STRIP`, `HOME_FEATURED_ASPECTS_HEADER`, `HOME_FEATURED_ASPECTS_BANNER`, `HOME_TOP_SELECTION_HEADER`, `HOME_TRENDING_HEADER`, `HOME_BRAND_STRIP`.
- Defines `maxCount()` returning 5 for `HOME_HERO_CAROUSEL`, 4 for `HOME_SERVICE_STRIP`, and 1 for the remaining seven singleton placements.

### 3.2 Block Transitions and Updates
- Transition actions [PublishContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php) and [EnableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php) execute within database transactions and take row locks (`lockForUpdate`), but previously lacked cross-row capacity checks and placement-level serialization.
- Domain action [UpdateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php) executes under row lock but did not validate schedule overlaps against concurrent live blocks.

### 3.3 Serving Status Action
- File: [GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php)
- Computes `ServingStatus` based on payload servability, status, and schedule window. Did not verify whether a published, enabled, in-window block was shadowed by higher-priority blocks exceeding placement `maxCount()`.

### 3.4 Filament ContentBlock Schema and Pages
- [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php): Had placement and type select fields without page-level grouping or cascading reactivity.
- [ContentBlockTable.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php): Listed records with placement, type, status, and dates, but lacked a Page badge column and tab-scoped placement filtering.
- [ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php): Standard list page without tab grouping.

---

## 4. File Manifest

| Operation | File Path | Purpose |
|---|---|---|
| Create | `app/Domain/Content/Enums/PlacementPage.php` | Defines `GLOBAL_LAYOUT` and `HOME` pages with labels. |
| Modify | `app/Domain/Content/Enums/Placement.php` | Adds `page()`, `shortLabel()`, and `forPage()`. |
| Create | `app/Domain/Content/Exceptions/PlacementCapacityExceededException.php` | Domain exception extending `ContentException` thrown on capacity overflow. |
| Create | `app/Domain/Content/Services/EnsurePlacementCapacity.php` | Domain service validating schedule overlaps against `Placement::maxCount()`. |
| Create | `app/Domain/Content/Actions/CountLiveBlocksForPlacement.php` | Domain action counting currently live blocks for a placement. |
| Modify | `app/Domain/Content/Actions/PublishContentBlock.php` | Adds advisory lock and capacity guard before publishing. |
| Modify | `app/Domain/Content/Actions/EnableContentBlock.php` | Adds advisory lock and capacity guard before enabling. |
| Modify | `app/Domain/Content/Actions/UpdateContentBlock.php` | Adds advisory lock and conditional capacity guard when schedule changes on active block. |
| Modify | `app/Domain/Content/Actions/GetBlockServingStatus.php` | Restores non-live check first, then verifies top-N ranking among servable candidates with array cache. |
| Modify | `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php` | Adds page tabs (`all`, `home`, `global_layout`) with badge counts and `history: false`. |
| Modify | `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` | Adds Page badge column, removes SelectFilter 'page', scopes Placement filter options to active tab. |
| Modify | `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` | Implements disabled placement select until page chosen, cascading selection, and domain capacity hint. |
| Modify | `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php` | Catches `PlacementCapacityExceededException`, sends danger notification, and halts action. |
| Modify | `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php` | Catches `PlacementCapacityExceededException` in `handleRecordUpdate` and halts save. |
| Modify | `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php` | Handles form error mapping and data preparation for edit page. |
| Create | `docs/adr/0004-live-capacity-enforcement.md` | Architectural Decision Record documenting live capacity enforcement and amending D8. |
| Modify | `ARCHITECTURE.md` | Adds decision D10 in Section 12. |
| Create | `tests/Unit/Domain/Content/Enums/PlacementPageTest.php` | Unit tests for `PlacementPage` cases and `Placement` page mapping. |
| Modify | `tests/Unit/Domain/Content/Enums/PlacementTest.php` | Pins `maxCount()` across all 9 placements. |
| Create | `tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php` | Tests concurrency, schedule overlap algorithms, expired windows, and self-exclusion. |
| Modify | `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php` | Tests top-N capacity ranking, array caching, and non-live corrupt payload preservation. |
| Modify | `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php` | Tests conditional schedule capacity checking and advisory lock ordering. |
| Modify | `tests/Feature/Domain/Content/ServingHealthIntegrityTest.php` | Tests serving health integrity under capacity constraints. |
| Create | `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php` | Tests Filament transition and edit handling for capacity exceptions. |
| Create | `tests/Feature/Admin/ContentBlock/ContentBlockCreateCascadeTest.php` | Tests Create form cascading reactivity, disabled placement state, and reset behaviors. |
| Modify | `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php` | Tests page tabs, tab-scoped placement filter, and Page badge column. |
| Modify | `tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php` | Resolves test block capacity collisions in state tests. |
| Modify | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` | Updates form structure assertions for cascading create/edit. |
| Modify | `tests/Feature/Admin/ContentBlock/HomepageAdminWorkflowTest.php` | Updates workflow assertions with page field selection. |

---

## 5. Target State & Invariants

### 5.1 PlacementPage Enum
- Located at `app/Domain/Content/Enums/PlacementPage.php`.
- Strict typing, backed enum string:
  ```php
  enum PlacementPage: string
  {
      case GLOBAL_LAYOUT = 'global_layout';
      case HOME = 'home';
  }
  ```
- Methods:
  - `label(): string`: returns `'Global Layout'` for `GLOBAL_LAYOUT`, `'Homepage'` for `HOME`.

### 5.2 Placement Enum Extensions
- Add to `app/Domain/Content/Enums/Placement.php`:
  - `page(): PlacementPage`:
    - Returns `PlacementPage::GLOBAL_LAYOUT` for `ANNOUNCEMENT_BAR`.
    - Returns `PlacementPage::HOME` for the other 8 placements.
  - `shortLabel(): string`: returns stripped display label when scoped under page:
    - `ANNOUNCEMENT_BAR`: `'Announcement Bar'`
    - `HOME_HERO_CAROUSEL`: `'Hero Carousel'`
    - `HOME_HERO_SIDE`: `'Hero Side Banner'`
    - `HOME_SERVICE_STRIP`: `'Service Strip'`
    - `HOME_FEATURED_ASPECTS_HEADER`: `'Featured Aspects Header'`
    - `HOME_FEATURED_ASPECTS_BANNER`: `'Featured Aspects Banner'`
    - `HOME_TOP_SELECTION_HEADER`: `'Top Selection Header'`
    - `HOME_TRENDING_HEADER`: `'Trending Header'`
    - `HOME_BRAND_STRIP`: `'Brand Strip'`
  - `static forPage(PlacementPage $page): array`: returns all `Placement` cases where `$placement->page() === $page`.
- `Placement::maxCount()` is the sole source of placement multiplicity truth. `BlockDefinition` does not declare multiplicity.

### 5.3 Exception Class
- Located at `app/Domain/Content/Exceptions/PlacementCapacityExceededException.php`.
- Extends `App\Domain\Content\Exceptions\ContentException`.
- Constructor:
  ```php
  public function __construct(
      public readonly Placement $placement,
      public readonly int $maxCount,
      public readonly int $activeCount,
  ) {
      parent::__construct(
          "Placement '{$placement->value}' cannot exceed {$maxCount} concurrent live block(s); {$activeCount} currently scheduled or active."
      );
  }
  ```

### 5.4 PostgreSQL Placement Advisory Locking
To prevent concurrent race conditions across transactions publishing, enabling, or updating blocks in the same placement, each mutating Action serializes on a transaction-level PostgreSQL advisory lock.
- **Lock Statement**: `SELECT pg_advisory_xact_lock(?);` via `DB::statement()`.
- **Key Derivation**: A signed 32-bit integer derived deterministically from the placement string:
  ```php
  $lockKey = (int) (crc32('placement:' . $placement->value) & 0x7FFFFFFF);
  ```
- **Lock Ordering**:
  1. Transaction-scoped Placement Advisory Lock: `SELECT pg_advisory_xact_lock($lockKey)`
  2. Target Block Row Lock: `ContentBlock::query()->lockForUpdate()->findOrFail($id)`
  3. Candidate Row Locks (inside `EnsurePlacementCapacity`): `SELECT ... FROM content_blocks WHERE placement = ? ... FOR UPDATE`
- **Deadlock Immunity Proof**:
  All mutating Actions acquire the placement-level advisory lock as the very first database operation in the transaction before acquiring any row-level locks. Because transactions targeting the same placement queue at the advisory lock level, no transaction can hold a row lock on block A and block B simultaneously across competing placements. Advisory locks are transaction-scoped (`pg_advisory_xact_lock`) and automatically released on transaction commit or rollback.

### 5.5 Domain Capacity Enforcement and Action Specifications

#### EnsurePlacementCapacity Service
- Located at `app/Domain/Content/Services/EnsurePlacementCapacity.php`.
- Signature:
  ```php
  public function check(
      Placement $placement,
      ?CarbonInterface $startsAt,
      ?CarbonInterface $endsAt,
      ?int $ignoreBlockId = null,
  ): void
  ```
- Logic:
  1. If `$endsAt !== null && $endsAt->isPast()`, return immediately (expired schedules cannot cause live overlap).
  2. Lock and fetch all active candidate blocks in the placement:
     ```php
     $candidates = ContentBlock::query()
         ->where('placement', $placement->value)
         ->where('status', PublishStatus::PUBLISHED->value)
         ->where('is_enabled', true)
         ->when($ignoreBlockId !== null, fn ($q) => $q->where('id', '!=', $ignoreBlockId))
         ->where(function ($q) {
             $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
         })
         ->lockForUpdate()
         ->orderBy('sort_order', 'asc')
         ->orderBy('id', 'asc')
         ->get();
     ```
  3. Generate critical time boundaries to evaluate instantaneous concurrency:
     - Check `$now = now()`.
     - Check `$startsAt` (if `$startsAt !== null && $startsAt->isFuture()`).
     - Check each candidate block's `starts_at` (if in the future and within the target block's window).
  4. At each boundary instant $T$, count candidate blocks active at $T$ via `isLiveAt($T)`. If target block is active at $T$, add 1.
  5. If concurrency at any boundary exceeds `$placement->maxCount()`, throw `new PlacementCapacityExceededException($placement, $placement->maxCount(), $concurrency)`.

#### CountLiveBlocksForPlacement Action
- Located at `app/Domain/Content/Actions/CountLiveBlocksForPlacement.php`.
- Invokable action:
  ```php
  public function __invoke(Placement $placement): int
  {
      return ContentBlock::query()
          ->where('placement', $placement->value)
          ->where('status', PublishStatus::PUBLISHED->value)
          ->where('is_enabled', true)
          ->where(function ($q) {
              $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
          })
          ->where(function ($q) {
              $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
          })
          ->count();
  }
  ```

#### PublishContentBlock
- Inject `EnsurePlacementCapacity $ensureCapacity` via constructor (no default value).
- Inside transaction:
  1. Take advisory lock: `DB::statement('SELECT pg_advisory_xact_lock(?)', [(int) (crc32('placement:' . $block->placement->value) & 0x7FFFFFFF)])`.
  2. Take row lock on target block.
  3. Call `$this->ensureCapacity->check($placement, $block->starts_at, $block->ends_at, $block->id)`.
  4. Perform servability checks and update status to `PUBLISHED`.

#### EnableContentBlock
- Inject `EnsurePlacementCapacity $ensureCapacity` via constructor (no default value).
- Inside transaction:
  1. Take advisory lock on placement.
  2. Take row lock on target block.
  3. Call `$this->ensureCapacity->check($placement, $block->starts_at, $block->ends_at, $block->id)`.
  4. Perform servability checks and update `is_enabled = true`.

#### UpdateContentBlock
- Inject `EnsurePlacementCapacity $ensureCapacity` via constructor (no default value).
- Inside transaction:
  1. Take row lock on target block.
  2. Compare incoming schedule values with stored values:
     ```php
     $scheduleChanged = ($data->starts_at?->toIso8601String() !== $block->starts_at?->toIso8601String())
         || ($data->ends_at?->toIso8601String() !== $block->ends_at?->toIso8601String());
     ```
  3. If `$scheduleChanged && $block->status === PublishStatus::PUBLISHED && $block->is_enabled`:
     - Take advisory lock on placement.
     - Call `$this->ensureCapacity->check($block->placement, $data->starts_at, $data->ends_at, $block->id)`.
  4. Proceed with payload upgrades, validation, and update.

#### GetBlockServingStatus
- Candidate selection order aligns exactly with `GetLayoutContent.php` lines 68-75:
  1. If `!$block->isLive()`: return `ServingStatus::NotLive` immediately. Non-live blocks (draft, disabled, out-of-schedule, archived) do not evaluate payload servability or capacity, preserving backward compatibility.
  2. For live blocks (`isLive() === true`): evaluate payload servability via `ResolveServablePayload`. If outcome is not `Servable`, return `ServingStatus::PayloadError`.
  3. Evaluate placement capacity: query all active candidate blocks in placement ordered by `servingOrder()`, filter only servable candidates, and take top `$placement->maxCount()` IDs.
  4. If target block ID is in top IDs, return `ServingStatus::Live`; otherwise return `ServingStatus::NotLive`.
  5. Cache servable candidate ID sets per request in array cache keyed by `serving_status_candidates_{placement}_{timestamp}`.

### 5.6 Filament List Page Tabs and Table
- In `ListContentBlocks.php`:
  ```php
  public function getTabs(): array
  {
      $homePlacements = collect(Placement::forPage(PlacementPage::HOME))->map(fn ($p) => $p->value)->all();
      $globalPlacements = collect(Placement::forPage(PlacementPage::GLOBAL_LAYOUT))->map(fn ($p) => $p->value)->all();

      return [
          'all' => Tab::make('All Pages')
              ->badge(ContentBlock::query()->count()),
          'home' => Tab::make('Homepage')
              ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('placement', $homePlacements))
              ->badge(ContentBlock::query()->whereIn('placement', $homePlacements)->count()),
          'global_layout' => Tab::make('Global Layout')
              ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('placement', $globalPlacements))
              ->badge(ContentBlock::query()->whereIn('placement', $globalPlacements)->count()),
      ];
  }
  ```
- In `ContentBlockTable.php`:
  - Add `TextColumn::make('page')` with badge rendering and color mapping (`info` for Homepage, `gray` for Global Layout).
  - Remove `SelectFilter::make('page')` (tabs cover page filtering).
  - Scope `SelectFilter::make('placement')` options dynamically based on `$livewire->activeTab`:
    - When `activeTab === 'home'`, options are `Placement::forPage(PlacementPage::HOME)`.
    - When `activeTab === 'global_layout'`, options are `Placement::forPage(PlacementPage::GLOBAL_LAYOUT)`.
    - Otherwise, options are `Placement::cases()`.
  - In `ListContentBlocks`, reset `tableFilters.placement.value` when `updatedActiveTab()` fires.
  - Browser history convention: tabs and table filters follow `ARCHITECTURE.md` Section 8 (`history: false` on table state).

### 5.7 Filament Create and Edit Schemas
- In `ContentBlockForm.php`:
  - On Create:
    - Add `Select::make('page')` with options `PlacementPage::cases()`.
    - `Select::make('placement')`:
      - `disabled(fn (Get $get) => blank($get('page')))`
      - `options(...)`: populated via `Placement::forPage(...)` when `page` is set; empty array when `page` is null.
      - Displays `shortLabel()`.
      - Changing `page` clears `placement`, `type`, and `payload`.
      - Single-type placements auto-select their allowed type.
    - Capacity helper text on `placement`:
      ```php
      ->helperText(function (Get $get): ?string {
          $placementValue = $get('placement');
          if (blank($placementValue)) return null;
          $placement = Placement::tryFrom($placementValue);
          if ($placement === null) return null;
          $liveCount = app(CountLiveBlocksForPlacement::class)($placement);
          $max = $placement->maxCount();
          return "Live capacity: {$liveCount} / {$max} active block(s).";
      })
      ```
  - On Edit:
    - `page` is hydrated via `$record->placement->page()->value`.
    - `page`, `placement`, and `type` remain disabled on Edit.

---

## 6. Rules & Canonical Invariants

- **S1 & S2**: Business logic strictly lives in `EnsurePlacementCapacity`, `CountLiveBlocksForPlacement`, and transition Actions. Filament pages and forms remain thin callers.
- **S3 & S4**: No direct Model write calls from Filament; zero imports from `App\Filament` in `App\Domain`.
- **C1 & C3**: Block keys and schema versions are immutable; `Placement::maxCount()` governs capacity.
- **DB1 & DB5**: PostgreSQL transaction-level advisory locks prevent race conditions; row locks (`FOR UPDATE`) guard updates.
- **SEC4**: Sensitive state transitions and schedule adjustments log activity.
- **T1 & T2**: Feature tests cover authorization, race condition serialization, and capacity boundary conditions.

---

## 7. Data Structures & Schemas

### Advisory Lock Key Specification
- Derivation function: `crc32('placement:' . $placement->value) & 0x7FFFFFFF`
- Type: 32-bit signed integer compatible with PostgreSQL `pg_advisory_xact_lock(bigint/integer)`.
- Key mapping for Phase 1 placements:
  - `announcement_bar`: `(int) (crc32('placement:announcement_bar') & 0x7FFFFFFF)`
  - `home_hero_carousel`: `(int) (crc32('placement:home_hero_carousel') & 0x7FFFFFFF)`
  - `home_hero_side`: `(int) (crc32('placement:home_hero_side') & 0x7FFFFFFF)`
  - `home_service_strip`: `(int) (crc32('placement:home_service_strip') & 0x7FFFFFFF)`
  - `home_featured_aspects_header`: `(int) (crc32('placement:home_featured_aspects_header') & 0x7FFFFFFF)`
  - `home_featured_aspects_banner`: `(int) (crc32('placement:home_featured_aspects_banner') & 0x7FFFFFFF)`
  - `home_top_selection_header`: `(int) (crc32('placement:home_top_selection_header') & 0x7FFFFFFF)`
  - `home_trending_header`: `(int) (crc32('placement:home_trending_header') & 0x7FFFFFFF)`
  - `home_brand_strip`: `(int) (crc32('placement:home_brand_strip') & 0x7FFFFFFF)`

### Exception Response Structure
- HTTP status: Handled by Filament via `Action::halt()` or `Halt` exception.
- Notification:
  - Title: `'Capacity Exceeded'`
  - Status: `'danger'`
  - Body: Exception message detailing placement, max capacity, and current active count.

---

## 8. Public API & External Contracts

No changes to external APIs. The public endpoint `GET /api/v1/content/layout` continues to return the exact contract shape specified in `docs/contracts/content-api.md`. Capacity enforcement ensures that the database does not contain active schedule overlaps exceeding placement bounds.

---

## 9. Performance & Security

- **Pessimistic Advisory Locking**: Serializing capacity validation per placement prevents double-activation under high concurrent administrative operations without table-wide locking.
- **Query Optimization**: Placement advisory locks eliminate row lock contention across different placements. Candidate queries use indexed columns (`placement`, `status`, `is_enabled`, `ends_at`).
- **Array Caching**: `GetBlockServingStatus` caches servable candidate IDs per request in memory (`Cache::store('array')`), avoiding repeated database queries during table rendering.

---

## 10. Test Strategy

### 10.1 Unit Tests
- `PlacementPageTest.php`:
  - Asserts `PlacementPage::cases()` contains `GLOBAL_LAYOUT` and `HOME`.
  - Asserts `Placement::forPage()` maps placements accurately.
  - Asserts `shortLabel()` outputs concise names.
- `PlacementTest.php`:
  - Pins `Placement::maxCount()`:
    - Exactly 1 for: `announcement_bar`, `home_hero_side`, `home_featured_aspects_header`, `home_featured_aspects_banner`, `home_top_selection_header`, `home_trending_header`, `home_brand_strip`.
    - Exactly 5 for: `home_hero_carousel`.
    - Exactly 4 for: `home_service_strip`.

### 10.2 Domain Service Tests (`EnsurePlacementCapacityTest.php`)
- Staggered windows on a max-4 placement: 4 staggered active blocks pass without error.
- Overlap rejection: 5th concurrent block at any instant throws `PlacementCapacityExceededException`.
- Expired schedule: block with `ends_at <= now()` is ignored and passes.
- Non-live states: draft, disabled, and archived blocks are ignored in concurrency calculations.
- Update self-exclusion: target block ID is excluded from candidate count when updating itself.
- Advisory lock execution order: using `DB::listen`, asserts `pg_advisory_xact_lock` executes before candidate queries in `PublishContentBlock`, `EnableContentBlock`, and `UpdateContentBlock`.

### 10.3 Update Capacity Tests (`UpdateContentBlockTest.php`)
- Two legacy live blocks in singleton placement: non-schedule edit (e.g. text change) succeeds without capacity error.
- Schedule modification on legacy live block causing overlap throws `PlacementCapacityExceededException`.
- Unchanged schedule on over-capacity legacy block passes without error.

### 10.4 Serving Status Tests (`GetBlockServingStatusTest.php`)
- Status parity: asserts the set of block IDs with `ServingStatus::Live` strictly equals the block IDs returned in `GetLayoutContent` across datasets (corrupt rank #1, shadowed block, future-scheduled, disabled).
- Corrupt payload preservation: draft, disabled, or archived blocks with invalid payloads return `ServingStatus::NotLive`, matching `main` behavior.
- Top-N ranking: blocks ranked beyond `maxCount()` return `ServingStatus::NotLive`.

### 10.5 Filament Admin Tests
- `ContentBlockCreateCascadeTest.php`:
  - Placement select is disabled and empty until Page is chosen.
  - Selecting Page enables Placement select with tab-scoped `shortLabel()` options.
  - Changing Page clears Placement, Type, and Payload.
  - Livewire fill order: setting `data.page`, then `data.placement`, then `data.type`.
- `PlacementCapacityGuardsTest.php`:
  - Publishing second block in singleton placement displays `'Capacity Exceeded'` danger notification and leaves block in draft.
  - Enabling second block in singleton placement displays `'Capacity Exceeded'` notification and leaves block disabled.
  - Schedule edit on edit page overlapping active block displays `'Capacity Exceeded'` notification and halts save.
  - Activating second block succeeds once first block is disabled or archived.
- `ListContentBlocksTableTest.php`:
  - Table filters records by page tabs (`all`, `home`, `global_layout`) with badge counts.
  - Placement filter options match the active tab and reset when switching tabs.
  - Renders Page badge column.

---

## 11. Documentation & ADR

### 11.1 Architectural Decision Record
Create `docs/adr/0004-live-capacity-enforcement.md`:
- **Title**: Live Capacity Enforcement via Placement Advisory Locks
- **Status**: Accepted
- **Context**: Decision D8 originally enforced `maxCount` solely at API query time. This permitted admins to publish multiple active blocks in singleton placements, leading to confusing admin UX where active blocks were shadowed and never displayed.
- **Decision**: Amend D8 with D10. Enforce `maxCount` during `PublishContentBlock`, `EnableContentBlock`, and schedule modifications in `UpdateContentBlock`. Transactions serialize per placement using PostgreSQL advisory locks. D8 serving order remains the tie-breaker for legacy over-capacity data.

### 11.2 ARCHITECTURE.md Update
Update Section 12 table with decision D10:
`| D10 | Live capacity limits (maxCount) are enforced at publish, enable, and schedule edit via advisory lock and EnsurePlacementCapacity, amending D8. |`

---

## 12. Commit Order

### Commit 1: Enums and Domain Capacity Service
- `app/Domain/Content/Enums/PlacementPage.php`
- `app/Domain/Content/Enums/Placement.php`
- `app/Domain/Content/Exceptions/PlacementCapacityExceededException.php`
- `app/Domain/Content/Services/EnsurePlacementCapacity.php`
- `app/Domain/Content/Actions/CountLiveBlocksForPlacement.php`
- `docs/adr/0004-live-capacity-enforcement.md`
- `ARCHITECTURE.md`
- `tests/Unit/Domain/Content/Enums/PlacementPageTest.php`
- `tests/Unit/Domain/Content/Enums/PlacementTest.php`
- `tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php`

### Commit 2: Domain Action Guards and Serving Status
- `app/Domain/Content/Actions/PublishContentBlock.php`
- `app/Domain/Content/Actions/EnableContentBlock.php`
- `app/Domain/Content/Actions/UpdateContentBlock.php`
- `app/Domain/Content/Actions/GetBlockServingStatus.php`
- `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php`
- `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php`
- `tests/Feature/Domain/Content/ServingHealthIntegrityTest.php`
- `tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php`

### Commit 3: Filament Admin UX and Capacity Guards
- `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php`
- `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php`
- `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php`
- `tests/Feature/Admin/ContentBlock/ContentBlockCreateCascadeTest.php`
- `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`
- `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php`
- `tests/Feature/Admin/ContentBlock/HomepageAdminWorkflowTest.php`

---

## 13. Risks & Open Questions

### 13.1 D8 Reversal and Decision D10
- **D8 Original Statement**: Decision D8 specified that `maxCount` was an API output limit, allowing multiple blocks to be published with tie-breaking choosing the winner.
- **D8 Reversal / Amendment**: Enforcing capacity at publish, enable, and schedule update prevents admins from creating conflicting active blocks. Legacy over-capacity blocks in the database remain intact and editable for non-schedule attributes, with D8 tie-breaking governing API output. Decision D10 formally amends D8.

### 13.2 Serving Status Parity for Corrupt Non-Live Blocks
- Checking payload servability before `isLive()` in `GetBlockServingStatus` would alter the serving status of draft or disabled blocks with corrupt payloads from `NotLive` to `PayloadError`. To avoid breaking contracts and existing tests, `GetBlockServingStatus` evaluates `!$block->isLive()` first, returning `ServingStatus::NotLive`. Payload health is evaluated only for live blocks.

### 13.3 Deferred Scope Findings for Future Steps
The following findings are recorded as deferred to subsequent steps:
- **Deferred to P1-UI6 (Block Preview)**:
  - **P1**: `@props` directive is ignored in `@include` partials; Blade components or variable fallbacks are required.
  - **P2**: Singleton preview must replace placement contents rather than append.
  - **P3**: Preview rendering depends on temporary storefront demo harness (`DEMO-TEARDOWN.md`).
  - **P4**: Preview endpoints require panel authentication and unauthenticated 403 test coverage.
  - **P5**: Iframe sandbox with `allow-same-origin` provides no security isolation; an escaping test is required.
  - **P6**: Ephemeral preview state synchronization trigger, block form inclusion in manifest, safe fallback for corrupt DB payloads, and dedicated Action for preview cache writes.
- **Deferred to P1-UI7 (Menus)**:
  - Menu management redesign and hierarchical tree preview panel.

---

## 14. Acceptance Criteria

- **AC-1**: `Placement::maxCount()` is pinned via unit test across all 9 placements (1 for 7 singletons, 5 for carousel, 4 for service strip).
- **AC-2**: `PlacementPage` enum provides `GLOBAL_LAYOUT` and `HOME`. `Placement::forPage()` and `shortLabel()` map accurately.
- **AC-3**: `PublishContentBlock`, `EnableContentBlock`, and `UpdateContentBlock` take a PostgreSQL advisory lock on placement CRC32 before taking row locks.
- **AC-4**: `EnsurePlacementCapacity` prevents concurrent schedule overlaps beyond `Placement::maxCount()` on publish, enable, and schedule update.
- **AC-5**: Non-schedule updates to over-capacity legacy blocks succeed without capacity exceptions.
- **AC-6**: `GetBlockServingStatus` candidate selection matches `GetLayoutContent` order, ranking shadowed blocks as `ServingStatus::NotLive` and preserving `NotLive` for non-live corrupt blocks.
- **AC-7**: List Content Blocks page provides `All Pages`, `Homepage`, and `Global Layout` tabs with dynamic badge counts.
- **AC-8**: Content Block table includes Page badge column and scopes Placement filter options to active tab.
- **AC-9**: Create Content Block form disables Placement select until Page is chosen; changing Page resets Placement, Type, and Payload.
- **AC-10**: Capacity hint on Create and Edit displays live count from `CountLiveBlocksForPlacement`.
- **AC-11**: `PlacementCapacityExceededException` caught in Filament transition actions and edit save, displaying danger notification and halting execution.
- **AC-12**: Full test suite passes green via `composer check` with zero regressions.
