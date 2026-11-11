---
kind: normative
area: catalog
status: active
summary: Shared catalog taxonomy, category hierarchy, deterministic slug generation, depth constraints, brand aggregate, and catalog mutation actions.
paths:
  - app/Domain/Catalog/**
  - database/factories/CategoryFactory.php
  - database/factories/BrandFactory.php
  - database/factories/ProductModelFactory.php
  - database/migrations/*categories*
  - database/migrations/*brands*
  - database/migrations/*product_models*
  - tests/Feature/Catalog/**
decisions: [D10, ADR-0010, ADR-0011]
depends-on: [data]
tests:
  - vendor/bin/pest tests/Feature/Catalog/ tests/Feature/Admin/Category/ tests/Feature/Admin/Brand/ tests/Arch/CatalogArchTest.php
triggers:
  - paths:
      - app/Domain/Content/**
    reconsider: >-
      Content modifications require checking one thing:
      (1) Does any Content file now store or compute a category name, slug or URL? (CT1)
  - paths:
      - app/Domain/Catalog/Models/Category.php
    reconsider: >-
      Category model modifications require checking two things:
      (1) Is effective-active still one expression used by both scopes and correct for any MAX_DEPTH? (CT6)
      (2) Is the advisory lock still taken only through CategorySiblingLock?
---

# Catalog

The `catalog` area owns all wholesale electronics marketplace taxonomy data, including category trees, brand aggregates, deterministic slug generation, and catalog mutation actions.

## How it works now

- **Two-level category hierarchy.** Categories use adjacency list storage via `parent_id`. Depth is strictly capped at `Category::MAX_DEPTH = 2` (root categories and subcategories).
- **Deterministic cosmetic slugs.** Slugs are automatically generated from category names via kebab-casing. Collisions across different branches are resolved with incremental numeric suffixes (`-2`, `-3`). Once generated, a category slug is immutable.
- **Sequential sort order.** Sort order is assigned per parent node under a PostgreSQL transaction advisory lock (`pg_advisory_xact_lock`), ensuring monotonic sequential allocation among siblings.
- **Case-insensitive sibling uniqueness.** Sibling categories sharing the same parent node must have distinct names (evaluated case-insensitively). The constraint is guarded in domain actions and enforced by a PostgreSQL unique expression index.
- **Mutation audit trails.** Persisted category creations and updates write structured activity log entries to the `catalog` log with causer stamping and changed attribute properties. Updates with no modified fields perform no database writes and write no activity log entries.
- **Context boundary isolation.** External contexts interact with Catalog solely through domain Actions and Data transfer objects, never querying Catalog models directly.
- **Category activation state transitions.** Category activation and deactivation execute solely through `ActivateCategory` and `DeactivateCategory`. Transitions are idempotent, serialized via row-level locks, and audited via `category.activated` and `category.deactivated` events. Deactivating a parent never modifies child rows in the database.
- **Single effective-active SQL definition.** Dynamic visibility is resolved at read time through a single recursive PostgreSQL expression (`Category::effectiveActiveRawSql()`). A category is effectively active if and only if itself and every ancestor has `is_active = true`. Both the `effectivelyActive()` query scope filter and the `withEffectiveActive()` computed attribute share this identical definition.
- **Hierarchical tree retrieval.** `GetCategoryTree` loads the full category taxonomy in a single database query and constructs nested `CategoryTreeNodeData` objects in memory. For `Storefront` audience, inactive nodes, nodes under inactive ancestors, and nodes with `show_in_nav = false` (along with their entire subtrees) are excluded without leaving orphaned child branches. For `Admin` audience, all nodes are returned with their resolved `is_effectively_active` flag.
- **Structural leaf options.** `ListCategoryOptions` produces a flat, depth-first list of effectively active structural leaf categories (`CategoryOptionData`) formatted with full breadcrumb path labels (`Parent > Child`), ready for product assignment.
- **Subtree moving.** `MoveCategory` reparents a category subtree under sibling advisory locks, preventing cycles, enforcing the `Category::MAX_DEPTH` constraint against the combined parent chain and subtree height, checking case-insensitive sibling uniqueness, and assigning the next sequential sort order under the destination parent.
- **Sibling reordering.** `ReorderCategories` serializes sibling sort order permutations under sibling advisory locks, verifying strict bijection against current siblings and updating only rows whose sort order modified.
- **Reference- and child-guarded deletion.** `DeleteCategory` allows hard deletion of childless, unreferenced categories only, protected by sibling advisory locks, target row locks, domain child existence checks, and PostgreSQL foreign key restriction translation to `CategoryRuleViolation::inUse`.
- **Batch category existence checks.** `GetExistingCategories` takes a list of public IDs and returns existing category public IDs as `CategoryExistenceData` in a single query.
- **Centralized sibling locking.** Concurrent mutations targeting siblings under any parent (including root) serialize on `CategorySiblingLock::acquire($parentId)`.
- **Category administration UI.** Categories are administered through Filament `CategoryResource` in the `admin` panel. The resource presents the taxonomy in hierarchical tree order, displays stored activation alongside computed effective-active visibility, and delegates all mutations (create, edit, activate, deactivate, move, reorder, delete) to domain actions without direct model writes. Access is authorized via `CategoryPolicy`.
- **Flat brand taxonomy**: Brands are stored as a flat list with unique ULID `public_id`, `name`, immutable cosmetic `slug`, activation toggle `is_active`, and storefront nav readiness flag `show_in_nav`.
- **Case-insensitive brand uniqueness**: Brand names are unique evaluated case-insensitively, guarded in domain Actions and enforced by PostgreSQL functional unique index `brands_lower_name_unique`.
- **Brand mutation actions**: Mutations execute strictly through domain Actions (`CreateBrand`, `UpdateBrand`, `ActivateBrand`, `DeactivateBrand`), recording structured audit trails to the `catalog` activity log.
- **Brand selection options**: `ListBrandOptions` returns active brands with search filtering, literal LIKE metacharacter escaping, clamped limits (default 20, max 50), and deterministic ordering (`lower(name) ASC, id ASC`) in a single query.
- **Brand administration UI**: Brands are administered through Filament `BrandResource` in the `admin` panel (`/admin/brands`). The resource renders active and inactive brands, provides case-insensitive name and slug search, filter toggles for `is_active` and `show_in_nav`, form creation and editing delegating strictly to `CreateBrand` and `UpdateBrand` without editable slugs or status fields, and dedicated row and header actions for `Activate` and `Deactivate`. Delete actions are permanently omitted (CT4). Access is authorized via `BrandPolicy`.
- **Product model aggregate**: Product models are stored with unique ULID `public_id`, `brand_id`, `category_id`, `name`, immutable cosmetic `slug`, and `is_active` toggle. Each model belongs to exactly one brand and one leaf category, both fixed at creation.
- **Case-insensitive model uniqueness**: Model names are unique per brand evaluated case-insensitively, guarded in domain Actions and enforced by PostgreSQL functional unique index `product_models_brand_lower_name_unique`. The same model name under different brands is permitted.
- **Model mutation actions**: Mutations execute strictly through domain Actions (`CreateProductModel`, `UpdateProductModel`, `ActivateProductModel`, `DeactivateProductModel`), recording structured audit trails to the `catalog` activity log.
- **Model selection options**: `ListModelOptions` returns active models for a specified active brand and effectively active leaf category with search filtering, literal LIKE metacharacter escaping, clamped limits (default 20, max 50), and deterministic ordering (`lower(name) ASC, id ASC`) in a single database query.
- **Category model reference guards**: Categories referenced by product models cannot be deleted (`DeleteCategory` throws `CategoryRuleViolation::inUse`), cannot gain child categories (`CreateCategory` throws `CategoryRuleViolation::parentHasModels`), and cannot become destination parents for moved categories (`MoveCategory` throws `CategoryRuleViolation::newParentHasModels`).

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | Depth cap is the single constant `Category::MAX_DEPTH = 2`. | Prevents deep recursive queries and maintains layout predictability. | `tests/Feature/Catalog/CreateCategoryTest.php and tests/Feature/Catalog/MoveCategoryTest.php` |
| 2 | Taxonomy slug is immutable once created and globally unique. | Prevents link rot and guarantees deterministic URL routing (CT3). | `tests/Feature/Catalog/UpdateCategoryTest.php`, `tests/Feature/Catalog/CreateCategoryTest.php`, `tests/Feature/Catalog/BrandSchemaTest.php`, `tests/Feature/Catalog/ProductModelSchemaTest.php`, `tests/Feature/Catalog/CreateProductModelTest.php`, and `tests/Feature/Catalog/UpdateProductModelTest.php` |
| 3 | Sibling category name is unique ignoring case. | Prevents duplicate category labels under the same parent node. | `tests/Feature/Catalog/CreateCategoryTest.php` |
| 4 | Brand and model names are unique within scope ignoring case; options list active records only. | Prevents duplicate taxonomy labels and guarantees inactive records never appear in options. | `tests/Feature/Catalog/CreateBrandTest.php`, `tests/Feature/Catalog/BrandSchemaTest.php`, `tests/Feature/Catalog/ListBrandOptionsTest.php`, `tests/Feature/Catalog/CreateProductModelTest.php`, `tests/Feature/Catalog/ProductModelSchemaTest.php`, and `tests/Feature/Catalog/ListModelOptionsTest.php` |
| 5 | Actions are the sole write path for catalog mutations. | Centralizes validation, slug generation, advisory locks, and audit trails (S1, C5). | `tests/Arch/FilamentWritesTest.php`, `tests/Feature/Admin/Category/CategoryResourceTest.php`, and `tests/Feature/Admin/Brand/BrandResourceTest.php` |
| 6 | Cross-context isolation, and a referenced category cannot be hard-deleted or gain children. | External contexts touch Catalog only through Actions and Data (CT5). A referenced category is not hard-deleted and cannot receive child categories (CT2, CT4). | `tests/Arch/CatalogArchTest.php`, `tests/Feature/Catalog/DeleteCategoryTest.php`, and `tests/Feature/Catalog/CategoryModelGuardsTest.php` |
| 7 | `is_active` changes only through aggregate Activate and Deactivate actions. | Centralizes activation state transitions, timestamps, and audit logging (C4, CT4). | `tests/Feature/Catalog/CategoryStateActionsTest.php`, `tests/Feature/Catalog/BrandStateActionsTest.php`, `tests/Feature/Admin/Brand/BrandResourceTest.php`, and `tests/Feature/Catalog/ProductModelStateActionsTest.php` |
| 8 | Effective-active has one SQL definition used for both filtering and the computed flag. | Guarantees parity between query filters and tree visibility flags across any depth (CT6). | `tests/Feature/Catalog/CategoryEffectiveActiveTest.php` and `tests/Feature/Catalog/ListModelOptionsTest.php` |

## Changing it

- **Creating categories**: Invoke `CreateCategory` with validated `CreateCategoryData` and an optional authenticated causer user.
- **Updating category details**: Invoke `UpdateCategory` with a `Category` instance or public ID ULID string, `UpdateCategoryData`, and an optional causer.
- **Activating categories**: Invoke `ActivateCategory` with a public ID ULID string and an optional causer.
- **Deactivating categories**: Invoke `DeactivateCategory` with a public ID ULID string and an optional causer.
- **Retrieving category trees**: Invoke `GetCategoryTree` passing `CategoryTreeAudience::Admin` or `CategoryTreeAudience::Storefront`.
- **Listing product assignable category options**: Invoke `ListCategoryOptions` to retrieve effectively active leaf options.
- **Checking category existence**: Invoke `GetExistingCategories` with an array of category public ID ULIDs.
- **Moving categories**: Invoke `MoveCategory` with a public ID ULID string, `MoveCategoryData`, and an optional causer.
- **Reordering sibling categories**: Invoke `ReorderCategories` with `ReorderCategoriesData` and an optional causer.
- **Deleting categories**: Invoke `DeleteCategory` with a public ID ULID string and an optional causer.
- **Creating brands**: Invoke `CreateBrand` with validated `CreateBrandData` and an optional authenticated causer user.
- **Updating brand details**: Invoke `UpdateBrand` with a `Brand` instance or public ID ULID string, `UpdateBrandData`, and an optional causer.
- **Activating brands**: Invoke `ActivateBrand` with a public ID ULID string and an optional causer.
- **Deactivating brands**: Invoke `DeactivateBrand` with a public ID ULID string and an optional causer.
- **Listing brand options**: Invoke `ListBrandOptions` with optional search string and limit.
- **Creating product models**: Invoke `CreateProductModel` with validated `CreateProductModelData` and an optional causer.
- **Updating product model details**: Invoke `UpdateProductModel` with a `ProductModel` instance or public ID ULID string, `UpdateProductModelData`, and an optional causer.
- **Activating product models**: Invoke `ActivateProductModel` with a public ID ULID string and an optional causer.
- **Deactivating product models**: Invoke `DeactivateProductModel` with a public ID ULID string and an optional causer.
- **Listing product model options**: Invoke `ListModelOptions` with brand public ID, category public ID, optional search string, and limit.
- **Administering categories via Filament**: Access `/admin/categories`. Form inputs create and edit categories via `CreateCategory` and `UpdateCategory`. Sibling movement, reordering, activation transitions, and deletion execute through dedicated table and header actions.
- **Administering brands via Filament**: Access `/admin/brands`. Form inputs create and edit brands via `CreateBrand` and `UpdateBrand`. Activation state transitions execute through dedicated table row and header actions without direct model writes. Access is governed by `BrandPolicy`.
- **Running tests**: Run `vendor/bin/pest tests/Feature/Catalog/ tests/Feature/Admin/Category/ tests/Feature/Admin/Brand/ tests/Arch/CatalogArchTest.php`.

## Running and testing

Clear the configuration cache and run the targeted Pest tests:

```bash
php artisan config:clear
vendor/bin/pest tests/Feature/Catalog/ tests/Feature/Admin/Category/ tests/Feature/Admin/Brand/ tests/Arch/CatalogArchTest.php
```

## Known limits and open questions

- External foreign key constraints guard against hard deletion of categories in use; categories cannot be deleted while referenced by external records such as navigation menu items (CT4).
- Category read queries and trees do not support pagination.
- Category options cover product assignment only.
- Category records do not yet store images, icons, or extended descriptions.
- Brand records do not have a delete action; brands are deactivated rather than removed (OD-1).
- No request or proposal flow exists for missing brands; superadmins create brands directly.
- The `show_in_nav` flag is stored and editable, but no navigation consumer reads it yet.
- Product models are fixed to their brand and leaf category at creation; models cannot be moved to another brand or category.
- Product models do not have a delete action; models are deactivated rather than removed (CT4).
- No proposal or custom model request flow exists; superadmins create models directly.
- No products currently reference product models yet.

## History

- ADR 0010: Established Catalog context, taxonomy source of truth, and superseded Decision D4.
- ADR 0011: Typed menu category references and foreign key delete guard.
- Migration `2026_11_02_000000_create_categories_table.php` provisioned the `categories` table with PostgreSQL check constraints and unique indexes.
- Migration `2026_11_10_000000_create_brands_table.php` provisioned the `brands` table with PostgreSQL check constraints and functional unique index.
- Migration `2026_11_10_000001_create_product_models_table.php` provisioned the `product_models` table with foreign keys, PostgreSQL check constraints, and functional unique index.
- Brand administration UI: Introduced `BrandResource` and `BrandPolicy` in the admin panel, delegating brand mutations strictly to domain actions.
