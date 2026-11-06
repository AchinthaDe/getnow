# 0011: Typed Menu Category References and Foreign Key Delete Guard

Date: 2026-11-06

## Status
Accepted (supersedes D4)

## Context
Menus in the Content context require linking to Catalog taxonomy categories without hardcoding volatile URLs. In accordance with rules CT1, CT4, CT5, and DB4:
1. Catalog owns all taxonomy data. Content must not store category names, slugs, or URLs.
2. Context boundaries must remain isolated: Content cannot import Catalog models or query the `categories` table directly.
3. Referenced categories must never be hard-deleted (CT4).
4. Referential integrity must be enforced at the database layer (DB4).

## Decision
1. **Typed Menu Items**:
   - `menu_items` introduces `kind` (`link`, `category`, `category_tree`) backed by enum `MenuItemKind`.
   - `category_public_id` column stores a foreign key to `categories.public_id` with `ON DELETE RESTRICT`.
   - Database CHECK constraints enforce kind values and shape validity per kind.
2. **Context Boundary Isolation**:
   - A cross-context foreign key from `menu_items.category_public_id` to `categories.public_id` is permitted because Content stores only the durable `public_id` ULID and never queries Catalog models or tables directly.
   - Category existence validation executes through a single batched Catalog read Action `GetExistingCategories` returning `CategoryExistenceData` DTOs.
3. **Foreign Key Delete Guard Translation**:
   - `DeleteCategory` executes its deletion statement under advisory and row locks.
   - When PostgreSQL raises a foreign key restriction violation (SQLSTATE 23503 or 23001), `DeleteCategory` inspects the constraint name. Internal self-referencing child violations (`categories_parent_id_foreign`) raise `CategoryRuleViolation::hasChildren`, while all external referencing violations raise `CategoryRuleViolation::inUse`.
   - Catalog contains no code mentioning Content, menus, or `menu_items`, allowing future referencers (such as products, brands, or collections) to reuse the identical delete guard.
4. **Deviations and Realizations from Phase Brief §2.2**:
   - Optional label override on `category` items: Phase brief §2.2 stated "label and URL come from that record". We add an optional label override (`label` nullable, varchar 80) on `category` items to allow storefront navigation display copy to be customized (e.g. "Shop Smartphones") without altering catalog taxonomy names.
   - Optional subtree root reference on `category_tree` items: Phase brief §2.2 described Source items as expanding to "the active category tree". We add an optional `category_public_id` reference on `category_tree` items to allow mega-menus to root hierarchical expansion at a specific category subtree rather than expanding only the entire catalog tree.

## Consequences
- Referential integrity is guaranteed by PostgreSQL constraint.
- Zero taxonomy data is duplicated in Content.
- Catalog and Content contexts communicate strictly via Actions and Data transfer objects.
- Storefront layout contract and API response remain unaffected until read-time resolution in P3-NAV1b.

