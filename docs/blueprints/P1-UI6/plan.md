# [P1-UI6 | PLAN v2 | Gemini]

## Change Log (Review Feedback Mapping: v1 -> v2)

| Finding ID | Reviewer Issue | Resolution in Plan v2 | Section Changed |
|---|---|---|---|
| **B1** | Built from Brief v1, not v2/v3 (missing ADR 0005, `#[Locked]`, `wire:key`, form-scoped listener, `log: false`, `canAccessPanel`, MUT checks, `ActionCoverageTest` update, own-commit for `m1` fix in Commit 1, Q1–Q5 decisions, 16 ACs, 5 commits). | Rebuilt plan strictly from Brief v3: introduced ADR 0005, `#[Locked]` attributes on preview Livewire properties, `wire:key` on inner preview iframe wrapper, form-scoped listener (`$el.closest('form')`), `log: false` in `ResolveContentBlockPreview`, `canAccessPanel` in controller, MUT-1 through MUT-5 checks across checkpoints, `ActionCoverageTest` updates for new actions, isolated Commit 1 for carry-forward `m1` fix, incorporated Decisions D-a to D-g, expanded to all 16 ACs, and structured 5 commits across 2 checkpoints. | **Section 1, Section 4, Section 5, Section 7, Section 8, Section 9, Section 10, Section 11, Section 12** |
| **B2** | Isolated view throws on every placement via dynamic type string concatenation; partial files are hyphenated (`hero-carousel`, `service-strip`), missing `$variant` and array collections. | Replaced dynamic type string inclusion in `isolated-preview.blade.php` with an explicit placement-to-partial mapping across all 9 `Placement` enum cases, providing exact partial paths, required variables (`$variant`, `$slides`, `$items`), and `<div class="container">` wrappers. | **Section 5.3, Section 7.2** |
| **B3** | `ContentBlockPreviewData` DTO has non-nullable `Placement $placement`, throwing `TypeError` when a block has invalid/null placement via `TolerantEnum`. | Made `$placement` nullable (`public readonly ?Placement $placement = null`) in `ContentBlockPreviewData` and updated `ResolveContentBlockPreview` to handle and return `null` placement safely without throwing a 500. | **Section 4, Section 5.1, Section 6** |
| **B4** | Multi-block ordering sorted `LayoutBlockData` by non-existent `sort_order`, diverging from Decision D8. | Rewrote multi-block placement resolution in `ResolveContentBlockPreview`: queries visible candidates, filters servable candidates, maps to `LayoutBlockData` while tracking candidate metadata, merges with target block (using cached ephemeral sort order if present), and sorts in strict Decision D8 order (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`), applying capacity eviction when exceeding `maxCount()`. | **Section 5.2, Section 7.1, Section 10.2** |
| **B5** | Template structure not followed (sections 5-11 and 14). Missing rule matrix, AC traceability, per-test arrange/act/assert, exact doc edits, and Dependencies. Section 13 incorrectly reported no open questions despite sandbox spike. | Formatted plan to adhere strictly to the 14-section template: §5 Architecture & Component Design, §6 Data and Migrations, §7 Behavior & Flows, §8 Rule Compliance Matrix, §9 Acceptance Criteria Traceability, §10 Testing Strategy & Fixtures with Arrange/Act/Assert, §11 Documentation Updates with verbatim edits, §12 Commit Order & Checkpoints, §13 Risks, Assumptions & Conflicts with sandbox spike note, and §14 Dependencies. | **Section 5, Section 6, Section 7, Section 8, Section 9, Section 10, Section 11, Section 13, Section 14** |
| **M1** | Draft validated at `$contentBlock->schema_version` instead of `$definition->schemaVersion()`; stored fallback skipped `ResolveServablePayload(log: false)`. | In `ResolveContentBlockPreview`, validated cached drafts using current `$definition->schemaVersion()`. In §3, verified `EditContentBlock::mutateFormDataBeforeFill()` upgrades payloads on fill. Resolved stored fallback via `ResolveServablePayload($contentBlock, log: false)` for complete parity with `GetLayoutContent`. | **Section 3.2, Section 5.2, Section 7.1, Section 10.2** |
| **M2** | Auth checked `can('view')` only; missing `canAccessPanel`. `route('login')` lacked verification. | Added `abort_unless($user instanceof User && $panel !== null && $user->canAccessPanel($panel) && $user->can('view', $contentBlock), 403)` to `ContentBlockPreviewController`. Verified in §3 via `php artisan route:list --name=login` that `login` is Fortify's login, confirming `web` middleware with deterministic 403 avoids redirect loops. | **Section 3.1, Section 5.4, Section 8, Section 10.3** |
| **M3** | Edit page had unrequested `mount()` calling `syncPreviewState()`; lacked `#[Locked]` and `wire:key` iframe wrapper. | Removed `mount()` sync call so preview cache is only populated on explicit user interaction or debounced input. Added `#[Locked]` attributes to `$previewMode`, `$previewDevice`, and `$previewToken`. Wrapped iframe in `<div wire:key="preview-frame-{{ $previewMode }}-{{ $this->previewToken }}">` to prevent browser history stack pollution. | **Section 5.6, Section 7.3, Section 10.4** |
| **M4** | Page mode under-specified (no highlight CSS, no banner/placeholder variable names, only `a` clicks intercepted, carousel target slide double display). | Added shared `preview-chrome.blade.php` partial defining exact banner (`#preview-fallback-banner`) and placeholder (`#preview-unservable-placeholder`) DOM elements, outline CSS for `[data-preview-target="true"]`, and JavaScript intercepting both `click` on links and `submit` on forms. Updated carousel markup so target slide is set as initial active slide without duplicate display. | **Section 5.3, Section 7.2, Section 10.1** |
| **M5** | Missing tests (`ActionCoverageTest`, golden snapshot parity, per-user isolation, zero markers on `GET /`, dedicated `SaveContentBlockPreviewStateTest`) and missing doc edits (ADR 0005, ARCHITECTURE §1/§5/§6/§8/§12). | Added `SaveContentBlockPreviewStateTest.php` with per-user isolation and token tests; added golden snapshot parity and zero-marker assertions to `DemoHomeTest.php`; added `ActionCoverageTest` assertions for new actions; added ADR 0005 and exact text edits for `ARCHITECTURE.md` and `DEMO-TEARDOWN.md`. | **Section 4, Section 10.1, Section 10.2, Section 11** |
| **M6** | Unverified in §3: View variable `$layout` type, Controller base class, and exception hierarchy. | In §3, verified that `DemoHomeController` passes `$layout` as `LayoutData`, verified `App\Http\Controllers\Controller` exists, and verified that all four domain exceptions extend `ContentException` while `ValidationException` is caught and wrapped. | **Section 3.1, Section 3.6** |

---

## 1. Understanding

The goal of Step P1-UI6 is to implement a storefront preview system for Content Blocks in the Filament admin panel:
1. An isolated single-block storefront preview on [ViewContentBlock](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php) that renders the selected block in isolation inside an iframe styled with storefront CSS.
2. A native split-screen live preview titled `'Storefront Preview (Approximation)'` on [EditContentBlock](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) supporting:
   - Mode toggles: Page Context (rendering the block inserted into the homepage layout) versus Isolated Block (rendering only the target block).
   - Viewport width toggles: Desktop (100%), Tablet (768px), and Mobile (375px).
   - Ephemeral live preview: Form input changes trigger debounced synchronization to cache via [SaveContentBlockPreviewState](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/SaveContentBlockPreviewState.php) without mutating the `content_blocks` database row, preserving Decision D7.
3. Architecture alignment via ADR 0005 amending Decision D7 and the [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) phase table so ephemeral admin previews ship in Phase 1 while persistent revisions and public API preview tokens remain in Phase 2.
4. Single rendering pipeline ("One Render Path") where public `GET /`, isolated preview, and page context preview all consume the exact same storefront block Blade partials.

### Scope Breakdown

#### Part A — Carry-Forward Fix, Shared Storefront Partials & Domain Preview Actions (Checkpoint CP-A)
1. Fix carry-forward `m1` from `P1-UI5` in [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php#L147) in Commit 1:
   - Replace the direct query `ContentBlock::visible()->where('placement', ...)->count()` on `placement_display` with [CountLiveBlocksForPlacement](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/CountLiveBlocksForPlacement.php) to adhere to Rules S1 and S2.
   - Add a test in [ContentBlockEditTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php) verifying that the edit page renders the `"X / Y live in this placement"` helper text.
2. Extract 6 shared storefront block Blade partials under `resources/views/storefront/partials/blocks/` plus 1 shared preview chrome partial `resources/views/storefront/partials/preview-chrome.blade.php`:
   - `announcement-bar.blade.php`: expects `$block` (`?LayoutBlockData`), `$previewTarget` (`bool`).
   - `hero-carousel.blade.php`: expects `$slides` (`array<int, LayoutBlockData>`), `$previewTargetId` (`?string`), forwarding `$previewTargetId` to [<x-promo-carousel>](file:///c:/dev/getnow-cms/resources/views/components/promo-carousel.blade.php).
   - `feature-banner.blade.php`: expects `$block` (`?LayoutBlockData`), `$variant` (`'hero_side'|'collection'`), `$previewTarget` (`bool`).
   - `service-strip.blade.php`: expects `$items` (`array<int, LayoutBlockData>`), `$previewTargetId` (`?string`).
   - `section-header.blade.php`: expects `$block` (`?LayoutBlockData`), `$previewTarget` (`bool`).
   - `brand-strip.blade.php`: expects `$block` (`?LayoutBlockData`), `$previewTarget` (`bool`).
   - `preview-chrome.blade.php`: expects `$previewModeActive` (`bool`), `$usingFallbackPayload` (`bool`), `$isUnservable` (`bool`). Handles warning banners, outline styles, and script interception for links and form submissions.
   - Refactor [layouts/storefront.blade.php](file:///c:/dev/getnow-cms/resources/views/layouts/storefront.blade.php) and [storefront/home.blade.php](file:///c:/dev/getnow-cms/resources/views/storefront/home.blade.php) to `@include` these partials.
   - Capture `tests/Fixtures/storefront/home-seeded-golden.html` before refactoring and assert 100% normalized HTML parity and zero preview markers in [DemoHomeTest.php](file:///c:/dev/getnow-cms/tests/Feature/Storefront/DemoHomeTest.php).
3. Expose public static catalog helpers on [DemoHomeController](file:///c:/dev/getnow-cms/app/Http/Controllers/Storefront/DemoHomeController.php):
   - `getFeaturedProducts(): array`
   - `getNewProducts(): array`
   - `getCategories(): array`
4. Create Spatie Data DTO [ContentBlockPreviewData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/ContentBlockPreviewData.php).
5. Create invokable Domain Action [SaveContentBlockPreviewState](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/SaveContentBlockPreviewState.php).
6. Create invokable Domain Action [ResolveContentBlockPreview](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/ResolveContentBlockPreview.php).

#### Part B — Preview Controller, Isolated View Preview, Split-Screen Edit Preview & Docs (Checkpoint CP-B)
7. Create [ContentBlockPreviewController](file:///c:/dev/getnow-cms/app/Http/Controllers/Admin/ContentBlockPreviewController.php) with `isolated()` and `page()` methods mounted under `Route::middleware(['web'])->prefix('admin/content-blocks')->name('admin.content-blocks.')`. Check `$user->canAccessPanel($panel)` and `$user->can('view', $contentBlock)` with `abort_unless(..., 403)`, returning responses with header `X-Robots-Tag: noindex, nofollow`.
8. Create `resources/views/filament/admin/resources/content-block-resource/pages/isolated-preview.blade.php` rendering a standalone HTML5 shell embedding storefront assets, chrome partial, and the placement partial.
9. Wire isolated preview section titled `'Storefront Preview (Approximation)'` at the top of [ViewContentBlock](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php) using `view-isolated-preview.blade.php`.
10. Wire split-screen live preview on [EditContentBlock](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) and [ContentBlockForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) using a 12-column Grid (7-column left form group, 5-column right `'Storefront Preview (Approximation)'` section), `live-preview-pane.blade.php`, Alpine debounced form listeners, and `wire:key` history protection.
11. Create ADR `docs/adr/0005-admin-block-preview-approximation.md`, update [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) sections 1, 5, 6, 8, 12, and update [DEMO-TEARDOWN.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-S1/DEMO-TEARDOWN.md).

### Out of Scope Restated
- Installing `safi/filament-atelier` or third-party Composer or NPM packages (Rule X1).
- Menus UI reorganization (deferred to Step P1-UI7).
- Adding database migrations or table schema modifications (Rule DB2).
- Modifying the public JSON contract shape of `GET /api/v1/content/layout` (Rule K8).
- Public API preview tokens or persistent block revision tables (Phase 2).

---

## 2. Sources Read

| Source File or Document Section | What in it constrains P1-UI6 |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Business logic restricted to `app/Domain/Content/Actions/`; thin Filament pages and controllers; zero direct model writes in Filament; strict types; final classes; no raw HTML/CSS/JS fields; no external packages without approval; definition of done. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) Sections 1, 4, 5, 6, 7, 8, 12 | Architecture boundaries; 9 Phase 1 placements and block definitions; D7 live edit semantics; D8 tie-breaking (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`); D9 pessimistic locks; admin panel routing conventions. |
| [DEMO-TEARDOWN.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-S1/DEMO-TEARDOWN.md) | Inventory of temporary demo assets; Level 1 teardown sequence; records that shared block partials belong to demo harness and must be documented for cleanup. |
| [0004-placement-live-capacity-enforcement.md](file:///c:/dev/getnow-cms/docs/adr/0004-placement-live-capacity-enforcement.md) | Architectural Decision Record amending D8 with write-time capacity enforcement and advisory locking. |
| [rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | Canonical rule register (`E1, S1..S7, C1..C8, K1..K9, DB1..DB6, SEC1..SEC5, T1..T5, G1..G4, X1..X7`). |
| [GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php) | Action assembling `LayoutResponseData`; reference implementation for placement block resolution and full-page layout assembly. |
| [GetUpgradedBlockPayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetUpgradedBlockPayload.php) | Action upgrading and validating block payloads sequentially; throws `UnknownBlockTypeException`, `UnsupportedSchemaVersionException`, `UpgradeFailedException`, `InvalidPayloadException`. |
| [ResolveServablePayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Services/ResolveServablePayload.php) | Service evaluating block servability, handling logging throttle, and returning `ServingHealthResult`. |
| [CountLiveBlocksForPlacement.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/CountLiveBlocksForPlacement.php) | Domain Action counting published, enabled, in-window blocks for a placement; required for carry-forward `m1` fix in `ContentBlockForm.php`. |
| [BlockRegistry.php](file:///c:/dev/getnow-cms/app/Domain/Content/Contracts/BlockRegistry.php) | Registry contract defining `get(string $typeKey): ?BlockDefinition` and `all(): array`. |
| [BlockDefinition.php](file:///c:/dev/getnow-cms/app/Domain/Content/Contracts/BlockDefinition.php) | Definition contract declaring `schemaVersion()`, `dataClass()`, and `upgradePayload()`. |
| [LayoutResponseData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutResponseData.php) | DTO wrapping `LayoutData` and `LayoutMetaData`. |
| [LayoutData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutData.php) | DTO carrying `LayoutSettingsData`, `placements` array, and `menus` array. |
| [LayoutBlockData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/LayoutBlockData.php) | DTO carrying block public ID, type, schema version, and validated payload array. |
| [ServingHealthResult.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/ServingHealthResult.php) | DTO carrying `ServingHealthOutcome` and nullable `BlockPayload`. |
| [Placement.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php) | Enum defining 9 placements, `allowedBlockTypes()`, `maxCount()`, `label()`, `page()`, `shortLabel()`, and `forPage()`. |
| [PlacementPage.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/PlacementPage.php) | Enum defining `GLOBAL_LAYOUT` and `HOME`. |
| [PublishStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/PublishStatus.php) | Enum defining `DRAFT`, `PUBLISHED`, `ARCHIVED`. |
| [ServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/ServingStatus.php) | Enum defining `Live`, `NotLive`, `PayloadError`. |
| [ContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php) | Eloquent model for blocks; defines `public_id`, scopes `visible()` and `servingOrder()`, methods `isLiveAt()` and `isLive()`. |
| [ContentBlockPolicy.php](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/ContentBlockPolicy.php) | Authorization policy defining `view()` returning `$user->isAdmin()`. |
| [ContentException.php](file:///c:/dev/getnow-cms/app/Domain/Content/Exceptions/ContentException.php) | Base exception class for all content domain errors. |
| [ContentBlockResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource.php) | Resource registering `index`, `create`, `view`, and `edit` routes keyed by `public_id`. |
| [ViewContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php) | View page component; requires mounting isolated storefront preview section at top. |
| [EditContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) | Edit page component; requires preview mode, device, and token properties, `syncPreviewState()`, and cache cleanup on save. |
| [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) | Form schema; requires 12-column grid wrapper (7 col form, 5 col preview) and `CountLiveBlocksForPlacement` fix at line 147. |
| [InteractsWithContentBlockActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php) | Helper trait for form data preparation and validation error re-mapping. |
| [AnnouncementBarData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php) | Data class defining `prepareForPipeline` string normalization. |
| [DemoHomeController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Storefront/DemoHomeController.php) | Controller for `GET /`; contains hardcoded catalog arrays to be extracted into public static methods. |
| [layouts/storefront.blade.php](file:///c:/dev/getnow-cms/resources/views/layouts/storefront.blade.php) | Storefront layout shell; requires refactoring announcement bar to `@include('storefront.partials.blocks.announcement-bar')`. |
| [storefront/home.blade.php](file:///c:/dev/getnow-cms/resources/views/storefront/home.blade.php) | Storefront homepage; requires refactoring all block regions to `@include` shared partials. |
| [promo-carousel.blade.php](file:///c:/dev/getnow-cms/resources/views/components/promo-carousel.blade.php) | Storefront carousel component; reference implementation for slide markup and preview target ID attachment. |
| [routes/web.php](file:///c:/dev/getnow-cms/routes/web.php) | Web routes; requires registering preview routes under `admin/content-blocks`. |
| [ActionCoverageTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/ActionCoverageTest.php) | Activity log test coverage for mutating actions. |
| [DomainArchTest.php](file:///c:/dev/getnow-cms/tests/Arch/DomainArchTest.php) | Architecture test verifying domain isolation, final actions, invokable actions, and exceptions. |
| [DemoHomeTest.php](file:///c:/dev/getnow-cms/tests/Feature/Storefront/DemoHomeTest.php) | Integration tests asserting HTML structure and behavior of `GET /`. |
| [ContentBlockViewTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php) | Feature tests for View page; requires assertions for preview section and badges. |
| [ContentBlockEditTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php) | Feature tests for Edit page; requires assertions for split-screen preview and field state preservation. |
| [storefront.js](file:///c:/dev/getnow-cms/resources/js/storefront.js) | Storefront JS bundle importing carousel, dialogs, forms modules. |

---

## 3. Verified Current State

### 3.1 Route Verification (`php artisan route:list --name=login`)
Execution of `php artisan route:list --name=login` returns:
```
GET|HEAD login .. login › Laravel\Fortify › AuthenticatedSessionController@create
POST login .. login.store › Laravel\Fortify › AuthenticatedSessionController@store
POST passkeys/login .. passkey.login › Laravel\Passkeys › PasskeyLoginController@store
GET|HEAD passkeys/login/options .. passkey.login-options › Laravel\Passkeys › PasskeyLoginController@index
GET|HEAD two-factor-challenge .. two-factor.login › Laravel\Fortify › TwoFactorAuthenticatedSessionController@create
POST two-factor-challenge .. two-factor.login.store › Laravel\Fortify › TwoFactorAuthenticatedSessionController@store
```
Route `login` is Fortify's frontend customer login route, not the Filament panel login at `/admin/login`. Applying standard `auth` middleware to preview iframe routes would redirect unauthenticated requests to `/login`, loading Fortify's session controller inside the admin iframe. Mounting preview routes under `web` middleware and checking authorization via `abort_unless($user instanceof User && $panel !== null && $user->canAccessPanel($panel) && $user->can('view', $contentBlock), 403)` ensures both unauthenticated guests and non-admin users receive a deterministic `403 Forbidden` response without redirection.

### 3.2 Form Hydration, Schema Versions, and Empty String Normalization
- [EditContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php#L43-L50):
  ```php
  protected function mutateFormDataBeforeFill(array $data): array
  {
      $record = $this->getRecord();
      $upgraded = app(GetUpgradedBlockPayload::class)($record->type, $record->schema_version, $record->payload);
      $data['payload'] = $upgraded->toArray();
      return $data;
  }
  ```
  `mutateFormDataBeforeFill` upgrades stored payloads to the current schema version (`$definition->schemaVersion()`). Thus, the form state residing in `$this->data['payload']` on [EditContentBlock](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) is already formatted at `$definition->schemaVersion()`.
- When form fields are cleared in the browser, Livewire transmits empty strings `""`. In [AnnouncementBarData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php#L49-L73), `prepareForPipeline` normalizes empty strings to `null` for `secondary_text`, `locale_text`, and `link_url`. In [EditContentBlock](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php), `syncPreviewState()` normalizes empty strings to `null` before passing `$rawPayload` into [SaveContentBlockPreviewState](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/SaveContentBlockPreviewState.php), ensuring optional cleared fields do not trigger false validation failures.

### 3.3 Component State and Record Access in Filament 5 Schemas
- [View.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/View.php) extends `Filament\Schemas\Components\Component`.
- `Component` uses `BelongsToModel` which defines `getRecord(): Model|array|null` and `BelongsToContainer` which defines `getLivewire(): Component&HasSchemas`.
- When rendering views registered via `View::make('view-name')`, Filament provides `$getRecord` closure, `$record` model instance, and `$livewire` component instance to the Blade template.

### 3.4 Storefront JavaScript and Iframe Sandboxing
- [storefront.js](file:///c:/dev/getnow-cms/resources/js/storefront.js) imports ES modules (`carousel.js`, `dialogs.js`, `forms.js`).
- In production (`npm run build`), Vite produces `<script type="module" src="...">` tags.
- In iframe elements with `sandbox="allow-scripts"` (omitting `allow-same-origin`), the iframe origin is treated as an opaque origin `null`. Browsers permit local script execution under `allow-scripts`, but module imports requesting assets across origins require standard CORS handling. Baseline configuration specifies `sandbox="allow-scripts"` without `allow-same-origin`.

### 3.5 Action Signatures in Content Domain
- [GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php#L24-L33):
  ```php
  public function __construct(
      private readonly StorefrontSettings $settings,
      private readonly BlockRegistry $registry,
      private readonly ResolveServablePayload $resolveServablePayload,
  ) {}
  public function __invoke(): LayoutResponseData
  ```
- [GetUpgradedBlockPayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetUpgradedBlockPayload.php#L16-L26):
  ```php
  public function __construct(
      private readonly BlockRegistry $registry
  ) {}
  public function __invoke(string $type, int $schemaVersion, array $payload): BlockPayload
  ```
- [ResolveServablePayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Services/ResolveServablePayload.php#L18-L25):
  ```php
  public function __construct(
      private readonly GetUpgradedBlockPayload $getUpgradedBlockPayload
  ) {}
  public function __invoke(ContentBlock $block, bool $log = true): ServingHealthResult
  ```
- [CountLiveBlocksForPlacement.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/CountLiveBlocksForPlacement.php#L11-L14):
  ```php
  public function __invoke(Placement $placement): int
  ```

### 3.6 Additional Verifications (M6)
- **View Variable `$layout`**: In [DemoHomeController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Storefront/DemoHomeController.php#L16-L17), `$layoutResponse = $getLayoutContent(); $layout = $layoutResponse->data;`. The variable passed to `storefront.home` via `compact('layout', ...)` is an instance of `LayoutData`, not `LayoutResponseData`.
- **Controller Base Class**: [App\Http\Controllers\Controller](file:///c:/dev/getnow-cms/app/Http/Controllers/Controller.php) exists as an abstract base class (`abstract class Controller`).
- **Exception Hierarchy**:
  - `UnknownBlockTypeException extends ContentException`
  - `UnsupportedSchemaVersionException extends ContentException`
  - `UpgradeFailedException extends ContentException`
  - `InvalidPayloadException extends ContentException`
  - In [GetUpgradedBlockPayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetUpgradedBlockPayload.php#L55-L59), `ValidationException` is caught and rethrown as `InvalidPayloadException`. Catching `ContentException|ValidationException` in `ResolveContentBlockPreview` guarantees that any upgrade failure, schema mismatch, or validation exception is caught without uncaught exceptions escaping.

---

## 4. File Manifest

| Operation | File Path | Purpose |
|---|---|---|
| Modify | [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) | Fix carry-forward `m1` by replacing direct `ContentBlock::visible()` query with `CountLiveBlocksForPlacement`; wrap form schema in 12-column grid with 5-column live preview pane. |
| Modify | [ContentBlockEditTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php) | Add test verifying `placement_display` helper text on edit page; add test for preview state cleanup on save. |
| Create | `resources/views/storefront/partials/blocks/announcement-bar.blade.php` | Shared Blade partial for announcement bar with preview target attributes. |
| Create | `resources/views/storefront/partials/blocks/hero-carousel.blade.php` | Shared Blade partial forwarding slides and preview target ID to promo carousel. |
| Create | `resources/views/storefront/partials/blocks/feature-banner.blade.php` | Shared Blade partial rendering hero side or collection banner variant with preview target attributes. |
| Create | `resources/views/storefront/partials/blocks/service-strip.blade.php` | Shared Blade partial rendering service strip items with preview target attributes. |
| Create | `resources/views/storefront/partials/blocks/section-header.blade.php` | Shared Blade partial rendering section header with preview target attributes. |
| Create | `resources/views/storefront/partials/blocks/brand-strip.blade.php` | Shared Blade partial rendering brand strip with preview target attributes. |
| Create | `resources/views/storefront/partials/preview-chrome.blade.php` | Shared preview chrome partial handling fallback banner, unservable placeholder, outline styles, and script interception. |
| Modify | [promo-carousel.blade.php](file:///c:/dev/getnow-cms/resources/views/components/promo-carousel.blade.php) | Accept `$previewTargetId` prop, set target slide as active slide index, and attach `data-preview-target="true" id="preview-target-block"`. |
| Modify | [layouts/storefront.blade.php](file:///c:/dev/getnow-cms/resources/views/layouts/storefront.blade.php) | Include `preview-chrome` and refactor announcement bar to `@include('storefront.partials.blocks.announcement-bar')`. |
| Modify | [storefront/home.blade.php](file:///c:/dev/getnow-cms/resources/views/storefront/home.blade.php) | Refactor all block sections to `@include` shared partials. |
| Modify | [DemoHomeController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Storefront/DemoHomeController.php) | Extract catalog arrays into `getFeaturedProducts()`, `getNewProducts()`, `getCategories()`. |
| Create | `tests/Fixtures/storefront/home-seeded-golden.html` | Pre-refactor golden HTML snapshot fixture for `GET /`. |
| Modify | [DemoHomeTest.php](file:///c:/dev/getnow-cms/tests/Feature/Storefront/DemoHomeTest.php) | Add golden snapshot parity assertion and verify absence of preview markers on public `GET /`. |
| Create | `app/Domain/Content/Data/ContentBlockPreviewData.php` | Spatie Data DTO for preview resolution output with nullable `?Placement $placement`. |
| Create | `app/Domain/Content/Actions/SaveContentBlockPreviewState.php` | Invokable action caching ephemeral draft payload and sort order. |
| Create | `app/Domain/Content/Actions/ResolveContentBlockPreview.php` | Invokable action resolving preview block, handling fallback, and assembling full layout. |
| Create | `tests/Feature/Domain/Content/Actions/SaveContentBlockPreviewStateTest.php` | Feature tests for cache writing, token generation, per-user isolation, and clearing. |
| Create | `tests/Feature/Domain/Content/Actions/ResolveContentBlockPreviewTest.php` | Feature tests for draft resolution, fallback banner flag, corrupt DB row handling, singleton replacement, multi-block D8 sorting, and capacity eviction. |
| Modify | [ActionCoverageTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/ActionCoverageTest.php) | Ensure action coverage verification handles non-mutating domain actions. |
| Create | `app/Http/Controllers/Admin/ContentBlockPreviewController.php` | Controller hosting `isolated()` and `page()` preview endpoints with panel authorization. |
| Modify | [routes/web.php](file:///c:/dev/getnow-cms/routes/web.php) | Register `admin.content-blocks.preview.isolated` and `admin.content-blocks.preview.page` routes. |
| Create | `resources/views/filament/admin/resources/content-block-resource/pages/isolated-preview.blade.php` | Standalone HTML5 document shell for isolated block preview with explicit placement mapping. |
| Create | `resources/views/filament/admin/resources/content-block-resource/components/view-isolated-preview.blade.php` | Component for View page displaying preview iframe and metadata badges. |
| Create | `resources/views/filament/admin/resources/content-block-resource/components/live-preview-pane.blade.php` | Component for Edit page displaying live preview pane, toolbar, and iframe. |
| Modify | [ViewContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php) | Mount `'Storefront Preview (Approximation)'` section at top of schema. |
| Modify | [EditContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) | Add locked preview properties, `setPreviewMode`, `setPreviewDevice`, `syncPreviewState`, and clear cache on save. |
| Create | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` | Integration tests for preview routes: 200 responses, 403 authorization, noindex headers, and fallback banner rendering. |
| Modify | [ContentBlockViewTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php) | Add assertions for View page preview section and metadata badges. |
| Create | `docs/adr/0005-admin-block-preview-approximation.md` | Architectural Decision Record documenting ephemeral block preview and amending D7. |
| Modify | [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | Update Phase 1 progress, Phase 2 scope, code structure, content rules, admin conventions, and Decision D7. |
| Modify | [DEMO-TEARDOWN.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-S1/DEMO-TEARDOWN.md) | Document shared partials and preview controller cleanup during Level 1 teardown. |

---

## 5. Architecture & Component Design

### 5.1 DTO Specification (`ContentBlockPreviewData.php`)
Located at `app/Domain/Content/Data/ContentBlockPreviewData.php`:
```php
<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\Placement;
use Spatie\LaravelData\Data;

final class ContentBlockPreviewData extends Data
{
    public function __construct(
        public readonly ?LayoutBlockData $block,
        public readonly ?Placement $placement,
        public readonly ?LayoutData $layout,
        public readonly bool $usingFallbackPayload,
        public readonly bool $isUnservable,
    ) {}
}
```
Field specifications:
- `$block`: `?LayoutBlockData`, null when `$isUnservable === true`.
- `$placement`: `?Placement`, null only when `$contentBlock->placement` is null or invalid via `TolerantEnum` (resolving B3).
- `$layout`: `?LayoutData`, populated when `$includeFullLayout === true`, null in isolated mode.
- `$usingFallbackPayload`: `bool`, true when cached draft failed validation and fallback stored payload is used.
- `$isUnservable`: `bool`, true when stored database payload is corrupt and no valid draft exists, or placement/definition is invalid.

### 5.2 Actions Specification

#### `SaveContentBlockPreviewState.php`
- Invokable action:
  ```php
  public function __invoke(int $userId, string $publicId, array $rawPayload, int $sortOrder): string
  ```
- Generates 16-character hex token: `bin2hex(random_bytes(8))`.
- Writes array `['payload' => $rawPayload, 'sort_order' => $sortOrder, 'token' => $token]` to `Cache::put(self::cacheKey($userId, $publicId), ..., now()->addMinutes(15))`.
- `public static function cacheKey(int $userId, string $publicId): string` returns `"content_block_preview:{$userId}:{$publicId}"`.
- `public static function clear(int $userId, string $publicId): void` calls `Cache::forget(self::cacheKey($userId, $publicId))`.

#### `ResolveContentBlockPreview.php`
- Dependencies: `BlockRegistry`, `GetUpgradedBlockPayload`, `ResolveServablePayload`, `GetLayoutContent`.
- Signature:
  ```php
  public function __invoke(ContentBlock $contentBlock, int $userId, bool $includeFullLayout = false, bool $useEphemeralCache = false): ContentBlockPreviewData
  ```
- Guard: Checks `$placement = $contentBlock->placement` and `$definition = $this->registry->get($contentBlock->type)`. If `! $placement instanceof Placement || $definition === null`, returns `ContentBlockPreviewData(block: null, placement: $placement instanceof Placement ? $placement : null, layout: $includeFullLayout ? ($this->getLayoutContent)()->data : null, usingFallbackPayload: false, isUnservable: true)`.
- Draft & Stored Payload Resolution:
  - If `$useEphemeralCache === true` and cache has data at `SaveContentBlockPreviewState::cacheKey($userId, $contentBlock->public_id)`:
    - Attempts upgrading `$cachedData['payload']` using `$definition->schemaVersion()` via `GetUpgradedBlockPayload`.
    - If valid, constructs `$block = new LayoutBlockData(id: $contentBlock->public_id, type: $contentBlock->type, version: $definition->schemaVersion(), data: $upgradedDraft->toArray())` with `$usingFallbackPayload = false`, `$isUnservable = false`.
    - If `ContentException` or `ValidationException` caught: sets `$usingFallbackPayload = true`, falls back to stored database payload via `ResolveServablePayload($contentBlock, log: false)`.
  - If `$useEphemeralCache === false` or no cache entry: resolves stored database payload via `ResolveServablePayload($contentBlock, log: false)`.
  - Stored Payload Outcome: If `$health->isServable() && $health->payload !== null`, constructs `$block` with `$definition->schemaVersion()`; otherwise sets `$isUnservable = true`, `$block = null`, `$usingFallbackPayload = false`.
- Full-Layout Placement Assembly & D8 Ordering (resolving B4):
  - When `$includeFullLayout === true`:
    - Reads `$baseLayout = ($this->getLayoutContent)()->data; $placements = $baseLayout->placements;`.
    - If `$block !== null`:
      - Singleton (`$placement->maxCount() === 1`): Sets `$placements[$placement->value] = [$block]`.
      - Multi-block (`$placement->maxCount() > 1`):
        - Queries `ContentBlock::visible()->where('placement', $placement->value)->where('id', '!=', $contentBlock->id)->servingOrder()->get()`.
        - Filters servable candidates via `ResolveServablePayload($candidate, log: false)` and allowed block types.
        - Constructs candidate entries with metadata (`block`, `sort_order`, `starts_at`, `id`).
        - Determines target block's effective sort order: reads `$cachedData['sort_order']` if `$useEphemeralCache === true` and present, otherwise `$contentBlock->sort_order`.
        - Merges target block entry and sorts all entries by Decision D8:
          (1) `sort_order ASC`
          (2) `starts_at !== null` before `starts_at === null`, non-null timestamps ordered `DESC` (`starts_at DESC NULLS LAST`)
          (3) `id DESC`
        - Eviction at capacity (`count > $placement->maxCount()`): If target block is within top `maxCount()`, slices array to `maxCount()`. If target block ranks beyond `maxCount()`, slices to `maxCount() - 1` and places target block at index `maxCount() - 1`.
        - Extracts `array_column($top, 'block')` and assigns to `$placements[$placement->value]`.
    - Constructs `$layout = new LayoutData(settings: $baseLayout->settings, placements: $placements, menus: $baseLayout->menus)`.

### 5.3 Shared Storefront Partials & Preview Chrome (resolving B2, M4)
All partials reside in `resources/views/storefront/partials/`.
No `@props` directives are used. Every `@include` call explicitly passes target markers.
1. `preview-chrome.blade.php`:
   - Fallback banner: `<div id="preview-fallback-banner" role="status" style="background-color: #fef3c7; color: #92400e; padding: 0.75rem 1rem; border-bottom: 1px solid #fcd34d; font-size: 0.875rem; text-align: center; font-weight: 500;">Showing last valid saved state — current draft inputs have validation errors.</div>` when `$usingFallbackPayload === true`.
   - Unservable placeholder: `<div id="preview-unservable-placeholder" role="alert" style="background-color: #fee2e2; color: #991b1b; padding: 2rem 1rem; border: 1px dashed #f87171; border-radius: 0.375rem; font-size: 0.875rem; text-align: center; margin: 1rem auto; max-width: 600px;">Preview unavailable: stored block payload failed schema validation.</div>` when `$isUnservable === true`.
   - Outline style: `<style> [data-preview-target="true"] { outline: 3px solid #6366f1 !important; outline-offset: 4px !important; } </style>`.
   - Script interception: On `DOMContentLoaded`, calls `scrollIntoView({ behavior: 'instant', block: 'center' })` on `#preview-target-block`, and intercepts `click` on `a[href]` (`preventDefault()`) and `submit` on `form` (`preventDefault()`).
2. `isolated-preview.blade.php` Explicit Mapping (resolving B2):
   - Shell: HTML5 document with `@vite(['resources/css/storefront.css', 'resources/js/storefront.js'])`.
   - Includes `storefront.partials.preview-chrome`.
   - Placement matching:
     - `Placement::ANNOUNCEMENT_BAR`: `@include('storefront.partials.blocks.announcement-bar', ['block' => $block, 'previewTarget' => true])`
     - `Placement::HOME_HERO_CAROUSEL`: `<div class="container">@include('storefront.partials.blocks.hero-carousel', ['slides' => [$block], 'previewTargetId' => $block->id])</div>`
     - `Placement::HOME_HERO_SIDE`: `<div class="container">@include('storefront.partials.blocks.feature-banner', ['block' => $block, 'variant' => 'hero_side', 'previewTarget' => true])</div>`
     - `Placement::HOME_SERVICE_STRIP`: `<div class="container">@include('storefront.partials.blocks.service-strip', ['items' => [$block], 'previewTargetId' => $block->id])</div>`
     - `Placement::HOME_CATEGORIES_HEADER`, `Placement::HOME_FEATURED_HEADER`, `Placement::HOME_NEW_ARRIVALS_HEADER`: `<div class="container">@include('storefront.partials.blocks.section-header', ['block' => $block, 'previewTarget' => true])</div>`
     - `Placement::HOME_COLLECTION_BANNER`: `<div class="container">@include('storefront.partials.blocks.feature-banner', ['block' => $block, 'variant' => 'collection', 'previewTarget' => true])</div>`
     - `Placement::HOME_BRAND_STRIP`: `@include('storefront.partials.blocks.brand-strip', ['block' => $block, 'previewTarget' => true])`
3. Carousel Target Display (resolving M4):
   - In [promo-carousel.blade.php](file:///c:/dev/getnow-cms/resources/views/components/promo-carousel.blade.php), calculates `$targetIndex = $previewTargetId !== null ? collect($slides)->search(fn ($s) => $s->id === $previewTargetId) : false; $activeSlideIndex = $targetIndex !== false ? (int) $targetIndex : 0;`.
   - Slide visibility uses `@if($loop->index !== $activeSlideIndex) hidden inert @endif` and pagination dot uses `@if($loop->index === $activeSlideIndex) aria-current="true" @endif`. Target slide is displayed as active slide without rendering two slides at once.

### 5.4 Controller Specification (`ContentBlockPreviewController.php`)
Located at `app/Http/Controllers/Admin/ContentBlockPreviewController.php`:
- Extends `App\Http\Controllers\Controller`.
- Method `isolated(Request $request, ContentBlock $contentBlock, ResolveContentBlockPreview $resolvePreview): Response`:
  - Enforces `abort_unless($user instanceof User && $panel !== null && $user->canAccessPanel($panel) && $user->can('view', $contentBlock), 403)`.
  - Invokes `$preview = $resolvePreview($contentBlock, (int) $user->getAuthIdentifier(), includeFullLayout: false, useEphemeralCache: $request->boolean('live'))`.
  - Returns view `filament.admin.resources.content-block-resource.pages.isolated-preview` with header `X-Robots-Tag: noindex, nofollow`.
- Method `page(Request $request, ContentBlock $contentBlock, ResolveContentBlockPreview $resolvePreview): Response`:
  - Enforces `abort_unless($user instanceof User && $panel !== null && $user->canAccessPanel($panel) && $user->can('view', $contentBlock), 403)`.
  - Invokes `$preview = $resolvePreview($contentBlock, (int) $user->getAuthIdentifier(), includeFullLayout: true, useEphemeralCache: $request->boolean('live'))`.
  - Returns view `storefront.home` with header `X-Robots-Tag: noindex, nofollow`, passing `$layout = $preview->layout`, `$featured`, `$newProducts`, `$categories`, `$cartCount = 0`, and preview flags.

### 5.5 View Page Wire-Up (`ViewContentBlock.php`)
- In [ViewContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php#L71), prepends `Section::make('Storefront Preview (Approximation)')->schema([ View::make('filament.admin.resources.content-block-resource.components.view-isolated-preview') ])`.
- Component `view-isolated-preview.blade.php`: displays badges for Page, Placement, Status, and Serving Status alongside `<iframe src="{{ route('admin.content-blocks.preview.isolated', ['contentBlock' => $getRecord()->public_id]) }}" sandbox="allow-scripts" style="width: 100%; height: 320px; border: 1px solid #e5e7eb; border-radius: 6px;"></iframe>`.

### 5.6 Edit Page & Form Wire-Up (`EditContentBlock.php` & `ContentBlockForm.php`)
- In [EditContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php):
  - Properties: `#[Locked] public string $previewMode = 'page'; #[Locked] public string $previewDevice = 'desktop'; #[Locked] public string $previewToken = '';`.
  - `setPreviewMode(string $mode)`: accepts only `'page'` or `'isolated'`.
  - `setPreviewDevice(string $device)`: accepts only `'desktop'`, `'tablet'`, or `'mobile'`.
  - `syncPreviewState()`: normalizes empty strings to null on form payload, calls `SaveContentBlockPreviewState`, updates `$previewToken`. No call in `mount()` (resolving M3).
  - `handleRecordUpdate()`: calls `SaveContentBlockPreviewState::clear((int) $causer->getAuthIdentifier(), $record->public_id)` upon successful update.
- In [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php):
  - Wraps schema in `Grid::make(12)`:
    - Left column: `Group::make([...existing components...])->columnSpan(fn (string $operation): int => $operation === 'edit' ? 7 : 12)`.
    - Right column: `Section::make('Storefront Preview (Approximation)')->schema([ View::make('filament.admin.resources.content-block-resource.components.live-preview-pane') ])->columnSpan(5)->visibleOn('edit')`.
- Component `live-preview-pane.blade.php`:
  - Outer toolbar wrapper has Alpine listener: `x-data x-init="$el.closest('form').addEventListener('input', () => { setTimeout(() => $wire.syncPreviewState(), 500) }); $el.closest('form').addEventListener('change', () => { setTimeout(() => $wire.syncPreviewState(), 250) });"`.
  - Inner iframe wrapper keyed by Livewire: `<div wire:key="preview-frame-{{ $previewMode }}-{{ $this->previewToken }}">` embedding `<iframe src="{{ route('admin.content-blocks.preview.' . (in_array($this->previewMode, ['page', 'isolated'], true) ? $this->previewMode : 'page'), ['contentBlock' => $getRecord()->public_id, 'live' => 1, 'token' => $this->previewToken]) }}" sandbox="allow-scripts" style="width: {{ $viewportWidth }}; height: 620px; border: 1px solid #e5e7eb; border-radius: 6px; margin: 0 auto; display: block;"></iframe></div>`.

---

## 6. Data and Migrations

### 6.1 Database Schema
Zero migrations and zero schema changes (Rule DB2). The `content_blocks` table remains unchanged.

### 6.2 Cache Schema
- **Cache Key**: `content_block_preview:{userId}:{publicId}`
- **TTL**: 15 minutes (`now()->addMinutes(15)`)
- **Payload Shape**:
  ```php
  [
      'payload' => array<string, mixed>,
      'sort_order' => int,
      'token' => string, // 16-character hex string
  ]
  ```

### 6.3 Nullable TolerantEnum Placement Handling (resolving B3)
`ContentBlock::$placement` is cast via `TolerantEnum::class.':'.Placement::class`. If a legacy or malformed record has an invalid placement string, Eloquent returns `null`. `ContentBlockPreviewData::$placement` is typed as `?Placement`. `ResolveContentBlockPreview` checks `$placement instanceof Placement` and safely returns `placement: null` and `isUnservable: true` without throwing `TypeError`.

---

## 7. Behavior & Flows

### 7.1 Isolated Preview Flow (`/preview/isolated`)
```
Admin Browser ──► GET /admin/content-blocks/{public_id}/preview/isolated
                      │
                      ▼
        ContentBlockPreviewController::isolated()
                      │
                      ├─► Authorization check: User is admin, canAccessPanel('admin'), can('view', block)
                      │   (Fails: 403 Forbidden)
                      │
                      ▼
        ResolveContentBlockPreview::__invoke(block, userId, includeFullLayout: false, useEphemeralCache: live)
                      │
                      ├─► If live=1: Fetch cache. If valid draft, use draft; if invalid, set usingFallbackPayload=true.
                      ├─► If live=0 or no cache: Resolve stored payload via ResolveServablePayload(log: false).
                      │
                      ▼
        Return Response: isolated-preview.blade.php
                      │
                      ├─► If isUnservable: Renders #preview-unservable-placeholder
                      ├─► If usingFallbackPayload: Renders #preview-fallback-banner
                      └─► Renders placement-specific block partial with storefront CSS
```

### 7.2 Page Context Preview Flow (`/preview/page`)
```
Admin Browser ──► GET /admin/content-blocks/{public_id}/preview/page
                      │
                      ▼
        ContentBlockPreviewController::page()
                      │
                      ├─► Authorization check (403 if unauthorized)
                      │
                      ▼
        ResolveContentBlockPreview::__invoke(block, userId, includeFullLayout: true, useEphemeralCache: live)
                      │
                      ├─► Resolves block payload (draft or stored fallback)
                      ├─► Calls GetLayoutContent() for base layout
                      ├─► If singleton (maxCount=1): Replaces placement array with [$block]
                      ├─► If multi-block (maxCount>1): Merges with other servable blocks, sorts D8, evicts overflow
                      │
                      ▼
        Return Response: storefront.home view
                      │
                      ├─► Injects layout, demo products, demo categories
                      ├─► Includes preview-chrome partial
                      ├─► Highlights target block with data-preview-target="true"
                      └─► Intercepts links and form submissions
```

### 7.3 Edit Page Live Preview Sync Flow
```
Admin types in Edit Form ──► Form-scoped input event ($el.closest('form'))
                                  │
                                  ▼
                             Debounce 500ms
                                  │
                                  ▼
                             Livewire calls $wire.syncPreviewState()
                                  │
                                  ├─► Normalizes empty strings "" to null
                                  ├─► Calls SaveContentBlockPreviewState(userId, publicId, payload, sortOrder)
                                  ├─► Generates new 16-char hex token
                                  ├─► Caches state for 15 minutes
                                  └─► Sets $this->previewToken
                                  │
                                  ▼
                             Livewire re-renders Edit page
                                  │
                                  ▼
                             <div wire:key="preview-frame-{mode}-{token}">
                             Updates iframe src with new token
                             (Replaces DOM node; zero browser history stack pollution)
```

### 7.4 Save and Clear Flow
```
Admin clicks Save on Edit Form ──► EditContentBlock::handleRecordUpdate()
                                       │
                                       ▼
                                  UpdateContentBlockAction::__invoke()
                                       │
                                       ▼
                                  SaveContentBlockPreviewState::clear(userId, publicId)
                                       │
                                       ▼
                                  Cache key deleted
```

---

## 8. Rule Compliance Matrix

| Rule | Description | Compliance Mechanism in P1-UI6 |
|---|---|---|
| **E1** | Windows environment & PowerShell commands | Commands executed individually without bash chaining or `&&`. |
| **S1 & S2** | Business logic in Domain Actions; thin controllers and Filament | Preview caching in `SaveContentBlockPreviewState`; layout assembly in `ResolveContentBlockPreview`; placement counting in `CountLiveBlocksForPlacement`. |
| **S3 & S4** | Domain isolation; zero direct Model writes in Filament | Zero `App\Filament` imports in `App\Domain`; preview cache operations do not mutate database rows. |
| **S7** | Code in correct folder | Preview controller placed in `app/Http/Controllers/Admin/`. |
| **C1 & C2** | Strict types, type coverage, final classes | Every PHP file declares `strict_types=1`; typed properties and methods; final classes. |
| **C6** | Validation and policy authorization | `ContentBlockPolicy::view()` and `canAccessPanel` checked on all preview requests. |
| **K3** | No raw HTML/CSS/JS in fields; auto-escaping | Strict `{{ ... }}` auto-escaping in all partials and views; zero `{!! ... !!}`. |
| **K8** | Additive Content API contract | `GET /api/v1/content/layout` JSON shape unmodified. |
| **DB2** | Never edit merged migrations | Zero database migrations introduced. |
| **SEC4** | Server-side authorization | Preview routes check `$user->canAccessPanel(Filament::getPanel('admin'))` and policy `view()`. |
| **T1 & T2** | Pest feature tests & smoke tests | Integration tests for preview routes, domain actions, and Filament View/Edit pages. |
| **D7** | Published edits go live on save | Live preview state remains in cache; database row mutates only when Save is clicked. |

---

## 9. Acceptance Criteria Traceability

| AC ID | Brief Requirement | Implementation Mechanism | Test Coverage |
|---|---|---|---|
| **AC-1** | Edit placement helper text uses `CountLiveBlocksForPlacement` | Line 147 in `ContentBlockForm.php` invokes action | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` |
| **AC-2** | Shared partials render `GET /` with 100% golden snapshot parity and zero markers | Refactored `layouts/storefront.blade.php` and `storefront/home.blade.php` | `tests/Feature/Storefront/DemoHomeTest.php` |
| **AC-3** | Singleton replacement & multi-block D8 sorting with capacity eviction | `ResolveContentBlockPreview` singleton replace and multi-block D8 sort logic | `tests/Feature/Domain/Content/Actions/ResolveContentBlockPreviewTest.php` |
| **AC-4** | Multi-block live parity with `GetLayoutContent` | Candidate filtering and D8 sort formula in `ResolveContentBlockPreview` | `tests/Feature/Domain/Content/Actions/ResolveContentBlockPreviewTest.php` |
| **AC-5** | Single target marker `id="preview-target-block"` on page context preview | Explicit parameter passing in partial includes and promo carousel active index | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` |
| **AC-6** | Invalid ephemeral draft fallback banner `#preview-fallback-banner` | Catch validation exceptions and set `usingFallbackPayload = true` | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` |
| **AC-7** | Valid ephemeral draft renders over corrupt stored DB payload | Draft validated first at code schema version before stored fallback | `tests/Feature/Domain/Content/Actions/ResolveContentBlockPreviewTest.php` |
| **AC-8** | Corrupt stored DB payload placeholder `#preview-unservable-placeholder` | `isUnservable = true` returned with `log: false` | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` |
| **AC-9** | Preview routes return HTTP 200 with `X-Robots-Tag: noindex, nofollow` | Controller methods set headers across all statuses and placements | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` |
| **AC-10** | Unauthenticated guests and non-admin users receive HTTP 403 | `abort_unless(..., 403)` with `canAccessPanel` in controller | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` |
| **AC-11** | `<script>` escaping in block payload | Strict Blade `{{ ... }}` auto-escaping in all partials | `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php` |
| **AC-12** | Ephemeral cache write with string normalization and zero DB mutation | `syncPreviewState()` normalizes `""` to `null` and writes to cache | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` |
| **AC-13** | Per-user cache isolation and clear on save | Cache key incorporates `{userId}`; `handleRecordUpdate()` calls `clear()` | `tests/Feature/Domain/Content/Actions/SaveContentBlockPreviewStateTest.php` & `ContentBlockEditTest.php` |
| **AC-14** | Livewire properties locked with `#[Locked]` and validated in setters | `#[Locked]` on `$previewMode`, `$previewDevice`, `$previewToken` | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` |
| **AC-15** | View and Edit schema integration with `wire:key` history protection | Section added to `ViewContentBlock`; Grid 12 and `wire:key` in `EditContentBlock` | `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php` & `ContentBlockEditTest.php` |
| **AC-16** | ADR 0005, ARCHITECTURE.md, DEMO-TEARDOWN.md, `ActionCoverageTest` green | Documentation files created/updated; full `composer check` passes | `ActionCoverageTest.php` & Layer A checks |

---

## 10. Testing Strategy & Fixtures

### 10.1 Carry-Forward & Storefront Integration Tests

#### Test: Edit Placement Helper Text (`ContentBlockEditTest.php`)
- **Arrange**: Seed content blocks. Create admin user. Query published block in `announcement_bar`.
- **Act**: Load `EditContentBlock` page for record.
- **Assert**: Assert form renders `"1 / 1 live in this placement"`.

#### Test: Golden Snapshot Parity on `GET /` (`DemoHomeTest.php`)
- **Arrange**: Seed database via `ContentSeeder::run()`. Pre-captured golden HTML at `tests/Fixtures/storefront/home-seeded-golden.html`.
- **Act**: Execute `GET /`.
- **Assert**: Assert response HTTP 200. Normalize whitespace across both output and fixture, asserting byte-for-byte equality.

#### Test: Zero Preview Markers on Public `GET /` (`DemoHomeTest.php`)
- **Arrange**: Seed database via `ContentSeeder::run()`.
- **Act**: Execute `GET /`.
- **Assert**: Assert response does not contain `data-preview-target`, `preview-target-block`, `preview-fallback-banner`, or `preview-unservable-placeholder`.

### 10.2 Domain Action Feature Tests

#### `SaveContentBlockPreviewStateTest.php`
- **Test 1: Cache Write & Token Generation**:
  - **Arrange**: Admin user ID 1, public ID `01J...`, payload `['text' => 'Hello']`, sort order 2.
  - **Act**: Invoke `SaveContentBlockPreviewState`.
  - **Assert**: Returns 16-character hex token. `Cache::get("content_block_preview:1:01J...")` contains payload, sort order, and token.
- **Test 2: Per-User Isolation**:
  - **Arrange**: Admin A (ID 1), Admin B (ID 2), same public ID `01J...`.
  - **Act**: Save state for Admin A with text 'A', save state for Admin B with text 'B'.
  - **Assert**: Cache for Admin A has text 'A'; cache for Admin B has text 'B'.
- **Test 3: Clear on Save**:
  - **Arrange**: Cache state stored for Admin 1, public ID `01J...`.
  - **Act**: Call `SaveContentBlockPreviewState::clear(1, '01J...')`.
  - **Assert**: `Cache::has("content_block_preview:1:01J...")` is false.

#### `ResolveContentBlockPreviewTest.php`
- **Test 1: Singleton Placement Replacement**:
  - **Arrange**: Live published block in `announcement_bar`. Target block in draft in `announcement_bar`.
  - **Act**: Invoke `ResolveContentBlockPreview` with `includeFullLayout: true`.
  - **Assert**: `layout->placements['announcement_bar']` has length 1 containing target block's public ID.
- **Test 2: Multi-Block D8 Sorting**:
  - **Arrange**: 3 live slides in `home_hero_carousel` with sort orders 10, 20, 30. Draft slide with sort order 15.
  - **Act**: Invoke `ResolveContentBlockPreview` with `includeFullLayout: true`.
  - **Assert**: `layout->placements['home_hero_carousel']` contains 4 slides sorted 10, 15, 20, 30.
- **Test 3: Capacity Eviction**:
  - **Arrange**: 5 live slides in `home_hero_carousel` (maxCount=5) with sort orders 10, 20, 30, 40, 50. Draft slide with sort order 99.
  - **Act**: Invoke `ResolveContentBlockPreview` with `includeFullLayout: true`.
  - **Assert**: Placement contains exactly 5 slides: first 4 live slides (10, 20, 30, 40) plus draft slide (99), evicting slide 50.
- **Test 4: Parity with `GetLayoutContent`**:
  - **Arrange**: Seeded database. Target block is the active live collection banner.
  - **Act**: Resolve preview for block with `includeFullLayout: true`. Call `GetLayoutContent`.
  - **Assert**: Block data in `home_collection_banner` strictly matches `GetLayoutContent`.
- **Test 5: Fallback Banner Flag on Invalid Draft**:
  - **Arrange**: Valid stored block. Cached draft has empty required text `['text' => '']`.
  - **Act**: Resolve preview with `useEphemeralCache: true`.
  - **Assert**: `$preview->usingFallbackPayload` is true; `$preview->block` contains stored payload.
- **Test 6: Valid Draft over Corrupt Stored Payload**:
  - **Arrange**: Stored block payload corrupted in DB. Cached draft is valid `['text' => 'Valid']`.
  - **Act**: Resolve preview with `useEphemeralCache: true`.
  - **Assert**: `$preview->usingFallbackPayload` is false; `$preview->isUnservable` is false; `$preview->block` contains draft data.
- **Test 7: Unservable State on Corrupt DB**:
  - **Arrange**: Stored block payload corrupted in DB. No cached draft.
  - **Act**: Resolve preview with `useEphemeralCache: false`.
  - **Assert**: `$preview->isUnservable` is true; `$preview->block` is null.

### 10.3 Admin Controller Feature Tests (`ContentBlockPreviewTest.php`)
- **Test 1: Guest 403**: Unauthenticated request to `/preview/isolated` and `/preview/page` returns 403 Forbidden.
- **Test 2: Non-Admin 403**: Authenticated user without admin role returns 403 Forbidden.
- **Test 3: Admin 200 Happy Path**: Authenticated platform admin receives 200 on both endpoints with header `X-Robots-Tag: noindex, nofollow` across draft, published, disabled, and archived blocks.
- **Test 4: Single Target Marker**: `/preview/page` HTML contains `data-preview-target="true"` and `id="preview-target-block"` on at most one element.
- **Test 5: Fallback Banner**: Requesting `/preview/page?live=1` with invalid draft renders `#preview-fallback-banner`.
- **Test 6: Unservable Placeholder**: Preview for corrupt DB row renders `#preview-unservable-placeholder`.
- **Test 7: XSS Escaping**: Block with payload text `<script>alert(1)</script>` renders as `&lt;script&gt;alert(1)&lt;/script&gt;`.
- **Test 8: Clear on Save**: Editing form on `EditContentBlock` and clicking Save clears preview cache entry.

### 10.4 Mutation Spot-Checks

#### Executed at Checkpoint CP-A:
- **`MUT-1` (Singleton Replacement)**: In `ResolveContentBlockPreview.php`, change `$placement->maxCount() === 1` to `false`. Run `ResolveContentBlockPreviewTest`. Test asserting singleton draft replacement must FAIL.
- **`MUT-2` (Fallback Banner Flag)**: In `ResolveContentBlockPreview.php`, set `$usingFallbackPayload = false` in the exception catch block. Run `ResolveContentBlockPreviewTest`. Test asserting fallback flag must FAIL.
- **`MUT-3` (Per-User Cache Key)**: In `SaveContentBlockPreviewState::cacheKey()`, remove `{$userId}` from key string. Run `SaveContentBlockPreviewStateTest`. Test asserting per-user isolation must FAIL.

#### Executed at Checkpoint CP-B:
- **`MUT-4` (Authorization Guard)**: In `ContentBlockPreviewController::isolated()`, comment out the `abort_unless(...)` check. Run `ContentBlockPreviewTest`. Test asserting guest/non-admin 403 must FAIL.
- **`MUT-5` (Clear Cache on Save)**: In `EditContentBlock::handleRecordUpdate()`, comment out `SaveContentBlockPreviewState::clear(...)`. Run `ContentBlockPreviewTest`. Test asserting cache cleanup on save must FAIL.

---

## 11. Documentation Updates

### 11.1 ADR 0005 (`docs/adr/0005-admin-block-preview-approximation.md`)
Create file with verbatim content:
```markdown
# 0005: Ephemeral Admin Block Preview Approximation

Date: 2026-10-19

## Status
Accepted (Amends ADR 0001, Decision D7, and ARCHITECTURE.md §1 phase table)

## Context
Decision D7 specified that published content edits go live immediately on Save, deferring revisions and previews to Phase 2. However, admins editing visual blocks (hero carousels, feature banners, service strips) require visual feedback before publishing to prevent storefront disruption.

## Decision
1. Introduce ephemeral cache-backed admin block previews in Phase 1:
   - Isolated single-block preview on `ViewContentBlock`.
   - Native split-screen live preview ('Storefront Preview Approximation') on `EditContentBlock` with Desktop, Tablet, and Mobile viewport toggles.
2. Form input changes on `EditContentBlock` synchronize debounced state to cache via `SaveContentBlockPreviewState` without mutating the database row, preserving Decision D7.
3. Persistent revisions, version history, and public API preview tokens remain strictly in Phase 2.
4. Preview routes reside in `app/Http/Controllers/Admin/ContentBlockPreviewController.php` under `admin/content-blocks/{public_id}/preview/` with panel and policy authorization.
5. Preview temporarily depends on the `P1-S1` storefront demo harness (`resources/views/storefront/`, `DemoHomeController`).

## Consequences
- Admins gain real-time visual preview approximation before saving.
- Zero database write overhead or lock contention during preview editing.
- Preview dependencies are cataloged in `DEMO-TEARDOWN.md` for clean removal at teardown.
```

### 11.2 ARCHITECTURE.md Updates
1. **§1 Phases**:
   - Update Phase 2 table row:
     `| 2 Pages and media | Pages made of blocks, image blocks (hero), persistent revisions and public API preview tokens (ephemeral admin block preview shipped in Phase 1 per ADR 0005) | Planned |`
   - Progress list: Add `- [x] Step P1-UI6 (admin block previews: isolated view preview, split-screen edit preview, shared storefront partials, ADR 0005)`.
2. **§5 Code Structure**:
   - Add `app/Http/Controllers/Admin/` (`ContentBlockPreviewController.php`).
   - Add `SaveContentBlockPreviewState.php` and `ResolveContentBlockPreview.php` under `app/Domain/Content/Actions/`.
3. **§6 Content Model**:
   - Amend Phase 2 preview sentence: `Phase 1 provides ephemeral cache-backed admin block preview approximation (ADR 0005); persistent database revisions and public API preview tokens arrive in Phase 2.`
4. **§8 Filament Conventions**:
   - Document preview iframe routing conventions, server-side authorization guards (`canAccessPanel` and policy `view()`), and `wire:key` history protection.
5. **§12 Decisions**:
   - Update Decision D7 row:
     `| D7 | Published content edits go live immediately on Save. Ephemeral cache-backed admin block preview is provided in Phase 1 (ADR 0005). Persistent revisions and version history arrive in Phase 2. |`

### 11.3 DEMO-TEARDOWN.md Updates
Update `docs/blueprints/P1-S1/DEMO-TEARDOWN.md`:
- List preview assets coupled to the storefront demo harness: `ContentBlockPreviewController::page()`, the `preview.page` route, and the `Page Context` toggle in `live-preview-pane.blade.php`.
- Document Level 1 teardown commands removing `ContentBlockPreviewController::page()`, retaining isolated block preview if block partials are kept, or removing all preview partials in Level 2 teardown.

---

## 12. Commit Order & Checkpoints

### Checkpoint CP-A (Commits 1 to 3)

#### Commit 1: `fix(admin): use CountLiveBlocksForPlacement for edit placement capacity helper text`
- Files:
  - [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php)
  - [ContentBlockEditTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php)

#### Commit 2: `refactor(storefront): extract shared storefront block and preview-chrome Blade partials and catalog helpers`
- Files:
  - `tests/Fixtures/storefront/home-seeded-golden.html` (captured pre-refactor)
  - `resources/views/storefront/partials/blocks/announcement-bar.blade.php`
  - `resources/views/storefront/partials/blocks/hero-carousel.blade.php`
  - `resources/views/storefront/partials/blocks/feature-banner.blade.php`
  - `resources/views/storefront/partials/blocks/service-strip.blade.php`
  - `resources/views/storefront/partials/blocks/section-header.blade.php`
  - `resources/views/storefront/partials/blocks/brand-strip.blade.php`
  - `resources/views/storefront/partials/preview-chrome.blade.php`
  - [promo-carousel.blade.php](file:///c:/dev/getnow-cms/resources/views/components/promo-carousel.blade.php)
  - [layouts/storefront.blade.php](file:///c:/dev/getnow-cms/resources/views/layouts/storefront.blade.php)
  - [storefront/home.blade.php](file:///c:/dev/getnow-cms/resources/views/storefront/home.blade.php)
  - [DemoHomeController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Storefront/DemoHomeController.php)
  - [DemoHomeTest.php](file:///c:/dev/getnow-cms/tests/Feature/Storefront/DemoHomeTest.php)

#### Commit 3: `feat(content): add SaveContentBlockPreviewState and ResolveContentBlockPreview actions`
- Files:
  - `app/Domain/Content/Data/ContentBlockPreviewData.php`
  - `app/Domain/Content/Actions/SaveContentBlockPreviewState.php`
  - `app/Domain/Content/Actions/ResolveContentBlockPreview.php`
  - `tests/Feature/Domain/Content/Actions/SaveContentBlockPreviewStateTest.php`
  - `tests/Feature/Domain/Content/Actions/ResolveContentBlockPreviewTest.php`
  - [ActionCoverageTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/ActionCoverageTest.php)
- **CP-A Verification Gate**:
  - Execute `composer check`.
  - Execute mutations `MUT-1`, `MUT-2`, and `MUT-3`.
  - Halt and submit CP-A Implementation Report for review.

---

### Checkpoint CP-B (Commits 4 to 5, post CP-A approval)

#### Commit 4: `feat(admin): add preview controller, isolated view preview, and split-screen edit preview`
- Files:
  - `app/Http/Controllers/Admin/ContentBlockPreviewController.php`
  - [routes/web.php](file:///c:/dev/getnow-cms/routes/web.php)
  - `resources/views/filament/admin/resources/content-block-resource/pages/isolated-preview.blade.php`
  - `resources/views/filament/admin/resources/content-block-resource/components/view-isolated-preview.blade.php`
  - `resources/views/filament/admin/resources/content-block-resource/components/live-preview-pane.blade.php`
  - [ViewContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php)
  - [EditContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php)
  - [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php)
  - `tests/Feature/Admin/ContentBlock/ContentBlockPreviewTest.php`
  - [ContentBlockViewTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php)
  - [ContentBlockEditTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php)

#### Commit 5: `docs(architecture): add ADR 0005 and update ARCHITECTURE and DEMO-TEARDOWN for block previews`
- Files:
  - `docs/adr/0005-admin-block-preview-approximation.md`
  - [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md)
  - [DEMO-TEARDOWN.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-S1/DEMO-TEARDOWN.md)
- **CP-B Verification Gate**:
  - Execute `composer check`.
  - Execute mutations `MUT-4` and `MUT-5`.
  - Execute Manual Verification Script (Layer C).
  - Submit CP-B Implementation Report.

---

## 13. Risks, Assumptions & Conflicts

### 13.1 Iframe Sandbox Attribute and Vite ES Module Imports (Spike Note)
- Baseline configuration enforces `sandbox="allow-scripts"` (without `allow-same-origin`) to restrict iframe privileges.
- When Vite bundles frontend assets with `<script type="module">`, browsers treat an iframe with opaque origin `null` as making cross-origin requests.
- Achintha is conducting the browser spike in parallel prior to Gate G1 approval to verify whether Vite-bundled scripts require additional headers or whether inline scripts in the chrome partial provide full coverage. Result will be locked in at Gate G1.

---

## 14. Dependencies

1. **Prerequisite Step**: Step P1-UI5 (Commit `4b83b3e feat(admin): implement page grouping tabs cascading create and live capacity guards`).
2. **Pinned Packages** (from `composer.lock`):
   - `php: ^8.3`
   - `laravel/framework: v12.11.0`
   - `spatie/laravel-settings: 3.4.3`
   - `spatie/laravel-data: 4.23.0`
   - `spatie/laravel-activitylog: 4.12.0`
   - `filament/filament: v5.1.1`
   - `pestphp/pest: 4.7.0`
3. **Database**: PostgreSQL native instance (`phone_store` for local development, `phone_store_test` for test suite execution). Zero container dependencies.
4. **External Services**: Zero external runtime dependencies.
