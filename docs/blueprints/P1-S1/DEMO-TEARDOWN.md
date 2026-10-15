# P1-S1 Storefront Demo Teardown Guide

This document details the exact temporary assets, views, configurations, routes, and tests introduced in **Step P1-S1 (Part B - Storefront Demo Harness)** to visually showcase and validate the Phase 1 Layout CMS in action.

---

## 1. Permanent CMS Code vs. Temporary Demo Code

### Permanent Phase 1 CMS Code (DO NOT REMOVE)
The following files are permanent Phase 1 production CMS deliverables and **must NOT be removed** during teardown:
- `database/seeders/ContentSeeder.php` (Seeds production layout defaults, settings, announcement bar, and menus)
- `database/seeders/DatabaseSeeder.php` (Calls `ContentSeeder` under local/testing environments)
- `tests/Feature/Database/ContentSeederTest.php` (Permanent test suite verifying seeder idempotency and layout API integration)

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
| **Configs** | `config/storefront.php` | Demo storefront branding and currency configuration |
| **Configs** | `config/promotions.php` | In-memory homepage promo carousel configuration |
| **Configs** | `config/blade-icons.php` | Blade Icons component alias override (disables default icon alias) |
| **Tests** | `tests/Feature/Storefront/DemoHomeTest.php` | Demo homepage integration test suite |

---

## 3. Step-by-Step PowerShell Cleanup Commands

Execute these commands from the repository root (`c:\dev\getnow-cms`) in PowerShell:

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

# 4. Remove demo controller and support DTO
Remove-Item -Recurse -Force app/Http/Controllers/Storefront
Remove-Item -Recurse -Force app/Support/DemoStorefront

# 5. Remove demo configs and test
Remove-Item -Force config/storefront.php
Remove-Item -Force config/promotions.php
Remove-Item -Force config/blade-icons.php
Remove-Item -Force tests/Feature/Storefront/DemoHomeTest.php

# 6. Revert modified routes and Vite bundler configuration
git checkout -- routes/web.php vite.config.js ARCHITECTURE.md

# 7. Rebuild production assets and run full quality gates
npm run build
composer check
```

---

## 4. Post-Teardown Verification

Following execution of the teardown script:
1. `npm run build` must succeed, generating the bundle without storefront entrypoints.
2. `composer check` must pass cleanly:
   - Pint: 0 formatting issues
   - Larastan: Level 8 static analysis green with 0 errors
   - Pest: all 379 tests passing (including `ContentSeederTest`, `DashboardTest`, `ProfileUpdateTest`, and all API/Filament tests).
3. The root route `GET /` reverts to `Route::view('/', 'welcome')`.
