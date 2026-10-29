[P1-UI6 | BRIEF | Claude]

Goal: Add an isolated single-block storefront preview on `ViewContentBlock` and a native
  split-screen live preview ("Storefront Preview Approximation" with Page Context vs.
  Isolated Block modes and Desktop/Tablet/Mobile viewport toggles) on `EditContentBlock`,
  powered by shared storefront block Blade partials ("One Render Path") and two invokable
  Domain Actions (`SaveContentBlockPreviewState` and `ResolveContentBlockPreview`), while
  strictly preserving Decision D7 (never mutating database records until Save is clicked).

Depends on (verify exact signatures from code in Section 3):
  GetLayoutContent::__invoke(): LayoutResponseData (`app/Domain/Content/Actions/GetLayoutContent.php`)
  GetUpgradedBlockPayload::__invoke(string $type, int $schemaVersion, array $payload): BlockPayload
  CountLiveBlocksForPlacement::__invoke(Placement $placement): int (`app/Domain/Content/Actions/CountLiveBlocksForPlacement.php`)
  LayoutResponseData, LayoutData, LayoutBlockData (`app/Domain/Content/Data/`)
  Placement & PlacementPage enums (`app/Domain/Content/Enums/`)
  PublishStatus & ServingStatus enums (`app/Domain/Content/Enums/`)
  ContentBlock model (`app/Domain/Content/Models/ContentBlock.php`) and `ContentBlockPolicy`
  ContentException (`app/Domain/Content/Exceptions/ContentException.php`)
  ViewContentBlock, EditContentBlock, ContentBlockForm, InteractsWithContentBlockActions
    (`app/Filament/Admin/Resources/ContentBlockResource/`)
  DemoHomeController (`app/Http/Controllers/Storefront/DemoHomeController.php`),
    `resources/views/layouts/storefront.blade.php`,
    `resources/views/storefront/home.blade.php`,
    `resources/views/components/promo-carousel.blade.php`,
    `routes/web.php`

In scope:
  Part A — Shared Storefront Block Partials & Domain Preview Actions (CP-A):
  1. Extract shared storefront Blade partials under `resources/views/storefront/partials/blocks/`
     and wire `layouts/storefront.blade.php` and `storefront/home.blade.php` to `@include` them
     with 100% HTML parity on `GET /`:
     - `announcement-bar.blade.php`
     - `hero-carousel.blade.php`
     - `feature-banner.blade.php`
     - `service-strip.blade.php`
     - `section-header.blade.php`
     - `brand-strip.blade.php`
     *Important (Trap P1):* Because `@props` does NOT work inside `@include` partials, every
     partial MUST initialize optional variables at the top using `@php($previewTarget = $previewTarget ?? false)`
     (and `@php($previewSlide = $previewSlide ?? null)` / `@php($previewItem = $previewItem ?? null)`).
     When `$previewTarget === true`, the partial attaches `data-preview-target="true" id="preview-target-block"`
     to its outer element.
  2. Expose public static catalog helper methods on `DemoHomeController`
     (`getFeaturedProducts(): array`, `getNewProducts(): array`, `getCategories(): array`) so
     `DemoHomeController::__invoke()` and `ContentBlockPreviewController::page()` share the
     exact same demo catalog arrays with zero duplication.
  3. Add Spatie Data DTO `app/Domain/Content/Data/ContentBlockPreviewData.php`:
     - `public readonly ?LayoutBlockData $block` (null only when `isUnservable === true`)
     - `public readonly Placement $placement`
     - `public readonly ?LayoutData $layout` (populated when `$includeFullLayout === true`)
     - `public readonly bool $usingFallbackPayload` (`true` when an ephemeral draft existed in
       cache but failed upgrade/validation, so the stored payload was used instead)
     - `public readonly bool $isUnservable` (`true` when even the stored database payload fails
       upgrade/validation)
  4. Add invokable Domain Action `app/Domain/Content/Actions/SaveContentBlockPreviewState.php`:
     - Signature: `public function __invoke(int $userId, string $publicId, array $rawPayload, int $sortOrder): string`
     - Writes `['payload' => $rawPayload, 'sort_order' => $sortOrder]` to `Cache::put(self::cacheKey($userId, $publicId), ..., now()->addMinutes(15))`
       and returns a short random preview token string.
     - Also provide `public static function clear(int $userId, string $publicId): void` and
       `public static function cacheKey(int $userId, string $publicId): string`.
  5. Add invokable Domain Action `app/Domain/Content/Actions/ResolveContentBlockPreview.php`:
     - Signature: `public function __invoke(ContentBlock $contentBlock, int $userId, bool $includeFullLayout = false, bool $useEphemeralCache = false): ContentBlockPreviewData`
     - Resolves the block payload:
       * If `$useEphemeralCache === true`, reads `SaveContentBlockPreviewState::cacheKey($userId, $contentBlock->public_id)`.
       * If cached data exists, tries `$getUpgradedBlockPayload($contentBlock->type, $contentBlock->schema_version, $cachedData['payload'])`.
       * If that throws `ContentException` or `ValidationException`, sets `$usingFallbackPayload = true`
         and falls back to upgrading `$contentBlock->payload`.
       * If upgrading `$contentBlock->payload` also throws `ContentException` or `ValidationException`
         (e.g. a corrupt stored block in the DB), catches it and sets `$isUnservable = true` and
         `$block = null` (never throwing a 500!).
     - When `$includeFullLayout === true`:
       * Calls `GetLayoutContent` to get the base `LayoutResponseData`.
       * If `$block !== null` and `$contentBlock->placement !== null`:
         - **Singleton placement (`$placement->maxCount() === 1`, Trap P2):** Unconditionally sets
           `$placements[$placement->value] = [$block]` (replacing any currently live block so the
           previewed block is always the one rendered at index `0`).
         - **Multi-block placement (`$placement->maxCount() > 1`):** Replaces the block with
           matching `id === $contentBlock->public_id` or inserts `$block` at the position
           corresponding to its effective `sort_order` (using cached `sort_order` when present),
           and caps the array to `$placement->maxCount()` while guaranteeing `$block` is retained.
       * Constructs a new `LayoutData` instance with the updated `$placements` array.
  6. Fix carry-forward `m1` from `P1-UI5` in `ContentBlockForm.php`:
     - Update `TextEntry::make('placement_display')->helperText(...)` on Edit to use
       `app(CountLiveBlocksForPlacement::class)($record->placement)` instead of querying
       `ContentBlock::visible()` directly in Filament (`Rule S1`, `S2`).

  Part B — Preview Controller, Isolated View Preview & Split-Screen Edit Preview (CP-B):
  7. Add `app/Http/Controllers/Admin/ContentBlockPreviewController.php` and register routes in
     `routes/web.php`:
     - Routes under `Route::middleware(['web'])->prefix('admin/content-blocks')->name('admin.content-blocks.')`:
       * `GET {contentBlock:public_id}/preview/isolated` -> `isolated` (`name('preview.isolated')`)
       * `GET {contentBlock:public_id}/preview/page` -> `page` (`name('preview.page')`)
     - *Authorization (Trap P4):* Inside both `isolated()` and `page()`, check:
       `$user = $request->user(); abort_unless($user !== null && $user->can('view', $contentBlock), 403);`
       (Using `abort_unless(..., 403)` under `web` middleware ensures unauthenticated requests
       and non-admin users both receive a deterministic `403 Forbidden` without crashing on a
       missing `route('login')`).
     - Both methods pass `$useEphemeralCache = $request->boolean('live')` to
       `ResolveContentBlockPreview` and return `Illuminate\Http\Response` via
       `response()->view(...)->header('X-Robots-Tag', 'noindex, nofollow')`.
     - When rendering `storefront.home` in `page()`, pass `'previewTargetId' => $contentBlock->public_id`,
       `'previewUsingFallback' => $previewData->usingFallbackPayload`, and
       `'previewIsUnservable' => $previewData->isUnservable`.
  8. Add `resources/views/filament/admin/resources/content-block-resource/pages/isolated-preview.blade.php`:
     - Minimal HTML document loading `@vite(['resources/css/storefront.css', 'resources/js/storefront.js'])`.
     - If `$preview->usingFallbackPayload` is true, renders a compact warning banner at the top:
       `"Showing last valid saved state — current draft inputs have validation errors."`
     - If `$preview->isUnservable` is true (or `$preview->block === null`), renders a clean error
       placeholder state: `"Preview unavailable: stored block payload failed schema validation."`
     - Otherwise, `@include`s the matching partial from `resources/views/storefront/partials/blocks/`
       for `$preview->placement` with `previewTarget => true` (and `previewSlide => $preview->block`
       for `home_hero_carousel`, `previewItem => $preview->block` for `home_service_strip`).
     - Disables link navigation inside the preview (`a[href]` click prevention script or CSS/JS
       handler) so clicking a block link inside the preview iframe does not navigate the iframe away.
  9. Wire Isolated Block Preview on `ViewContentBlock`:
     - Add `Section::make('Block Preview (Storefront Approximation)')` at the top of
       `ViewContentBlock::form(Schema $schema)` containing
       `View::make('filament.admin.resources.content-block-resource.components.view-isolated-preview')`.
     - `view-isolated-preview.blade.php` renders an `<iframe src="{{ route('admin.content-blocks.preview.isolated', ['contentBlock' => $record->public_id]) }}" sandbox="allow-scripts" ...>`
       alongside badges for Page, Placement, Status, and Serving Status.
  10. Wire Split-Screen Live Preview on `EditContentBlock` & `ContentBlockForm`:
      - In `EditContentBlock`:
        * Properties: `public string $previewMode = 'page';`, `public string $previewDevice = 'desktop';`,
          `public string $previewToken = '';`
        * Methods:
          - `setPreviewMode(string $mode): void` (accepts `'page'` or `'isolated'`, ignores invalid values)
          - `setPreviewDevice(string $device): void` (accepts `'desktop'`, `'tablet'`, `'mobile'`)
          - `syncPreviewState(): void` (extracts `$this->data['payload'] ?? []` and
            `(int) ($this->data['sort_order'] ?? $this->getRecord()->sort_order)`, calls
            `app(SaveContentBlockPreviewState::class)(...)`, and updates `$this->previewToken`)
        * In `handleRecordUpdate()`, after `UpdateContentBlockAction` succeeds, call
          `SaveContentBlockPreviewState::clear((int) $causer->id, $record->public_id)`.
      - In `ContentBlockForm`:
        * Wrap the schema in a native Filament 5 `Grid::make(12)`:
          - Left column (`columnSpan(fn (string $operation): int => $operation === 'edit' ? 7 : 12)`):
            all existing form inputs and the D7 live-edit callout notice.
          - Right column (`columnSpan(5)->visibleOn('edit')`):
            `Section::make('Storefront Preview (Approximation)')->schema([ View::make('filament.admin.resources.content-block-resource.components.live-preview-pane') ])`
      - In `live-preview-pane.blade.php`:
        * Renders the toolbar with Mode buttons (`Page Context`, `Isolated Block`), Viewport buttons
          (`Desktop 100%`, `Tablet 768px`, `Mobile 375px`), and a `Refresh Preview` button
          (`wire:click="syncPreviewState"`).
        * Attaches a debounced window/form listener (`x-on:input.window.debounce.500ms="$wire.syncPreviewState()"`
          and `x-on:change.window.debounce.250ms="$wire.syncPreviewState()"`) so editing any field in
          any of the 6 block forms automatically syncs the preview without modifying the 6
          `app/Filament/Blocks/*Form.php` files (Trap P6).
        * Embeds the `<iframe sandbox="allow-scripts" ...>` pointing to the selected route
          (`preview.page` or `preview.isolated`) with `?live=1&t={{ $this->previewToken }}`.
  11. Update `docs/blueprints/P1-S1/DEMO-TEARDOWN.md` and `ARCHITECTURE.md` Section 1 to document
      `P1-UI6` and note that `resources/views/storefront/partials/blocks/` and `DemoHomeController`
      catalog helpers power the admin block preview approximation.

Out of scope:
  - Installing `safi/filament-atelier` or any Composer/NPM dependency (`Rule X1`).
  - Menus UI reorganization (deferred to `P1-UI7`).
  - Database migrations (`Rule DB2`).
  - Modifying the public JSON shape of `GET /api/v1/content/layout` (`Rule K8`).

Read first:
  - `ARCHITECTURE.md` sections 1, 5, 6, 7, 8, 12 (decisions D1, D7, D8, D9).
  - `AGENTS.md` ('Where code goes', 'Coding standards', 'Content rules (critical)', 'Testing').
  - `docs/blueprints/P1-S1/DEMO-TEARDOWN.md`.
  - Inspect existing files in `C:\dev\getnow-cms`:
    * `app/Domain/Content/Actions/GetLayoutContent.php`, `GetUpgradedBlockPayload.php`,
      `CountLiveBlocksForPlacement.php`
    * `app/Domain/Content/Data/LayoutResponseData.php`, `LayoutData.php`, `LayoutBlockData.php`
    * `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php`,
      `EditContentBlock.php`
    * `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php`
    * `app/Http/Controllers/Storefront/DemoHomeController.php`
    * `resources/views/layouts/storefront.blade.php`, `resources/views/storefront/home.blade.php`,
      `resources/views/components/promo-carousel.blade.php`
    * `routes/web.php`
    * `tests/Feature/Storefront/DemoHomeTest.php`,
      `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php`,
      `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php`

Decisions made (do not re-decide):
  D-a Explicit Variable Defaults in `@include` Partials (Trap P1):
      Use `@include('storefront.partials.blocks.<name>', [...])` with `@php($previewTarget = $previewTarget ?? false)`
      at the top of every partial so `@include` never suffers from `@props` being ignored.
  D-b Singleton Placement Replacement in Page Preview (Trap P2):
      When `$placement->maxCount() === 1`, `ResolveContentBlockPreview` replaces the placement's
      block list with `[$targetBlockData]` so previewing a draft/disabled singleton block always
      displays that block instead of being shadowed by an existing live block.
  D-c Preview Approximation Labeling & Teardown Lifecycle (Trap P3):
      Label the preview UI `"Storefront Preview (Approximation)"` and document the shared block
      partials in `DEMO-TEARDOWN.md`.
  D-d Deterministic 403 on Preview Routes (Trap P4):
      Mount the preview routes under `web` middleware and enforce
      `abort_unless($request->user()?->can('view', $contentBlock), 403)` inside
      `ContentBlockPreviewController` so unauthenticated guests and non-admin users both receive
      HTTP 403 (never a 500 `Route [login] not defined`).
  D-e Opaque-Origin Iframe Sandbox & Strict Blade Escaping (Trap P5):
      Use `sandbox="allow-scripts"` (without `allow-same-origin`) on preview iframes and strictly
      use Blade `{{ ... }}` auto-escaping on all block payload fields.
  D-f Domain Actions for Preview State & Invalid-Draft Banner (Trap P6):
      All cache writes and preview resolution go through `SaveContentBlockPreviewState` and
      `ResolveContentBlockPreview`. When an unsaved draft fails payload validation, the preview
      renders the stored payload AND displays a visible warning banner (`usingFallbackPayload = true`).
      When a stored DB payload itself is corrupt, the preview renders a safe error placeholder
      (`isUnservable = true`) with HTTP 200 instead of throwing a 500.

Open decisions:
  None.

Acceptance criteria:
  AC-1  Shared storefront partials (`resources/views/storefront/partials/blocks/*.blade.php`)
        render `GET /` with zero regressions (all existing `DemoHomeTest` tests pass).
  AC-2  `ContentBlockForm.php` on Edit uses `CountLiveBlocksForPlacement` for the placement
        capacity helper text (zero direct `ContentBlock::visible()` queries in `app/Filament`).
  AC-3  `ResolveContentBlockPreview` replaces the active block in singleton placements
        (`maxCount = 1`) so previewing a `Draft` or `Disabled` block in `announcement_bar` or
        `home_hero_side` renders that draft block in full-page preview, and injects/re-sorts
        blocks in multi-block placements (`maxCount > 1`).
  AC-4  When an ephemeral cached payload in `EditContentBlock` fails validation (e.g. empty
        required field or invalid URL), `ResolveContentBlockPreview` falls back to the stored
        payload, sets `usingFallbackPayload = true`, and the preview HTML displays the
        `"Showing last valid saved state"` warning banner with HTTP 200.
  AC-5  When a block's stored database payload is corrupt (`InvalidAfterUpgrade`),
        `ResolveContentBlockPreview` sets `isUnservable = true` and the preview routes return
        HTTP 200 with the `"Preview unavailable"` placeholder (never a 500).
  AC-6  `GET /admin/content-blocks/{public_id}/preview/isolated` and `/preview/page` return
        HTTP 200 with header `X-Robots-Tag: noindex, nofollow` for platform admins across
        `Draft`, `Published`, `Disabled`, and `Archived` blocks, and return HTTP 403 for both
        unauthenticated guests and non-admin users.
  AC-7  XSS Escaping (`Rule K3`, Trap P5): if a block payload in the DB contains raw HTML/script
        tags (e.g. `<script>alert(1)</script>`), the preview endpoints escape it as
        `&lt;script&gt;alert(1)&lt;/script&gt;` and never output unescaped `<script>alert(1)</script>`.
  AC-8  `ViewContentBlock` renders the Isolated Block Preview section at the top of the page
        with an `<iframe sandbox="allow-scripts">` pointing to `/preview/isolated`.
  AC-9  `EditContentBlock` renders the split-screen preview pane with mode switching
        (`Page Context` vs. `Isolated Block`), viewport switching (`Desktop`, `Tablet`, `Mobile`),
        and `syncPreviewState()` calling `SaveContentBlockPreviewState` without mutating the
        `content_blocks` database row until `Save` is clicked (`Decision D7`).
  AC-10 `DEMO-TEARDOWN.md` and `ARCHITECTURE.md` are updated, and `composer check` (Pint,
        PHPStan Level 8, Pest including `tests/Arch`) passes with zero errors, zero new
        dependencies, and zero baseline additions.

Rules most at risk:
  - S1, S2, S4, C5 (preview state writes and layout assembly must live in Domain Actions;
    `ContentBlockForm` must not query `ContentBlock::visible()` directly; preview sync must
    never write to the `content_blocks` table).
  - D7 (live preview edits on a published block must remain ephemeral in cache until Save).
  - K3 & SEC4 (strict HTML escaping in all 6 block partials; deterministic 403 for guests and
    non-admins on preview routes).
  - X1 (zero new Composer or NPM packages).

Known traps:
  1. Trap P1 (`@props` in `@include`): Do not use `@props` in `resources/views/storefront/partials/blocks/*.blade.php`
     because they are loaded via `@include(...)`. Use `@php($previewTarget = $previewTarget ?? false)`
     at the top of each partial.
  2. Trap P2 (Singleton preview replacement): In `ResolveContentBlockPreview`, when
     `$placement->maxCount() === 1`, do not append `$targetBlockData` after existing live blocks;
     replace the placement array with `[$targetBlockData]`.
  3. Trap P4 (Guest request on preview routes): Do not use Laravel's default `auth` middleware
     on preview routes because `route('login')` does not exist and will 500 on unauthenticated
     requests. Use `web` middleware + `abort_unless($request->user()?->can('view', $contentBlock), 403)`.
  4. Trap P6 (Grid wrapping in `ContentBlockForm`): Ensure wrapping `ContentBlockForm` in
     `Grid::make(12)` preserves all field state paths (`page`, `placement`, `type`, `starts_at`,
     `ends_at`, `sort_order`, `payload.*`) so zero existing `ContentBlockCreateTest`,
     `ContentBlockEditTest`, or `ContentBlockViewTest` assertions break.

Size guard and checkpoints:
  Expected ~22–24 files gated by 2 sequential checkpoints in Section 12:
  - CP-A (Commits 1–2):
    * Commit 1: Extract the 6 storefront block partials + `DemoHomeController` static catalog
      helpers + refactor `layouts/storefront.blade.php` and `storefront/home.blade.php`
      (`composer check` and `DemoHomeTest` green).
    * Commit 2: `ContentBlockPreviewData` + `SaveContentBlockPreviewState` +
      `ResolveContentBlockPreview` + `ContentBlockForm.php:147` `CountLiveBlocksForPlacement`
      fix + `ResolveContentBlockPreviewTest` (`composer check` green, stop and submit CP-A report).
  - CP-B (Commits 3–4):
    * Commit 3: `ContentBlockPreviewController` + `routes/web.php` + `isolated-preview.blade.php`,
      `view-isolated-preview.blade.php`, `live-preview-pane.blade.php` + `ViewContentBlock`,
      `EditContentBlock`, `ContentBlockForm` split-screen grid + `ContentBlockPreviewTest`
      (`composer check` green).
    * Commit 4: Update `DEMO-TEARDOWN.md` and `ARCHITECTURE.md` (`composer check` green, full
      `IMPL-REPORT`).

Manual verification script (Layer C):
  1. Open `/admin/content-blocks` and click `View` on any block (including a `Draft` or
     `Disabled` block):
     - Verify the **Block Preview (Storefront Approximation)** section at the top renders only
       that specific block in real storefront CSS inside the isolated preview frame.
  2. Click `Edit` on a `Draft` `announcement_bar` block (while another `announcement_bar` block
     is currently `Live` on the storefront):
     - Verify the right-hand split-screen preview in `Page Context` mode displays the `Draft`
       announcement bar being edited (replacing the live one in the preview), and automatically
       scrolls/highlights it.
  3. In `Edit`, modify the headline text in the form without clicking Save:
     - Verify the preview pane updates after ~500ms (or when clicking `Refresh Preview`).
     - Open `GET /` in a separate tab and verify the public storefront is unchanged until `Save`
       is clicked.
  4. In `Edit`, clear the required `text` field (making the draft invalid):
     - Verify the preview displays the warning banner (`"Showing last valid saved state — current draft inputs have validation errors"`)
       and does not crash.
  5. Toggle `Desktop (100%)`, `Tablet (768px)`, `Mobile (375px)` and `Isolated Block` mode on
     the preview toolbar and verify the iframe resizes and switches modes cleanly.

Step-specific checks (Layer A extras):
  php artisan route:list --path=admin/content-blocks
  git diff main...HEAD -- composer.json composer.lock package.json
  Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '::create(','->update(','->delete(','->save(','ContentBlock::visible('
  Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Filament','App\Http','Illuminate\Http'
