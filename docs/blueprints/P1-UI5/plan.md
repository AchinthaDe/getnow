# [P1-UI5 | PLAN v2 | Gemini]

## Change Log

| Finding ID | Reviewer Issue | Resolution in Plan v2 | Section Changed |
|---|---|---|---|
| **B1** | `ContentBlockPreviewController::isolated()` and `page()` declared return type `View` while returning `header()`, which throws `BadMethodCallException`. | Changed return type of `isolated()` and `page()` to `Illuminate\Http\Response` returning `response()->view(...)->header('X-Robots-Tag', 'noindex, nofollow')`. | **Section 5.6** |
| **B2** | 1. Mutating `readonly` `LayoutResponseData` in place is invalid. 2. `storefront.home` requires `$featured`, `$newProducts`, `$categories`, `$cartCount` duplicated from `DemoHomeController`. 3. Cached ephemeral payload may fail validation mid-edit. | 1. Specified constructing new `LayoutBlockData` and `LayoutData` instances. 2. Added public static helper methods to `DemoHomeController` (`getFeaturedProducts()`, `getNewProducts()`, `getCategories()`) reused by `ContentBlockPreviewController`. 3. Caught `ContentException` and `ValidationException` during ephemeral payload upgrade, falling back to stored payload. | **Section 5.6** |
| **B3** | Overriding `protected static string $view` on `EditContentBlock` and `EditMenu` risks breaking Filament 5 `EditRecord` schema, actions, and D7 notice. | Eliminated `edit-content-block.blade.php` and `edit-menu.blade.php`. Specified native Filament 5 Schema `Grid::make(12)` layouts inside `ContentBlockForm` and `MenuForm` embedding `View::make(...)` components for the preview panes. | **Section 4, Section 5.7, Section 12** |
| **B4** | In `GetBlockServingStatus`, checking SQL `take($maxCount)` before checking payload servability causes corrupt rank #1 blocks to shadow valid rank #2 blocks and masks payload errors. | Refactored `GetBlockServingStatus`: evaluate payload servability via `ResolveServablePayload` first; then check `isLive()`; and iterate `servingOrder()` candidates filtering only servable blocks up to `maxCount()`. | **Section 5.5** |
| **B5** | `PlacementPageTest.php` and `GetBlockServingStatusTest.php` missing from Section 4; `PlacementPageTest.php` path was in `tests/Feature` instead of `tests/Unit`. | Added both test files to Section 4 File Manifest table; positioned `PlacementPageTest.php` under `tests/Unit/Domain/Content/Enums/PlacementPageTest.php` matching existing enums. | **Section 4, Section 9, Section 10.1, Section 12** |
| **M1** | Section 5 omitted interface/variable specs for the 6 shared storefront partials and `isolated-preview.blade.php`. | Added Section 5.8 with exact `@props` and variable specifications for all 6 partials and `isolated-preview.blade.php`, including `data-preview-target="true"` rendering. | **Section 5.8** |
| **M2** | Compatibility of `placement_display` and `placement` labels in existing tests; capacity hint query and `Halt` on update exception unspecified. | Specified keeping `label()` when `page` is null and on `placement_display` for test compatibility, using `shortLabel()` when scoped by `page`. Specified exact live capacity hint query and `throw new Halt` in `EditContentBlock::handleRecordUpdate`. | **Section 5.7** |
| **M3** | Dependency injection for `EnsurePlacementCapacity` was missing in Actions; execution ordering in `UpdateContentBlock` unstated; candidate ordering in capacity check non-deterministic. | Injected `EnsurePlacementCapacity` into constructors of `PublishContentBlock`, `EnableContentBlock`, and `UpdateContentBlock`. In `UpdateContentBlock`, placed capacity check inside transaction after validation. Added `->orderBy('sort_order', 'asc')->orderBy('id', 'asc')` in `EnsurePlacementCapacity::check()`. | **Section 5.4, Section 5.5** |
| **m1** | `MenuForm` reactivity on fields not specified; dynamic `itemLabel` could fail on null or empty values. | Added `->live(onBlur: true)` to repeater inputs. Specified null-safe formatting with default fallbacks for `label`, `url`, and child counts. | **Section 5.7** |
| **m2** | Layer B mutation spot-checks and Layer C manual verification missing from Section 10. | Added Section 10.8 (`MUT-1` through `MUT-4`) and Section 10.9 (Layer C Manual Verification click path) to Section 10. | **Section 10.8, Section 10.9** |

---

## 1. Understanding

The goal is to enhance the Filament Admin UX and domain capacity safeguards across Content Blocks and Navigation Menus by:
1. Introducing the `PlacementPage` enum, filtering list tabs and table columns by page, and establishing a cascading Page -> Placement -> Type selection flow on block creation.
2. Enforcing code-defined live capacity limits (`maxCount`) across singleton and multi-block placements during `PublishContentBlock`, `EnableContentBlock`, and `UpdateContentBlock`, preventing concurrent active schedule overlaps beyond capacity via `PlacementCapacityExceededException`, while refining `GetBlockServingStatus` so shadowed blocks rank as `ServingStatus::NotLive`.
3. Extracting storefront layout and homepage markup into shared Blade partials under `resources/views/storefront/partials/blocks/` ("One Render Path"), providing an isolated single-block preview on `ViewContentBlock`, and an Atelier-style split-screen live preview with viewport and mode controls on `EditContentBlock` powered by ephemeral cache state.
4. Redesigning `MenuForm` with collapsible top-level summary cards, compact horizontal input grids, and an inset sub-items section, complemented by a real-time hierarchical Menu Structure tree preview panel on `EditMenu`.

### Out of Scope Restated
- Installing `safi/filament-atelier` or any third-party Composer or NPM packages (Rule X1).
- Adding database migrations or modifying existing database schemas or columns (Rule DB2).
- Altering the public JSON response shape of `GET /api/v1/content/layout` (Rule K8).
- Introducing a 3rd nesting depth level to Menus (depth remains strictly 2).
- Adding block drafts, version histories, or revision tables (deferred to Phase 2).

---

## 2. Sources Read

| Source File or Document Section | What in it constrains P1-UI5 |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Business logic restricted to `app/Domain/Content/Actions/` and Services; thin Filament resources; zero direct model writes in Filament; strict types; final classes; no raw HTML/CSS/JS fields; no external packages without approval; definition of done. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) Sections 5, 6, 7, 8, 12 | Monolith structure; 9 Phase 1 placements and block definitions; D7 live edit semantics; D8 tie-breaking (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`); D9 pessimistic locks on transitions; admin panel routing conventions. |
| [rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | Canonical rule register (`E1, S1..S7, C1..C8, K1..K9, DB1..DB6, SEC1..SEC5, T1..T5, G1..G3, X1..X7`). |
| [BlockDefinition.php](file:///c:/dev/getnow-cms/app/Domain/Content/Contracts/BlockDefinition.php) | Contract defining block types, schema versions, data classes, and payload upgrades. Requires addition of `defaultMaxLivePerPlacement(): int`. |
| [AnnouncementBarBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php) | Announcement bar block definition returning type `announcement_bar` and schema version 2. |
| [PromoSlideBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php) | Promo slide block definition returning type `promo_slide` and schema version 1. |
| [FeatureBannerBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php) | Feature banner block definition returning type `feature_banner` and schema version 1. |
| [ServiceItemBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php) | Service item block definition returning type `service_item` and schema version 1. |
| [SectionHeaderBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php) | Section header block definition returning type `section_header` and schema version 1. |
| [BrandStripBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php) | Brand strip block definition returning type `brand_strip` and schema version 1. |
| [Placement.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php) | Enum defining 9 placement cases, `allowedBlockTypes()`, `maxCount()`, and `label()`. |
| [PublishStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/PublishStatus.php) | Enum defining `DRAFT`, `PUBLISHED`, `ARCHIVED`. |
| [ServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/ServingStatus.php) | Enum defining `Live`, `NotLive`, `PayloadError`. |
| [ContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php) | Eloquent model for blocks, casting `placement` with `TolerantEnum`, scopes `visible()` and `servingOrder()`, methods `isLiveAt()` and `isLive()`. |
| [PublishContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php) | Transition action locking block row, verifying servability, setting `PUBLISHED`. Requires capacity guard invocation. |
| [EnableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php) | Transition action locking block row, verifying servability, setting `is_enabled = true`. Requires capacity guard invocation. |
| [DisableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/DisableContentBlock.php) | Transition action setting `is_enabled = false`. |
| [ArchiveContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/ArchiveContentBlock.php) | Transition action setting `ARCHIVED` and `is_enabled = false`. |
| [CreateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/CreateContentBlock.php) | Domain action validating placement compatibility, schedule bounds, and creating draft block. |
| [UpdateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php) | Domain action locking block row, validating and upgrading payload, updating schedule and sort order. Requires capacity guard invocation when active. |
| [GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php) | Evaluates block serving status; requires verification that block ranks within top `maxCount()` under D8 order before returning `Live`. |
| [GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php) | Assembles `LayoutResponseData` using visible blocks in D8 serving order bounded by `maxCount()`. Used for storefront layout assembly. |
| [GetUpgradedBlockPayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetUpgradedBlockPayload.php) | Resolves and validates block payload sequentially through block upgraders. |
| [ResolveServablePayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Services/ResolveServablePayload.php) | Validates servability and handles failure caching and logging. |
| [ContentBlockResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource.php) | Resource registering `index`, `create`, `view`, and `edit` routes keyed by `public_id`. |
| [ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php) | List page component configuring pagination history. Requires `getTabs()` for page filtering. |
| [CreateContentBlock.php (Page)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/CreateContentBlock.php) | Create page component delegating record creation to domain Action. |
| [EditContentBlock.php (Page)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) | Edit page component handling hydration and updates. Provides live preview synchronization. |
| [ViewContentBlock.php (Page)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php) | View page component displaying read-only metadata and payload. Requires isolated block preview section at top. |
| [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) | Form schema. Requires Page -> Placement -> Type cascading select, native split-screen Grid on edit, and backward compatibility for null page. |
| [ContentBlockTable.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php) | Table schema. Requires Page badge column and Page select filter. |
| [ContentBlockTransitionActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php) | Shared transition actions. Requires catching `PlacementCapacityExceededException` and dispatching danger notifications. |
| [InteractsWithContentBlockActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php) | Form data preparation helper converting datetime inputs and re-mapping validation errors. |
| [ContentBlockPolicy.php](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/ContentBlockPolicy.php) | Defines abilities `view`, `create`, `update`, `publish`, `archive`, `enable`, `disable`. |
| [MenuResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource.php) | Resource registering `index` and `edit` routes keyed by `public_id`. |
| [ListMenus.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/ListMenus.php) | List page for menus. |
| [EditMenu.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) | Edit page for menus. |
| [MenuForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) | Menu form schema. Requires collapsible summary cards, compact horizontal grids, inset sub-items, and native split-screen Grid with Menu Structure preview. |
| [MenuTable.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Tables/MenuTable.php) | Table schema for menus. |
| [DemoHomeController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Storefront/DemoHomeController.php) | Controller for `GET /` assembling `GetLayoutContent` and rendering `storefront.home`. Requires shared sample catalog methods. |
| [storefront.blade.php](file:///c:/dev/getnow-cms/resources/views/layouts/storefront.blade.php) | Storefront layout view containing announcement bar markup. |
| [home.blade.php](file:///c:/dev/getnow-cms/resources/views/storefront/home.blade.php) | Storefront home view containing promo carousel, hero side, service strip, section headers, collection banner, brand strip. |
| [promo-carousel.blade.php](file:///c:/dev/getnow-cms/resources/views/components/promo-carousel.blade.php) | Blade component rendering promo carousel slides and controls. |
| [ContentException.php](file:///c:/dev/getnow-cms/app/Domain/Content/Exceptions/ContentException.php) | Base domain exception class required by `DomainArchTest`. |
| [DomainArchTest.php](file:///c:/dev/getnow-cms/tests/Arch/DomainArchTest.php) | Enforces that all exceptions in `App\Domain\Content\Exceptions` extend `ContentException`. |
| [FilamentWritesTest.php](file:///c:/dev/getnow-cms/tests/Arch/FilamentWritesTest.php) | Enforces zero direct model write calls (`::create`, `->update`, `->save`, `->delete`) in `app/Filament`. |
| [TestFailedUpgradeBlock.php](file:///c:/dev/getnow-cms/tests/Support/TestFailedUpgradeBlock.php) | Test stub implementing `BlockDefinition`. |
| [ServingHealthIntegrityTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/ServingHealthIntegrityTest.php) | Contains anonymous test classes implementing `BlockDefinition`. |
| [GetUpgradedBlockPayloadTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php) | Contains `FakeBlockDefinition` implementing `BlockDefinition`. |
| [GetLayoutContentTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php) | Contains anonymous test class implementing `BlockDefinition`. |
| [PlacementTest.php](file:///c:/dev/getnow-cms/tests/Unit/Domain/Content/Enums/PlacementTest.php) | Unit tests verifying placement cases, labels, and allowed block types. |
| [routes/web.php](file:///c:/dev/getnow-cms/routes/web.php) | Web routes file where admin preview routes will be mounted. |

---

## 3. Verified Current State

### 3.1 BlockDefinition Contract and Implementations
- Contract: [BlockDefinition.php:9-25](file:///c:/dev/getnow-cms/app/Domain/Content/Contracts/BlockDefinition.php#L9-L25)
  ```php
  interface BlockDefinition {
      public function typeKey(): string;
      public function schemaVersion(): int;
      public function dataClass(): string;
      public function upgradePayload(int $fromVersion, array $payload): array;
  }
  ```
- Implementations:
  - `AnnouncementBarBlock`: [AnnouncementBarBlock.php:9-42](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php#L9-L42) (`typeKey(): 'announcement_bar'`, `schemaVersion(): 2`)
  - `PromoSlideBlock`: [PromoSlideBlock.php:9-34](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php#L9-L34) (`typeKey(): 'promo_slide'`, `schemaVersion(): 1`)
  - `FeatureBannerBlock`: [FeatureBannerBlock.php:9-34](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php#L9-L34) (`typeKey(): 'feature_banner'`, `schemaVersion(): 1`)
  - `ServiceItemBlock`: [ServiceItemBlock.php:9-34](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php#L9-L34) (`typeKey(): 'service_item'`, `schemaVersion(): 1`)
  - `SectionHeaderBlock`: [SectionHeaderBlock.php:9-34](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php#L9-L34) (`typeKey(): 'section_header'`, `schemaVersion(): 1`)
  - `BrandStripBlock`: [BrandStripBlock.php:9-34](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php#L9-L34) (`typeKey(): 'brand_strip'`, `schemaVersion(): 1`)
  - Test stubs implementing `BlockDefinition`:
    - `TestFailedUpgradeBlock`: [TestFailedUpgradeBlock.php:11-45](file:///c:/dev/getnow-cms/tests/Support/TestFailedUpgradeBlock.php#L11-L45)
    - `ServingHealthIntegrityTest.php`: line 23 (`test_upgrade_block`) and line 111 (`test_validating_upgrade_block`)
    - `GetUpgradedBlockPayloadTest.php`: line 31 (`FakeBlockDefinition`)
    - `GetLayoutContentTest.php`: line 232 (`other_type`)

### 3.2 Placement Enum
- Path: [Placement.php:9-58](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php#L9-L58)
  - Cases: `ANNOUNCEMENT_BAR`, `HOME_HERO_CAROUSEL`, `HOME_HERO_SIDE`, `HOME_SERVICE_STRIP`, `HOME_CATEGORIES_HEADER`, `HOME_FEATURED_HEADER`, `HOME_COLLECTION_BANNER`, `HOME_NEW_ARRIVALS_HEADER`, `HOME_BRAND_STRIP`.
  - Methods: `allowedBlockTypes(): array<int, string>`, `maxCount(): int` (`5` for `HOME_HERO_CAROUSEL`, `4` for `HOME_SERVICE_STRIP`, `1` for all other 7 cases), `label(): string`.

### 3.3 Domain Actions and Serving Status
- `PublishContentBlock`: [PublishContentBlock.php:15-57](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php#L15-L57)
  - Signature: `public function __invoke(int $id, ?User $causer = null): ContentBlock`
  - Throws `IllegalStateTransitionException` if archived; early return if already published; throws `UnservablePayloadException` if not servable; locks row with `lockForUpdate()`.
- `EnableContentBlock`: [EnableContentBlock.php:15-57](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php#L15-L57)
  - Signature: `public function __invoke(int $id, ?User $causer = null): ContentBlock`
  - Throws `IllegalStateTransitionException` if archived; early return if already enabled; throws `UnservablePayloadException` if not servable; locks row with `lockForUpdate()`.
- `UpdateContentBlock`: [UpdateContentBlock.php:19-155](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php#L19-L155)
  - Signature: `public function __invoke(int $id, UpdateContentBlockData $data, array $rawPayload, ?User $causer = null): ContentBlock`
  - Throws `ValidationException`, `IllegalStateTransitionException`, `UnknownBlockTypeException`, `UnsupportedSchemaVersionException`; locks row with `lockForUpdate()`.
- `GetBlockServingStatus`: [GetBlockServingStatus.php:14-47](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php#L14-L47)
  - Signature: `public function __invoke(ContentBlock $block): BlockServingStatusResult`
  - Evaluates servability and caches outcome via `Cache::store('array')`.
- `GetLayoutContent`: [GetLayoutContent.php:24-173](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L24-L173)
  - Signature: `public function __invoke(): LayoutResponseData`

### 3.4 Filament Resources and Schemas
- `ContentBlockForm`: [ContentBlockForm.php:24-130](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php#L24-L130)
  - `placement`: `Select::make('placement')->options(...)->required()->live()->afterStateUpdated(...)->visibleOn('create')`
  - `type`: `Select::make('type')->options(...)->required()->live()->afterStateUpdated(...)->visibleOn('create')`
  - `placement_display`: `TextEntry::make('placement_display')->label('Placement')->visibleOn('edit')`
  - `type_display`: `TextEntry::make('type_display')->label('Type')->visibleOn('edit')`
- `ContentBlockTable`: [ContentBlockTable.php:29-157](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php#L29-L157)
  - Columns: `placement`, `type`, `status`, `is_enabled`, `starts_at`, `ends_at`, `serving_status`.
  - Filters: `hide_archived`, `placement`, `type`, `status`, `is_enabled`.
- `ContentBlockTransitionActions`: [ContentBlockTransitionActions.php:21-155](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php#L21-L155)
  - Methods: `publish(): Action`, `archive(): Action`, `enable(): Action`, `disable(): Action`.
  - Internal execution: `executeTransition(...)` catches `UnservablePayloadException` and `IllegalStateTransitionException`.
- `EditContentBlock`: [EditContentBlock.php:25-90](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php#L25-L90)
  - Handles update in `handleRecordUpdate(Model $record, array $data): Model`.
- `ViewContentBlock`: [ViewContentBlock.php:24-89](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php#L24-L89)
  - Builds form schema containing `Metadata` section and `Payload` section.
- `MenuForm`: [MenuForm.php:14-68](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php#L14-L68)
  - Contains `TextInput::make('title')`, `Repeater::make('items')` with nested `Repeater::make('children')`.
- `EditMenu`: [EditMenu.php:21-126](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php#L21-L126)
  - Hydrates tree via `GetMenuTree` and persists changes via `UpdateMenu`.

### 3.5 Storefront Views and Controller
- `DemoHomeController`: [DemoHomeController.php:12-209](file:///c:/dev/getnow-cms/app/Http/Controllers/Storefront/DemoHomeController.php#L12-L209)
  - Invokes `GetLayoutContent` and renders `storefront.home` with `layout`, `featured`, `newProducts`, `categories`, `cartCount`.
- Storefront Blade Views:
  - `resources/views/layouts/storefront.blade.php`: Announcement bar rendered directly at lines 17-44.
  - `resources/views/storefront/home.blade.php`: Renders hero grid, carousel, hero side, service strip, category header, featured header, collection banner, new arrivals header, brand strip.

### 3.6 Interaction of Existing Tests with Live Capacity Guards
- `HomepageBlocksCreateAndServeTest.php`: Uses isolated placement tests in a clean database; creates 1 block per placement, publishes it, disables it, and re-enables it. Never exceeds `maxCount()`.
- `HomepageAdminWorkflowTest.php`: Creates 5 blocks as `DRAFT` (status `DRAFT` is not active, so capacity is not consumed). Then edits an existing carousel slide's sort order (capacity remains 3 of 5). Never exceeds `maxCount()`.
- `PublishContentBlockTest.php`: Creates and publishes 1 draft block per test. Never creates 2 concurrent published blocks in the same placement.
- `EnableContentBlockTest.php`: Blocks created with `disabled()` state have default status `DRAFT`, not `PUBLISHED`. Enabling a draft block leaves status as `DRAFT`, which is not live, so capacity check is bypassed.
- `ContentSeeder.php`: Seeds exactly 1 announcement bar, 3 promo slides (limit 5), 1 hero side, 4 service items (limit 4), 1 categories header, 1 featured header, 1 collection banner, 1 new arrivals header, 1 brand strip. All conform to `maxCount()`.

---

## 4. File Manifest

| File Path | New or Modify | Layer | One-Line Responsibility | Rule IDs Satisfied |
|---|---|---|---|---|
| `app/Domain/Content/Enums/PlacementPage.php` | New | Domain | Backed string enum declaring `GLOBAL_LAYOUT` and `HOME` page groupings with human-readable labels. | C1, C2, C3 |
| `app/Domain/Content/Enums/Placement.php` | Modify | Domain | Adds `page()`, `shortLabel()`, and static `forPage()` methods to the placement enum. | C1, C2, C3, K5 |
| `app/Domain/Content/Contracts/BlockDefinition.php` | Modify | Domain | Declares `defaultMaxLivePerPlacement(): int` on the block definition contract. | C1, C2, S5 |
| `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php` | Modify | Domain | Implements `defaultMaxLivePerPlacement(): 1`. | C1, C2, S5 |
| `app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php` | Modify | Domain | Implements `defaultMaxLivePerPlacement(): 5`. | C1, C2, S5 |
| `app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php` | Modify | Domain | Implements `defaultMaxLivePerPlacement(): 1`. | C1, C2, S5 |
| `app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php` | Modify | Domain | Implements `defaultMaxLivePerPlacement(): 4`. | C1, C2, S5 |
| `app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php` | Modify | Domain | Implements `defaultMaxLivePerPlacement(): 1`. | C1, C2, S5 |
| `app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php` | Modify | Domain | Implements `defaultMaxLivePerPlacement(): 1`. | C1, C2, S5 |
| `app/Domain/Content/Exceptions/PlacementCapacityExceededException.php` | New | Domain | Domain exception thrown when concurrent active blocks exceed placement live capacity. | C1, C2, S7 |
| `app/Domain/Content/Services/EnsurePlacementCapacity.php` | New | Domain | Pure domain service evaluating schedule window overlaps against placement `maxCount()` under row locks. | C1, C2, S1, S3 |
| `app/Domain/Content/Actions/PublishContentBlock.php` | Modify | Domain | Injects `EnsurePlacementCapacity` and checks capacity before transitioning block to `PUBLISHED`. | C1, C2, S1, S3 |
| `app/Domain/Content/Actions/EnableContentBlock.php` | Modify | Domain | Injects `EnsurePlacementCapacity` and checks capacity before transitioning block to `is_enabled = true`. | C1, C2, S1, S3 |
| `app/Domain/Content/Actions/UpdateContentBlock.php` | Modify | Domain | Injects `EnsurePlacementCapacity` and checks capacity before saving schedule changes on an active block. | C1, C2, S1, S3 |
| `app/Domain/Content/Actions/GetBlockServingStatus.php` | Modify | Domain | Refines serving status calculation to check servability first and verify block is within top `maxCount()`. | C1, C2, S1, S3 |
| `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php` | Modify | Filament | Adds top tabs for `All Pages`, `Homepage`, and `Global Layout` with badge counts. | C1, C2, S2, S6 |
| `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` | Modify | Filament | Adds `Page` badge column and `Page` select filter. | C1, C2, S2, S6 |
| `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` | Modify | Filament | Adds cascading Page -> Placement -> Type selection on create, live capacity hint, and native edit split-screen Grid. | C1, C2, S2, S6 |
| `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php` | Modify | Filament | Catches `PlacementCapacityExceededException` on Publish and Enable and displays danger notification. | C1, C2, S2, S4 |
| `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php` | Modify | Filament | Catches `PlacementCapacityExceededException` on update with `Halt`; provides Livewire properties for preview mode and device. | C1, C2, S2, S4, D7 |
| `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php` | Modify | Filament | Injects the isolated block preview section at the top of the View page schema. | C1, C2, S2, S6 |
| `resources/views/storefront/partials/blocks/announcement-bar.blade.php` | New | Storefront | Shared Blade partial rendering the storewide announcement bar. | K3, K4 |
| `resources/views/storefront/partials/blocks/hero-carousel.blade.php` | New | Storefront | Shared Blade partial rendering the promo slides carousel or single slide preview. | K3, K4 |
| `resources/views/storefront/partials/blocks/feature-banner.blade.php` | New | Storefront | Shared Blade partial rendering hero side banner or mid-page collection banner. | K3, K4 |
| `resources/views/storefront/partials/blocks/service-strip.blade.php` | New | Storefront | Shared Blade partial rendering full service strip or an isolated service item card. | K3, K4 |
| `resources/views/storefront/partials/blocks/section-header.blade.php` | New | Storefront | Shared Blade partial rendering section headers. | K3, K4 |
| `resources/views/storefront/partials/blocks/brand-strip.blade.php` | New | Storefront | Shared Blade partial rendering the brand logo and link strip. | K3, K4 |
| `resources/views/layouts/storefront.blade.php` | Modify | Storefront | Replaces raw announcement bar markup with `@include('storefront.partials.blocks.announcement-bar')`. | K3, K4 |
| `resources/views/storefront/home.blade.php` | Modify | Storefront | Replaces raw block markup sections with `@include` calls to shared block partials. | K3, K4 |
| `app/Http/Controllers/Storefront/DemoHomeController.php` | Modify | Storefront | Adds public static helper methods for sample products and categories to prevent duplication in preview controller. | S2 |
| `app/Http/Controllers/Admin/ContentBlockPreviewController.php` | New | HTTP | Authenticated admin controller returning `Response` for isolated block and full-page previews with security headers. | C1, C2, SEC4, S2 |
| `routes/web.php` | Modify | Routing | Registers admin preview routes protected by `auth` middleware and policy authorization. | SEC4 |
| `resources/views/filament/admin/resources/content-block-resource/pages/isolated-preview.blade.php` | New | View | Minimal HTML view shell embedding isolated block storefront partial and assets. | K3 |
| `resources/views/filament/admin/resources/content-block-resource/components/live-preview-pane.blade.php` | New | View | Blade component embedding the live preview toolbar and iframe inside `ContentBlockForm` on edit. | K3 |
| `resources/views/filament/admin/resources/content-block-resource/components/view-isolated-preview.blade.php` | New | View | Blade component embedding the isolated preview iframe on `ViewContentBlock`. | K3 |
| `app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php` | Modify | Filament | Collapsible item cards, dynamic summary labels, compact multi-column grids, inset sub-items, and live tree preview column. | C1, C2, S2, S6 |
| `app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php` | Modify | Filament | Provides Livewire helper for menu tree preview synchronization. | C1, C2, S2, S6 |
| `resources/views/filament/admin/resources/menu-resource/components/menu-tree-preview.blade.php` | New | View | Blade component rendering the hierarchical menu items tree, badges, and capacity counters. | K3 |
| `tests/Unit/Domain/Content/Enums/PlacementPageTest.php` | New | Test | Unit tests for `PlacementPage` enum, `Placement::page()`, `shortLabel()`, and `forPage()`. | T1 |
| `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php` | New | Test | Feature tests verifying that corrupt blocks fail first and valid blocks beyond `maxCount` return `NotLive`. | T1 |
| `tests/Support/TestFailedUpgradeBlock.php` | Modify | Test | Implements `defaultMaxLivePerPlacement(): 1`. | T1 |
| `tests/Feature/Domain/Content/ServingHealthIntegrityTest.php` | Modify | Test | Implements `defaultMaxLivePerPlacement(): 1` on anonymous block classes. | T1 |
| `tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php` | Modify | Test | Implements `defaultMaxLivePerPlacement(): 1` on `FakeBlockDefinition`. | T1 |
| `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | Modify | Test | Implements `defaultMaxLivePerPlacement(): 1` on anonymous block class. | T1 |
| `tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php` | New | Test | Unit and domain feature tests for live capacity calculations across singleton and multi-block placements. | T1, T4 |
| `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php` | New | Test | Feature tests for capacity enforcement in Filament Publish, Enable, and schedule edits. | T1, T2 |
| `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` | New | Test | Feature tests for isolated block and full-page previews, authorization, and headers. | T1, SEC4 |
| `tests/Feature/Admin/ContentBlock/ContentBlockCreateCascadeTest.php` | New | Test | Feature tests for Page -> Placement -> Type selection cascade and backward compatibility. | T1, T2 |
| `tests/Feature/Admin/Menu/MenuStructurePreviewTest.php` | New | Test | Feature tests for MenuForm collapsible items, compact layouts, and Menu Structure tree preview. | T1, T2 |
| `ARCHITECTURE.md` | Modify | Documentation | Updates Section 1 progress, Section 6 placement definitions, and Section 12 decisions. | X7 |

Total files touched or added: 50 files.

---

## 5. Interface Specs

### 5.1 Enums

#### `App\Domain\Content\Enums\PlacementPage`
- Namespace: `App\Domain\Content\Enums`
- Type: `enum PlacementPage: string`
- Cases:
  - `GLOBAL_LAYOUT = 'global_layout'`
  - `HOME = 'home'`
- Methods:
  - `public function label(): string`:
    ```php
    return match ($this) {
        self::GLOBAL_LAYOUT => 'Global Layout',
        self::HOME => 'Homepage',
    };
    ```

#### `App\Domain\Content\Enums\Placement` (Modifications)
- Additions to `App\Domain\Content\Enums\Placement`:
  - `public function page(): PlacementPage`:
    ```php
    return match ($this) {
        self::ANNOUNCEMENT_BAR => PlacementPage::GLOBAL_LAYOUT,
        self::HOME_HERO_CAROUSEL,
        self::HOME_HERO_SIDE,
        self::HOME_SERVICE_STRIP,
        self::HOME_CATEGORIES_HEADER,
        self::HOME_FEATURED_HEADER,
        self::HOME_COLLECTION_BANNER,
        self::HOME_NEW_ARRIVALS_HEADER,
        self::HOME_BRAND_STRIP => PlacementPage::HOME,
    };
    ```
  - `public function shortLabel(): string`:
    ```php
    return match ($this) {
        self::ANNOUNCEMENT_BAR => 'Announcement Bar',
        self::HOME_HERO_CAROUSEL => 'Hero Carousel',
        self::HOME_HERO_SIDE => 'Hero Side Banner',
        self::HOME_SERVICE_STRIP => 'Service Strip',
        self::HOME_CATEGORIES_HEADER => 'Categories Header',
        self::HOME_FEATURED_HEADER => 'Featured Products Header',
        self::HOME_COLLECTION_BANNER => 'Collection Banner',
        self::HOME_NEW_ARRIVALS_HEADER => 'New Arrivals Header',
        self::HOME_BRAND_STRIP => 'Brand Strip',
    };
    ```
  - `public static function forPage(PlacementPage $page): array`:
    ```php
    /** @return array<int, self> */
    return array_values(array_filter(
        self::cases(),
        fn (self $placement): bool => $placement->page() === $page
    ));
    ```

### 5.2 BlockDefinition Contract & Classes
- In `App\Domain\Content\Contracts\BlockDefinition`:
  ```php
  public function defaultMaxLivePerPlacement(): int;
  ```
- Implementation on all 6 block definition classes:
  - `AnnouncementBarBlock`: `public function defaultMaxLivePerPlacement(): int { return 1; }`
  - `PromoSlideBlock`: `public function defaultMaxLivePerPlacement(): int { return 5; }`
  - `FeatureBannerBlock`: `public function defaultMaxLivePerPlacement(): int { return 1; }`
  - `ServiceItemBlock`: `public function defaultMaxLivePerPlacement(): int { return 4; }`
  - `SectionHeaderBlock`: `public function defaultMaxLivePerPlacement(): int { return 1; }`
  - `BrandStripBlock`: `public function defaultMaxLivePerPlacement(): int { return 1; }`

### 5.3 Exception: `PlacementCapacityExceededException`
- Namespace: `App\Domain\Content\Exceptions`
- Class: `final class PlacementCapacityExceededException extends ContentException`
- Constructor:
  ```php
  public function __construct(
      public readonly Placement $placement,
      public readonly int $maxCount,
      public readonly string $conflictingPublicId,
      string $message = '',
  ) {
      $resolvedMessage = $message !== ''
          ? $message
          : "Placement [{$placement->label()}] allows at most {$maxCount} live block(s). Conflicting block: [{$conflictingPublicId}].";

      parent::__construct($resolvedMessage);
  }
  ```

### 5.4 Capacity Service: `EnsurePlacementCapacity`
- Namespace: `App\Domain\Content\Services`
- Class: `final class EnsurePlacementCapacity`
- Public methods:
  ```php
  /**
   * Evaluates concurrent active schedule overlaps within the placement.
   * Throws PlacementCapacityExceededException if activating or updating $block exceeds $placement->maxCount().
   *
   * @throws PlacementCapacityExceededException
   */
  public function check(
      ContentBlock $targetBlock,
      ?CarbonInterface $startsAt,
      ?CarbonInterface $endsAt
  ): void
  ```
- Method implementation details:
  - If `$targetBlock->placement` is null, returns immediately.
  - `$now = now();`
  - If `$endsAt !== null && $endsAt->lte($now)`, schedule window is in the past; return immediately without error.
  - Determine target block interval:
    - `$targetStart = $startsAt !== null ? max($startsAt->getTimestamp(), $now->getTimestamp()) : $now->getTimestamp();`
    - `$targetEnd = $endsAt !== null ? $endsAt->getTimestamp() : PHP_INT_MAX;`
  - Fetch concurrent candidate rows in the same placement under row locks ordered deterministically by sort order and primary key:
    ```php
    $existingBlocks = ContentBlock::query()
        ->where('placement', $targetBlock->placement->value)
        ->where('id', '!=', $targetBlock->id)
        ->where('status', PublishStatus::PUBLISHED->value)
        ->where('is_enabled', true)
        ->where(function (Builder $q) use ($now) {
            $q->whereNull('ends_at')
              ->orWhere('ends_at', '>', $now);
        })
        ->orderBy('sort_order', 'asc')
        ->orderBy('id', 'asc')
        ->lockForUpdate()
        ->get();
    ```
  - For each `$existing` block, compute its active interval:
    - `$existStart = $existing->starts_at !== null ? max($existing->starts_at->getTimestamp(), $now->getTimestamp()) : $now->getTimestamp();`
    - `$existEnd = $existing->ends_at !== null ? $existing->ends_at->getTimestamp() : PHP_INT_MAX;`
  - Overlap condition between target and existing:
    `max($targetStart, $existStart) < min($targetEnd, $existEnd)`
  - Find all existing blocks that overlap with the target block interval.
  - If `$targetBlock->placement->maxCount() === 1`:
    - If count of overlapping existing blocks is $\ge 1$, throw `PlacementCapacityExceededException` identifying `$targetBlock->placement`, limit `1`, and the first overlapping block's `public_id`.
  - If `$targetBlock->placement->maxCount() > 1`:
    - Collect all distinct boundary timestamps $\{ t \}$ within $[targetStart, targetEnd)$ consisting of:
      `$targetStart`, and every `$existStart` where `$existStart >= $targetStart && $existStart < $targetEnd`.
    - At each point $t$, count active blocks (target block plus every existing block where `$existStart <= t && t < $existEnd`).
    - If active count $> $targetBlock->placement->maxCount(), throw `PlacementCapacityExceededException` identifying the placement, limit, and the conflicting existing block's `public_id`.

### 5.5 Action Updates

#### `PublishContentBlock`
- Constructor:
  ```php
  public function __construct(
      private readonly ResolveServablePayload $resolveServablePayload,
      private readonly EnsurePlacementCapacity $ensurePlacementCapacity = new EnsurePlacementCapacity(),
  ) {}
  ```
- Updates in `__invoke(int $id, ?User $causer = null): ContentBlock`:
  - When `$block->is_enabled === true`:
    Invoke `$this->ensurePlacementCapacity->check($block, $block->starts_at, $block->ends_at)`.
  - If capacity check passes, proceed with setting `status = PUBLISHED`.

#### `EnableContentBlock`
- Constructor:
  ```php
  public function __construct(
      private readonly ResolveServablePayload $resolveServablePayload,
      private readonly EnsurePlacementCapacity $ensurePlacementCapacity = new EnsurePlacementCapacity(),
  ) {}
  ```
- Updates in `__invoke(int $id, ?User $causer = null): ContentBlock`:
  - When `$block->status === PublishStatus::PUBLISHED`:
    Invoke `$this->ensurePlacementCapacity->check($block, $block->starts_at, $block->ends_at)`.
  - If capacity check passes, proceed with setting `is_enabled = true`.

#### `UpdateContentBlock`
- Constructor:
  ```php
  public function __construct(
      private readonly BlockRegistry $registry,
      private readonly EnsurePlacementCapacity $ensurePlacementCapacity = new EnsurePlacementCapacity(),
  ) {}
  ```
- Updates in `__invoke(int $id, UpdateContentBlockData $data, array $rawPayload, ?User $causer = null): ContentBlock`:
  - Inside `DB::transaction`, AFTER schedule validation (`ends_at > starts_at`) and payload validation:
    When `$block->status === PublishStatus::PUBLISHED && $block->is_enabled === true`:
    Invoke `$this->ensurePlacementCapacity->check($block, $startsAt, $endsAt)`.
  - If capacity check passes, proceed with updating schedule, sort order, and payload.

#### `GetBlockServingStatus`
- Updates in `__invoke(ContentBlock $block): BlockServingStatusResult`:
  1. Evaluate payload servability first without logging:
     ```php
     $health = ($this->resolveServablePayload)($block, log: false);
     if (! $health->isServable()) {
         return new BlockServingStatusResult(ServingStatus::PayloadError, $health->outcome);
     }
     ```
  2. Check whether block is active:
     ```php
     if (! $block->isLive() || $block->placement === null) {
         return new BlockServingStatusResult(ServingStatus::NotLive);
     }
     ```
  3. Verify whether block ranks within the top `maxCount()` servable blocks under Decision D8 order (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`):
     ```php
     $candidates = ContentBlock::visible()
         ->where('placement', $block->placement->value)
         ->servingOrder()
         ->get();

     $servableCount = 0;
     $isServed = false;
     $maxCount = $block->placement->maxCount();

     foreach ($candidates as $candidate) {
         $candHealth = ($this->resolveServablePayload)($candidate, log: false);
         if (! $candHealth->isServable()) {
             continue; // Exclude corrupt blocks from capacity slot calculation
         }

         $servableCount++;
         if ($candidate->id === $block->id) {
             $isServed = true;
             break;
         }

         if ($servableCount >= $maxCount) {
             break;
         }
     }

     if ($isServed) {
         return new BlockServingStatusResult(ServingStatus::Live, ServingHealthOutcome::Servable);
     }

     return new BlockServingStatusResult(ServingStatus::NotLive);
     ```

### 5.6 Admin Preview Controller & Routes
- Namespace: `App\Http\Controllers\Admin`
- Class: `final class ContentBlockPreviewController extends Controller`
- Dependencies: None in constructor.
- Shared catalog data extracted in `DemoHomeController`:
  - `DemoHomeController::getFeaturedProducts(): array`
  - `DemoHomeController::getNewProducts(): array`
  - `DemoHomeController::getCategories(): array`
- Methods:
  1. `public function isolated(Request $request, ContentBlock $contentBlock, GetUpgradedBlockPayload $getUpgradedBlockPayload): Response`:
     - Authorize: `abort_unless($request->user()?->can('view', $contentBlock), 403);`
     - Read cached ephemeral state if present:
       ```php
       $cacheKey = "content_block_preview:{$request->user()->id}:{$contentBlock->public_id}";
       $cachedData = Cache::get($cacheKey);
       $payload = $contentBlock->payload;

       if (is_array($cachedData) && isset($cachedData['payload']) && is_array($cachedData['payload'])) {
           try {
               $upgraded = $getUpgradedBlockPayload($contentBlock->type, $contentBlock->schema_version, $cachedData['payload']);
               $payload = $upgraded->toArray();
           } catch (ContentException|ValidationException) {
               // Fall back to stored payload if cached draft fails validation during typing
               $upgraded = $getUpgradedBlockPayload($contentBlock->type, $contentBlock->schema_version, $contentBlock->payload);
               $payload = $upgraded->toArray();
           }
       } else {
           $upgraded = $getUpgradedBlockPayload($contentBlock->type, $contentBlock->schema_version, $contentBlock->payload);
           $payload = $upgraded->toArray();
       }
       ```
     - Construct `LayoutBlockData`:
       ```php
       $blockData = new LayoutBlockData(
           id: $contentBlock->public_id,
           type: $contentBlock->type,
           version: $contentBlock->schema_version,
           data: $payload,
       );
       ```
     - Return:
       ```php
       return response()
           ->view('filament.admin.resources.content-block-resource.pages.isolated-preview', [
               'block' => $blockData,
               'placement' => $contentBlock->placement,
           ])
           ->header('X-Robots-Tag', 'noindex, nofollow');
       ```
  2. `public function page(Request $request, ContentBlock $contentBlock, GetLayoutContent $getLayoutContent, GetUpgradedBlockPayload $getUpgradedBlockPayload): Response`:
     - Authorize: `abort_unless($request->user()?->can('view', $contentBlock), 403);`
     - Read cached ephemeral state if present with fallback to stored payload:
       ```php
       $cacheKey = "content_block_preview:{$request->user()->id}:{$contentBlock->public_id}";
       $cachedData = Cache::get($cacheKey);
       $payload = $contentBlock->payload;

       if (is_array($cachedData) && isset($cachedData['payload']) && is_array($cachedData['payload'])) {
           try {
               $upgraded = $getUpgradedBlockPayload($contentBlock->type, $contentBlock->schema_version, $cachedData['payload']);
               $payload = $upgraded->toArray();
           } catch (ContentException|ValidationException) {
               $upgraded = $getUpgradedBlockPayload($contentBlock->type, $contentBlock->schema_version, $contentBlock->payload);
               $payload = $upgraded->toArray();
           }
       } else {
           $upgraded = $getUpgradedBlockPayload($contentBlock->type, $contentBlock->schema_version, $contentBlock->payload);
           $payload = $upgraded->toArray();
       }
       ```
     - Assemble layout via `$layoutResponse = $getLayoutContent();`
     - Replace or insert target block inside `$layoutResponse->data->placements`:
       ```php
       $targetBlockData = new LayoutBlockData(
           id: $contentBlock->public_id,
           type: $contentBlock->type,
           version: $contentBlock->schema_version,
           data: $payload,
       );

       $placements = $layoutResponse->data->placements;
       $placementKey = $contentBlock->placement->value;
       $existing = $placements[$placementKey] ?? [];

       $newPlacementBlocks = [];
       $replaced = false;
       foreach ($existing as $b) {
           if ($b->id === $contentBlock->public_id) {
               $newPlacementBlocks[] = $targetBlockData;
               $replaced = true;
           } else {
               $newPlacementBlocks[] = $b;
           }
       }
       if (! $replaced) {
           $newPlacementBlocks[] = $targetBlockData;
       }
       $placements[$placementKey] = $newPlacementBlocks;

       $layout = new LayoutData(
           settings: $layoutResponse->data->settings,
           placements: $placements,
           menus: $layoutResponse->data->menus,
       );
       ```
     - Return:
       ```php
       return response()
           ->view('storefront.home', [
               'layout' => $layout,
               'featured' => DemoHomeController::getFeaturedProducts(),
               'newProducts' => DemoHomeController::getNewProducts(),
               'categories' => DemoHomeController::getCategories(),
               'cartCount' => 0,
               'previewTargetId' => $contentBlock->public_id,
           ])
           ->header('X-Robots-Tag', 'noindex, nofollow');
       ```
- Route registrations in `routes/web.php`:
  ```php
  Route::middleware(['web', 'auth'])->prefix('admin/content-blocks')->name('admin.content-blocks.')->group(function () {
      Route::get('{contentBlock:public_id}/preview/isolated', [ContentBlockPreviewController::class, 'isolated'])
          ->name('preview.isolated');
      Route::get('{contentBlock:public_id}/preview/page', [ContentBlockPreviewController::class, 'page'])
          ->name('preview.page');
  });
  ```

### 5.7 Filament Components and Pages

#### `ContentBlockForm`
- Create page cascading selector:
  - `Select::make('page')`:
    - Label: `Page`
    - Options: `collect(PlacementPage::cases())->mapWithKeys(fn (PlacementPage $p) => [$p->value => $p->label()])->all()`
    - Reactivity: `live()`
    - Persistence: `dehydrated(false)`
    - `afterStateUpdated(function (Set $set): void { $set('placement', null); $set('type', null); $set('payload', []); })`
    - `visibleOn('create')`
  - `Select::make('placement')`:
    - Options closure:
      - When `$get('page')` is populated, returns `Placement::forPage(PlacementPage::from($get('page')))` mapped to `$p->shortLabel()`.
      - When `$get('page')` is null (such as in programmatic test calls `fillForm(['placement' => '...'])`), returns all `Placement::cases()` mapped to `$p->label()` ensuring full compatibility with existing test assertions.
    - Reactivity: `live()`
    - `afterStateUpdated`:
      ```php
      function (Get $get, Set $set, ?string $state): void {
          if (blank($state)) {
              $set('type', null);
              $set('payload', []);
              return;
          }
          $placement = Placement::tryFrom($state);
          if ($placement !== null) {
              $set('page', $placement->page()->value);
              $allowedTypes = $placement->allowedBlockTypes();
              if (count($allowedTypes) === 1) {
                  $set('type', $allowedTypes[0]);
              } else {
                  $set('type', null);
              }
          }
          $set('payload', []);
      }
      ```
  - `Select::make('type')`:
    - Options scoped to allowed block types for the selected placement.
- Edit and View components:
  - `TextEntry::make('page_display')`: Label `Page`, displays `$record->placement?->page()->label()`.
  - `TextEntry::make('placement_display')`: Label `Placement`, displays `$record->placement?->label() ?? ''` (preserving existing test assertions).
  - `TextEntry::make('type_display')`: Label `Type`, displays title-cased block type.
  - Live capacity hint closure displayed on `placement_display` / `page_display`:
    ```php
    ->helperText(function (?ContentBlock $record): string {
        if ($record === null || $record->placement === null) {
            return '';
        }
        $liveCount = ContentBlock::visible()
            ->where('placement', $record->placement->value)
            ->count();
        $maxCount = $record->placement->maxCount();

        return "{$liveCount} / {$maxCount} live in this placement";
    })
    ```
- Native Split-Screen Layout on Edit:
  - Form schema wrapped inside `Grid::make(12)`:
    - Left column (`columnSpan(['default' => 12, 'xl' => 6])` on edit, `12` on create): Contains form sections (`starts_at`, `ends_at`, `sort_order`, payload group, Callout notice).
    - Right column (`columnSpan(['default' => 12, 'xl' => 6])`):
      `Section::make('Live Preview')->schema([ View::make('filament.admin.resources.content-block-resource.components.live-preview-pane') ])->visibleOn('edit')`

#### `ContentBlockTable`
- Columns:
  - `TextColumn::make('page')`:
    - Label `Page`
    - Badge
    - State closure: `fn (ContentBlock $record): string => $record->placement?->page()->label() ?? '—'`
    - Positioned as the first column preceding `placement`.
- Filters:
  - `SelectFilter::make('page')`:
    - Options: `collect(PlacementPage::cases())->mapWithKeys(fn (PlacementPage $p) => [$p->value => $p->label()])->all()`
    - Query:
      ```php
      $query->whereIn('placement', collect(Placement::forPage(PlacementPage::from($state)))->pluck('value'));
      ```

#### `ListContentBlocks`
- Header tabs via `getTabs(): array`:
  - `'all' => Tab::make('All Pages')->badge(ContentBlock::count())`
  - `'home' => Tab::make('Homepage')->modifyQueryUsing(fn ($q) => $q->whereIn('placement', collect(Placement::forPage(PlacementPage::HOME))->pluck('value')))->badge(...)`
  - `'global_layout' => Tab::make('Global Layout')->modifyQueryUsing(fn ($q) => $q->whereIn('placement', collect(Placement::forPage(PlacementPage::GLOBAL_LAYOUT))->pluck('value')))->badge(...)`

#### `ViewContentBlock`
- Top schema component in `form(Schema $schema)`:
  - `Section::make('Isolated Block Preview')`
    - Contains `View::make('filament.admin.resources.content-block-resource.components.view-isolated-preview')`
    - Embeds isolated preview iframe pointing to `/admin/content-blocks/{record->public_id}/preview/isolated` with `sandbox="allow-same-origin allow-scripts"`.
    - Renders status badges alongside the iframe: Page, Placement, Status, and Serving Status.

#### `EditContentBlock`
- Component properties:
  - `public string $previewMode = 'page';` (Options: `'page'`, `'isolated'`)
  - `public string $previewDevice = 'desktop';` (Options: `'desktop'`, `'tablet'`, `'mobile'`)
  - `public string $previewToken = '';`
- Component methods:
  - `public function setPreviewMode(string $mode): void`: Sets mode and updates preview iframe.
  - `public function setPreviewDevice(string $device): void`: Sets viewport width (`100%`, `768px`, `375px`).
  - `public function syncPreviewState(): void`: Writes `$this->data['payload']` and `$this->data['sort_order']` into `Cache::put("content_block_preview:{$userId}:{$publicId}", ..., now()->addMinutes(15))`, sets `$this->previewToken = Str::random(6)`.
- Updates in `handleRecordUpdate(Model $record, array $data): Model`:
  - Catch `PlacementCapacityExceededException`:
    ```php
    try {
        return app(UpdateContentBlockAction::class)((int) $record->getKey(), $actionData, $rawPayload, $causer);
    } catch (ValidationException $e) {
        $this->throwRemappedValidationException($e);
    } catch (PlacementCapacityExceededException $e) {
        Notification::make()
            ->danger()
            ->title('Capacity Exceeded')
            ->body($e->getMessage())
            ->send();
        throw new Halt;
    }
    ```

#### `MenuForm`
- `Repeater::make('items')`:
  - `->collapsible()`
  - `->collapsed()`
  - `->collapseAllAction(fn ($action) => $action->label('Collapse All'))`
  - `->expandAllAction(fn ($action) => $action->label('Expand All'))`
  - `->live()`
  - Inputs inside item:
    - `TextInput::make('label')->live(onBlur: true)->columnSpan(4)`
    - `TextInput::make('url')->live(onBlur: true)->columnSpan(4)`
    - `Toggle::make('opens_in_new_tab')->label('New tab')->live()->columnSpan(2)`
    - `Toggle::make('is_enabled')->label('Enabled')->live()->columnSpan(2)`
  - Dynamic `itemLabel` closure on `items`:
    ```php
    ->itemLabel(function (array $state): string {
        $label = filled($state['label'] ?? null) ? (string) $state['label'] : 'Untitled item';
        $url = filled($state['url'] ?? null) ? (string) $state['url'] : 'No URL';
        $children = isset($state['children']) && is_array($state['children']) ? $state['children'] : [];
        $childCount = count($children);
        $countSuffix = $childCount > 0 ? " ({$childCount} " . ($childCount === 1 ? 'sub-item' : 'sub-items') . ')' : '';
        $disabledSuffix = array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled'] ? ' [Disabled]' : '';

        return "{$label}  •  {$url}{$countSuffix}{$disabledSuffix}";
    })
    ```
  - Sub-items repeater `Repeater::make('children')`:
    - Inset within `Section::make('Dropdown Sub-Items (Level 2)')->collapsible()`
    - `->live()`
    - Sub-item inputs: `label` (4), `url` (4), `opens_in_new_tab` (2), `is_enabled` (2) all configured with `->live(onBlur: true)` or `->live()`.
    - Dynamic `itemLabel` closure on `children`:
      ```php
      ->itemLabel(function (array $state): string {
          $label = filled($state['label'] ?? null) ? (string) $state['label'] : 'Untitled sub-item';
          $url = filled($state['url'] ?? null) ? (string) $state['url'] : 'No URL';
          $disabledSuffix = array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled'] ? ' [Disabled]' : '';

          return "{$label}  •  {$url}{$disabledSuffix}";
      })
      ```
- Native Split-Screen Layout on `EditMenu`:
  - `MenuForm::form(Schema $schema)` wrapped in `Grid::make(12)`:
    - Left column (`columnSpan(['default' => 12, 'xl' => 8])`): Contains `title` and `items` repeater.
    - Right column (`columnSpan(['default' => 12, 'xl' => 4])`):
      `Section::make('Menu Structure Preview')->schema([ View::make('filament.admin.resources.menu-resource.components.menu-tree-preview') ])`

### 5.8 Shared Storefront Blade Partials Specification
All 6 shared block Blade partials accept structured `LayoutBlockData` instances:

1. `resources/views/storefront/partials/blocks/announcement-bar.blade.php`:
   - `@props(['block', 'previewTarget' => false])`
   - Expects `$block`: `?LayoutBlockData`.
   - If `$previewTarget` is true, attributes include `data-preview-target="true" id="preview-target-block"`.
   - Renders announcement bar markup accessing `$block->data['text']`, `$block->data['secondary_text']`, `$block->data['locale_text']`, `$block->data['link_url']`, `$block->data['tone']`.

2. `resources/views/storefront/partials/blocks/hero-carousel.blade.php`:
   - `@props(['slides' => [], 'previewSlide' => null, 'previewTarget' => false])`
   - Expects `$slides`: `array<int, LayoutBlockData>`.
   - If `$previewSlide !== null`, renders single slide preview wrapped in carousel markup.
   - If `$previewTarget` is true, attaches `data-preview-target="true" id="preview-target-block"`.
   - Otherwise renders `<x-promo-carousel :slides="$slides"/>`.

3. `resources/views/storefront/partials/blocks/feature-banner.blade.php`:
   - `@props(['block', 'placement' => null, 'previewTarget' => false])`
   - Expects `$block`: `?LayoutBlockData`, `$placement`: `?string` (`'home_hero_side'` or `'home_collection_banner'`).
   - If `$placement === 'home_collection_banner'`, renders collection banner layout (`<div class="collection-banner">`).
   - If `$placement === 'home_hero_side'` (or in isolated preview), renders hero side banner layout (`<article class="hero hero-side">`).
   - If `$previewTarget` is true, attaches `data-preview-target="true" id="preview-target-block"`.

4. `resources/views/storefront/partials/blocks/service-strip.blade.php`:
   - `@props(['items' => [], 'previewItem' => null, 'previewTarget' => false])`
   - Expects `$items`: `array<int, LayoutBlockData>`.
   - If `$previewItem !== null`, renders `<div class="service-strip">` wrapping only that single item.
   - If `$previewTarget` is true, attaches `data-preview-target="true" id="preview-target-block"`.
   - Renders icon, title, subtitle, link.

5. `resources/views/storefront/partials/blocks/section-header.blade.php`:
   - `@props(['block', 'previewTarget' => false])`
   - Expects `$block`: `?LayoutBlockData`.
   - If `$previewTarget` is true, attaches `data-preview-target="true" id="preview-target-block"`.
   - Renders `<div class="section-heading">` with eyebrow, title, cta_label, cta_url.

6. `resources/views/storefront/partials/blocks/brand-strip.blade.php`:
   - `@props(['block', 'previewTarget' => false])`
   - Expects `$block`: `?LayoutBlockData`.
   - If `$previewTarget` is true, attaches `data-preview-target="true" id="preview-target-block"`.
   - Renders `<section class="brand-strip container">` with eyebrow, title, and brand links.

7. `resources/views/filament/admin/resources/content-block-resource/pages/isolated-preview.blade.php`:
   - `@props(['block', 'placement'])`
   - Expects `$block`: `LayoutBlockData`, `$placement`: `Placement`.
   - Minimal HTML shell loading `@vite(['resources/css/storefront.css', 'resources/js/storefront.js'])`.
   - Embeds the corresponding partial matching `$placement` and `$block->type`.

---

## 6. Data and Migrations

- Tables: None.
- Columns: None.
- Migrations: None.
- Backward compatibility: Zero database changes. All enums, limits, and relationships operate on existing columns (`content_blocks.placement`, `menus`, `menu_items`).

---

## 7. Behavior and Flows

### 7.1 Page and Placement Cascade Flow (Block Create)
1. Admin navigates to `/admin/content-blocks/create`.
2. Form initializes with `page` select containing `Global Layout` and `Homepage`.
3. Admin selects `Homepage`:
   - Livewire updates; `placement` options are filtered to the 8 Homepage placements (`Hero Carousel`, `Hero Side Banner`, `Service Strip`, etc.).
   - `type` and `payload` remain cleared.
4. Admin selects `Placement = Hero Side Banner`:
   - `placement` triggers `afterStateUpdated`.
   - `count(Placement::HOME_HERO_SIDE->allowedBlockTypes()) === 1` (`feature_banner`).
   - System auto-selects `type = 'feature_banner'`, revealing Feature Banner payload fields immediately without requiring manual type selection.
5. Admin fills payload and clicks `Create`:
   - Block is persisted as `status = DRAFT` via `CreateContentBlock`.

### 7.2 Capacity Enforcement on Publish Flow
1. Block $B_2$ is in placement `announcement_bar` (`maxCount = 1`, `status = DRAFT`, `is_enabled = true`).
2. Block $B_1$ in placement `announcement_bar` is already `status = PUBLISHED` and `is_enabled = true` with overlapping schedule.
3. Admin clicks `Publish` on $B_2$:
   - Filament triggers `ContentBlockTransitionActions::publish()`.
   - Action calls domain `PublishContentBlock($B_2->id, $admin)`.
   - `PublishContentBlock` invokes `$this->ensurePlacementCapacity->check($B_2, $B_2->starts_at, $B_2->ends_at)`.
   - Overlap detected with $B_1$; `EnsurePlacementCapacity` throws `PlacementCapacityExceededException`.
   - `PublishContentBlock` transaction rolls back; $B_2$ remains `DRAFT`.
   - `ContentBlockTransitionActions` catches `PlacementCapacityExceededException`, dispatches danger notification stating `Announcement Bar allows at most 1 live block. Conflicting block: [01J...]`.
4. Admin opens $B_1$ and clicks `Disable` (or `Archive`):
   - $B_1$ becomes `is_enabled = false`.
5. Admin clicks `Publish` on $B_2$:
   - Capacity check passes with zero overlapping active blocks.
   - $B_2$ status transitions to `PUBLISHED` and becomes `Live`.

### 7.3 Capacity Enforcement on Schedule Update Flow
1. Block $B_2$ is in `announcement_bar` (`PUBLISHED`, `is_enabled = true`), scheduled for next week (`starts_at = next Monday`).
2. Block $B_1$ is currently active in `announcement_bar` (`ends_at = next Sunday`).
3. Admin edits $B_2$ schedule to start today (`starts_at = now()`):
   - Livewire calls `save` on `EditContentBlock`.
   - `EditContentBlock` calls `UpdateContentBlock`.
   - Under row locks, `EnsurePlacementCapacity` evaluates active overlap between $[now, \infty)$ and $B_1$'s interval $[now, next Sunday)$.
   - Overlap exceeds capacity (`maxCount = 1`); `PlacementCapacityExceededException` is thrown.
   - `EditContentBlock` catches exception, presents danger notification, halts save with `throw new Halt`, and leaves database schedule unchanged.

### 7.4 Atelier-Style Live Edit Preview Flow
1. Admin opens `/admin/content-blocks/{public_id}/edit`.
2. Left column of `Grid(12)` renders form fields; right column renders live preview toolbar and iframe.
3. Default mode is `Page Context` (full page); default device is `Desktop (100%)`.
4. Admin modifies headline text in form:
   - Livewire debounces input and updates `$this->data['payload']['text']`.
   - Component writes unsaved payload into short-lived cache key `content_block_preview:{admin_id}:{public_id}`.
   - Preview iframe reloads with token query parameter.
   - `ContentBlockPreviewController::page` reads cached payload, replaces target block in layout, wraps with `data-preview-target="true"`, and returns rendered response.
   - Preview automatically scrolls target block into view with disabled links.
   - Live database row in `content_blocks` remains untouched until admin clicks `Save`.
5. Admin toggles device to `Mobile (375px)`:
   - Preview container smoothly constrains iframe width to `375px`.
6. Admin toggles mode to `Isolated Block`:
   - Iframe switches source to `/admin/content-blocks/{public_id}/preview/isolated`, rendering only that block partial.

### 7.5 Menu Structure Preview Flow
1. Admin opens `/admin/menus/{public_id}/edit`.
2. Existing menu items load in `Repeater::make('items')` collapsed by default, displaying 1-line summary bars (`Label • URL • count sub-items`).
3. Right column of `Grid(12)` displays the sticky hierarchical Menu Structure tree.
4. Admin expands an item, types a new label, toggles `Enabled`, or drags to reorder:
   - Tree preview updates in real time to reflect the new hierarchy, badges, and capacity counters (`Top-level: X / 10`).

---

## 8. Rule Compliance Matrix

| Rule ID | Applies (Y/N) | How Satisfied, or Why N/A |
|---|---|---|
| E1 | Y | PowerShell commands run one at a time, non-interactive flags used, zero background servers left running. |
| S1 | Y | Capacity checking logic lives in `app/Domain/Content/Services/EnsurePlacementCapacity.php` and is invoked by domain Actions. |
| S2 | Y | Controllers and Filament pages only validate, authorize, call Actions or domain services, and return responses. |
| S3 | Y | `App\Domain` never imports from `App\Filament` or `App\Http`. |
| S4 | Y | Filament pages and actions never call `Model::create`, `update`, `delete`, or `save` directly. |
| S5 | Y | Block definitions remain in `app/Domain/Content/Blocks/`, form schemas in `app/Filament/Blocks/`. |
| S6 | Y | Panel code located exclusively under `app/Filament/Admin/`. |
| S7 | Y | No new top-level directories created; previews use standard `app/Http/Controllers/Admin/`. |
| C1 | Y | `declare(strict_types=1);` present in every new and modified PHP file. |
| C2 | Y | Strict parameter, return, and property typing applied across all classes; all new classes declared `final`. |
| C3 | Y | `PlacementPage` string enum created; existing enums used for state and type definitions. |
| C4 | Y | Status and enabled state changes occur exclusively through transition Actions (`PublishContentBlock`, `EnableContentBlock`). |
| C5 | Y | All persistent database state modifications go through domain Actions. |
| C6 | Y | Authorization enforced via `ContentBlockPolicy` (`view`, `update`, `publish`, `enable`); input validated via Data and Form components. |
| C7 | Y | Code will pass Pint and Larastan Level 8 with zero baseline additions. |
| C8 | Y | Comments explain architectural rationale; zero commented-out code. |
| K1 | Y | Block type keys are untouched and permanent. |
| K2 | Y | Schema versions are respected; payloads upgraded sequentially via `GetUpgradedBlockPayload`. |
| K3 | Y | Zero raw HTML, CSS, or JS input fields. |
| K4 | Y | Links validated via `SafeLink` rule (`/` relative path or `https://`). |
| K5 | Y | `PlacementPage` and `Placement` defined in code. |
| K6 | Y | Block definitions registered explicitly in `ContentServiceProvider`. |
| K7 | Y | Visibility rules strictly enforced; `GetBlockServingStatus` returns `NotLive` for shadowed blocks. |
| K8 | Y | Public Content API JSON contract remains untouched. |
| K9 | Y | N/A (no new block type added in this step). |
| DB1 | Y | Dev and tests run natively against PostgreSQL. |
| DB2 | Y | Zero database migrations added or modified. |
| DB3 | Y | N/A (no schema modifications). |
| DB4 | Y | N/A (no schema modifications). |
| DB5 | Y | External routes use `public_id` (ULID); internal incremental IDs are never exposed. |
| DB6 | Y | Database operations run on local databases; seeders remain idempotent. |
| SEC1 | Y | Zero secrets or `.env` files touched or committed. |
| SEC2 | Y | Authorization and policies are never bypassed or weakened. |
| SEC3 | Y | Zero sensitive data logged. |
| SEC4 | Y | Admin panel and preview routes protected by server-side `auth` middleware and policy gates. |
| SEC5 | Y | Transitions and state changes write audit logs via `spatie/laravel-activitylog`. |
| T1 | Y | Pest tests cover domain capacity service, Filament form cascades, preview controller, and menu tree. |
| T2 | Y | Smoke tests load list, create, edit, and view pages. |
| T3 | Y | N/A (no new block types added). |
| T4 | Y | Tests use factories and deterministic states. |
| T5 | Y | Coverage maintained; zero tests skipped or marked incomplete. |
| G1 | Y | Git branch created from main: `feat/admin-ux-and-capacity-guards`. |
| G2 | Y | Conventional Commits applied per checkpoint. |
| G3 | Y | Never push directly to main. |
| X1 | Y | Zero third-party Composer or NPM packages installed (`safi/filament-atelier` rejected per Decision D-a). |
| X2 | Y | Zero Docker, Sail, or container files created. |
| X3 | Y | Zero deployment or CI/CD infrastructure changes. |
| X4 | Y | Active scope restricted strictly to Phase 1 CMS. |
| X5 | Y | Storefront repository untouched. |
| X6 | Y | `composer check` passes with output verified before declaring complete. |
| X7 | Y | `ARCHITECTURE.md` updated with progress, placement page mappings, and capacity decisions. |

---

## 9. AC Traceability

| AC | Implementing Files | Test Files & Names | Verification Method |
|---|---|---|---|
| AC-1 | `PlacementPage.php`, `Placement.php`, `BlockDefinition.php`, all 6 Block classes | `tests/Unit/Domain/Content/Enums/PlacementPageTest.php`:<br>- `it('maps every placement to a placement page and provides shortLabel')`<br>- `it('declares defaultMaxLivePerPlacement on all block definitions')` | Assert enum cases, mappings, short labels, and contract methods. |
| AC-2 | `ListContentBlocks.php`, `ContentBlockTable.php` | `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`:<br>- `it('filters list table by page tabs with badge counts')`<br>- `it('renders page badge column and filters by page select')` | Test table rendering, badge counts, and query filtering in Livewire. |
| AC-3 | `ContentBlockForm.php`, `CreateContentBlock.php` | `tests/Feature/Admin/ContentBlock/ContentBlockCreateCascadeTest.php`:<br>- `it('cascades page to placement and auto-selects type for single-type placements')`<br>- `it('preserves backward compatibility when placement is filled without page')` | Test Livewire form cascade and compatibility with legacy test fills. |
| AC-4 | `EnsurePlacementCapacity.php`, `PublishContentBlock.php`, `EnableContentBlock.php`, `UpdateContentBlock.php`, `ContentBlockTransitionActions.php` | `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php`:<br>- `it('prevents publishing a 2nd active block in singleton placement')`<br>- `it('prevents enabling a 2nd active block in singleton placement')`<br>- `it('prevents schedule edit that overlaps existing active singleton block')`<br>- `it('allows activating 2nd block once 1st block is disabled or archived')` | Assert `PlacementCapacityExceededException` thrown and Filament danger notification sent. |
| AC-5 | `EnsurePlacementCapacity.php`, `PublishContentBlock.php`, `EnableContentBlock.php` | `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php`:<br>- `it('allows up to maxCount active blocks in multi-block placements and rejects maxCount + 1')`<br>- `it('allows non-overlapping future scheduled blocks in singleton placements')` | Assert boundary capacity limits for carousel (5) and service strip (4). |
| AC-6 | `GetBlockServingStatus.php` | `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php`:<br>- `it('returns ServingStatus::PayloadError if payload is corrupt regardless of ranking')`<br>- `it('returns ServingStatus::Live for valid rank 2 block when rank 1 has corrupt payload')`<br>- `it('returns ServingStatus::NotLive for blocks ranking beyond maxCount in D8 order')` | Test serving status outcome for shadowed blocks with valid and invalid payloads. |
| AC-7 | `resources/views/storefront/partials/blocks/*.blade.php`, `layouts/storefront.blade.php`, `home.blade.php` | `tests/Feature/Storefront/DemoHomeTest.php`:<br>- All 8 existing tests pass with 100% byte/HTML parity. | Run existing `DemoHomeTest` suite to verify zero visual or HTML regression. |
| AC-8 | `ContentBlockPreviewController.php`, `ViewContentBlock.php`, `view-isolated-preview.blade.php` | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php`:<br>- `it('renders isolated block preview iframe on ViewContentBlock across all statuses')`<br>- `it('returns 403 on preview routes for non-admin users')` | HTTP assertions on `/preview/isolated` verifying HTML rendering and security headers. |
| AC-9 | `ContentBlockPreviewController.php`, `ContentBlockForm.php`, `EditContentBlock.php`, `live-preview-pane.blade.php` | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php`:<br>- `it('renders split screen live preview with mode and viewport controls on EditContentBlock')`<br>- `it('synchronizes unsaved form edits via ephemeral cache without mutating database')` | Livewire assertions on `EditContentBlock` testing cache sync and device switching. |
| AC-10 | `MenuForm.php`, `EditMenu.php`, `menu-tree-preview.blade.php` | `tests/Feature/Admin/Menu/MenuStructurePreviewTest.php`:<br>- `it('renders top-level menu items as collapsed summary cards with dynamic labels')`<br>- `it('renders compact horizontal field rows and collapsible sub-items repeater')`<br>- `it('renders live hierarchical menu tree preview on EditMenu reflecting form state')` | Livewire tests for `EditMenu` verifying form hydration, tree rendering, and existing `MenuResourceTest`. |
| AC-11 | All files touched | Running `composer check` | Execute `composer check` and assert Pint, Larastan Level 8, and Pest all pass green. |

---

## 10. Test Plan

### 10.1 `tests/Unit/Domain/Content/Enums/PlacementPageTest.php`
- `it('maps every placement to a placement page and provides shortLabel')`:
  - Arrange: Collect `Placement::cases()`.
  - Act: Call `$placement->page()`, `$placement->shortLabel()`, and `Placement::forPage(...)`.
  - Assert: Every placement returns an instance of `PlacementPage`; `shortLabel` matches expected section name; `forPage` returns exact subsets.
- `it('declares defaultMaxLivePerPlacement on all block definitions')`:
  - Arrange: Retrieve all definitions from `BlockRegistry`.
  - Act: Call `defaultMaxLivePerPlacement()`.
  - Assert: Returns `1` for singleton blocks, `5` for `promo_slide`, `4` for `service_item`.

### 10.2 `tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php`
- `it('allows active block when below placement capacity')`:
  - Arrange: Create 1 draft `announcement_bar` block with no existing blocks.
  - Act: Call `EnsurePlacementCapacity::check($block, null, null)`.
  - Assert: No exception thrown.
- `it('throws PlacementCapacityExceededException when overlapping singleton block exists')`:
  - Arrange: Create 1 published + enabled `announcement_bar` block; create 2nd draft block.
  - Act: Call `EnsurePlacementCapacity::check($secondBlock, null, null)`.
  - Assert: Throws `PlacementCapacityExceededException` identifying placement and conflicting public ID.
- `it('allows non-overlapping future schedule in singleton placement')`:
  - Arrange: Create 1 published + enabled block ending in 2 days; create 2nd block starting in 3 days.
  - Act: Call `EnsurePlacementCapacity::check($secondBlock, now()->addDays(3), null)`.
  - Assert: No exception thrown.
- `it('enforces multi-block capacity up to maxCount')`:
  - Arrange: Create 5 published + enabled `home_hero_carousel` blocks; create 6th draft slide.
  - Act: Call `EnsurePlacementCapacity::check($sixthBlock, null, null)`.
  - Assert: Throws `PlacementCapacityExceededException`.

### 10.3 `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php`
- `it('prevents publishing a 2nd active block in singleton placement')`:
  - Arrange: Platform admin authenticated; create 1 published + enabled `home_hero_side` block; create 2nd draft block.
  - Act: Call `PublishContentBlock` action on 2nd block (or invoke `Publish` action in Livewire).
  - Assert: Action halted; danger notification sent; 2nd block status remains `DRAFT`.
- `it('prevents enabling a 2nd active block in singleton placement')`:
  - Arrange: Platform admin authenticated; create 1 published + enabled block; create 2nd published + disabled block.
  - Act: Call `EnableContentBlock` action on 2nd block.
  - Assert: Action halted; danger notification sent; 2nd block `is_enabled` remains `false`.
- `it('prevents schedule edit that overlaps existing active singleton block')`:
  - Arrange: Platform admin authenticated; create 1 active block; create 2nd published block scheduled for next week; open Edit page on 2nd block and change `starts_at` to now.
  - Act: Call `save` on `EditContentBlock`.
  - Assert: Save halted with danger notification; database record schedule unchanged.
- `it('allows activating 2nd block once 1st block is disabled')`:
  - Arrange: Disable 1st block using `DisableContentBlock`.
  - Act: Call `PublishContentBlock` on 2nd block.
  - Assert: Succeeds; 2nd block becomes `PUBLISHED` and `Live`.

### 10.4 `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php`
- `it('returns ServingStatus::PayloadError if payload is corrupt regardless of ranking')`:
  - Arrange: Create a published + enabled block with invalid payload.
  - Act: Call `GetBlockServingStatus` on the corrupt block.
  - Assert: Returns `ServingStatus::PayloadError` and corresponding health outcome.
- `it('returns ServingStatus::Live for valid rank 2 block when rank 1 has corrupt payload')`:
  - Arrange: Create block A (`sort_order = 0`, corrupt payload) and block B (`sort_order = 1`, valid payload) in `announcement_bar` (`maxCount = 1`).
  - Act: Call `GetBlockServingStatus` on block B.
  - Assert: Block B returns `ServingStatus::Live`.
- `it('returns ServingStatus::NotLive for blocks ranking beyond maxCount in D8 order')`:
  - Arrange: Create 2 published + enabled `announcement_bar` blocks with valid payloads; block A with `sort_order = 0`, block B with `sort_order = 1`.
  - Act: Call `GetBlockServingStatus` on block A and block B.
  - Assert: Block A returns `ServingStatus::Live`; Block B returns `ServingStatus::NotLive`.

### 10.5 `tests/Feature/Admin/ContentBlock/ContentBlockCreateCascadeTest.php`
- `it('cascades page to placement and auto-selects type for single-type placements')`:
  - Arrange: Platform admin authenticated; mount `CreateContentBlock` Livewire component.
  - Act: Set `data.page` to `home`; verify `placement` options are filtered; set `data.placement` to `home_hero_side`.
  - Assert: `data.type` automatically set to `feature_banner`; feature banner payload schema becomes visible.
- `it('preserves backward compatibility when placement is filled without page')`:
  - Arrange: Mount `CreateContentBlock` Livewire component.
  - Act: Call `fillForm(['placement' => 'announcement_bar', 'type' => 'announcement_bar', ...])` without providing `page`.
  - Assert: Form validates with zero form errors; `page` automatically resolves to `global_layout`.

### 10.6 `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php`
- `it('renders isolated block preview iframe on ViewContentBlock across all statuses')`:
  - Arrange: Platform admin authenticated; create block in each status (`draft`, `published`, `archived`, `disabled`).
  - Act: Hit `GET /admin/content-blocks/{public_id}/preview/isolated`.
  - Assert: Returns HTTP 200 Response; contains block markup; header `X-Robots-Tag: noindex, nofollow` present.
- `it('returns 403 on preview routes for non-admin users')`:
  - Arrange: Authenticate as standard non-admin user.
  - Act: Hit `GET /admin/content-blocks/{public_id}/preview/isolated` and `/preview/page`.
  - Assert: Returns HTTP 403 Forbidden.
- `it('synchronizes unsaved form edits via ephemeral cache without mutating database')`:
  - Arrange: Platform admin authenticated; mount `EditContentBlock` on published block; change text field; call `syncPreviewState()`.
  - Act: Inspect database row and hit `/preview/page`.
  - Assert: Database row unchanged; preview response reflects edited in-memory text.

### 10.7 `tests/Feature/Admin/Menu/MenuStructurePreviewTest.php`
- `it('renders top-level menu items as collapsed summary cards with dynamic labels')`:
  - Arrange: Platform admin authenticated; mount `EditMenu` on `main_nav`.
  - Act: Check repeater state and item labels.
  - Assert: Repeater items are collapsed; dynamic item labels display label, url, and sub-item counts.
- `it('renders live hierarchical menu tree preview on EditMenu reflecting form state')`:
  - Arrange: Mount `EditMenu`; add child item under item 1.
  - Act: Inspect preview tree data.
  - Assert: Hierarchical tree view includes the newly added child under parent with correct badges.

### 10.8 Layer B Mutation Spot-Checks (MUT-1..MUT-4)
- `MUT-1`: In `EnsurePlacementCapacity.php`, invert the overlap interval check condition `max($targetStart, $existStart) < min($targetEnd, $existEnd)` to `>`. Run `tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php`. Assert test fails with capacity exception not thrown when overlapping.
- `MUT-2`: In `GetBlockServingStatus.php`, remove the candidate servability filter in the loop before incrementing `$servableCount`. Run `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php`. Assert test fails when rank #1 has corrupt payload and rank #2 is valid.
- `MUT-3`: In `ContentBlockForm.php`, remove the fallback to `Placement::cases()` when `$get('page')` is null. Run `tests/Feature/Admin/ContentBlock/ContentBlockCreateCascadeTest.php`. Assert test fails with validation error "The selected placement is invalid."
- `MUT-4`: In `PublishContentBlock.php`, remove the call to `$this->ensurePlacementCapacity->check(...)`. Run `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php`. Assert test fails when publishing a second active block in a singleton placement.

### 10.9 Layer C Manual Verification
1. Open `/admin/content-blocks`:
   - Verify top tabs (`All Pages`, `Homepage`, `Global Layout`), `Page` badge column, and `Page` filter work.
2. Click `New Content Block`:
   - Select `Page = Homepage` -> verify `Placement` lists only the 8 Homepage placements with short labels.
   - Select `Placement = Hero Side Banner` -> verify `Type` auto-selects `feature_banner` and reveals the Feature Banner payload fields immediately.
3. Test Live Capacity Guard:
   - Create a 2nd `announcement_bar` block (saves as `Draft`).
   - Open it and click `Publish` while the seeded `announcement_bar` block is already `Live` -> expect danger notification stating `Announcement Bar` allows at most 1 live block, and status remains `Draft`.
   - Disable the first `announcement_bar` block, then click `Publish` on the 2nd block -> expect success and the 2nd block becomes `Live`.
4. Test `View` Isolated Preview & `Edit` Live Preview:
   - Open `View` on any block -> verify only that specific block renders in storefront styling at the top of the View page.
   - Open `Edit` on a block -> verify split-screen preview renders on the right, test `Desktop`, `Tablet`, `Mobile` width toggles and `Page Context` vs `Isolated Block` switch, edit title text in the form, and watch preview update without publishing changes to `GET /` until `Save` is clicked.
5. Open `/admin/menus` -> click `Edit` on `Main Navigation`:
   - Verify all 6 top-level items load collapsed as clean 1-line summary bars (`Label • URL • sub-items count`) alongside the live Menu Structure tree preview panel on the right.
   - Expand `All Categories` -> verify its fields and the 6 `Dropdown Sub-Items` render in compact horizontal rows.

---

## 11. Docs and Contract Updates

### 11.1 `ARCHITECTURE.md`
- Section 1 Progress: Tick off `P1-UI5`.
- Section 6: Document `PlacementPage` enum (`GLOBAL_LAYOUT`, `HOME`) and link multiplicity (`defaultMaxLivePerPlacement()`).
- Section 12: Add ADR summary note under Decisions referencing Decision D-d (write-time live capacity guards on Publish/Enable/Update while retaining D8 tie-break as a read-side safety net).

### 11.2 `docs/contracts/content-api.md`
- None. The public Content API v1 JSON payload remains 100% additive and unchanged (Rule K8).

---

## 12. Implementation Order

### Checkpoint A (CP-A): Capacity Guards, PlacementPage Enum & Create Cascade
- **Commit 1**: `feat(content): add PlacementPage enum and multiplicity to BlockDefinition`
  - Files:
    - `app/Domain/Content/Enums/PlacementPage.php`
    - `app/Domain/Content/Enums/Placement.php`
    - `app/Domain/Content/Contracts/BlockDefinition.php`
    - `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php`
    - `app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php`
    - `app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php`
    - `app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php`
    - `app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php`
    - `app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php`
    - `tests/Support/TestFailedUpgradeBlock.php`
    - `tests/Feature/Domain/Content/ServingHealthIntegrityTest.php`
    - `tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php`
    - `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php`
    - `tests/Unit/Domain/Content/Enums/PlacementPageTest.php`
  - Quality state: `composer check` passes green.
- **Commit 2**: `feat(content): enforce live capacity guards in domain actions and serving status`
  - Files:
    - `app/Domain/Content/Exceptions/PlacementCapacityExceededException.php`
    - `app/Domain/Content/Services/EnsurePlacementCapacity.php`
    - `app/Domain/Content/Actions/PublishContentBlock.php`
    - `app/Domain/Content/Actions/EnableContentBlock.php`
    - `app/Domain/Content/Actions/UpdateContentBlock.php`
    - `app/Domain/Content/Actions/GetBlockServingStatus.php`
    - `tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php`
    - `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php`
  - Quality state: `composer check` passes green.
- **Commit 3**: `feat(admin): add page tabs, table page badge filter, and cascading create selector`
  - Files:
    - `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php`
    - `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`
    - `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php`
    - `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php`
    - `tests/Feature/Admin/ContentBlock/PlacementCapacityGuardsTest.php`
    - `tests/Feature/Admin/ContentBlock/ContentBlockCreateCascadeTest.php`
    - `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`
  - Quality state: `composer check` passes green.
  - **Checkpoint CP-A Stop**: Submit CP-A progress and verify green checks before proceeding.

### Checkpoint B (CP-B): Shared Partials, Isolated View Preview & Atelier Edit Preview
- **Commit 4**: `refactor(storefront): extract shared storefront block Blade partials`
  - Files:
    - `resources/views/storefront/partials/blocks/announcement-bar.blade.php`
    - `resources/views/storefront/partials/blocks/hero-carousel.blade.php`
    - `resources/views/storefront/partials/blocks/feature-banner.blade.php`
    - `resources/views/storefront/partials/blocks/service-strip.blade.php`
    - `resources/views/storefront/partials/blocks/section-header.blade.php`
    - `resources/views/storefront/partials/blocks/brand-strip.blade.php`
    - `resources/views/layouts/storefront.blade.php`
    - `resources/views/storefront/home.blade.php`
    - `app/Http/Controllers/Storefront/DemoHomeController.php`
    - `tests/Feature/Storefront/DemoHomeTest.php`
  - Quality state: `composer check` passes green; existing `DemoHomeTest` suite passes with 100% parity.
- **Commit 5**: `feat(admin): add preview controller, isolated view preview, and native split-screen edit preview`
  - Files:
    - `app/Http/Controllers/Admin/ContentBlockPreviewController.php`
    - `routes/web.php`
    - `resources/views/filament/admin/resources/content-block-resource/pages/isolated-preview.blade.php`
    - `resources/views/filament/admin/resources/content-block-resource/components/live-preview-pane.blade.php`
    - `resources/views/filament/admin/resources/content-block-resource/components/view-isolated-preview.blade.php`
    - `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php`
    - `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php`
    - `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php`
    - `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php`
    - `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php`
    - `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php`
  - Quality state: `composer check` passes green.
  - **Checkpoint CP-B Stop**: Submit CP-B progress and verify green checks before proceeding.

### Checkpoint C (CP-C): Menus Admin UI Reorganization & Final Closeout
- **Commit 6**: `feat(admin): reorganize MenuForm with collapsible summary cards and live tree preview`
  - Files:
    - `app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php`
    - `app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php`
    - `resources/views/filament/admin/resources/menu-resource/components/menu-tree-preview.blade.php`
    - `tests/Feature/Admin/Menu/MenuStructurePreviewTest.php`
    - `tests/Feature/Admin/Menu/MenuResourceTest.php`
  - Quality state: `composer check` passes green.
- **Commit 7**: `docs(architecture): update Section 1 progress and capacity decisions`
  - Files:
    - `ARCHITECTURE.md`
  - Quality state: `composer check` passes green.

---

## 13. Risks, Assumptions, Conflicts

- None.

---

## 14. Dependencies

- New packages: None. (Strict adherence to Rule X1 and Decision D-a: zero new Composer or NPM dependencies).
