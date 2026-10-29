# Change Request: CR-P1-S1-01

**Title:** Resolve Layout Shell Collision (`layouts.app`) and Blade Icons `<x-icon>` Component Conflict  
**Status:** Submitted for Reviewer Approval  
**Step ID:** P1-S1  
**Files Affected:**
1. `resources/views/layouts/storefront.blade.php` (new file for storefront layout shell instead of overwriting `resources/views/layouts/app.blade.php`)
2. `resources/views/storefront/home.blade.php` (`@extends('layouts.storefront')` instead of `@extends('layouts.app')`)
3. `config/blade-icons.php` (new config file disabling `blade-icons` default component alias)
4. `docs/blueprints/P1-S1/plan.md` (updated references in Section 4, 5.5, 11, 12)
5. `docs/blueprints/P1-S1/DEMO-TEARDOWN.md` (updated teardown commands)

---

## 1. Rationale & Problem Analysis

During implementation of Commit 4 (`feat(storefront): wire demo homepage controller, routes, and views to GetLayoutContent`), executing `composer check` revealed two critical collisions caused by Plan v2 specifications:

### Finding 1: Layout Collision on `resources/views/layouts/app.blade.php`
- **Root Cause:**
  `getnow-cms` already contains `resources/views/layouts/app.blade.php` from the Laravel Livewire Flux starter kit:
  ```blade
  <x-layouts::app.sidebar :title="$title ?? null">
      <flux:main>
          {{ $slot }}
      </flux:main>
  </x-layouts::app.sidebar>
  ```
  In Laravel Blade, the anonymous component namespace `<x-layouts::app>` maps directly to `resources/views/layouts/app.blade.php`. This component is used by:
  - `resources/views/dashboard.blade.php` (`<x-layouts::app :title="__('Dashboard')">`)
  - `resources/views/settings/profile.blade.php`
  - `resources/views/settings/password.blade.php`
  - `resources/views/settings/appearance.blade.php`
  These pages are covered by existing test suites: `DashboardTest`, `ProfileUpdateTest`, `PasswordUpdateTest`, `AppearanceTest`.
- **Defect Under Plan v2:**
  Plan v2 specified overwriting `resources/views/layouts/app.blade.php` with the storefront layout. This immediately broke all dashboard and user profile settings pages with 500 errors during test execution because the storefront layout expects `@yield('content')` and variables `$layout`, `$categories`, `$promotions`, instead of the starter kit's `$slot`.
- **Proposed Solution:**
  Place the storefront layout shell in `resources/views/layouts/storefront.blade.php` (leaving `resources/views/layouts/app.blade.php` completely untouched for the starter kit), and update `resources/views/storefront/home.blade.php` to `@extends('layouts.storefront')`.
  During teardown, `Remove-Item -Force resources/views/layouts/storefront.blade.php` cleanly removes the demo layout with zero risk to the starter kit.

### Finding 2: `<x-icon>` Component Collision with `blade-ui-kit/blade-icons`
- **Root Cause:**
  `getnow-cms` has `blade-ui-kit/blade-icons` installed (a direct dependency of Filament). In `BladeUI\Icons\BladeIconsServiceProvider:L127-129`:
  ```php
  if ($name = config('blade-icons.components.default')) {
      Blade::component($name, Icon::class);
  }
  ```
  `blade-icons` defaults `config('blade-icons.components.default')` to `'icon'`.
  Because Laravel gives registered class component aliases priority over anonymous component files, any `<x-icon name="..." ...>` tag in Blade is intercepted by `BladeUI\Icons\Components\Icon::class` rather than resolving to `resources/views/components/icon.blade.php`.
  `BladeUI\Icons\Components\Icon::class` looks for registered SVG icon sets in `blade-icons` and throws:
  `BladeUI\Icons\Exceptions\SvgNotFound: Svg by name "..." from set "default" not found.`
- **Defect Under Plan v2:**
  Every transferred storefront view (`layouts/storefront.blade.php`, `storefront/home.blade.php`, `components/logo.blade.php`, `components/promo-carousel.blade.php`, `components/product-card.blade.php`) that calls `<x-icon>` crashes with `SvgNotFound`.
- **Proposed Solution:**
  Create `config/blade-icons.php`:
  ```php
  <?php

  declare(strict_types=1);

  return [
      'components' => [
          'default' => null,
      ],
  ];
  ```
  Setting `'components.default' => null` prevents `blade-icons` from registering the global `<x-icon>` alias, allowing `<x-icon>` to resolve naturally to the transferred `resources/views/components/icon.blade.php`.
  Filament is unaffected because Filament uses its own namespaced icon component (`<x-filament::icon>`) and the `@svg` directive.
  During teardown, `Remove-Item -Force config/blade-icons.php` removes the configuration file.

---

## 2. Test Verification Evidence

With both changes applied:
- `DashboardTest`: **PASSED**
- `ProfileUpdateTest`: **PASSED**
- `tests/Feature/Database/ContentSeederTest.php`: **PASSED**
- All 355 Feature tests: **PASSED** (`{"tool":"pest","result":"passed","tests":355,"passed":355,"assertions":1718}`)
- `tests/Arch`: **PASSED** (10 tests, 24 assertions)
- `tests/Unit`: **PASSED** (24 tests, 53 assertions)
- Static analysis: `composer stan` **PASSED** (0 errors)
- Lint check: `composer lint` **PASSED**

---

## 3. Requested Approval

Please review and approve Change Request CR-P1-S1-01 so we can incorporate these adjustments into Commit 4 and Commit 5 of Step P1-S1.
