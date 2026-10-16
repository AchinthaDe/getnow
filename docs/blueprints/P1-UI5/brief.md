[P1-UI5 | BRIEF | Claude]

Goal: Upgrade the Filament Admin UX and domain capacity guards across Content Blocks and
  Navigation Menus by: (1) introducing `PlacementPage` page-level grouping, table tabs/filters,
  and a cascading Page -> Placement -> Type selector on block creation; (2) enforcing per-block
  and per-placement live capacity limits (`maxCount`) on Publish, Enable, and schedule edits
  so singleton placements like `announcement_bar` cannot have two blocks live at once;
  (3) adding an isolated single-block preview on `ViewContentBlock` and a Filament Atelier-style
  split-screen live preview on `EditContentBlock` using shared storefront Blade partials; and
  (4) reorganizing `MenuForm` and `EditMenu` with collapsible summary cards, compact horizontal
  field grids, and a live hierarchical Menu Tree preview panel.

Depends on (verify exact signatures from code in Section 3):
  BlockDefinition contract (`app/Domain/Content/Contracts/BlockDefinition.php`) and all 6
    block definition classes (`AnnouncementBarBlock`, `PromoSlideBlock`, `FeatureBannerBlock`,
    `ServiceItemBlock`, `SectionHeaderBlock`, `BrandStripBlock`)
  Placement enum (`app/Domain/Content/Enums/Placement.php`): cases(), allowedBlockTypes(),
    maxCount(), label()
  PublishStatus & ServingStatus enums (`app/Domain/Content/Enums/`)
  ContentBlock model (`app/Domain/Content/Models/ContentBlock.php`): scopes, attributes, casts
  CreateContentBlock, UpdateContentBlock, PublishContentBlock, EnableContentBlock,
    DisableContentBlock, ArchiveContentBlock, GetBlockServingStatus, GetLayoutContent,
    GetUpgradedBlockPayload, ResolveServablePayload (`app/Domain/Content/Actions/` & `Services/`)
  ContentBlockResource, ListContentBlocks, CreateContentBlock, EditContentBlock,
    ViewContentBlock, ContentBlockForm, ContentBlockTable, ContentBlockTransitionActions,
    InteractsWithContentBlockActions (`app/Filament/Admin/Resources/ContentBlockResource/`)
  MenuResource, ListMenus, EditMenu, MenuForm, MenuTable
    (`app/Filament/Admin/Resources/MenuResource/`)
  DemoHomeController, `resources/views/layouts/storefront.blade.php`,
    `resources/views/storefront/home.blade.php`,
    `resources/views/components/promo-carousel.blade.php`,
    and `resources/css/storefront/`

In scope:
  Part A — Page Context (`PlacementPage`), Cascading Create Selector & Live Capacity Enforcement (CP-A):
  1. Add `PlacementPage` backed string enum (`app/Domain/Content/Enums/PlacementPage.php`):
     - Cases:
       * `GLOBAL_LAYOUT = 'global_layout'` (label: `'Global Layout'`)
       * `HOME = 'home'` (label: `'Homepage'`)
     - Expand `Placement` (`app/Domain/Content/Enums/Placement.php`) with:
       * `page(): PlacementPage` (`ANNOUNCEMENT_BAR` -> `PlacementPage::GLOBAL_LAYOUT`; all 8
         `HOME_*` cases -> `PlacementPage::HOME`)
       * `shortLabel(): string` (human-readable section name without the redundant page prefix,
         e.g. `'Announcement Bar'`, `'Hero Carousel'`, `'Hero Side Banner'`, `'Service Strip'`,
         `'Categories Header'`, `'Featured Products Header'`, `'Collection Banner'`,
         `'New Arrivals Header'`, `'Brand Strip'`)
       * `static forPage(PlacementPage $page): array<int, self>`
  2. Define code-level live multiplicity on `BlockDefinition` and `Placement`:
     - Add `defaultMaxLivePerPlacement(): int` to `BlockDefinition` contract and implement on
       all 6 blocks (and any test block stubs in `tests/Support/TestBlocks.php` if applicable):
       * `AnnouncementBarBlock`: `1`
       * `PromoSlideBlock`: `5`
       * `FeatureBannerBlock`: `1`
       * `ServiceItemBlock`: `4`
       * `SectionHeaderBlock`: `1`
       * `BrandStripBlock`: `1`
     - Keep `Placement::maxCount(): int` aligned with these exact limits (`1` for
       `ANNOUNCEMENT_BAR`, `HOME_HERO_SIDE`, `HOME_CATEGORIES_HEADER`, `HOME_FEATURED_HEADER`,
       `HOME_COLLECTION_BANNER`, `HOME_NEW_ARRIVALS_HEADER`, `HOME_BRAND_STRIP`; `5` for
       `HOME_HERO_CAROUSEL`; `4` for `HOME_SERVICE_STRIP`).
  3. Enforce Live Capacity on Write Actions & Serving Status:
     - Add `PlacementCapacityExceededException` in `app/Domain/Content/Exceptions/`.
     - Create a domain capacity check (e.g. in `app/Domain/Content/Services/` or inside the
       Actions under `DB::transaction` + `lockForUpdate()` on the placement's rows) invoked by:
       * `PublishContentBlock::__invoke` (when the block being published has `is_enabled = true`
         and its schedule window is not already expired)
       * `EnableContentBlock::__invoke` (when the block being enabled has `status = PUBLISHED`
         and its schedule window is not already expired)
       * `UpdateContentBlock::__invoke` (when the block is already `status = PUBLISHED` and
         `is_enabled = true`, and the updated `starts_at` / `ends_at` window would overlap with
         existing active blocks beyond `$placement->maxCount()`)
     - Overlap rule: Two `PUBLISHED` + `is_enabled = true` blocks in the same `Placement`
       whose schedule windows have not ended (`ends_at IS NULL OR ends_at > $now`) conflict if
       their active windows `[max(starts_at ?? $now, $now), ends_at ?? +infinity)` overlap.
       If activating/updating the target block causes more than `$placement->maxCount()` blocks
       to be concurrently active in that window, throw `PlacementCapacityExceededException`
       with a clear message stating the placement label, the `maxCount()` limit, and the
       conflicting active block's `public_id`.
     - Update `GetBlockServingStatus`: when a block is otherwise eligible (`PUBLISHED`,
       `is_enabled = true`, within schedule window, valid payload), verify that it ranks within
       the top `$placement->maxCount()` eligible blocks for its placement under Decision D8
       order (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`). If it ranks beyond
       `$placement->maxCount()`, return `ServingStatus::NotLive` (never falsely showing `Live`
       for a shadowed block).
     - Wire `ContentBlockTransitionActions` (Publish, Enable) and `EditContentBlock` to catch
       `PlacementCapacityExceededException` and display a clear Filament danger notification /
       field error without a 500.
  4. Update `ListContentBlocks`, `ContentBlockTable`, and `ContentBlockForm`:
     - `ListContentBlocks`: Add status/page filtering tabs (`getTabs()`) for `'all'` (`All Pages`),
       `'home'` (`Homepage`), and `'global_layout'` (`Global Layout`) with badge counts.
     - `ContentBlockTable`:
       * Add a `Page` badge column (`page`, derived from `$record->placement?->page()->label()`)
         before the `Placement` column.
       * Add `SelectFilter::make('page')` filtering `whereIn('placement', ...)` for the
         selected `PlacementPage`.
     - `ContentBlockForm`:
       * On Create: Add `Select::make('page')` (`PlacementPage` options, `live()`,
         `dehydrated(false)`). Scope `Select::make('placement')` options to the selected `page`
         (falling back to all `Placement::cases()` if `page` is null so existing Livewire tests
         calling `fillForm(['placement' => ...])` without `page` continue to pass!).
       * When `placement` changes (`afterStateUpdated`), automatically sync `page` to
         `$placement->page()->value`, and if `count($placement->allowedBlockTypes()) === 1`,
         automatically set `type` to `$placement->allowedBlockTypes()[0]` so the admin does not
         have to manually pick a single-option block type.
       * On Edit & View: Show `Page` (read-only), `Placement` (read-only), and `Type` (read-only),
         plus a helper/hint indicating current live usage for that placement (e.g. `1 / 1 live`).

  Part B — Shared Storefront Block Partials, Isolated View Preview & Atelier-Style Live Edit Preview (CP-B):
  5. Extract shared storefront Blade partials ("One Render Path", zero duplication between
     public storefront and admin preview):
     - Extract each placement/block's markup from `resources/views/layouts/storefront.blade.php`
       and `resources/views/storefront/home.blade.php` into reusable Blade partials under
       `resources/views/storefront/partials/blocks/` (or `resources/views/components/storefront/blocks/`):
       * `announcement-bar.blade.php`
       * `hero-carousel.blade.php` (wrapping `<x-promo-carousel>` or single slide preview)
       * `feature-banner.blade.php` (handling both `home_hero_side` and `home_collection_banner`
         variants based on placement)
       * `service-strip.blade.php` (rendering the strip or a single `service_item` card in
         isolated mode)
       * `section-header.blade.php`
       * `brand-strip.blade.php`
     - Refactor `layouts/storefront.blade.php` and `storefront/home.blade.php` to `@include`
       these exact partials so `GET /` output remains 100% identical (all existing `DemoHomeTest`
       assertions must pass unchanged).
  6. Admin Preview Controller / Routes (`app/Http/Controllers/Admin/ContentBlockPreviewController.php`):
     - Register admin-only preview routes under `/admin/content-blocks/{contentBlock:public_id}/preview/...`
       protected by `auth` and policy authorization (`can:view,contentBlock` / `can:update,contentBlock`),
       returning responses with `X-Robots-Tag: noindex, nofollow`:
       * **Isolated Block Preview (`GET /admin/content-blocks/{contentBlock:public_id}/preview/isolated`):**
         Renders a minimal HTML document loading `@vite(['resources/css/storefront.css', 'resources/js/storefront.js'])`
         and rendering **only that specific block's partial** (using an ephemeral preview
         override if passed/cached during Edit, or `$contentBlock`'s upgraded stored payload on
         View — regardless of whether the block is `Draft`, `Disabled`, `Archived`, or `Live`).
       * **Full-Page Context Preview (`GET /admin/content-blocks/{contentBlock:public_id}/preview/page`):**
         Assembles the storefront layout (`GetLayoutContent`) and overrides/injects the target
         `$contentBlock` (using the in-memory edited payload from `EditContentBlock` even if the
         block is `Draft` or `Disabled`), wraps the target placement in a
         `data-preview-target="true"` highlight container that scrolls into view, and disables
         navigation links inside the preview iframe so clicking a link does not navigate the
         preview away from the page.
  7. Wire Previews into `ViewContentBlock` and `EditContentBlock`:
     - `ViewContentBlock`:
       * Render an **Isolated Block Preview** section at the top of the View page embedding the
         isolated preview iframe (`sandbox="allow-same-origin allow-scripts"`), showing only the
         specific block in its real storefront styling alongside badges for its Page, Placement,
         Status (`Draft`/`Published`/`Archived`), and Serving Status (`Live`/`Not Live`).
     - `EditContentBlock` (Filament Atelier-style Live Editor):
       * Use a responsive split-screen layout (Form controls on the left, sticky Live Preview
         pane on the right) with a toolbar offering:
         1. **Preview Mode switch:** `Page Context` (full page scrolled to block) vs
            `Isolated Block` (just the block).
         2. **Viewport width switcher:** `Desktop (100%)`, `Tablet (768px)`, `Mobile (375px)`.
         3. **Refresh / Live sync:** As form fields change (debounced) or when clicking Refresh
            Preview, store the unsaved form state (`payload`, `sort_order`) in a short-lived
            cache key scoped to `(auth()->id(), $record->public_id)` (never mutating the
            `content_blocks` DB row until Save!) and refresh the preview canvas smoothly.

  Part C — Menus Admin UI Reorganization (`MenuForm` & `EditMenu`) (CP-C):
  8. Reorganize `MenuForm` (`app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php`)
     and `EditMenu` (`app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php`):
     - **Collapsible Summary Cards on Top-Level Items (`Repeater::make('items')`):**
       * Make `items` repeater `->collapsible()` and `->collapsed()` by default when loading
         existing items, with `->collapseAllAction(...)` and `->expandAllAction(...)`.
       * Add a descriptive `->itemLabel(...)` closure on `items` that displays:
         `"{label}  •  {url}"` plus child item count (e.g. `"(6 sub-items)"`) and `"[Disabled]"`
         when `is_enabled` is false, so admins can scan and drag-reorder the entire top-level
         menu in a compact list without scrolling through expanded forms.
     - **Compact Horizontal Field Layout Inside Each Item:**
       * Arrange top-level item fields (`label`, `url`, `opens_in_new_tab`, `is_enabled`) in a
         clean multi-column `Grid` row instead of a tall vertical stack.
       * Place `Repeater::make('children')` inside a visually distinct, collapsible
         `Section::make('Dropdown Sub-Items (Level 2)')` (or compact inset group) showing the
         current sub-item count (`0 / 10`).
       * Make each child item in `Repeater::make('children')` `->collapsible()` with a dynamic
         `->itemLabel(...)` (`"{label}  •  {url}"`) and arrange its fields (`label`, `url`,
         `opens_in_new_tab`, `is_enabled`) on a single compact horizontal row.
     - **Live Hierarchical Menu Tree Overview Panel on `EditMenu`:**
       * Add a sticky side-by-side **Menu Structure Preview** panel on `EditMenu` (e.g. 8 cols
         form / 4 cols tree overview on desktop) that renders a clean visual tree of the
         current form state (`$this->data['items']`): showing top-level items in order, indented
         level-2 children underneath each parent, visual badges for `Disabled` or `New Tab`,
         and total capacity counters (`Top-level: X / 10`).

Out of scope:
  - Installing `safi/filament-atelier` or any other third-party Composer/NPM package (`Rule X1`).
  - Creating database migrations or modifying existing migrations (`Rule DB2` — `PlacementPage`
    and `defaultMaxLivePerPlacement()` are code-defined on the enums/contracts per `Rule K5`).
  - Changing the public JSON shape of `GET /api/v1/content/layout` (`Rule K8`).
  - Adding a 3rd nesting level to Menus (depth remains strictly 2 per `ARCHITECTURE.md` Section 6).

Read first:
  - `ARCHITECTURE.md` sections 5, 6, 7, 8, 12 (decisions D7, D8, D9).
  - `AGENTS.md` ('Where code goes', 'Coding standards', 'Content rules (critical)', 'Testing').
  - Inspect all existing files in `C:\dev\getnow-cms` listed in `Depends on`, plus:
    * `tests/Feature/Admin/ContentBlock/` (all files — especially `HomepageBlocksCreateAndServeTest.php`,
      `ContentBlockCreateTest.php`, `ContentBlockEditTest.php`, `ContentBlockViewTest.php`,
      `ContentBlockActionsTest.php`, `ListContentBlocksTableTest.php`)
    * `tests/Feature/Domain/Content/Actions/` (`PublishContentBlockTest.php`,
      `EnableContentBlockTest.php`, `UpdateContentBlockTest.php`)
    * `tests/Feature/Admin/Menu/MenuResourceTest.php`
    * `tests/Support/TestBlocks.php` and `tests/Arch/`

Decisions made (do not re-decide):
  D-a Native Atelier Preview Pattern (No External Package):
      Do not add `safi/filament-atelier` to `composer.json` (`Rule X1`). Implement the Atelier
      one-render-path iframe preview natively using shared storefront Blade partials and an
      admin-authenticated preview controller.
  D-b Ephemeral Edit Preview State (Respecting D7):
      Editing fields in `EditContentBlock` updates a short-lived cache/session preview payload
      for the preview iframe and NEVER writes to `content_blocks` until the admin clicks Save
      (which invokes `UpdateContentBlock`).
  D-c View Page Shows Isolated Block Preview Only:
      `ViewContentBlock` renders only the specific block partial in an isolated storefront-styled
      preview frame (not the entire homepage), whereas `EditContentBlock` defaults to full-page
      context scrolled to the block (with a toggle to switch to isolated block view and
      Desktop/Tablet/Mobile widths).
  D-d Live Capacity Enforcement on Write Actions + Serving Status:
      Multiple blocks of the same type/placement may exist as `Draft`, `Disabled`, `Archived`,
      or with non-overlapping future schedules, but at most `$placement->maxCount()` blocks may
      be concurrently active (`PUBLISHED` + `is_enabled = true` with overlapping active schedule
      windows). Exceeding this limit in `PublishContentBlock`, `EnableContentBlock`, or
      `UpdateContentBlock` throws `PlacementCapacityExceededException`. `GetLayoutContent`
      retains D8 tie-breaking as a read-side safety net, and `GetBlockServingStatus` marks any
      block ranked beyond `maxCount()` in D8 order as `ServingStatus::NotLive`.
  D-e Backward-Compatible `page` Selector in `ContentBlockForm`:
      `Select::make('page')` on `ContentBlockForm` is `dehydrated(false)`. If `placement` is
      populated without `page` (such as in existing Livewire feature tests calling `fillForm`),
      `placement` options still validate and automatically sync `page` from
      `Placement::tryFrom($state)?->page()->value`.

Open decisions:
  None.

Acceptance criteria:
  AC-1  `PlacementPage` enum defines `GLOBAL_LAYOUT` and `HOME`. Every `Placement` case maps to
        a `PlacementPage` via `$placement->page()` and provides `$placement->shortLabel()`.
        `BlockDefinition` requires `defaultMaxLivePerPlacement(): int`, implemented across all
        block definitions (`1` for singleton blocks, `5` for `promo_slide`, `4` for
        `service_item`).
  AC-2  `ListContentBlocks` provides page tabs (`All Pages`, `Homepage`, `Global Layout`), and
        `ContentBlockTable` includes a `Page` badge column and a `Page` `SelectFilter` that
        filters blocks by their placement's `PlacementPage`.
  AC-3  On `CreateContentBlock`, selecting a `Page` filters the `Placement` dropdown options to
        only placements belonging to that page, and selecting a `Placement` with a single
        allowed block type auto-selects `type` and reveals the block payload schema immediately.
        Existing Livewire `fillForm(['placement' => ...])` calls continue to pass.
  AC-4  Live capacity guard on singleton placements (`maxCount = 1`, e.g. `announcement_bar`,
        `home_hero_side`, `home_collection_banner`, `home_brand_strip`, section headers):
        an admin can create multiple blocks, but attempting to `Publish` or `Enable` a 2nd
        block (or edit schedules to overlap) while 1 block is already active throws
        `PlacementCapacityExceededException` and displays a danger notification in Filament.
        Once the first block is disabled or archived, the second block can be published/enabled.
  AC-5  Live capacity guard on multi-block placements (`home_hero_carousel` `maxCount = 5`,
        `home_service_strip` `maxCount = 4`): up to `maxCount` blocks can be active
        concurrently; attempting to activate block `maxCount + 1` is rejected with
        `PlacementCapacityExceededException`. Non-overlapping future-scheduled blocks are
        permitted.
  AC-6  `GetBlockServingStatus` returns `ServingStatus::NotLive` for any block that falls
        outside the top `$placement->maxCount()` eligible blocks in Decision D8 order.
  AC-7  Storefront Blade views (`layouts/storefront.blade.php` and `storefront/home.blade.php`)
        render through shared block partials with zero visual or HTML regression on `GET /`
        (all existing `DemoHomeTest` tests pass).
  AC-8  `ViewContentBlock` renders an isolated block preview iframe (`/admin/content-blocks/{public_id}/preview/isolated`)
        displaying only that block's storefront partial with its current stored payload (working
        for `Draft`, `Published`, `Disabled`, and `Archived` blocks). Non-admin users receive 403.
  AC-9  `EditContentBlock` renders the split-screen live preview with viewport switcher
        (`Desktop`, `Tablet`, `Mobile`) and mode toggle (`Page Context` vs `Isolated Block`).
        Updating form state refreshes the preview via ephemeral cache state without mutating the
        database record until Save is clicked.
  AC-10 `MenuForm` and `EditMenu` render top-level items as collapsible cards (`collapsed()` by
        default) with dynamic summary labels (`label`, `url`, child count, disabled badge),
        compact multi-column field rows, an inset `Dropdown Sub-Items` section, and a live
        hierarchical Menu Structure preview panel on `EditMenu`. All existing `MenuResourceTest`
        tests pass.
  AC-11 `composer check` (Pint, PHPStan Level 8, Pest including `tests/Arch`) passes with zero
        errors, zero new dependencies, and zero baseline additions.
