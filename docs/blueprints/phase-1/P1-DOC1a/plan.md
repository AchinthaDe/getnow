# [P1-DOC1a | PLAN v1 | Gemini]

### 1. Understanding

Establish the documentation foundation by authoring [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md) (defining the guide format, nine-area map, and update rule) and the first four normative area guides in `docs/areas/`: [content-blocks.md](file:///c:/dev/getnow-cms/docs/areas/content-blocks.md), [menus.md](file:///c:/dev/getnow-cms/docs/areas/menus.md), [site-settings.md](file:///c:/dev/getnow-cms/docs/areas/site-settings.md), and [content-api.md](file:///c:/dev/getnow-cms/docs/areas/content-api.md). Guides document architectural intent, invariants, change procedures, and verified limits without duplicating code, database schema migrations, or contract payloads. Out of scope: editing or deleting existing repository files ([ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md), [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md), ADRs, blueprints); guides for the remaining five areas; application code, tests, configurations, and dependencies; and any material for subsequent phases.

---

### 2. Sources read

| File or doc section | What in it constrains this step |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Quality gates, Conventional Commits, PowerShell command rules, and phase scope constraints. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) §1–12 | System architecture, Decision table D1–D9, area boundaries, and Phase 1 CMS concepts. |
| [docs/adr/0001-content-api-tie-break.md](file:///c:/dev/getnow-cms/docs/adr/0001-content-api-tie-break.md) | Sorting order, tie-breaking, and capacity limit capping in the layout query. |
| [docs/adr/0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md) | Serving health resolution, reason categories, and fail-closed public serving. |
| [docs/adr/0003-content-block-concurrency.md](file:///c:/dev/getnow-cms/docs/adr/0003-content-block-concurrency.md) | Last-write-wins payload saves and pessimistic locking on lifecycle transitions. |
| [docs/adr/0004-placement-live-capacity-enforcement.md](file:///c:/dev/getnow-cms/docs/adr/0004-placement-live-capacity-enforcement.md) | Write-time live capacity enforcement and PostgreSQL transaction advisory locking. |
| [docs/adr/0005-admin-block-preview-approximation.md](file:///c:/dev/getnow-cms/docs/adr/0005-admin-block-preview-approximation.md) | Ephemeral cache-backed preview approximation and preview route authorization. |
| [docs/contracts/content-api.md](file:///c:/dev/getnow-cms/docs/contracts/content-api.md) | Content API v1 contract: schema shape, ETag conditional caching, rate limits, and status codes. |
| [docs/blueprints/P1-DOC1a/reference-guide.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-DOC1a/reference-guide.md) | Normative front matter schema, seven mandatory markdown sections, and invariant table format. |
| [docs/blueprints/P1-DOC0/report.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-DOC0/report.md) | Area path boundaries, draft invariants, audit baseline, and PostgreSQL features. |
| [app/Domain/Content/Models/ContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php) | ContentBlock scopes `scopeVisible`, `scopeServingOrder`, helper `isLive()`, constants, and casts. |
| [app/Domain/Content/Enums/Placement.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php) | Placement enum cases, `allowedBlockTypes()`, `maxCount()`, and `page()` grouping. |
| [app/Domain/Content/Enums/PublishStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/PublishStatus.php) | Lifecycle status enum cases (`DRAFT`, `PUBLISHED`, `ARCHIVED`). |
| [app/Domain/Content/Actions/PublishContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php) | Publish action: inline advisory lock via `pg_advisory_xact_lock`, row lock, serving health check, and capacity check. |
| [app/Domain/Content/Actions/EnableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php) | Enable action: inline advisory lock via `pg_advisory_xact_lock`, row lock, serving health check, and capacity check. |
| [app/Domain/Content/Actions/UpdateContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php) | Update action: inline advisory lock via `pg_advisory_xact_lock`, row lock, payload validation, and capacity check. |
| [app/Domain/Content/Policies/ContentBlockPolicy.php](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/ContentBlockPolicy.php) | Policy authorization checking `$user->isAdmin()`, preventing edits to archived blocks, and disallowing delete. |
| [app/Domain/Content/Policies/BaseContentPolicy.php](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/BaseContentPolicy.php) | Policy base class verifying `$user->is_platform_admin` without owner scoping. |
| [app/Http/Controllers/Admin/ContentBlockPreviewController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Admin/ContentBlockPreviewController.php) | Preview controller endpoints hardcoding panel `'admin'` and policy checks. |
| [app/Domain/Content/Actions/UpdateMenu.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) | UpdateMenu action: row lock, depth guard (max depth 2), caps, atomic item update, and public_id preservation. |
| [app/Domain/Content/Actions/GetMenuTree.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php) | Menu tree query: ordering by `sort_order`, filtering enabled items, and suppressing children of disabled parents. |
| [app/Domain/Content/Policies/MenuPolicy.php](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/MenuPolicy.php) | Menu authorization: `create()` and `delete()` return false; `view()` and `update()` check `$user->isAdmin()`. |
| [app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) | Menu form schema: hidden public_id, collapsible repeaters, max items validation, and SafeLink rules. |
| [app/Domain/Content/Settings/StorefrontSettings.php](file:///c:/dev/getnow-cms/app/Domain/Content/Settings/StorefrontSettings.php) | Spatie settings class defining global scalar string properties under group `'storefront'`. |
| [app/Domain/Content/Actions/UpdateStorefrontSettings.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateStorefrontSettings.php) | Storefront settings update action: database transaction, diff detection, and activity log emission. |
| [app/Domain/Content/Data/StorefrontSettingsData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/StorefrontSettingsData.php) | Storefront settings DTO: string length bounds and regex disallowing HTML `<` or `>`. |
| [app/Filament/Admin/Pages/ManageStorefrontSettings.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Pages/ManageStorefrontSettings.php) | Settings page: `canAccess()` checking `is_platform_admin`, direct invocation of `UpdateStorefrontSettings`. |
| [app/Domain/Content/Actions/GetLayoutContent.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php) | Layout assembly action: pre-indexed placement blocks query, tie-break slicing, menu mapping, and revision hashing. |
| [app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php](file:///c:/dev/getnow-cms/app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php) | Content API layout controller: JSON response, revision ETag header, and HTTP 304 evaluation. |
| [app/Providers/AppServiceProvider.php](file:///c:/dev/getnow-cms/app/Providers/AppServiceProvider.php) | Rate limiter configuration registering `content-api` per-minute limit by request IP. |
| [routes/api.php](file:///c:/dev/getnow-cms/routes/api.php) | Route registration for `GET /api/v1/content/layout` under middleware `throttle:content-api`. |

---

### 3. Verified current state

#### 3.1 Content Blocks Area (`content-blocks`)
- **Classes and Methods Verified**:
  - [ContentBlock](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php):
    - `public function isLive(): bool` (checks `status === PublishStatus::PUBLISHED`, `is_enabled === true`, and scheduling window)
    - `public function scopeVisible(Builder $query): Builder` (filters published, enabled, and in-window records)
    - `public function scopeServingOrder(Builder $query): Builder` (orders by `sort_order ASC`, `starts_at DESC NULLS LAST`, `id DESC`)
  - [BlockRegistry](file:///c:/dev/getnow-cms/app/Domain/Content/Contracts/BlockRegistry.php):
    - `public function register(BlockDefinition $definition): void`
    - `public function get(string $type): ?BlockDefinition`
    - `public function all(): array`
  - [BlockDefinition](file:///c:/dev/getnow-cms/app/Domain/Content/Contracts/BlockDefinition.php):
    - `public function type(): string`
    - `public function schemaVersion(): int`
    - `public function dataClass(): string`
    - `public function samplePayload(): array`
  - [Placement](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/Placement.php):
    - `public function allowedBlockTypes(): array`
    - `public function maxCount(): int`
    - `public function page(): PlacementPage`
  - [PublishContentBlock](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php):
    - `public function __invoke(int $id, ?User $causer = null): ContentBlock` (executes `SELECT pg_advisory_xact_lock(?)` with key `(int)(crc32('placement:'.$placementValue) & 0x7FFFFFFF)`, acquires `lockForUpdate()`, validates serving health and placement capacity)
  - [EnableContentBlock](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php):
    - `public function __invoke(int $id, ?User $causer = null): ContentBlock` (executes placement advisory lock, row lock, serving health check, and capacity check)
  - [UpdateContentBlock](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateContentBlock.php):
    - `public function __invoke(int $id, UpdateContentBlockData $data, array $rawPayload, ?User $causer = null): ContentBlock` (executes placement advisory lock, row lock, payload upgrader and validator, and capacity check)
  - [ContentBlockPolicy](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/ContentBlockPolicy.php):
    - `viewAny(User $user): bool`, `view(User $user, mixed $model): bool`, `create(User $user): bool`, `publish(User $user, mixed $model): bool`, `archive(User $user, mixed $model): bool`, `enable(User $user, mixed $model): bool`, `disable(User $user, mixed $model): bool` check `$user->isAdmin()`; `update(User $user, mixed $model): bool` disallows editing archived blocks; `delete` methods return `false`.
  - [ContentBlockPreviewController](file:///c:/dev/getnow-cms/app/Http/Controllers/Admin/ContentBlockPreviewController.php):
    - `isolated(Request $request, ContentBlock $contentBlock, ResolveContentBlockPreview $resolvePreview): Response` (hardcodes `$panel = Filament::getPanel('admin')`, verifies `$user->canAccessPanel($panel)` and `$user->can('view', $contentBlock)`)
    - `page(Request $request, ContentBlock $contentBlock, ResolveContentBlockPreview $resolvePreview): Response` (hardcodes `$panel = Filament::getPanel('admin')`)
- **Test Files and Enforcers Verified**:
  - [ServingWindowTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/ServingWindowTest.php) (enforces lifecycle window logic)
  - [EnsurePlacementCapacityTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Services/EnsurePlacementCapacityTest.php) (enforces write-time live capacity check)
  - [PublishContentBlockTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/PublishContentBlockTest.php) (enforces publish transitions and advisory lock)
  - [EnableContentBlockTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/EnableContentBlockTest.php) (enforces enable transitions and advisory lock)
  - [UpdateContentBlockTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php) (enforces payload updates and advisory lock)
  - [ContentBlockPolicyTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php) (enforces authorization rules)
  - [DBConstraintsTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Models/DBConstraintsTest.php) (enforces PostgreSQL CHECK constraints)
  - [NoHtmlTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Rules/NoHtmlTest.php) (enforces HTML rejection)
  - [SafeLinkTest.php](file:///c:/dev/getnow-cms/tests/Feature/Support/Rules/SafeLinkTest.php) (enforces link scheme validation)
  - [AnnouncementBarV1ToV2UpgraderTest.php](file:///c:/dev/getnow-cms/tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php) (enforces payload migration)
  - [HomepageBlocksCreateAndServeTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php) (enforces block payload validation and serving)
  - `Review only`

#### 3.2 Menus Area (`menus`)
- **Classes and Methods Verified**:
  - [MenuKey](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/MenuKey.php) (cases: `MAIN_NAV = 'main_nav'`, `MOBILE_NAV = 'mobile_nav'`, `FOOTER_COLUMN_1 = 'footer_column_1'`, `FOOTER_COLUMN_2 = 'footer_column_2'`, `FOOTER_COLUMN_3 = 'footer_column_3'`)
  - [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php):
    - `public function __invoke(Menu|MenuKey|string|int $menuIdentifier, UpdateMenuData $data, ?User $causer = null): MenuTreeData` (executes transaction with `lockForUpdate()`, validates title length max 255, validates top-level items max 10, validates children max 10 per parent, validates max depth 2, validates URLs with `SafeLink`, updates existing items preserving `public_id` and database IDs when supplied, creates new items with new ULIDs when unkeyed, deletes omitted items, logs to activity log)
  - [GetMenuTree](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php):
    - `public function __invoke(Menu|MenuKey|string|int $menuIdentifier): MenuTreeData` (returns tree sorted by `sort_order`, including only enabled items and suppressing children of disabled parents)
  - [MenuPolicy](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/MenuPolicy.php):
    - `create(User $user): bool` returns `false`
    - `delete(User $user, mixed $model): bool` returns `false`
    - `viewAny(User $user): bool`, `view(User $user, mixed $model): bool`, `update(User $user, mixed $model): bool` return `$user->isAdmin()`
  - [MenuForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php):
    - `public static function form(Schema $schema): Schema` (includes `Hidden::make('public_id')` on level 1 and level 2 items, max items caps, `NoHtml` rule, `SafeLink` rule)
- **Test Files and Enforcers Verified**:
  - [MenuPolicyTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Policies/MenuPolicyTest.php) (enforces policy checks, create/delete forbidden)
  - [UpdateMenuRejectionsTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php) (enforces max depth 2, item cap 10, child cap 10, link validation, HTML rejection)
  - [UpdateMenuTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/UpdateMenuTest.php) (enforces atomic replacement, row locking, activity logging, public_id preservation)
  - [GetMenuTreeTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php) (enforces disabled parent child suppression)
  - [SafeLinkTest.php](file:///c:/dev/getnow-cms/tests/Feature/Support/Rules/SafeLinkTest.php) (enforces URL scheme rules)
  - [MenuResourceTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuResourceTest.php) (enforces Filament menu editor access and interaction)
  - `Review only`

#### 3.3 Site Settings Area (`site-settings`)
- **Classes and Methods Verified**:
  - [StorefrontSettings](file:///c:/dev/getnow-cms/app/Domain/Content/Settings/StorefrontSettings.php):
    - Properties: `public string $site_name`, `public string $default_meta_description`, `public string $search_placeholder`, `public string $footer_brand_text`
    - Method: `public static function group(): string` returns `'storefront'`
  - [UpdateStorefrontSettings](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateStorefrontSettings.php):
    - `public function __invoke(StorefrontSettingsData $data, ?User $actor = null): StorefrontSettingsData` (executes database transaction, compares old vs new properties, saves to Spatie settings, and emits `activity('storefront-settings')` if properties changed)
  - [StorefrontSettingsData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/StorefrontSettingsData.php):
    - Constants: `MAX_SITE_NAME = 80`, `MAX_DESC = 255`, `MAX_SEARCH = 80`, `MAX_FOOTER = 300`, `HTML_REGEX = '/[<>]/`
    - Method: `public static function prepareValidated(array $input): self` (trims string inputs and invokes `validateAndCreate`)
  - [ManageStorefrontSettings](file:///c:/dev/getnow-cms/app/Filament/Admin/Pages/ManageStorefrontSettings.php):
    - `public static function canAccess(): bool` (returns `Filament::auth()->user()?->is_platform_admin === true`)
    - `public function save(): void` (bypasses default lifecycle hooks to enforce strict DTO validation via `StorefrontSettingsData::prepareValidated`, calls `UpdateStorefrontSettings`)
  - [StorefrontSettingsForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Schemas/StorefrontSettingsForm.php):
    - `public static function schema(): array` (defines TextInput and Textarea components with length bounds and regex constraints)
- **Test Files and Enforcers Verified**:
  - [StorefrontSettingsTest.php](file:///c:/dev/getnow-cms/tests/Feature/Content/StorefrontSettingsTest.php) (enforces settings persistence, activity log recording on change, validation limits, NoHtml regex rejection, unauthorized access 403, smoke test rendering)
  - `Review only`

#### 3.4 Content API Area (`content-api`)
- **Classes and Methods Verified**:
  - [GetLayoutContentController](file:///c:/dev/getnow-cms/app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php):
    - `public function __invoke(Request $request, GetLayoutContent $getLayoutContent): Response` (calls action, produces JSON response, sets strong ETag header, compares `If-None-Match` wildcards and quotes, returns 304 Not Modified when matched)
  - [GetLayoutContent](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetLayoutContent.php):
    - `public function __invoke(): LayoutResponseData` (reads `StorefrontSettings`, queries `ContentBlock::visible()->servingOrder()`, pre-indexes blocks by placement, resolves servable payload via `ResolveServablePayload`, enforces placement `maxCount()`, queries menus and maps enabled tree, builds `LayoutData`, generates SHA-256 revision hash from canonical JSON, builds `LayoutMetaData`)
  - [AppServiceProvider](file:///c:/dev/getnow-cms/app/Providers/AppServiceProvider.php):
    - `protected function configureRateLimiting(): void` (configures `RateLimiter::for('content-api', fn (Request $request): Limit => Limit::perMinute((int) config('content.rate_limit_per_minute', 60))->by($request->ip()))`)
- **Test Files and Enforcers Verified**:
  - [GetLayoutContentTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php) (enforces layout graph structure, deterministic tie-breaking, maxCount capping, skipping unservable blocks, contract fixture parity)
  - [GetLayoutContentEndpointTest.php](file:///c:/dev/getnow-cms/tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php) (enforces public HTTP access, ETag generation, 304 Not Modified conditional responses, rate limiting 429)
  - [ContentApiArchTest.php](file:///c:/dev/getnow-cms/tests/Arch/ContentApiArchTest.php) (enforces controller thinness and architectural boundaries)
  - `Review only`

---

### 4. File manifest

| Path | New or modify | Layer | One-line responsibility | Rule IDs it must satisfy |
|---|---|---|---|---|
| [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md) | New | Docs | Defines guide structure, area map table across all 9 areas, and the update rule. | X4, G1, G2, G3 |
| [docs/areas/content-blocks.md](file:///c:/dev/getnow-cms/docs/areas/content-blocks.md) | New | Docs | Normative guide for content block domain, registry, schemas, placements, serving health, and previews. | X4, G1, G2, G3 |
| [docs/areas/menus.md](file:///c:/dev/getnow-cms/docs/areas/menus.md) | New | Docs | Normative guide for navigation menus, tree constraints, whole-tree updates, and Filament menu editor. | X4, G1, G2, G3 |
| [docs/areas/site-settings.md](file:///c:/dev/getnow-cms/docs/areas/site-settings.md) | New | Docs | Normative guide for storefront global scalar settings, Spatie settings repository, and Filament page. | X4, G1, G2, G3 |
| [docs/areas/content-api.md](file:///c:/dev/getnow-cms/docs/areas/content-api.md) | New | Docs | Normative guide for Content API v1 layout endpoint, rate limiting, and revision ETag caching. | X4, G1, G2, G3 |

**Total file count**: 5 files. Zero modified or deleted files.  
**Placement check**: All files are placed strictly in [docs/](file:///c:/dev/getnow-cms/docs) and `docs/areas/`. No code folders or namespaces are touched.

---

### 5. Interface specs

N/A, docs only.

---

### 6. Data and migrations

N/A, docs only.

---

### 7. Behavior and flows

N/A, docs only.

---

### 8. Rule compliance matrix

| ID | Rule | Applies | How satisfied |
|---|---|---|---|
| G1 | Branch feat/, fix/ or chore/ from main | Y | Work will be executed on branch `chore/docs-area-guides` branched from `main`. |
| G2 | Conventional Commits | Y | Commits follow format `docs(<scope>): <description>` using type `docs`. |
| G3 | Never push to main or bypass hooks | Y | Commits remain on feature branch; pre-commit and pre-push hooks run without bypass flags. |
| X4 | Nothing for later phases | Y | No guides, directories, files, or prose added for Catalog, Retail, or other later phases. Mentions of future phases restricted strictly to Known limits and History sections. |
| X6 | composer check passes and its output was read | Y | Full `composer check` will be run and output verified before completing implementation. |
| X7 | ARCHITECTURE.md, content-api.md, .env.example updated where relevant | Y | Preserved unchanged per brief AC-1; temporary duplication accepted until P1-DOC2. |
| E1 | PowerShell, one command at a time, no && or bash syntax, no servers left running | Y | Every command is native PowerShell, executed individually without chaining or background server daemons. |

---

### 9. AC traceability

| AC | Files | Test names / verification commands | How verified |
|---|---|---|---|
| AC-1 | [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md), [docs/areas/content-blocks.md](file:///c:/dev/getnow-cms/docs/areas/content-blocks.md), [docs/areas/menus.md](file:///c:/dev/getnow-cms/docs/areas/menus.md), [docs/areas/site-settings.md](file:///c:/dev/getnow-cms/docs/areas/site-settings.md), [docs/areas/content-api.md](file:///c:/dev/getnow-cms/docs/areas/content-api.md) | `git status --short`, `git diff --name-status main...HEAD` | Proves exactly 5 files added and zero existing files modified or deleted. |
| AC-2 | `docs/areas/*.md` | Static review of YAML front matter in all 4 guides | Asserts kind, area, status, summary, paths, decisions, depends-on, tests, and at least 2 triggers with paths and reconsider checklist. |
| AC-3 | `docs/areas/*.md` | `powershell -ExecutionPolicy Bypass -File scratch/check_globs.ps1` | Verifies every glob matches at least one existing file and zero cross-guide file overlap. |
| AC-4 | `docs/areas/*.md` | `vendor/bin/pest tests/Feature/Domain/Content/Blocks/AnnouncementBarDataTest.php tests/Feature/Admin/ContentBlock/HomepageBlocksCreateAndServeTest.php`<br>`vendor/bin/pest tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php`<br>`vendor/bin/pest tests/Feature/Content/StorefrontSettingsTest.php`<br>`vendor/bin/pest tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` | Executes each tests command defined in the front matter of all four guides; proves all pass. |
| AC-5 | `docs/areas/*.md` | Inspection of Invariant tables across all 4 guides | Ensures Enforced by names a concrete test file path or literal `Review only`. |
| AC-6 | `docs/areas/*.md` | Claim-source matrix in IMPL-REPORT | Maps every assertion in How it works now and Invariants to file and class::method read in session. |
| AC-7 | `docs/areas/*.md` | `Get-Content docs/areas/*.md \| Measure-Object -Line`<br>`Get-ChildItem docs/areas -File \| Select-String -Pattern 'P1-[A-Z]+[0-9]', 'blueprint', '\.php:\d+'` | Confirms all 7 sections present, under 150 lines per guide, and zero forbidden pattern matches. |
| AC-8 | `docs/areas/*.md` | Inspection of Known limits sections | Validates inclusion of verified facts: content-blocks (isAdmin policy without owner scope, inline CRC32 advisory locks, admin panel hardcoded in preview controller); menus (plain URL link per D4, public_id preservation in code vs new ULIDs on insert); content-api (rate limiter registered only for content-api, no caching beyond ETag, per-request query count omitted); site-settings (scalar strings only, no write-time locking, platform admin check). |
| AC-9 | [docs/areas/menus.md](file:///c:/dev/getnow-cms/docs/areas/menus.md) | Inspection of `menus.md` Invariants, Decisions, and History | Verifies test enforcers cited ([MenuPolicyTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Policies/MenuPolicyTest.php), [UpdateMenuRejectionsTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php), [SafeLinkTest.php](file:///c:/dev/getnow-cms/tests/Feature/Support/Rules/SafeLinkTest.php)), D4 phrased as current decision requiring ADR to replace, and relative links to migration and ADRs. |
| AC-10 | [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md) | Inspection of [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md) | Confirms map table lists all 9 areas with 5 marked `planned (P1-DOC1b)` and documents PR update rule within 60 line limit. |

---

### 10. Test plan

N/A, docs only.

---

### 11. Docs and contract updates

None. Existing documentation ([ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md), [docs/contracts/content-api.md](file:///c:/dev/getnow-cms/docs/contracts/content-api.md), `.env.example`, and ADRs) is deliberately untouched in this step per brief AC-1. Temporary duplication with [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) is accepted until step P1-DOC2.

---

### 12. Implementation order

1. **Commit 1**: `docs(docs): add documentation system index and area map in docs/README.md`
   - File: [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md)
   - Scope: Guide structure format, nine-area map table, update rule (max 60 lines).
   - Verification: `composer check` passes.
2. **Commit 2**: `docs(content-blocks): add content-blocks area guide`
   - File: [docs/areas/content-blocks.md](file:///c:/dev/getnow-cms/docs/areas/content-blocks.md)
   - Scope: Normative guide for content blocks (front matter, intro, how it works now, invariants table, changing it, running and testing, verified known limits, history).
   - Verification: Front matter test commands pass; line count <= 150 lines; `composer check` passes.
3. **Commit 3**: `docs(menus): add menus area guide`
   - File: [docs/areas/menus.md](file:///c:/dev/getnow-cms/docs/areas/menus.md)
   - Scope: Normative guide for menus fixing audit draft (test enforcers, D4 ADR constraint, relative links, verified public_id preservation behavior).
   - Verification: Front matter test commands pass; line count <= 150 lines; `composer check` passes.
4. **Commit 4**: `docs(site-settings): add site-settings area guide`
   - File: [docs/areas/site-settings.md](file:///c:/dev/getnow-cms/docs/areas/site-settings.md)
   - Scope: Normative guide for site settings (Spatie settings repository, UpdateStorefrontSettings action, activity logging, validation rules, Filament page).
   - Verification: Front matter test command passes; line count <= 150 lines; `composer check` passes.
5. **Commit 5**: `docs(content-api): add content-api area guide`
   - File: [docs/areas/content-api.md](file:///c:/dev/getnow-cms/docs/areas/content-api.md)
   - Scope: Normative guide for Content API v1 (layout query, rate limiter isolation, ETag conditional caching, query count omission).
   - Verification: Front matter test commands pass; line count <= 150 lines; `composer check` passes.

---

### 13. Risks, assumptions, conflicts

1. **Conflict: Menu Item Public ID Preservation in Code vs. Audit / Brief AC-8**:
   - *Conflict*: `brief.md` AC-8 states *"menus: items link by plain URL (D4); UpdateMenu recreates all items with new public_ids on every save."* This originated from audit report `report.md:247` (*"Existing items are deleted and recreated with new ULID public_ids"*).
   - *Active Code Reality*: [UpdateMenu.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) (lines 116–172) and [UpdateMenuTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/UpdateMenuTest.php) (line 89, `"preserves public_id values on item reordering and reparenting"`) explicitly verify that when an item's `public_id` is supplied and matches an existing record, the record is updated in place, retaining its `public_id` and database ID. Only new items without a `public_id` receive newly generated ULIDs.
   - *Resolution*: Guided by brief instruction ("Read the code; do not rely on the audit or on ARCHITECTURE.md prose. Where they disagree with the code, the code wins and the conflict goes in plan section 13"), the guide documents the active code behavior: `UpdateMenu` preserves existing item IDs and `public_id`s when submitted in `UpdateMenuItemInputData`, while generating new ULIDs only for newly added items.
2. **Conflict: Non-Existent Unit Test Globs in Audit Report**:
   - *Conflict*: Audit report Section 4 listed eight globs under `tests/Unit/Domain/Content/...` and `tests/Unit/Support/...` (`PublishStatusTest.php`, `ToneTest.php`, `PromoThemeTest.php`, `ContentBlockTest.php`, `tests/Unit/Domain/Content/Services/**`, `tests/Unit/Support/Rules/**`, `tests/Unit/Domain/Content/Enums/Menu*.php`, `tests/Unit/Domain/Content/Models/Menu*.php`).
   - *Active Code Reality*: Verification shows these files do not exist in `tests/Unit/`; the corresponding tests reside under `tests/Feature/`. Citing non-existent files would violate AC-3 ("Every glob in every guide's paths: matches at least one existing file").
   - *Resolution*: The non-existent unit test globs are omitted from the front matter paths of `content-blocks.md` and `menus.md`. Verified via [check_globs.ps1](file:///c:/dev/getnow-cms/scratch/check_globs.ps1) that all retained globs match at least one file, with zero empty globs and zero cross-guide collisions.
3. **Conflict: Audit Report Non-Existent Smoke Test Directory**:
   - *Conflict*: Audit report Section 6 table row 21 cited `Smoke tests in tests/Feature/Admin/Smoke/`.
   - *Active Code Reality*: Directory `tests/Feature/Admin/Smoke/` does not exist. Smoke tests are defined within `ContentBlockCreateTest.php`, `ContentBlockEditTest.php`, `ContentBlockViewTest.php`, and `StorefrontSettingsTest.php`.
   - *Resolution*: Guides cite the actual test files containing the smoke tests.
4. **Composer Test Argument Forwarding Constraint**:
   - *Constraint*: Running `composer test -- --filter=...` fails on this repository because the composer test script triggers `@php artisan config:clear --ansi`, which does not accept `--filter`.
   - *Resolution*: All front matter `tests:` entries and `Running and testing` sections specify `vendor/bin/pest` directly with specific file targets, which execute and pass.
5. **No Query Count Assertion in Content API Tests**:
   - *Fact*: AC-8 requires: *"give a per-request query count only if an existing test asserts it, otherwise leave it out."* Code verification confirms no test currently asserts an exact query count for `GetLayoutContent` or `GetLayoutContentController`.
   - *Resolution*: Specific per-request query count figures are left out of `content-api.md`.

---

### 14. Dependencies

N/A, docs only.
