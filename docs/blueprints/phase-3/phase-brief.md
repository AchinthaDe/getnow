[P3 | PHASE BRIEF v2 | Claude]

# P3 Catalog: taxonomy, retailer products, review workflow

Status: proposed. Not active until P3-CAT1 passes G0 (section 10, question 1).
v2 adds: typed menu items, brands and collections as data, and the scalability review in section 3.

## 1. Goal

Superadmins manage the shared vocabulary of the marketplace: categories, brands and collections. Retailers list products against that vocabulary from their own dashboard. Superadmins review every product: approve it, or send it back with a comment on each field that needs work. The storefront nav, every admin dropdown and every product reference the same rows. No second copy of any taxonomy data exists anywhere.

## 2. The pattern

### 2.1 One owner, many readers

Anything a product is classified by, filtered by or linked to in the nav is a row, not a string. Free text is only for things unique to one product (name, description).

| Taxonomy | Shape | Chosen by | Notes |
| --- | --- | --- | --- |
| Category | Tree, max depth 2 (one constant, SCL1) | Retailer, on the product (leaf only) | Parent is derived, never stored on the product |
| Brand | Flat list | Retailer, on the product. Select-only, searchable | No free text. A missing brand is requested from the superadmin |
| Collection | Curated group of products (Deals, New arrivals, Featured) | Superadmin | Not chosen by retailers. Many-to-many with products |
| Variant option values | Controlled rows (colour, storage, RAM) | Retailer, on a variant | Stops "128GB" and "128 GB" splitting filters |

A promotion (discount rule with a time window) is not a collection. It belongs with pricing and sales and is out of this phase.

Storage: categories use an adjacency list (`parent_id`). At depth 2 nested sets, closure tables and `ltree` add nothing.

### 2.2 Menus become a typed layout layer

Menus stay, because footer columns, mobile nav, About and Contact, and choosing which categories appear in the header cannot come from taxonomy data. What changes is that a menu item stops holding taxonomy knowledge. Each item is one of three kinds:

| Kind | Behavior |
| --- | --- |
| Link | Hand-typed URL or route (K4 rules). For real non-data destinations and external sites |
| Reference | Points at one category, brand or collection. Label and URL come from that record, label can be overridden. Hidden from the API automatically while the target is not effectively active |
| Source | Expands at read time into many items: the active category tree, or brands flagged for the nav (capped) |

Schema: one nullable foreign key per reference type on `menu_items`, plus a CHECK that exactly the column matching the kind is set. Foreign keys stay (DB4). Existing rows remain Links, so the change is additive (DB3). The Content API item shape is unchanged, so the storefront does not change. A Source item may only sit at the top level of a menu, because expansion at depth 2 would produce depth 3.

Dependency runs one way: Content reads Catalog through Actions (CT5). Catalog never knows menus exist. Deleting a referenced taxonomy row is blocked by the foreign key and surfaced as a rule violation (CT4).

### 2.3 Rules added to AGENTS.md in P3-CAT1

| ID | Rule | Enforced from |
| --- | --- | --- |
| CT1 | Catalog owns all taxonomy data (categories, brands, collections, option values). No other context stores their names, slugs or URLs | CAT1 (arch test + review) |
| CT2 | A product references exactly one leaf category. A category with products cannot gain children. A category with children cannot receive products | PRD1a |
| CT3 | A category slug is immutable. Renaming changes the name only | CAT1 |
| CT4 | A referenced taxonomy row is never hard-deleted. It is deactivated through an Action | CAT2 |
| CT5 | Contexts touch Catalog only through `App\Domain\Catalog\Actions` and `Data`, never its models | CAT1 (arch test) |
| CT6 | What a storefront may see is computed at read time by one query definition. Never by writing flags across many rows | PRD1b |
| CT7 | Hot-write data (stock) lives in its own table, apart from reviewed content | VAR1b |

## 3. Scalability review

### 3.1 Decided now (cheap now, expensive to retrofit)

| ID | Decision | Why it matters later | Lands in |
| --- | --- | --- | --- |
| SCL1 | Depth cap is one constant. The tree loads in one query and is built in memory. Subtree resolution is one Action. Nothing else assumes depth 2 | Going to 3 levels is a one-line change, not a rewrite of queries, nav and forms | CAT1, CAT2 |
| SCL2 | A category is effectively active only if it and all its ancestors are active. Computed at read time | Deactivating a parent hides its subtree without rewriting rows | CAT2 |
| SCL3 | Visibility (approved, listed, not archived, retailer active, category effectively active) is one `VisibleProducts` definition used by the API, counts and lists | Suspending a retailer with 50,000 products is one row update, not 50,000 | PRD1b, API1a |
| SCL4 | Leaf rule both ways (CT2), plus a bulk `MoveProducts` Action | Adding a subcategory to a category that already holds products would otherwise strand them in a non-leaf | PRD1a, CAT2 |
| SCL5 | Stock lives in `variant_stock`, not on the product or variant row. Stock writes never touch the product's `updated_at`, `row_version` or review locks | Stock is the hottest write. It must not contend with review locks or bump the ETag revision | VAR1b |
| SCL6 | Every review round stores a `snapshot` (jsonb) of the reviewed fields at decision time | Gives reviewers a "changed since last approval" diff, audit evidence, and a path to revisions later with no schema change | PRD2a |
| SCL7 | Products carry `row_version`. Edits submit the version they loaded. A mismatch fails with a reload message | Retailer teams and two open tabs. Last-write-wins is wrong once a business has several users | PRD1a |
| SCL8 | Index plan: retailer-leading composite indexes. Partial unique `(retailer_id, sku)` where not archived. Partial index for visible products. Queue index `(review_status, submitted_at)`. Public API uses keyset pagination, never offset. PostgreSQL full-text or trigram search before any search engine | List and queue pages stay fast at 100,000 rows. A retailer can reuse the SKU of an archived product | PRD1a, API1a/b |
| SCL9 | Brand select uses server-side search (`ListBrandOptions(search, limit)`), not a preloaded list. Variant options are rows | Brand lists grow to hundreds. Free text fragments filters | BRD1, VAR1a |
| SCL10 | Public product URL is keyed by `public_id`. The slug is cosmetic and a stale slug redirects. `ListPublicProducts` returns Data objects | Renaming a product never breaks a link. A flattened read model can be added later without a contract change | API1a |
| SCL11 | Domain events dispatch after commit. Listeners are queued. Queue connection is `database` (built into Laravel, no package, ARCHITECTURE section 3 updated) | Notifications and image conversions must not run inside a request or before a transaction commits | PRD2b |
| SCL12 | `Model::shouldBeStrict()` outside production, plus a query-count test helper. List pages, nav resolution and the public API each get a test that query count does not grow with row count | N+1 queries fail the build instead of surfacing in production | QA1 |
| SCL13 | Menu references resolve in batch: one query per target type. Source expansion is capped by `show_in_nav` and a config maximum | A nav with 300 brands or 40 reference items must not cost 340 queries | NAV1b, NAV2 |
| SCL14 | Two contexts. `Catalog`: taxonomy, products, reviews, variants, media. `Retail`: retailers, membership, suspension. Inside a context, Actions and Data are subfolders per aggregate (`Actions/Category/`, `Actions/Product/`) | One flat Actions folder with 60 classes is unmaintainable. Products stay in Catalog so they can join taxonomy without breaking CT5 | CAT1, RET1a |
| SCL15 | Retailer membership table, one retailer per user for now (unique `user_id`), relaxable later. Every write stamps `created_by` and `updated_by` | Staff accounts and audit trails without a later migration | RET1a |
| SCL16 | Bulk admin operations run one transaction per item and return per-item results | One locked row or failure must not fail 200 approvals | ADM1 |
| SCL17 | Names are single-language text. Slug derives from the name only. Translations arrive later as an additive jsonb column and an additive API field | Keeps Sinhala and Tamil possible without breaking slugs or unique indexes (question 8) | CAT1 |
| SCL18 | A dropdown always lists the record's current value, even if it became inactive. Saving an unchanged value passes validation | Otherwise deactivating a brand blocks every edit of its products | RET2, ADM1 |

### 3.2 Seams only (designed for, not built)

- Trusted-retailer post-moderation: `SubmitProduct` is the only place that decides pending versus approved.
- Reviewer claim or assignment, per-retailer cap on pending products.
- Flattened read model for the public API.
- Price lists per buyer group, multi-warehouse stock.
- CSV import: Actions never depend on a Filament request, so an importer can call them.

### 3.3 Deferred without design

Search engine, CDN and cache-purge webhooks, activity log pruning, rule-based collections, per-category specification templates.

## 4. Product lifecycle

Two separate fields, both changed only through Actions (C4): `review_status` (platform decision) and `is_listed` (retailer switch). Deletion is a soft archive.

| review_status | Meaning | Retailer can edit |
| --- | --- | --- |
| draft | Being written, or withdrawn | Yes |
| pending_review | Submitted, waiting for a superadmin | No (locked) |
| changes_requested | Superadmin flagged fields | Yes |
| approved | Passed review | Yes, see question 3 |
| rejected | Policy failure, final | No. Retailer may archive it |

Actions: `CreateProduct`, `UpdateProduct`, `SubmitProduct` (draft or changes_requested to pending_review), `WithdrawProduct` (pending_review to draft), `ApproveProduct`, `RequestProductChanges`, `RejectProduct`, `ListProduct`, `UnlistProduct`, `ArchiveProduct`, `RestoreProduct` (superadmin), `MoveProducts` (superadmin, bulk).

Locking the product while pending removes the race where a retailer edits under the reviewer. Every transition runs under `lockForUpdate` with an expected-status check (D9, ADR 0003), so a double approve fails cleanly.

## 5. Review comments

- `product_reviews`: one row per round. Product, reviewer, decision, optional summary comment, `snapshot` jsonb (SCL6), timestamp. Immutable.
- `product_review_flags`: one row per flagged field. Round, `field_key`, comment (max 500), status `open`, `addressed` or `resolved`, optional retailer reply (max 500).
- `field_key` comes from a code-defined `ReviewableField` enum (name, description, brand, category, images, specifications, pricing, general).
- Retailer view: each flagged field shows the comment beneath it. A flag becomes `addressed` when the retailer saves a change to that field, or marks it addressed with a reply. `SubmitProduct` is blocked while any flag is `open`.
- Superadmin next round: addressed flags show for checking, with the diff against the last approved snapshot. Approval resolves all remaining flags. Requesting changes again opens new flags in a new round. History is never deleted.
- Retailer is notified of each decision by a queued Filament database notification. Email is out of scope.

## 6. Access model

- Two actors: platform admin (`is_platform_admin`) and retailer (membership of a `retailers` row). Retailers cannot self-register. A superadmin creates them.
- Retailer panel is a second Filament panel at `/retailer`. `canAccessPanel` checks membership and retailer status (SEC4).
- Ownership: every retailer-facing Action takes the retailer and filters by it, and a Policy checks it. Filament scoping alone is not trusted. Every retailer step has a cross-retailer test (retailer A reads, edits and archives retailer B's product: all denied).
- Admin teams with split permissions are deferred. Policies check named abilities so a permission package can be added later.

## 7. Product-system items beyond the original request

| Item | Step |
| --- | --- |
| Brands | BRD1a, BRD1b |
| Variants (colour, storage), SKU, price on the variant | VAR1a |
| Wholesale pricing: MOQ, quantity price breaks, currency, integer minor units (no money package, X1) | VAR1b |
| Stock per variant | VAR1b |
| Images (`spatie/laravel-medialibrary`, approved early by ADR, replaces D5 for products). Caps on count, size and type. Approval needs at least 1 image | MED1a, MED1b |
| Condition (new, refurbished, used) and warranty months | PRD1a |
| Soft archive, no hard delete once approved (DB3) | PRD1a |
| Retailer suspension hides all of a retailer's products (via SCL3) | RET1a |
| Reviewer queue, oldest first, count badge | ADM1 |
| Collections, with menu reference | COL1 |
| Public read API for approved products, contract doc and fixture | API1a, API1b |

## 8. Step list and order

Each step is one layer and ends with `composer check` green. Sizes are code and test files only. Docs are extra.

| Step | Layer | Scope | Size |
| --- | --- | --- | --- |
| P3-QA1 | quality | Strict models outside production, query-count test helper (SCL12) | ~4 |
| P3-CAT1 | domain | Catalog context, `categories` table, CreateCategory, UpdateCategory, ADR, rules CT1 to CT7, guide | ~11 |
| P3-CAT2 | domain | Activate, Deactivate, Delete, Move, Reorder, `GetCategoryTree`, `ListCategoryOptions`, subtree and effective-active resolution | ~11 |
| P3-CAT3 | admin UI | Category resource (tree, reorder), CategoryPolicy | ~8 |
| P3-NAV1a | domain | Typed menu items: migration, kinds, validation, category reference and `category_tree` source | ~10 |
| P3-NAV1b | API | Batched resolution in `GetLayoutContent`, revision includes taxonomy state, contract doc and fixture | ~8 |
| P3-NAV1c | admin UI | Menu form: kind select, category picker, inactive-target warnings | ~8 |
| P3-BRD1a | domain | Brands, `ListBrandOptions` with search, show_in_nav | ~9 |
| P3-BRD1b | admin UI | Brand resource | ~7 |
| P3-NAV2 | domain + API | Brand reference and brand source in menus | ~8 |
| P3-RET1a | domain | Retail context: retailers, membership, status, suspension. Needs question 1 | ~10 |
| P3-RET1b | UI | Retailer panel shell, superadmin retailer management | ~9 |
| P3-PRD1a | domain | Product core, create, update, archive, `row_version`, ownership scoping, CT2 guard, indexes | ~11 |
| P3-PRD1b | domain | Submit, withdraw, list, unlist, state machine, `VisibleProducts` | ~9 |
| P3-PRD2a | domain | Review rounds, snapshots, approve, request changes, reject | ~10 |
| P3-PRD2b | domain | Flags, retailer replies, events after commit, queued notifications, database queue | ~10 |
| P3-RET2 | retailer UI | Product CRUD with cascading category select, flagged-field display, submit | ~10 |
| P3-ADM1 | admin UI | Review queue, snapshot diff, flag editor, approve, admin create and edit, bulk actions | ~11 |
| P3-VAR1a | domain | Variants, option vocabulary | ~10 |
| P3-VAR1b | domain | Price breaks, `variant_stock` | ~9 |
| P3-VAR1c | UI | Variant, price and stock forms in both panels | ~9 |
| P3-MED1a | domain | Medialibrary ADR, product images, caps, conversions queued | ~8 |
| P3-MED1b | UI | Image upload in both panels | ~6 |
| P3-COL1 | domain + UI | Collections, product membership, menu reference | ~11 (split if over) |
| P3-API1a | HTTP | Public listing and detail, keyset pagination, `VisibleProducts` | ~9 |
| P3-API1b | HTTP | Filters, brand and category facets, text search | ~9 |
| P3-CLOSE | review | Exit review | none |

P3-QA1 to P3-NAV1c need neither retailers nor accounts and can start now. Everything from P3-RET1a depends on question 1.

## 9. Current docs this phase contradicts (resolved by the ADR in P3-CAT1 unless noted)

| Where | Says now | Change |
| --- | --- | --- |
| ARCHITECTURE D4 | Category data stays with the catalog source, menus link by URL | Superseded |
| ARCHITECTURE section 13 | Catalog = brands, models, variants, categories. Retail = offers, inventory | Catalog = taxonomy, products, reviews, variants, media. Retail = retailers, membership. Shared model catalog dropped (question 4) |
| ARCHITECTURE phases table, AGENTS "Active scope" | Phase 0 and 1 only | Add Phase 3 as active |
| AGENTS S1 | Actions live in `app/Domain/Content/Actions/` | `app/Domain/<Context>/Actions/`, with per-aggregate subfolders allowed |
| AGENTS S6, D6 | One panel under `app/Filament/Admin/`, one admin flag | Changed in RET1b, not before |
| ARCHITECTURE section 6 mapping table | Category dropdown stays driven by the storefront's catalog source | Updated in NAV1b |
| ARCHITECTURE section 3 stack | Queue not listed, medialibrary planned for phase 2 | Queue in PRD2b, medialibrary in MED1a, each by ADR |
| menus guide | Menu items are links only | Updated in NAV1a |
| DB1 | PostgreSQL only | The senior's draft table document is MySQL. Postgres wins. Use it for column ideas only |

## 10. Open decisions for Achintha (Im putting my replies here)

1. Is Phase 1 formally closed (P1-UI7, P1-DOC1b, P1-CLOSE), or do you waive that so P3 can start (X4)? Separately: merge the auth module before P3-RET1a, or build minimal retailers and membership here and reconcile later? 
Achintha - Phase 1 is formally closed and yes lets merge the auth module that we made in C:\dev\getnow-auth2 to this. 

2. Paste the categories and products sections of the senior's table draft so CAT1 and PRD1a can match column names where they make sense.
The senior said we can go ahead the way we want for now and that it isnt set in stone from their side yet.

3. Editing an `approved` product: (a) a change to a reviewed field sends it back to `pending_review` and it disappears until re-approved, while price, stock and MOQ apply immediately and are logged; (b) the live version stays up while the edit is reviewed, which needs revisions and conflicts with D7. Recommended: (a). SCL6 keeps (b) open for later.
4. Products are retailer-owned listings, not rows pointing at a shared model catalog. Recommended: yes.
5. Specification attributes: variant options are controlled rows from VAR1a. Per-category specification templates are deferred and `specifications` is free text until then. Recommended: confirm.
6. A product a superadmin creates on a retailer's behalf: starts `approved` (logged with the admin as approver) or goes through the queue. Recommended: starts `approved`.
7. Phase number: Catalog takes P3 and Phase 2 (pages and media) stays planned.
8. Languages: is Sinhala or Tamil product and category content needed soon? The announcement bar already has locale copy. If yes within this phase, translations move from SCL17 (deferred) into CAT1 and PRD1a. Recommended: English now, additive later.
9. One retailer per user for now (SCL15). Recommended: yes.
10. Context split (SCL14): Catalog holds taxonomy and products, Retail holds retailers. Recommended: confirm.

## 11. Risks

- Typed menu items change what the storefront receives. Expansion must keep the v1 item shape (K8) and the demo storefront must tolerate it.
- Menu depth cap is 2 and category depth is 2. A Source item nested under another item produces depth 3. NAV1a must reject it.
- Medialibrary and the database queue arrive earlier than ARCHITECTURE planned. Each needs an ADR and X1 approval.
- A second panel and a second actor type change authorization everywhere. Cross-retailer tests are mandatory.
- Auth module merge is a cross-repo dependency outside this brief's control.
- The queue needs a running worker, also on the Windows dev machine. PRD2b documents the command.
- Twenty-six steps plus the exit review is long. If scope must shrink, cut in this order: COL1, API1b, VAR1c, MED1b. Never cut QA1, PRD2a or the ownership tests.

## 12. Exit criteria (P3-CLOSE)

- All steps ACCEPTED and `composer check` green on main
- A category created in the admin appears in both product dropdowns and in the Content API nav with no other edit
- A deactivated category, brand or collection disappears from nav and dropdowns, and its menu references vanish from the API without a menu edit
- A retailer can create, edit, submit and archive only their own products. Cross-retailer tests pass
- A superadmin can approve, flag fields with comments, reject, edit, create and archive any product
- Query-count tests pass for list pages, nav resolution and the public API
- Every transition and review is in the activity log
- `catalog-api.md`, its fixture and the real API response agree
- `php artisan migrate:fresh --seed` reproduces a demo catalog
- CT1 to CT7 are each enforced by a named test or marked Review only in the guides
