# 0012: Read-Time Navigation Category Resolution and Revision Invalidation

Date: 2026-11-09

## Status
Accepted

## Context
Menus in the Content context must serve typed category items and whole-tree category expansions to storefront consumers without storing category names, slugs, or URLs (CT1), without querying Catalog models directly (CT5), ensuring Catalog stays unaware of menus (CT5), and ensuring HTTP ETags bust when taxonomy changes alter layout output (K7, K8).

Specific requirements:
1. Catalog owns all taxonomy data. Content must not store category names, slugs, or URLs in its database tables.
2. Context boundaries must remain isolated: Content cannot import Catalog models or query the `categories` table directly, interacting strictly through Catalog read Actions and DTOs.
3. Read performance must remain bounded: resolving multiple category items across navigation menus must not issue N+1 queries.
4. HTTP conditional caching (`If-None-Match`) and deterministic revision hashing (`meta.revision`) must bust when category renames, deactivations, or tree changes alter served layout JSON, without requiring write-time fan-out across menu item records.
5. Inactive or non-nav taxonomy nodes must be omitted fail-closed without failing sibling items or throwing server errors.

## Decision
1. **Read-Time Resolution in Layout Action**:
   - `GetLayoutContent` inspects servable menu items in memory. If any servable `category` or `category_tree` items exist, it executes `GetCategoryTree(CategoryTreeAudience::Storefront)` exactly once.
   - If zero servable typed items exist, `GetCategoryTree` is not executed, issuing zero category queries for link-only menus.
   - In-memory projection is delegated to `ProjectMenuLayoutItems`.
2. **Deterministic Category URLs and Label Overrides**:
   - Category URLs are derived dynamically as `/shop?category=` + `rawurlencode($slug)`. Slugs remain immutable in Catalog (CT3).
   - If `menu_items.label` is null, the item adopts the category's taxonomy `name`. If `menu_items.label` is not null (including an empty string `""`), the stored label override is preserved.
3. **Category Tree Source Expansion**:
   - Level-1 `category_tree` items with null `category_public_id` expand in place into all storefront root categories and their subcategories.
   - Level-1 `category_tree` items referencing a category expand in place into direct children of that target category.
   - Expanded category nodes emit category public IDs, taxonomy names, derived URLs, null icons, false `new_tab`, and ordered children.
4. **Fail-Closed Taxonomy Omission**:
   - Menu items referencing unresolvable taxonomy targets (inactive, under inactive ancestors, `show_in_nav = false`, or absent) are omitted from layout output along with their descendants.
   - Database state is untouched: rows retain their stored values without destructive cleanup on read.
5. **Canonical Revision Hashing and ETag Busting**:
   - `meta.revision` is computed from the SHA-256 hash of the canonical layout data structure containing the projected menu output, with request timestamps strictly excluded.
   - When taxonomy mutations alter served category names or visibility, the canonical JSON changes, automatically busting the revision hash and ETag without altering `menu_items.updated_at`.
6. **Strict Catalog Boundary Isolation**:
   - Content domain consumes only `GetCategoryTree` Action and `CategoryTreeNodeData` DTO. Catalog remains completely unaware of Content, menus, or menu item kinds.

## Consequences
- Cross-context isolation between Content and Catalog is preserved with zero domain dependency leaks.
- Zero taxonomy data is duplicated or persisted in Content tables.
- Bounded single-query performance: at most one batched taxonomy query regardless of category reference count.
- Storefront consumers receive deterministic, valid URLs and accurate category labels with automatic HTTP ETag cache invalidation.

