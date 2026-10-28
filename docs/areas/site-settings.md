---
kind: normative
area: site-settings
status: active
summary: Global storefront scalar settings, Spatie settings repository integration, and administrative settings management.
paths:
  - app/Domain/Content/Settings/StorefrontSettings.php
  - app/Domain/Content/Actions/UpdateStorefrontSettings.php
  - app/Domain/Content/Data/StorefrontSettingsData.php
  - app/Filament/Admin/Pages/ManageStorefrontSettings.php
  - app/Filament/Admin/Schemas/StorefrontSettingsForm.php
  - database/settings/*create_storefront_settings.php
  - tests/Feature/Content/StorefrontSettingsTest.php
decisions: [D5, D6]
depends-on: [admin-panel, data]
tests:
  - vendor/bin/pest tests/Feature/Content/StorefrontSettingsTest.php
triggers:
  - paths:
      - app/Domain/Content/Settings/StorefrontSettings.php
      - database/settings/**
    reconsider: >-
      Settings schema changes require checking three things:
      (1) New properties require a settings migration in database/settings/.
      (2) Phase 1 settings remain global scalar values without media uploads (D5).
      (3) Layout API response serialization must include any new setting property.
  - paths:
      - app/Domain/Content/Actions/UpdateStorefrontSettings.php
      - app/Domain/Content/Data/StorefrontSettingsData.php
    reconsider: >-
      Settings update logic changes require checking three things:
      (1) String inputs reject HTML tags using regex validation.
      (2) All mutations must be recorded in the activity log (SEC5).
      (3) Mutation remains encapsulated in an Action rather than direct model writes (C5).
---

# Site Settings

The `site-settings` area manages global scalar storefront configuration values such as site name, metadata description, search placeholder, and footer copy. Settings are persisted via Spatie Laravel Settings and edited through Filament.

## How it works now

- **Spatie settings repository.** Settings are declared as typed public properties on `StorefrontSettings` within group `'storefront'`. Default values are initialized by settings migrations in `database/settings/`.
- **Encapsulated mutation action.** Settings are updated through `UpdateStorefrontSettings`. The action runs inside a database transaction, compares previous values against incoming validated DTO attributes, and logs changes to the activity log.
- **Strict DTO validation.** `StorefrontSettingsData` enforces maximum string lengths and disallows `<` and `>` characters using regex validation to reject raw HTML.
- **Filament management page.** `ManageStorefrontSettings` exposes form fields defined in `StorefrontSettingsForm`. Saving calls `StorefrontSettingsData::prepareValidated` directly, bypassing default Filament persistence hooks.

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | **Global scalar strings only.** Settings contain only short text strings without media or files. | Phase 1 CMS scope constraint (D5); media library arrives with image blocks. | `Review only` |
| 2 | **Single group namespace.** All storefront settings reside under the `'storefront'` group. | Isolates storefront layout settings from other platform configuration. | `Review only` |
| 3 | **Strict text validation without raw HTML.** Property values reject `<` and `>` characters and respect length bounds. | Security rule K3; prevents script injection into storefront layout metadata. | `tests/Feature/Content/StorefrontSettingsTest.php` |
| 4 | **Platform admin authorization only.** Access to view or mutate storefront settings requires `is_platform_admin === true`. | Authorization rule SEC4 and decision D6; non-admin users receive 403 Forbidden. | `tests/Feature/Content/StorefrontSettingsTest.php` |
| 5 | **Activity log emission on modification.** Modifying settings writes previous and updated values to the activity log. | Audit trail requirement SEC5 for sensitive administrative configuration. | `tests/Feature/Content/StorefrontSettingsTest.php` |
| 6 | **Action encapsulation for mutations.** Settings writes execute via `UpdateStorefrontSettings` rather than direct calls. | Architectural rule C5; every write to persisted state goes through an Action. | `tests/Feature/Content/StorefrontSettingsTest.php` |

## Changing it

- **Adding a new setting property:**
  1. Add a typed property to `App\Domain\Content\Settings\StorefrontSettings`.
  2. Create a Spatie settings migration in `database/settings/` adding the default value.
  3. Add the property with validation attributes to `StorefrontSettingsData`.
  4. Add the input component to `StorefrontSettingsForm`.
  5. Update `UpdateStorefrontSettings` property mapping and `GetLayoutContent` layout response.
  6. Add test cases in `StorefrontSettingsTest`.

## Running and testing

Run configuration cache clearing before executing tests to ensure test database settings from `phpunit.xml` apply:

```powershell
php artisan config:clear
vendor/bin/pest tests/Feature/Content/StorefrontSettingsTest.php
```

## Known limits and open questions

- **No media or image assets.** Favicon, logos, and OpenGraph images are deferred (D5); media management arrives in later phases.
- **No optimistic locking.** Settings updates overwrite concurrently without version-conflict exceptions.

## History

- Settings migration [2026_10_07_091043_create_storefront_settings.php](../../database/settings/2026_10_07_091043_create_storefront_settings.php): initial storefront settings properties.
- Decisions: D5 (no images in phase 1), D6 (platform admin access flag).
