---
kind: normative
area: catalog
status: active
summary: Shared catalog taxonomy, category hierarchy, deterministic slug generation, depth constraints, and catalog mutation actions.
paths:
  - app/Domain/Catalog/**
  - database/factories/CategoryFactory.php
  - database/migrations/*categories*
  - tests/Feature/Catalog/**
decisions: [D10, ADR-0010]
depends-on: [data]
tests:
  - vendor/bin/pest tests/Feature/Catalog/CreateCategoryTest.php tests/Feature/Catalog/UpdateCategoryTest.php tests/Arch/CatalogArchTest.php
triggers:
  - paths:
      - app/Domain/Content/**
    reconsider: >-
      Content modifications require checking one thing:
      (1) Does any Content file now store or compute a category name, slug or URL? (CT1)
---

# Catalog

The `catalog` area owns all wholesale electronics marketplace taxonomy data, including category trees, deterministic slug generation, and catalog mutation actions.

## How it works now

- **Two-level category hierarchy.** Categories use adjacency list storage via `parent_id`. Depth is strictly capped at `Category::MAX_DEPTH = 2` (root categories and subcategories).
- **Deterministic cosmetic slugs.** Slugs are automatically generated from category names via kebab-casing. Collisions across different branches are resolved with incremental numeric suffixes (`-2`, `-3`). Once generated, a category slug is immutable.
- **Sequential sort order.** Sort order is assigned per parent node under a PostgreSQL transaction advisory lock (`pg_advisory_xact_lock`), ensuring monotonic sequential allocation among siblings.
- **Case-insensitive sibling uniqueness.** Sibling categories sharing the same parent node must have distinct names (evaluated case-insensitively). The constraint is guarded in domain actions and enforced by a PostgreSQL unique expression index.
- **Mutation audit trails.** Persisted category creations and updates write structured activity log entries to the `catalog` log with causer stamping and changed attribute properties. Updates with no modified fields perform no database writes and write no activity log entries.
- **Context boundary isolation.** External contexts interact with Catalog solely through domain Actions and Data transfer objects, never querying Catalog models directly.

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | Depth cap is the single constant `Category::MAX_DEPTH = 2`. | Prevents deep recursive queries and maintains layout predictability. | `tests/Feature/Catalog/CreateCategoryTest.php` |
| 2 | Category slug is immutable once created. | Prevents link rot across storefront and external consumers (CT3). | `tests/Feature/Catalog/UpdateCategoryTest.php` |
| 3 | Category slug is globally unique. | Guarantees deterministic URL routing. | `tests/Feature/Catalog/CreateCategoryTest.php` |
| 4 | Sibling category name is unique ignoring case. | Prevents duplicate category labels under the same parent node. | `tests/Feature/Catalog/CreateCategoryTest.php` |
| 5 | Actions are the sole write path for catalog mutations. | Centralizes validation, slug generation, advisory locks, and audit trails (S1, C5). | `tests/Arch/FilamentWritesTest.php` |
| 6 | Cross-context isolation: no external direct access to Catalog models. | Enforces boundary isolation; external contexts interact only via Actions and Data (CT5). | `tests/Arch/CatalogArchTest.php` |

## Changing it

- **Creating categories**: Invoke `CreateCategory` with validated `CreateCategoryData` and an optional authenticated causer user.
- **Updating category details**: Invoke `UpdateCategory` with a `Category` instance or public ID ULID string, `UpdateCategoryData`, and an optional causer.
- **Running tests**: Run `vendor/bin/pest tests/Feature/Catalog/ tests/Arch/CatalogArchTest.php`.

## Running and testing

Clear the configuration cache and run the targeted Pest tests:

```bash
php artisan config:clear
vendor/bin/pest tests/Feature/Catalog/CreateCategoryTest.php tests/Feature/Catalog/UpdateCategoryTest.php tests/Arch/CatalogArchTest.php
```

## Known limits and open questions

- Category read queries, storefront visibility trees, category deactivation, moving, and reordering actions are not yet implemented.
- Filament administration resources for categories are not yet implemented.
- Category records do not yet store images, icons, or extended descriptions.

## History

- ADR 0010: Established Catalog context, taxonomy source of truth, and superseded Decision D4.
- Initial migration `2026_11_02_000000_create_categories_table.php` provisioned the `categories` table with PostgreSQL check constraints and unique indexes.
