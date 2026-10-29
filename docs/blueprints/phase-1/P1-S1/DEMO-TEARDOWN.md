# Storefront Demo Teardown Guide

This document details the exact temporary assets, views, configurations, routes, and tests introduced in **Step P1-S1 (Part B - Storefront Demo Harness)** and **Step P1-S2 (Homepage CMS Blocks)** to visually showcase and validate the Phase 1 Layout CMS in action.

---

## 1. Permanent CMS Code vs. Temporary Demo Code

### Permanent Phase 1 CMS Deliverables (DO NOT REMOVE)
The following deliverables are permanent Phase 1 production CMS assets and **must NOT be removed** during teardown:
- **Block Definitions & Data DTOs**: `PromoSlideBlock` / `PromoSlideData`, `FeatureBannerBlock` / `FeatureBannerData`, `ServiceItemBlock` / `ServiceItemData`, `SectionHeaderBlock` / `SectionHeaderData`, `BrandStripBlock` / `BrandStripData`, `AnnouncementBarBlock` (v2) / `AnnouncementBarData` (v2), `AnnouncementBarV1ToV2Upgrader`.
- **Contracts & Enums**: `BlockPayloadUpgrader`, `PromoTheme`, `ServiceIcon`, and expanded `Placement` enum.
- **Filament Forms & Registries**: `PromoSlideForm`, `FeatureBannerForm`, `ServiceItemForm`, `SectionHeaderForm`, `BrandStripForm`, `AnnouncementBarForm` (v2), `BlockFormRegistry`, `ContentServiceProvider`.
- **Database Seeders**: `database/seeders/ContentSeeder.php` (idempotently populates content blocks, settings, and menus) and `database/seeders/DatabaseSeeder.php`.
- **Contract & Fixtures**: `docs/contracts/content-api.md`, `tests/Fixtures/contracts/content-layout-v1.json`.
- **Permanent Test Suites**: All unit and feature tests covering block types, forms, placements, upgraders, and actions (`HomepageBlocksCreateAndServeTest`, Data unit tests, `PlacementTest`, `ContentSeederTest`).

---

## 2. Temporary Demo Files to Remove

The following files and directories were introduced solely to host the in-memory homepage demonstration on `GET /` and should be removed when Phase 1 demo evaluation concludes:

| Category | Path | Description |
|---|---|---|
| **Views** | `resources/views/storefront/` | Homepage view template (`home.blade.php`) |
| **Views** | `resources/views/layouts/storefront.blade.php` | Storefront layout shell wired to `GetLayoutContent` |
| **Components** | `resources/views/components/logo.blade.php` | Storefront SVG logo component |
| **Components** | `resources/views/components/icon.blade.php` | Storefront SVG icon map component |
| **Components** | `resources/views/components/promo-carousel.blade.php` | Accessible promo carousel component |
| **Components** | `resources/views/components/product-card.blade.php` | Storefront product card component |
| **Stylesheets** | `resources/css/storefront.css` | Storefront CSS bundler entrypoint |
| **Stylesheets** | `resources/css/storefront/` | Modular Vanilla CSS styles (base, layout, components, pages, carousel, responsive) |
| **Scripts** | `resources/js/storefront.js` | Storefront JS bundler entrypoint |
| **Scripts** | `resources/js/modules/` | Modular ES modules (carousel.js, dialogs.js, forms.js) |
| **Static Assets** | `public/favicon.svg` | Storefront demo favicon |
| **Static Assets** | `public/images/placeholder.svg` | Fallback product placeholder image |
| **Static Assets** | `public/images/products/` | Demo catalog product imagery |
| **Controller** | `app/Http/Controllers/Storefront/` | `DemoHomeController.php` invoking `GetLayoutContent` |
| **Support DTO** | `app/Support/DemoStorefront/` | In-memory `Product.php` view model |
| **Filament Trait** | `app/Filament/Blocks/Concerns/HasDemoProductImages.php` | Demo product image suggestions datalist |
| **Configs** | `config/storefront.php` | Demo storefront branding and currency configuration |
| **Configs** | `config/promotions.php` | Legacy promotions configuration (now unused) |
| **Configs** | `config/blade-icons.php` | Blade Icons component alias override (disables default icon alias) |
| **Tests** | `tests/Feature/Storefront/DemoHomeTest.php` | Demo homepage integration test suite |
| **Tests** | `tests/Unit/Domain/Content/Enums/ServiceIconTest.php` | Demo SVG icon map parity test suite |

---

## 3. Level 1: Frontend-Only Teardown

Level 1 teardown removes only the in-memory storefront presentation layer while preserving all CMS blocks, placements, and ContentSeeder definitions. The Content API remains fully functional for external storefront consumers.

Execute these commands sequentially from the repository root (`c:\dev\getnow-cms`) in PowerShell:

```powershell
# 1. Remove temporary views and components
Remove-Item -Recurse -Force resources/views/storefront
Remove-Item -Force resources/views/layouts/storefront.blade.php
Remove-Item -Force resources/views/components/logo.blade.php
Remove-Item -Force resources/views/components/icon.blade.php
Remove-Item -Force resources/views/components/promo-carousel.blade.php
Remove-Item -Force resources/views/components/product-card.blade.php

# 2. Remove temporary stylesheets and JS modules
Remove-Item -Recurse -Force resources/css/storefront
Remove-Item -Force resources/css/storefront.css
Remove-Item -Recurse -Force resources/js/modules
Remove-Item -Force resources/js/storefront.js

# 3. Remove demo static assets and images
Remove-Item -Force public/favicon.svg
Remove-Item -Force public/images/placeholder.svg
Remove-Item -Recurse -Force public/images/products

# 4. Remove demo controller, support DTO, and Filament demo concerns
Remove-Item -Recurse -Force app/Http/Controllers/Storefront
Remove-Item -Recurse -Force app/Support/DemoStorefront
Remove-Item -Force app/Filament/Blocks/Concerns/HasDemoProductImages.php

# 5. Remove demo configs and demo-specific tests
Remove-Item -Force config/storefront.php
Remove-Item -Force config/promotions.php
Remove-Item -Force config/blade-icons.php
Remove-Item -Force tests/Feature/Storefront/DemoHomeTest.php
Remove-Item -Force tests/Unit/Domain/Content/Enums/ServiceIconTest.php

# 6. Revert modified routes and Vite bundler configuration
git checkout -- routes/web.php vite.config.js

# 7. Rebuild production assets and run full quality gates
npm run build
composer check
```

---

## 4. Level 2: Full Mock Homepage Block & Placement Teardown

Level 2 teardown performs Level 1 and additionally prunes the mock homepage blocks from the local database, leaving only the primary announcement bar and navigation menus.

Execute after Level 1 commands:

```powershell
# 1. Prune all mock homepage placement blocks except announcement_bar
php artisan tinker --execute="\App\Domain\Content\Models\ContentBlock::where('placement', '!=', \App\Domain\Content\Enums\Placement::ANNOUNCEMENT_BAR->value)->delete();"

# 2. Verify remaining blocks in database (expecting 1 announcement bar block)
php artisan tinker --execute="echo 'Remaining blocks: ' . \App\Domain\Content\Models\ContentBlock::count() . PHP_EOL;"

# 3. Run full quality gates
composer check
```

---

## 5. Post-Teardown Verification

Following execution of the teardown script:
1. `npm run build` must succeed, generating the bundle without storefront entrypoints.
2. `composer check` must pass cleanly:
   - Pint: 0 formatting issues
   - Larastan: Level 8 static analysis green with 0 errors
   - Pest: all tests passing cleanly.
3. The root route `GET /` reverts to `Route::view('/', 'welcome')`.

---

## 6. Recorded Technical Debt & Deferred Constraints (Step P1-S2)

During Step P1-S2, three architectural decisions resulted in recorded technical debt to be resolved when Phase 2 (Catalog / Storefront API) begins:
1. **Dead `config/promotions.php`**: Retained solely for historical reference during the demo phase; 0 references remain in `app/`, `resources/`, or `tests/`. It must be deleted during teardown.
2. **Dead `STOREFRONT_FREE_DELIVERY_MINOR` in `config/storefront.php`**: The demo storefront announcement bar copy now renders dynamically from the CMS `announcement_bar` block payload `secondary_text`. The env key `STOREFRONT_FREE_DELIVERY_MINOR` is unused by the CMS and storefront home page.
3. **`maxCount()` Enforced Only When Serving (`GetLayoutContent`)**: `maxCount()` constraints on `Placement` (e.g. 5 for `home_hero_carousel`, 1 for single-block placements) are strictly enforced at layout serving time (`GetLayoutContent.php`), taking the top `$maxCount` records ordered by `sort_order ASC, id ASC`. Validation on block creation/publishing is not enforced at the Filament form or domain action layer. As a result, an admin can create additional blocks that are persisted but silently omitted from the layout API response. Server-side create-time/publish-time validation is deferred to Phase 2.
