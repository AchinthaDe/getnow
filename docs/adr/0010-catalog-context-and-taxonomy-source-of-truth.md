# 0010: Catalog Context and Taxonomy Source of Truth

Date: 2026-11-02

## Status
Accepted (supersedes D4)

## Context
Phase 1 established the Content context for layout CMS capabilities. Phase 3 introduced Accounts for authentication, users, and tokens. Phase 3 now begins the wholesale electronics catalog features, starting with taxonomy.

Decision D4 originally stated:
> "Category data stays with the catalog source. Menus link by URL until a Catalog context exists."

With the initialization of the Catalog bounded context, taxonomy ownership, navigation integration, and domain scalability boundaries must be formalized.

## Decision
1. **Catalog Bounded Context**:
   - Resides under `App\Domain\Catalog/`.
   - Organizes code by aggregate subfolders: `Actions/<Aggregate>/` (one invokable class per use case) and `Data/<Aggregate>/` (Spatie Data transfer objects), alongside `Exceptions/` and `Models/` flat under `App\Domain\Catalog`.
   - Follows the invokable Action pattern established in ADR 0006.

2. **Taxonomy as Single Source of Truth (CT1, CT5)**:
   - Catalog owns all taxonomy data (categories, brands, collections, option values).
   - No other context stores category names, slugs, or URLs.
   - External contexts touch Catalog only through `App\Domain\Catalog\Actions` and `Data`, never its models directly.

3. **Menus as Typed Layout Layer**:
   - Navigation menus in the Content context will link to taxonomy via typed items (Link, Reference, Source).
   - The menu schema migration is a future step.

4. **Scalability Principles**:
   - **SCL1**: Category tree depth is strictly capped at a single constant `Category::MAX_DEPTH = 2`.
   - **SCL3**: Read-time storefront visibility resolution (via a single `VisibleProducts` query definition) rather than writing status flags across many rows.
   - **SCL5**: Hot-write data (stock) lives in its own table, separate from reviewed catalog content.
   - **SCL14**: Clear context boundary between Catalog (taxonomy, products, reviews, variants, media) and Retail (retailers, membership, offers).
   - **SCL17**: Single-language names with deterministic cosmetic slug generation from name and numeric suffix collision resolution.

5. **Supersedes Decision D4**:
   - Decision D4 is superseded: category data is owned by the Catalog context, and menus link via typed references or URLs.

## Consequences
- Categories and taxonomy live under `App\Domain\Catalog/`.
- Content context remains presentation-focused.
- Architecture tests in `tests/Arch/` enforce context boundaries and ensure external contexts do not query Catalog models directly.
