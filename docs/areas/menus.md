---
kind: normative
area: menus
status: active
summary: Navigation menu hierarchies, tree depth constraints, transactional whole-tree reconciliation, and Filament menu editor.
paths:
  - app/Domain/Content/Actions/*Menu*.php
  - app/Domain/Content/Data/MenuItemData.php
  - app/Domain/Content/Data/MenuTreeData.php
  - app/Domain/Content/Data/UpdateMenuData.php
  - app/Domain/Content/Data/UpdateMenuItemInputData.php
  - app/Domain/Content/Enums/Menu*.php
  - app/Domain/Content/Models/Menu*.php
  - app/Domain/Content/Policies/MenuPolicy.php
  - app/Filament/Admin/Resources/MenuResource.php
  - app/Filament/Admin/Resources/MenuResource/**
  - tests/Feature/Admin/Menu/**
  - tests/Feature/Domain/Content/Actions/*Menu*.php
  - tests/Feature/Domain/Content/Menu*.php
  - tests/Feature/Domain/Content/Policies/MenuPolicyTest.php
decisions: [D2, D4, D9]
depends-on: [admin-panel, data]
tests:
  - vendor/bin/pest tests/Feature/Domain/Content/Policies/MenuPolicyTest.php tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php tests/Feature/Support/Rules/SafeLinkTest.php tests/Feature/Domain/Content/Actions/UpdateMenuTest.php tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php tests/Feature/Admin/Menu/MenuFormTest.php tests/Feature/Domain/Content/MenuItemDatabaseConstraintsTest.php tests/Feature/Domain/Content/Actions/UpdateMenuTypedItemsTest.php tests/Feature/Domain/Content/Actions/ProjectMenuLayoutItemsTest.php
triggers:
  - paths:
      - app/Domain/Content/Enums/MenuKey.php
      - database/migrations/*create_menus*.php
    reconsider: >-
      Menu key changes require verifying three things:
      (1) Menu keys are code-owned and defined in MenuKey enum (D2).
      (2) Adding a menu key requires an idempotent database migration.
      (3) Layout API responses automatically map the new key from MenuKey cases.
  - paths:
      - app/Domain/Content/Actions/UpdateMenu.php
      - app/Domain/Content/Models/MenuItem.php
    reconsider: >-
      Menu mutation updates require verifying three things:
      (1) Tree depth is guarded to a maximum depth of 2.
      (2) Items matched by public_id update in place to preserve stable identifiers.
      (3) Do items reference categories by category_public_id (ADR 0011)? Another reference kind needs its own ADR.
  - paths:
      - app/Filament/Admin/Resources/MenuResource/**
    reconsider: >-
      Menu form schema changes require verifying three things:
      (1) Hidden public_id fields on root and child repeaters must remain present to prevent item regeneration on save.
      (2) Repeater nesting depth remains strictly capped at 2 levels.
      (3) URL fields enforce SafeLink validation rule.
---

# Menus

The `menus` area manages navigation menu hierarchies for the storefront layout. Menu keys are defined in code, while administrators curate menu item labels, links, and nested structures up to depth 2 in Filament.

## How it works now

- **Code-owned keys.** Five menu keys exist in the `MenuKey` enum (`main_nav`, `mobile_nav`, `footer_column_1`, `footer_column_2`, `footer_column_3`). Admins cannot create or delete menus (`MenuPolicy::create` and `MenuPolicy::delete` return `false`).
- **Typed menu items.** Menu items carry a code-defined `kind` (`link`, `category`, `category_tree`) backed by `MenuItemKind`. Allowed kinds per menu are enforced via `MenuKey::allowedKinds()`.
- **Category references.** `category_public_id` references `categories.public_id` with `ON DELETE RESTRICT`. Content stores no category names, slugs, or URLs. Category existence is validated in one batched Catalog query using `GetExistingCategories`.
- **Category tree source.** `category_tree` items are restricted to level 1 and cannot define children.
- **Transactional tree reconciliation.** When saving a menu, the whole desired tree is submitted to `UpdateMenu`. Under a `lockForUpdate()` row lock, items matched by `public_id` update in place, unkeyed items get new ULIDs, and omitted items are deleted, all inside one database transaction.
- **Tree depth guard.** Menus support a maximum depth of 2 (root item plus direct children). Nesting beyond depth 2 is rejected by `UpdateMenu` with `InvalidMenuTreeException`.
- **Filtering cascade.** `GetMenuTree` returns enabled root items and their enabled children. If a parent item is disabled, its children are excluded from output.
- **Filament menu editor.** Administrators curate menu hierarchies through `MenuResource`. The editor creates and edits `link`, `category`, and `category_tree` items within `MenuKey::allowedKinds()` and capped to depth 2. Category options are populated once per form load from `GetCategoryTree(CategoryTreeAudience::Admin)`. Non-blocking inline warnings notify editors when a selected category is inactive or hidden from navigation and will be omitted from the storefront layout. Form submissions explicitly pass `kind` on every item and reconcile whole trees through `UpdateMenu` without reverting typed items to links.
- **Public API serving.** Stored navigation menus are projected into the layout API at read time by `ProjectMenuLayoutItems`. `category` items derive URLs as `/shop?category={slug}` and fall back to category names when labels are null; level-1 `category_tree` items expand in place into storefront-visible categories. Unresolvable taxonomy targets are omitted. `GetMenuTree` retains the raw stored items for administrative editing.

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | **Menu keys are defined in code.** Admins cannot create or delete menus. | Storefront layout requires deterministic keys defined in the `MenuKey` enum (D2). | `tests/Feature/Domain/Content/Policies/MenuPolicyTest.php` |
| 2 | **Maximum nesting depth is 2.** No grandchild items allowed. | Storefront layout and responsive navigation UX constraints. | `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` |
| 3 | **Tree item capacity caps.** Maximum 10 top-level items and maximum 10 children per parent. | Usability and performance boundaries for layout menus. | `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` |
| 4 | **URLs must be relative `/` or `https://` for link items.** Reject `javascript:` and other schemes; category and category_tree items enforce null URLs. | Security rule K4; prevents XSS and open redirect vulnerabilities. | `tests/Feature/Support/Rules/SafeLinkTest.php` and `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` |
| 5 | **Existing item public_ids are stable across saves.** Updating an item matched by `public_id` updates it in place. | Preserves durable public identifiers during item reordering, reparenting, and label edits. | `tests/Feature/Domain/Content/Actions/UpdateMenuTest.php` |
| 6 | **Disabled parents suppress children.** Disabling a parent excludes all of its children from the tree. | Inactive navigation nodes must not leak child links to consumers. | `tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php` |
| 7 | **Atomic whole-tree mutation under row lock.** Mutations reconcile the desired tree inside a single database transaction under a row lock. | Concurrency decision D9; avoids interleaved tree states across concurrent admin edits. | `Review only` |
| 8 | **Category references must exist and cannot be deleted while referenced.** | CT4 and referential integrity; menu items cannot hold orphaned category references. | `tests/Feature/Domain/Content/Actions/UpdateMenuTypedItemsTest.php` and `tests/Feature/Catalog/DeleteCategoryTest.php` |

## Changing it

- **Adding a new navigation menu:**
  1. Add a new case to `App\Domain\Content\Enums\MenuKey`.
  2. Create an idempotent database migration in `database/migrations/` inserting the new row into `menus`.
  3. Update `docs/contracts/content-api.md` and fixture `tests/Fixtures/contracts/content-layout-v1.json`.
  4. Verify tests in `MenuSchemaAndProvisioningTest` and `GetLayoutContentTest`.
- **Adding a new menu item kind:**
  1. Add a case to `App\Domain\Content\Enums\MenuItemKind`.
  2. Add an additive migration updating check constraints on `menu_items`.
  3. Update `MenuKey::allowedKinds()`, validation in `UpdateMenu`, and layout mapping.
- **Modifying item validation rules:**
  1. Adjust constraints in `UpdateMenu` and `MenuItemData`.
  2. Update form schema components in `App\Filament\Admin\Resources\MenuResource\Schemas\MenuForm`.
  3. Verify rejection cases in `UpdateMenuRejectionsTest`.

## Running and testing

Run configuration cache clearing before executing tests to ensure test database settings from `phpunit.xml` apply:

```powershell
php artisan config:clear
vendor/bin/pest tests/Feature/Domain/Content/Policies/MenuPolicyTest.php tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php tests/Feature/Support/Rules/SafeLinkTest.php tests/Feature/Domain/Content/Actions/UpdateMenuTest.php tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php tests/Feature/Admin/Menu/MenuFormTest.php tests/Feature/Domain/Content/MenuItemDatabaseConstraintsTest.php tests/Feature/Domain/Content/Actions/UpdateMenuTypedItemsTest.php
```

## Known limits and open questions

- **Last-write-wins tree reconciliation.** Menus reconcile whole trees under a row lock on save; concurrent administrative edits commit last-write-wins without conflict exceptions (D9).

## History

- Migration [2026_10_14_000000_create_menus_and_menu_items_tables.php](../../database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php): initial schema for menus and menu items tables.
- Migration [2026_11_05_000000_add_kind_and_category_reference_to_menu_items_table.php](../../database/migrations/2026_11_05_000000_add_kind_and_category_reference_to_menu_items_table.php): added kind and category_public_id columns, RESTRICT foreign key, and CHECK constraints.
- [ARCHITECTURE.md §12 Decisions](../../ARCHITECTURE.md#12-decisions): D2 (code-owned keys), D4 (links by URL until catalog context exists), D9 (atomic tree reconciliation under row lock).
- [ADR 0011](../adr/0011-typed-menu-category-references-and-delete-guard.md): typed menu category references and foreign key delete guard (supersedes D4 for categories).
