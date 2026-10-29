Read AGENTS.md and ARCHITECTURE.md, then the Step Brief below. Produce a plan using the Plan template exactly: all 14 sections, in order, none skipped. Read the files you list in section 2 before writing section 3. Do not recall signatures from memory. Do not write code. Do not use: etc., as needed, appropriate, similar to, TBD. If something is ambiguous or conflicts, put it in section 13 and stop there; do not choose. Start with the tag [P1-API1 | PLAN v1 | Gemini].

[P1-API1 | BRIEF | Claude]

Goal: Build the read-only Content API v1 slice (`GET /api/v1/content/layout`) backed by an
  invokable `GetLayoutContent` domain Action and typed Spatie Data DTOs, returning site
  settings, visible placement blocks (enforcing server-side visibility, payload upgrading,
  and D8 tie-break/maxCount rules), and enabled navigation menu trees, complete with
  deterministic `meta.revision` ETag (`304 Not Modified`) support, rate limiting, updated
  `docs/contracts/content-api.md`, and contract fixture tests.

Depends on (verify exact signatures from code in Section 3):
  StorefrontSettings (spatie/laravel-settings): site_name, default_meta_description,
    search_placeholder, footer_brand_text
  Placement enum: cases(), allowedBlockTypes(), maxCount()
  PublishStatus enum: Draft, Published, Archived
  Tone enum: cases and backing values
  ContentBlock model: public_id (ULID), placement, type, schema_version, payload, status,
    is_enabled, sort_order, starts_at, ends_at, plus any existing visibility/serving scopes
    or helpers from Step 2a and P1-FIX1
  BlockRegistry & BlockDefinition contracts
  GetUpgradedBlockPayload::__invoke(string $type, int $schemaVersion, array$payload): BlockPayload
    (and any existing serving-health service/action in app/Domain/Content)
  MenuKey enum: MainNav, MobileNav, FooterColumn1, FooterColumn2, FooterColumn3
  Menu & MenuItem models (or GetMenuTree): public_id, key, title, parent_id, label, url,
    icon, opens_in_new_tab, is_enabled, sort_order

In scope:
  1. Spatie Data DTOs in `app/Domain/Content/Data/` representing the public layout payload
     (settings, placement blocks, menus/menu items, meta, and top-level layout response).
  2. `app/Domain/Content/Actions/GetLayoutContent.php` invokable read Action assembling
     settings, placements, menus, and meta (`api_version`, deterministic `revision`,
     `generated_at`).
  3. `app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php` (or existing
     controller name if a stub already exists in `app/Http/Controllers/Api/V1/Content/`).
  4. Route registration in `routes/api.php` for `GET /api/v1/content/layout` with named
     rate limiter middleware (`content-api`) and verify `bootstrap/app.php` API routing.
  5. Conditional request (`If-None-Match` / `ETag`) handling returning `304 Not Modified`
     with empty body when the client's ETag matches `meta.revision`.
  6. Update `docs/contracts/content-api.md` and the contract JSON fixture in
     `tests/Fixtures/contracts/`, plus tick `[x] Content API v1` in `ARCHITECTURE.md`.
  7. Feature, contract, and arch tests covering `GetLayoutContent`, visibility windows,
     D8 tie-breaking and `maxCount`, corrupt block resilience, menu item `is_enabled`
     filtering (including disabled parent cascade), ETag/304, rate limiting, and zero
     internal ID leakage.

Out of scope:
  Application/Redis caching and cache invalidation listeners (ARCHITECTURE.md section 7:
  "Application caching is added only when measured"), write/mutation API endpoints, draft
  preview tokens, page blocks (Phase 2), ContentSeeder (`P1-SEED`), Filament UI changes,
  database migrations, new Composer packages, or changes to existing write Actions.

Read first:
  ARCHITECTURE.md sections 1, 4, 5, 6, 7, 9, 10, 11, 12 (especially D1, D2, D4, D7, D8).
  AGENTS.md 'Where code goes', 'Coding standards', 'Content rules (critical)', 'Security',
  'Testing'.
  Files: inspect all files in `app/Domain/Content/` (Actions, Blocks, Contracts, Data,
  Enums, Exceptions, Models, Services, Settings), `routes/api.php`, `bootstrap/app.php`,
  `config/content.php`, `app/Providers/AppServiceProvider.php`,
  `app/Providers/ContentServiceProvider.php`, `docs/contracts/content-api.md` (if present),
  `tests/Fixtures/contracts/` (all files), `tests/Arch/` (all files), and existing block
  serving/visibility tests in `tests/Feature/` and `tests/Unit/`.

Decisions made (do not re-decide):
  D-a Single read Action: `GetLayoutContent` in `app/Domain/Content/Actions/` is the sole
      place where layout data is queried, filtered, upgraded, and assembled (S1, S2). The
      HTTP controller only calls `GetLayoutContent`, applies HTTP headers/ETag comparison,
      and returns the response.
  D-b Complete key presence: `data.placements` always contains a key for every case in
      `Placement::cases()` (mapping to `[]` when no block is eligible). `data.menus` always
      contains a key for every case in `MenuKey::cases()` (mapping to
      `{"title": null, "items": []}` even if a menu has no enabled items or its DB row is
      missing).
  D-c Block visibility & D8 tie-break: Only blocks with `status = Published`,
      `is_enabled = true`, and within their active UTC schedule window (`starts_at` /
      `ends_at` using the exact boundary semantics established in Step 2a / P1-FIX1) are
      candidates. Candidates for a placement are ordered by `sort_order ASC, starts_at DESC
      NULLS LAST, id DESC` (D8) and capped at `$placement->maxCount()`.
  D-d Corrupt block resilience & post-upgrade cap: Candidate blocks are passed through
      `GetUpgradedBlockPayload` (and checked against `$placement->allowedBlockTypes()`).
      If a database row fails payload upgrade/validation or has a disallowed/unknown type,
      `GetLayoutContent` must NOT throw a 500; it skips the unhealthy block (reusing any
      existing serving-health reporting/logging pattern from Step 2a if present) and fills
      the placement's `maxCount()` quota from the next valid candidate in D8 tie-break order.
  D-e Block JSON shape: Each block in `data.placements.<key>` serializes as:
      `{"id": <public_id ULID>, "type": <type string>, "version": <upgraded schema_version int>, "data": <upgraded payload array>}`.
      Never expose internal `id`, `status`, `is_enabled`, `sort_order`, `starts_at`,
      `ends_at`, `created_by`, or timestamps.
  D-f Menu visibility & disabled parent cascade: `GetLayoutContent` must NOT break
      `GetMenuTree` for `EditMenu` (which requires disabled items in the admin UI). In the
      public API, only items with `is_enabled = true` are returned. If a top-level item has
      `is_enabled = false`, both that parent and all of its children (even children with
      `is_enabled = true`) are excluded. Remaining items at each level are ordered by
      `sort_order ASC, id ASC`.
  D-g Menu item JSON shape: Each item in `data.menus.<key>.items` (and `children`)
      serializes as:
      `{"id": <public_id ULID>, "label": <string>, "url": <string>, "icon": <?string>, "new_tab": <bool from opens_in_new_tab>, "children": <array>}`.
      Note the contract field name is `new_tab` (NOT `opens_in_new_tab`). Level-2 child
      items always have `"children": []`.
  D-h Deterministic `meta.revision` & ETag: `meta` has exact keys:
      `{"api_version": 1, "revision": "<hash>", "generated_at": "<ISO-8601 UTC>"}`.
      `revision` is a deterministic hash (e.g. `hash('sha256', ...)` of the canonical JSON
      of `data` only — `settings`, `placements`, `menus` — NEVER hashing `generated_at`).
      The HTTP response sets header `ETag: "<revision>"` (quoted per HTTP spec). When the
      request carries a matching `If-None-Match` header, the endpoint returns `304 Not
      Modified` with an empty body and the `ETag` header.
  D-i Rate limiting: Register a named rate limiter `content-api` (configurable via
      `config('content.rate_limit_per_minute', 60)` per IP, adding the env var to
      `.env.example` if a new config key is introduced) and apply `throttle:content-api` to
      the route.

Open decisions:
  None. [Defaults above apply unless Achintha objects before G1.]

Acceptance criteria:
  AC-1  `GET /api/v1/content/layout` is publicly accessible without authentication and
        returns HTTP 200 with top-level keys `data` (`settings`, `placements`, `menus`)
        and `meta` (`api_version`, `revision`, `generated_at`).
  AC-2  `data.settings` returns `site_name`, `default_meta_description`,
        `search_placeholder`, and `footer_brand_text` matching `StorefrontSettings`.
  AC-3  Every case in `Placement::cases()` is present as a key in `data.placements` (empty
        list `[]` when no visible blocks exist), and every case in `MenuKey::cases()` is
        present as a key in `data.menus` (`{"title": ..., "items": []}` when empty or
        unprovisioned).
  AC-4  Block visibility (`K7`): blocks that are `draft`, `archived`, `is_enabled = false`,
        future-scheduled (`starts_at > now`), or expired (`ends_at <= now`, matching Step 2a
        / P1-FIX1 boundary semantics) are never returned in `data.placements`.
  AC-5  Placement tie-break and `maxCount` (`D8`): when multiple visible blocks exist for
        `announcement_bar` (`maxCount = 1`), the winner is selected by
        `sort_order ASC, starts_at DESC NULLS LAST, id DESC`, and at most `maxCount`
        blocks are returned.
  AC-6  Block payload upgrading and corrupt-row resilience: stored blocks at an older
        schema version are upgraded through `GetUpgradedBlockPayload` and output with the
        upgraded `version` and `data`. If the highest-ranked candidate block has a corrupt
        payload or fails upgrade/validation, the API does not 500; it skips that block and
        serves the next valid candidate in D8 order.
  AC-7  Menu visibility (`K7`): disabled top-level items (`is_enabled = false`) and all of
        their children (even if a child has `is_enabled = true`) are omitted from the API.
        Disabled child items under an enabled parent are omitted while enabled siblings
        remain, ordered by `sort_order ASC, id ASC`. `GetMenuTree` for admin UI still
        retains disabled items.
  AC-8  Menu item mapping: API menu items map `public_id` to `id` and `opens_in_new_tab`
        to `new_tab`, include `label`, `url`, `icon`, and `children: []`, and never leak
        `opens_in_new_tab`, `is_enabled`, `sort_order`, `menu_id`, `parent_id`, or raw
        incremental `id` (`DB5`).
  AC-9  No raw database primary keys (`id`), user foreign keys (`created_by`, `updated_by`),
        or internal lifecycle fields (`status`, `is_enabled`, `sort_order`, `starts_at`,
        `ends_at`) appear anywhere in the JSON response (`DB5`, `SEC3`).
  AC-10 `meta.revision` is deterministic: calling `GetLayoutContent` twice at different
        timestamps (`generated_at` differs) with unchanged content produces the exact same
        `meta.revision` hash. Mutating any visible setting, block payload/visibility, or
        enabled menu item changes `meta.revision`.
  AC-11 ETag & conditional GET: `GET /api/v1/content/layout` returns `ETag: "<revision>"`.
        Sending `If-None-Match: "<revision>"` (or weak `W/"<revision>"`) returns HTTP 304
        with an empty body. Sending a stale `If-None-Match: "old-hash"` returns HTTP 200
        with the full JSON payload and updated `ETag`.
  AC-12 Rate limiting: requests to `GET /api/v1/content/layout` exceeding the configured
        per-minute limit receive HTTP 429 Too Many Requests.
  AC-13 Query count discipline: `GetLayoutContent` executes in a bounded, constant number
        of database queries (no N+1 queries across placements or menu items).
  AC-14 Contract documentation and fixture (`K8`, `X7`): `docs/contracts/content-api.md`
        and the fixture in `tests/Fixtures/contracts/` match the exact v1 response schema,
        and an automated contract test verifies the live endpoint response against the
        fixture.
  AC-15 Architecture tests (`tests/Arch`) verify controller thinness / domain isolation
        (`S1`, `S2`, `S3`), `ARCHITECTURE.md` marks `Content API v1` complete, and
        `composer check` passes with zero errors and zero baseline additions.

Rules most at risk:
  S1 & S2 (putting query/filtering/hashing logic in the Controller instead of
  `GetLayoutContent`), S3 (importing Http/Request into Domain), K7 (leaking draft,
  archived, disabled, out-of-window blocks, or children of disabled menu parents), K8 & X7
  (contract doc and fixture drift), DB5 (exposing bigint `id` instead of `public_id`),
  C1 & C2 & C7 (strict types and Larastan Level 8 on nested array/Data shapes).

Known traps:
  1. Field name mismatch on menu items: the database column and admin `MenuItemData` use
     `opens_in_new_tab`, whereas the public API contract (`ARCHITECTURE.md` section 7)
     requires `"new_tab"`. Similarly, both blocks and menu items use `"id"` in JSON for
     the model's `public_id` ULID string.
  2. `GetMenuTree` shared usage: in P1-M1, `EditMenu` uses `GetMenuTree` to populate the
     Filament form and requires disabled items to be returned. Do not alter `GetMenuTree`
     to strip disabled items unconditionally unless controlled by a parameter or filtered
     inside `GetLayoutContent`.
  3. Disabled parent menu item with enabled children: filtering `menu_items` only by
     `WHERE is_enabled = true` in SQL without filtering out children whose parent is
     disabled would orphan or promote children of disabled parents.
  4. D8 `maxCount` vs corrupt blocks: if you apply `LIMIT 1` in SQL before running
     `GetUpgradedBlockPayload` and the top row has a corrupt payload, the placement will
     either 500 or return empty instead of falling back to the next valid published block.
  5. `meta.revision` hashing `generated_at`: if `revision` hashes the entire response
     including `meta.generated_at`, the hash changes every second and `If-None-Match` will
     never return 304. Hash only the canonical `data` structure.
  6. Empty associative arrays in PHP (`[]`) serialize as JSON lists `[]` instead of `{}` if
     all placements or menus were ever empty, or if a block's `data` is empty. Ensure
     `placements` and `menus` always have their enum keys present.
  7. Step 2a / P1-FIX1 existing code: check `app/Domain/Content/` for any existing
     `GetLayoutContent` stub, block visibility scopes, serving health classes, or contract
     fixtures before proposing new classes so nothing is duplicated or conflicted.

Size guard and checkpoints:
  Expected 10 to 15 files. More than 18 files, or any need to modify a merged migration or
  write Action, means stop and send a CR to split. Plan section 12 must define two
  checkpoints:
  CP-A Domain DTOs + `GetLayoutContent` Action + `docs/contracts/content-api.md` + contract
       fixture + `GetLayoutContentTest` (`composer check` green).
  CP-B Controller + Route + Rate Limiter + ETag/304 + HTTP/Contract/Arch tests +
       `ARCHITECTURE.md` update (full `IMPL-REPORT`).

Manual verification script (Layer C):
  1. Start local server (`php artisan serve`) and run:
     `Invoke-RestMethod -Uri http://127.0.0.1:8000/api/v1/content/layout`
     Expect HTTP 200 JSON with `data.settings`, `data.placements.announcement_bar`,
     `data.menus` (`main_nav`, `mobile_nav`, `footer_column_1..3`), and `meta`.
  2. Inspect response headers with `Invoke-WebRequest`:
     Expect `ETag` header matching `"` + `meta.revision` + `"`.
  3. Re-send request with header `If-None-Match: "<revision>"`:
     Expect HTTP `304 Not Modified` with empty body.
  4. In `/admin`, publish an `announcement_bar` block and add an enabled item and a
     disabled item to `main_nav`. Re-fetch `/api/v1/content/layout`:
     Expect the published block in `placements.announcement_bar`, only the enabled item in
     `menus.main_nav.items` (with `id` = ULID and `new_tab` boolean), and a new `revision`.
  5. Disable the `announcement_bar` block in `/admin` and re-fetch:
     Expect `placements.announcement_bar` is `[]`.

Step-specific checks (Layer A extras):
  php artisan route:list --path=api/v1/content
  git diff main...HEAD --stat -- database composer.json composer.lock package.json
  git diff main...HEAD --stat -- app/Filament
  Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -SimpleMatch 'App\Filament','App\Http','Illuminate\Http'