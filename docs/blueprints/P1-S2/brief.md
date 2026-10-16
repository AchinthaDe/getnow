Read AGENTS.md and ARCHITECTURE.md, then `docs/blueprints/P1-S1/homepage-migration.md` and `docs/blueprints/P1-S1/DEMO-TEARDOWN.md`, then the Step Brief below. Produce a plan using the Plan template exactly: all 14 sections, in order, none skipped. Read the files you list in section 2 before writing section 3. Do not recall signatures from memory. Do not write code. Do not use: etc., as needed, appropriate, similar to, TBD. If something is ambiguous or conflicts, put it in section 13 and stop there; do not choose. Start with the tag [P1-S2 | PLAN v1 | Gemini].

[P1-S2 | BRIEF | Claude]

Goal: Upgrade `announcement_bar` to schema v2 (adding editable `secondary_text` and
  `locale_text` with a v1->v2 payload upgrader) and introduce homepage Content Block types
  (`promo_slide`, `feature_banner`, `service_item`, `section_header`, `brand_strip`) and
  placements so that every non-product region and image on the demo storefront homepage is
  CMS-editable in Filament, enforce full-bar/full-card clickable link targets on the
  announcement bar, service items, and banners, seed all blocks idempotently in
  `ContentSeeder`, and document the demo block/placement teardown in `DEMO-TEARDOWN.md`.

Depends on (verify exact signatures from code in Section 3):
  BlockDefinition, BlockPayload, BlockRegistry, BlockPayloadUpgrader (verify exact
    interfaces and upgrader registration in `app/Domain/Content/Contracts/`,
    `app/Domain/Content/Services/`, and `app/Providers/ContentServiceProvider.php`)
  GetUpgradedBlockPayload & ResolveServablePayload (in `app/Domain/Content/Actions/` or
    `Services/`)
  BlockFormRegistry (in `app/Filament/Blocks/BlockFormRegistry.php`)
  ContentBlockResource Create/Edit/View pages and `BlockForm` schema wiring (inspect how
    `Placement` and block `type` selection work in `app/Filament/Admin/Resources/ContentBlockResource/`)
  AnnouncementBarBlock, AnnouncementBarData, AnnouncementBarForm
  Placement enum (`app/Domain/Content/Enums/Placement.php`)
  Tone enum (`app/Domain/Content/Enums/Tone.php`)
  SafeLink (`app/Support/Rules/SafeLink.php`) and NoHtml (`app/Support/Rules/NoHtml.php`)
  GetLayoutContent (`app/Domain/Content/Actions/GetLayoutContent.php`)
  ContentSeeder (`database/seeders/ContentSeeder.php`)
  DemoHomeController (`app/Http/Controllers/Storefront/DemoHomeController.php`),
    `resources/views/layouts/storefront.blade.php`,
    `resources/views/storefront/home.blade.php`,
    `resources/views/components/promo-carousel.blade.php`,
    and `resources/css/storefront/` (`layout.css`, `pages.css`, `components.css`)

In scope:
  Part A — Domain Blocks, Schema v2 Upgrader, Filament Forms & API Contract (CP-A):
  1. Upgrade `AnnouncementBarBlock` & `AnnouncementBarData` from `schema_version: 1` to
     `schema_version: 2` (`Rule K2`):
     - `text`: string, required, min 1, max 120, `NoHtml` (e.g. `'Good tech. Better everyday.'`)
     - `secondary_text`: ?string, nullable, max 120, `NoHtml` (e.g. `'Free delivery on orders over Rs. 25,000'`)
     - `locale_text`: ?string, nullable, max 60, `NoHtml` (e.g. `'Sri Lanka / LKR'`)
     - `link_url`: ?string, nullable, `SafeLink`
     - `tone`: `Tone` enum
     - Create and register `AnnouncementBarV1ToV2Upgrader` (upgrading stored v1 payloads by
       preserving `text`, `link_url`, `tone` and defaulting `secondary_text` and
       `locale_text` to `null`).
     - Update `app/Filament/Blocks/AnnouncementBarForm.php` with inputs for
       `secondary_text` and `locale_text`.
  2. Add `PromoTheme` backed string enum (`app/Domain/Content/Enums/PromoTheme.php`) with
     cases `SKY = 'sky'`, `LILAC = 'lilac'`, `MINT = 'mint'`.
  3. Add 5 versatile Content Block types (following `ARCHITECTURE.md` Section 6 checklist
     `Rule K9`: `<Name>Data.php`, `<Name>Block.php`, `app/Filament/Blocks/<Name>Form.php`,
     explicit registration in `ContentServiceProvider`):
     a) `promo_slide` (`PromoSlideBlock`, `PromoSlideData`, `PromoSlideForm`,
        `schema_version: 1`):
        - `theme`: `PromoTheme` enum (`sky`, `lilac`, `mint`)
        - `eyebrow`: string, max 60, `NoHtml`
        - `title_line_1`: string, max 60, `NoHtml`
        - `title_line_2`: ?string, nullable, max 60, `NoHtml`
        - `description`: string, max 200, `NoHtml`
        - `cta_label`: string, max 50, `NoHtml`
        - `cta_url`: string, `SafeLink`
        - `image_url`: string, `SafeLink` (in `PromoSlideForm`, provide `->datalist([...])`
          listing the 12 `/images/products/...` paths so admins can switch demo images with
          one click OR type any custom `/...` or `https://...` URL)
        - `image_alt`: string, max 120, `NoHtml`
        - `label`: string, max 60, `NoHtml` (accessible slide & pagination label)
     b) `feature_banner` (`FeatureBannerBlock`, `FeatureBannerData`, `FeatureBannerForm`,
        `schema_version: 1` — shared by both Hero Side Banner and Collection Banner):
        - `eyebrow`: string, max 60, `NoHtml`
        - `title_line_1`: string, max 60, `NoHtml`
        - `title_line_2`: ?string, nullable, max 60, `NoHtml`
        - `description`: ?string, nullable, max 240, `NoHtml`
        - `cta_label`: string, max 50, `NoHtml`
        - `cta_url`: string, `SafeLink`
        - `image_url`: string, `SafeLink` (with `->datalist([...])` of built-in product
          images in `FeatureBannerForm`)
        - `image_alt`: string, max 120, `NoHtml`
        - `badge_text`: ?string, nullable, max 40, `NoHtml` (renders `.side-caption` on
          hero side banner and `.collection-number` on collection banner)
     c) `service_item` (`ServiceItemBlock`, `ServiceItemData`, `ServiceItemForm`,
        `schema_version: 1`):
        - `icon`: string, `In` allowed icon keys from `components/icon.blade.php` (`truck`,
          `box`, `shield`, `headphones`, `bolt`, `check`, `lock`, `heart`, `user`, `watch`,
          `phone`, `speaker`, `battery`, `gamepad`, `home`, `laptop`, `mail`, `search`,
          `bag`)
        - `title`: string, max 60, `NoHtml`
        - `subtitle`: string, max 100, `NoHtml`
        - `link_url`: ?string, nullable, `SafeLink`
     d) `section_header` (`SectionHeaderBlock`, `SectionHeaderData`, `SectionHeaderForm`,
        `schema_version: 1` — shared by Categories, Featured Products, and New Arrivals
        section headings):
        - `eyebrow`: string, max 60, `NoHtml`
        - `title`: string, max 100, `NoHtml`
        - `cta_label`: ?string, nullable, max 50, `NoHtml`
        - `cta_url`: ?string, nullable, `SafeLink`
     e) `brand_strip` (`BrandStripBlock`, `BrandStripData`, `BrandStripForm`,
        `schema_version: 1`):
        - `eyebrow`: string, max 60, `NoHtml` (e.g. `'GOOD COMPANY'`)
        - `title`: string, max 60, `NoHtml` (e.g. `'Great brands.'`)
        - `brands`: array of up to 10 brand items (`array<int, array{label: string, url: string}>`),
          each item having `label` (string, max 40, `NoHtml`) and `url` (string, `SafeLink`),
          edited via a Filament `Repeater::make('brands')->maxItems(10)->defaultItems(0)`.
  4. Expand `Placement` enum (`app/Domain/Content/Enums/Placement.php`) with the homepage
     placements and their allowed block types and `maxCount()`:
     - `ANNOUNCEMENT_BAR = 'announcement_bar'` -> `['announcement_bar']`, `maxCount: 1`
     - `HOME_HERO_CAROUSEL = 'home_hero_carousel'` -> `['promo_slide']`, `maxCount: 5`
     - `HOME_HERO_SIDE = 'home_hero_side'` -> `['feature_banner']`, `maxCount: 1`
     - `HOME_SERVICE_STRIP = 'home_service_strip'` -> `['service_item']`, `maxCount: 4`
     - `HOME_CATEGORIES_HEADER = 'home_categories_header'` -> `['section_header']`, `maxCount: 1`
     - `HOME_FEATURED_HEADER = 'home_featured_header'` -> `['section_header']`, `maxCount: 1`
     - `HOME_COLLECTION_BANNER = 'home_collection_banner'` -> `['feature_banner']`, `maxCount: 1`
     - `HOME_NEW_ARRIVALS_HEADER = 'home_new_arrivals_header'` -> `['section_header']`, `maxCount: 1`
     - `HOME_BRAND_STRIP = 'home_brand_strip'` -> `['brand_strip']`, `maxCount: 1`
  5. Update `docs/contracts/content-api.md` and `tests/Fixtures/contracts/content-layout-v1.json`
     to reflect `announcement_bar` v2 (`secondary_text`, `locale_text`) and the new placement
     keys in `data.placements`, and add unit/feature tests for the v1->v2 upgrader, new
     block payload validations, and API output.

  Part B — Idempotent Seeding, Full-Bar/Full-Card Link UX, Homepage Wiring & Teardown (CP-B):
  6. Update `database/seeders/ContentSeeder.php` to idempotently seed all homepage placements
     matching the exact storefront homepage content (preserving exact row counts on repeat
     `php artisan db:seed` runs per `Rule DB6`):
     - `announcement_bar` (v2): `text: 'Good tech. Better everyday.'`,
       `secondary_text: 'Free delivery on orders over Rs. 25,000'`,
       `locale_text: 'Sri Lanka / LKR'`, `link_url: null`, `tone: 'info'`.
     - `home_hero_carousel`: 3 published `promo_slide` blocks (`sort_order: 0, 1, 2`) for
       `audio-offers`, `phone-edit`, and `charging-offers` with their exact `/images/products/...`
       paths and `/shop?...` `cta_url`s.
     - `home_hero_side`: 1 published `feature_banner` block for Marshall Acton III
       (`/images/products/marshall-acton-iii.png`, `cta_url: '/shop?category=speakers'`,
       `badge_text: 'MARSHALL ACTON III'`).
     - `home_service_strip`: 4 published `service_item` blocks (`sort_order: 0, 1, 2, 3`) for
       `Islandwide delivery`, `Cash on delivery`, `Shop with confidence`, and `Here to help`
       (`link_url: '/help'`).
     - `home_categories_header`, `home_featured_header`, `home_new_arrivals_header`: 1
       published `section_header` block in each placement matching the homepage section
       headings.
     - `home_collection_banner`: 1 published `feature_banner` block for Shargeek 300
       (`/images/products/shargeek-300.jpg`, `cta_url: '/shop?category=charging'`,
       `badge_text: '24/7'`).
     - `home_brand_strip`: 1 published `brand_strip` block (`GOOD COMPANY` / `Great brands.`
       with the 6 brand links `Apple`, `Samsung`, `Anker`, `JBL`, `Marshall`, `Sony`).
  7. Full-Bar & Full-Card Clickable Link UX + Blade/CSS Wiring:
     - **Announcement Bar (`resources/views/layouts/storefront.blade.php` & `layout.css`)**:
       Render `text`, `secondary_text` (when non-empty), and `locale_text` (when non-empty)
       from `$announcement->data`. When `$announcement->data['link_url']` is non-empty,
       render the **entire `.announcement` bar** as an `<a href="{{ $announcement->data['link_url'] }}" class="announcement announcement--{{ $announcement->data['tone'] ?? 'info' }} announcement--link">`
       (spanning the full width and height of the bar, with clean hover feedback and
       `text-decoration: none; color: inherit;`), NOT an `<a>` around only the first span!
     - **Hero Side Banner & Collection Banner (`resources/views/storefront/home.blade.php` & `pages.css`)**:
       Render both banners from `$layout->placements['home_hero_side'][0] ?? null` and
       `$layout->placements['home_collection_banner'][0] ?? null` (omitting the banner
       cleanly if disabled/empty). Make the **entire banner card clickable** when `cta_url`
       is present (e.g. via a CSS stretched-link overlay `.banner-stretched-link` on the
       CTA `<a>` inside `position: relative` `.hero-side` and `.collection-banner`, or
       wrapping the card) so clicking anywhere on the banner card navigates to `cta_url`.
     - **Service Strip (`resources/views/storefront/home.blade.php`)**:
       Render the items from `$layout->placements['home_service_strip'] ?? []` (omitting
       `.service-strip` if empty). For ANY service item where `link_url` is non-empty,
       render the entire item wrapper as `<a href="{{ $item->data['link_url'] }}">` so the
       whole service box is clickable; when `link_url` is null, render as `<div>`.
     - **Hero Promo Carousel (`resources/views/storefront/home.blade.php` & `components/promo-carousel.blade.php`)**:
       Render slides from `$layout->placements['home_hero_carousel'] ?? []` (omitting the
       carousel and switching `.hero-grid` to `.hero-grid--single` when the placement array
       is empty). Update `promo-carousel.blade.php` to read the block payload keys
       (`theme`, `eyebrow`, `title_line_1`, `title_line_2`, `description`, `cta_label`,
       `cta_url`, `image_url`, `image_alt`, `label`).
     - **Section Headers & Brand Strip (`resources/views/storefront/home.blade.php`)**:
       Render `home_categories_header`, `home_featured_header`, `home_new_arrivals_header`,
       and `home_brand_strip` from `$layout->placements[...]`.
     - Remove unused `config/promotions.php` (or mark it deprecated/removed in `DemoHomeController`
       and `DEMO-TEARDOWN.md`) since promotions are now 100% CMS-driven via
       `home_hero_carousel`.
  8. Update `tests/Feature/Database/ContentSeederTest.php` and
     `tests/Feature/Storefront/DemoHomeTest.php` to verify:
     - All 9 placements are seeded idempotently (running `ContentSeeder` twice produces
       zero duplicate blocks across all multi-block placements like `home_hero_carousel`
       and `home_service_strip`!).
     - Editing or disabling carousel slides, banners, service items, or the announcement bar
       in the CMS immediately updates `GET /`.
     - Setting `link_url` on `announcement_bar` renders the entire `.announcement` bar as a
       clickable `<a>` tag.
  9. Update `docs/blueprints/P1-S1/DEMO-TEARDOWN.md` (and `ARCHITECTURE.md` if needed) to
     document all temporary demo homepage blocks, placements, and teardown instructions.

Out of scope:
  - Creating database tables, migrations, or Eloquent models for products or categories
    (`Rule X4` — `$featured`, `$newProducts`, and `$categories` remain in-memory in
    `DemoHomeController`).
  - Adding `spatie/laravel-medialibrary` or new Composer/NPM packages (`Rule X1` — image
    fields use validated `SafeLink` paths/URLs with a Filament `datalist` of local demo
    images plus support for any `/...` or `https://...` URL).
  - Modifying existing database migrations (`Rule DB2`).

Read first:
  - `ARCHITECTURE.md` sections 1, 5, 6, 7, 8, 11, 12 (especially Block rules, Adding a block
    type checklist, D2, D3, D8).
  - `AGENTS.md` ('Coding standards', 'Content rules (critical)', 'Testing', 'Definition of done').
  - Inspect existing files in `C:\dev\getnow-cms`:
    * `app/Domain/Content/Contracts/` (all files — check `BlockDefinition`, `BlockRegistry`,
      `BlockPayloadUpgrader`)
    * `app/Domain/Content/Blocks/AnnouncementBar/` (all files)
    * `app/Domain/Content/Services/` and `app/Domain/Content/Actions/` (check how
      `GetUpgradedBlockPayload`, `ResolveServablePayload`, `CreateContentBlock`, and
      `UpdateContentBlock` validate payloads and run upgraders)
    * `app/Providers/ContentServiceProvider.php`
    * `app/Filament/Blocks/` (all files — check `BlockFormRegistry.php` and
      `AnnouncementBarForm.php`)
    * `app/Filament/Admin/Resources/ContentBlockResource/` (all files — check how placement
      selection filters or validates block `type` in `ContentBlockForm.php` / `CreateContentBlock`
      page)
    * `database/seeders/ContentSeeder.php`
    * `resources/views/layouts/storefront.blade.php`, `resources/views/storefront/home.blade.php`,
      `resources/views/components/promo-carousel.blade.php`
    * `resources/css/storefront/layout.css`, `resources/css/storefront/pages.css`
    * `docs/contracts/content-api.md` and `tests/Fixtures/contracts/content-layout-v1.json`
    * `tests/Feature/` and `tests/Unit/` (inspect existing block validation, upgrader, and
      Filament ContentBlockResource tests)

Decisions made (do not re-decide):
  D-a Schema v2 Upgrade for `announcement_bar` (`Rule K2`):
      `AnnouncementBarBlock::schemaVersion()` bumps from `1` to `2`. `AnnouncementBarData`
      adds `public ?string $secondary_text = null` and `public ?string $locale_text = null`.
      `AnnouncementBarV1ToV2Upgrader` upgrades v1 payloads to v2 so any existing v1 row in
      the DB or tests continues to load and upgrade cleanly.
  D-b Full-Bar / Full-Card Clickable Link UX:
      - When `announcement_bar` has a non-empty `link_url`, the outer `.announcement` element
        itself is an `<a href="...">` spanning the entire bar (not an `<a>` around just the
        first text span).
      - When a `service_item` has a non-empty `link_url`, the entire service item card is an
        `<a href="...">` box.
      - On `home_hero_side` and `home_collection_banner`, the CTA link uses a stretched-link
        overlay (or full-card link wrapper) so clicking anywhere on the banner navigates to
        `cta_url`.
  D-c Reusable Block Types Across Placements:
      Reuse `feature_banner` for both `home_hero_side` and `home_collection_banner`, and
      reuse `section_header` for `home_categories_header`, `home_featured_header`, and
      `home_new_arrivals_header`.
  D-d Multi-Block Placement Idempotency in `ContentSeeder` (`Rule DB6`):
      For placements with `maxCount > 1` (`home_hero_carousel` with 3 slides and
      `home_service_strip` with 4 items), `ContentSeeder` MUST match existing blocks by
      `(placement, type, sort_order)` (excluding `ARCHIVED`) and update them in place so
      re-running `php artisan db:seed` never creates duplicate slides or service items.
      Also ensure `CreateContentBlock` / `ContentSeeder` sets the deterministic `sort_order`
      (`0, 1, 2...`) for multi-block placements.
  D-e Image Paths via `SafeLink` + Filament `datalist`:
      Keep Phase 1 package-free (`Rule X1`): `image_url` in `PromoSlideData` and
      `FeatureBannerData` is validated by `SafeLink` (accepting `/images/...` relative paths
      or `https://...` URLs), and the Filament `TextInput::make('payload.image_url')`
      provides a `->datalist([...])` of all built-in `/images/products/...` paths for easy
      demo editing alongside custom URL input.

Open decisions:
  None. [If `ContentBlockForm.php` in Filament hardcodes `'announcement_bar'` or needs
  dynamic form switching by selected `type`, document the exact `ContentBlockForm.php`
  update in Section 5.]

Acceptance criteria:
  AC-1  `AnnouncementBarBlock` is at `schema_version = 2` with `text`, `secondary_text`,
        `locale_text`, `link_url`, and `tone`. Stored v1 payloads upgrade automatically via
        `AnnouncementBarV1ToV2Upgrader` to v2 in `GetUpgradedBlockPayload` and
        `GetLayoutContent` (`Rule K2`).
  AC-2  All 5 new block types (`promo_slide`, `feature_banner`, `service_item`,
        `section_header`, `brand_strip`) are registered in `ContentServiceProvider` and
        `BlockFormRegistry`, validate their payloads (`NoHtml`, `SafeLink`, max lengths,
        enums), and can be created and edited in `/admin/content-blocks` (`Rule K6`, `K9`).
  AC-3  All 9 `Placement` cases are present in `data.placements` on `GET /api/v1/content/layout`
        and documented in `docs/contracts/content-api.md` and
        `tests/Fixtures/contracts/content-layout-v1.json` (`Rule K8`).
  AC-4  `php artisan db:seed` populates all 9 placements (13 published `ContentBlock` rows
        total: 1 `announcement_bar`, 3 `promo_slide`, 2 `feature_banner`, 4 `service_item`,
        3 `section_header`, 1 `brand_strip` = 14 blocks total). Running `db:seed` twice
        produces the exact same row count (`14` blocks) with zero duplicates (`Rule DB6`).
  AC-5  Full-bar link UX on Announcement Bar: when `announcement_bar.link_url` is set (e.g.
        `'/shop?deals=1'`), `GET /` renders the outer `.announcement` bar itself as an
        `<a href="/shop?deals=1" class="announcement ...">` spanning the whole bar, and
        renders `text`, `secondary_text`, and `locale_text` dynamically.
  AC-6  Full-card link UX & Dynamic Homepage Rendering: `GET /` renders the hero promo
        carousel slides (and their images), hero side banner (and its image), 4 service
        strip items, 3 section headers, collection feature banner (and its image), and
        brand strip dynamically from `GetLayoutContent`, with full-card clickable links on
        banners and linked service items.
  AC-7  Dynamic CMS Mutation & Empty-State Resilience (`Rule K7`): editing any homepage
        block's text or `image_url` in the CMS immediately updates `GET /`, and disabling or
        deleting all blocks in any placement omits that section cleanly on `GET /` without
        crashing.
  AC-8  `DEMO-TEARDOWN.md` is updated to document the demo placements/blocks and cleanup
        procedure, and `composer check` (Pint, PHPStan Level 8, Pest including `tests/Arch`)
        passes with zero errors and zero baseline additions.

Rules most at risk:
  - K2 (bumping `AnnouncementBarBlock` to v2 without registering the v1->v2 upgrader, which
    would break existing v1 tests or fixtures).
  - K8 & X7 (`docs/contracts/content-api.md` and `tests/Fixtures/contracts/content-layout-v1.json`
    must include all new `Placement` keys and the v2 `announcement_bar` payload fields so
    the contract test in `GetLayoutContentEndpointTest` passes).
  - DB6 (`ContentSeeder` creating duplicate `promo_slide` or `service_item` blocks on second
    run if it doesn't match by `(placement, type, sort_order)`).
  - S4 & C4 (Filament `ContentBlockForm` must not write models directly or expose `status` /
    `is_enabled` as form fields).
  - C1, C2, C7 (all new Block, Data, Upgrader, Enum, and Form classes must have
    `declare(strict_types=1);`, `final`, and pass PHPStan Level 8).

Known traps:
  1. Existing tests using `ContentBlock::factory()` with v1 `announcement_bar` payloads:
     Inspect `database/factories/ContentBlockFactory.php` and existing tests in
     `tests/Feature/` and `tests/Unit/` to see whether they assert `version === 1` or
     register a mock v1->v2 upgrader on `announcement_bar` (remember `GetLayoutContentTest`
     Test 8 in P1-API1 tested upgrading `announcement_bar`!). Check how bumping
     `AnnouncementBarBlock::schemaVersion()` to `2` interacts with those existing tests so
     every existing test continues to pass.
  2. `ContentBlockForm.php` dynamic schema switching:
     Inspect `app/Filament/Admin/Resources/ContentBlockResource/` to see how the Filament
     form selects `placement` and `type` and loads the block form from `BlockFormRegistry`.
     Now that there are 6 block types across 9 placements, verify that selecting a
     `placement` in Create/Edit reactive-updates the allowed `type` (or renders the matching
     `BlockFormRegistry` schema) cleanly.
  3. Setting `sort_order` when seeding multi-block placements (`home_hero_carousel`,
     `home_service_strip`):
     Check whether `CreateContentBlock` sets `sort_order` or defaults it to `0`. If
     `CreateContentBlock` does not accept `sort_order`, check how `sort_order` is set so the
     3 carousel slides (`0, 1, 2`) and 4 service strip items (`0, 1, 2, 3`) maintain their
     exact display order and idempotent `(placement, type, sort_order)` lookup in
     `ContentSeeder`.

Size guard and checkpoints:
  Gate implementation into two strict checkpoints in Section 12:
  - CP-A: `AnnouncementBar` v2 upgrade + `PromoTheme` enum + the 5 new Block definitions,
    Data classes, and Filament Forms + `Placement` enum + `ContentServiceProvider` +
    `content-api.md` & fixture + Block/Upgrader unit & feature tests (`composer check` green).
  - CP-B: `ContentSeeder` update + `storefront.blade.php`, `home.blade.php`,
    `promo-carousel.blade.php` & CSS full-bar/full-card link updates + `ContentSeederTest` &
    `DemoHomeTest` updates + `DEMO-TEARDOWN.md` update (`composer check` green, full
    `IMPL-REPORT`).

Manual verification script (Layer C):
  1. Run `php artisan migrate:fresh --seed --no-interaction` and repeat
     `php artisan db:seed --no-interaction`.
     Verify in Tinker that `ContentBlock::count()` is `14` on both runs.
  2. Run `npm run build` and start `php artisan serve`.
  3. Open `http://127.0.0.1:8000/`:
     - Verify announcement bar shows all 3 texts (`Good tech. Better everyday.`,
       `Free delivery on orders over Rs. 25,000`, `Sri Lanka / LKR`).
     - Verify hero carousel, hero side banner, 4 service strip cards, 3 section headers,
       collection banner, and brand strip render identically to the storefront design.
  4. Open `http://127.0.0.1:8000/admin/content-blocks`:
     - Edit the `announcement_bar` block: set `link_url` to `/shop?deals=1` and change
       `secondary_text` -> save -> refresh `/` and verify clicking anywhere across the entire
       announcement bar navigates to `/shop?deals=1`.
     - Edit a `promo_slide` block: change the title and select a different image from the
       `image_url` dropdown/input -> save -> refresh `/` and verify the carousel slide text
       and image update immediately.
     - Verify clicking anywhere on the Hero Side Banner or Collection Banner card navigates
       to its `cta_url`.
       