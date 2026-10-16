# Architecture

Platform for wholesale mobile and electronics sellers to connect with business buyers.

This repository starts as the **content management backend**: admins edit storefront content in Filament, and storefronts read it through a versioned API. It will grow into the full marketplace backend, one phase at a time.

This document is the source of truth for structure and boundaries. If code and this document disagree, fix one of them in the same PR. Only the **active phase** is described in detail; later phases are sketched so names stay consistent, not so they get built early.

## 1. Phases

| Phase | Scope | Status |
|---|---|---|
| 0 Bootstrap | Laravel + PostgreSQL + Filament admin panel + quality gates | Done |
| 1 Layout CMS (pilot) | Site settings, announcement bar, menus, Content API v1, seed data mirroring the current storefront layout | Active |
| 2 Pages and media | Pages made of blocks, image blocks (hero), revisions and preview | Planned |
| Later (order TBD) | Accounts, Catalog, Retail (retailer panel), Sales, Negotiation, Procurement, Payments, Disputes, React storefront | Not started |

### Phase 1 progress
- [x] Step 2a (content block domain, actions, serving health)
- [x] UI Step 1 (prerequisites)
- [x] UI Step 2 (policy, timezone config, read-only list + badges)
- [x] UI Step 3 (BlockFormRegistry, Create/Edit forms)
- [x] UI Step 4 (View page, header/row actions)
- [x] Menus
- [x] Content API v1
- [x] Seed data
- [ ] Step P1-S1 (storefront demo harness & teardown guide)
- [x] Step P1-S2 (homepage CMS blocks: 5 block types, announcement bar v2, 9 placements, sort order, and link targets)

Rule: do not scaffold folders, models or packages for a phase that is not active.

## 2. Goals and constraints

- Admins change storefront content without a deploy.
- Consumers (Blade storefront now, React later) must not break when content changes or is missing.
- Modular monolith. One context (`Content`) for now.
- `main` is always working and tested. Quality gates run locally (`composer check`) and via a minimal GitHub Actions check workflow. No CD pipelines, no Docker for local development.

## 3. Stack (phases 0 to 2)

| Concern | Choice |
|---|---|
| Language / framework | PHP 8.3+, Laravel (latest stable) |
| Admin UI | Filament 5 (Livewire 4), single panel `admin` |
| Database | PostgreSQL (dev and tests) |
| Settings | spatie/laravel-settings + Filament settings plugin |
| DTOs / API payloads | spatie/laravel-data |
| Audit trail | spatie/laravel-activitylog |
| Quality | Pint, Larastan, Pest (including architecture tests) |
| Media (phase 2) | spatie/laravel-medialibrary + Filament plugin, added with the first image block |

Not in the stack yet. Add only when a phase needs it, with a short justification: Redis/Horizon, Reverb, Scout/Meilisearch, laravel-permission/Shield, laravel-model-states, brick/money, typescript-transformer, Sentry/Pulse. (Redis, Meilisearch and Mailpit may be installed locally but are unused for now.)

## 4. System overview

```
Admin (browser) ──► Filament admin panel ──► Actions ──► Content domain ──► PostgreSQL
                                                  ▲
Storefront (Blade now, React later) ──► GET /api/v1/content/layout ──► GetLayoutContent action
```

The CMS and the storefront are separate applications with separate databases. The storefront never reads this database. The API is the only contract.

*Note (Temporary Demo Storefront)*: For local Phase 1 demonstration and validation, a temporary read-only homepage harness is mounted on `GET /` calling `GetLayoutContent` directly. See `docs/blueprints/P1-S1/DEMO-TEARDOWN.md` for details and cleanup procedures.

## 5. Code structure

```
app/
├── Domain/
│   └── Content/
│       ├── Actions/            # one invokable class per use case, reads included
│       ├── Blocks/<Name>/      # <Name>Block.php (definition) + <Name>Data.php (payload)
│       ├── Contracts/          # BlockDefinition, BlockRegistry
│       ├── Data/               # DTOs (API output, action input)
│       ├── Enums/              # Placement, MenuKey, PublishStatus, Tone
│       ├── Events/
│       ├── Exceptions/
│       ├── Models/             # ContentBlock, Menu, MenuItem
│       ├── Policies/
│       ├── Services/
│       └── Settings/           # StorefrontSettings
├── Filament/
│   ├── Blocks/                 # BlockFormRegistry, plus form schemas per block type
│   └── Admin/                  # panel `admin`
│       ├── Resources/          # eg: Resources/ContentBlockResource/{Actions,Pages,Schemas,Tables}
│       ├── Pages/              # settings pages, custom pages
│       └── Widgets/
├── Http/
│   └── Controllers/Api/V1/Content/
├── Providers/                  # includes ContentServiceProvider (registers blocks)
└── Support/                    # small shared helpers only
    └── Rules/
database/
├── factories/
├── migrations/
├── seeders/
└── settings/                   # spatie/laravel-settings migrations
docs/{adr,contracts}/           # contracts/content-api.md is the API contract
routes/api.php
tests/{Feature,Unit}/Content/  tests/Arch/  tests/Fixtures/contracts/
```

Create new folders only when a phase needs them. `Infrastructure/` appears with the first external service.

### Dependency rules (enforced by `tests/Arch`)

1. `App\Domain` must not depend on `App\Filament` or `App\Http`.
2. Filament resources, pages and controllers contain no business rules. They validate, authorize, call an Action and return output.
3. Every PHP file declares `strict_types=1`.
4. Filament code never calls `Model::create/update/delete` directly. It calls Actions.
5. Contexts interact through Actions, events and contracts, never each other's models.

## 6. Content model (phase 1)

### Concepts

- **Site settings:** global scalar values (text, booleans). Spatie settings class `StorefrontSettings`, one Filament settings page. No images in phase 1.
- **Placement:** a named slot in the storefront layout, **defined in code** as the `Placement` enum. Each placement declares which block types it allows and a maximum block count. Admins cannot create placements.
- **Block:** one piece of content of a given type in a placement (`content_blocks` row). Its `payload` is JSON validated by the block's Data class.
- **Menu / MenuItem:** navigation trees. Menu keys are defined in code (`MenuKey` enum).

Placements, menu keys and block types are defined in code because the storefront must know them. Content is stored in the database.

### Mapping from the current storefront layout (`app.blade.php`)

| Region | Mechanism | Notes |
|---|---|---|
| Head: site name, default description | Site setting | favicon and logo deferred |
| Announcement bar | Block in placement `announcement_bar` | The free-delivery figure comes from storefront config. Text is free-form, the threshold is not computed here. |
| Header main (logo, search, account, wishlist, bag) | Leave in code | Search placeholder is a site setting |
| Desktop nav, mobile menu | Menus `main_nav`, `mobile_nav` | Category dropdown stays driven by the storefront's catalog source |
| Footer brand text | Site setting | |
| Footer link columns | Menus `footer_column_1..3` (menu `title` is the column heading) | |
| Flash messages | Leave in code | |
| Main content | Not phase 1 | Pages arrive in phase 2 |

### Homepage Placements and Block Types (Phase 1)

| Placement Key | Allowed Block Types | Max Count | Purpose |
|---|---|---|---|
| `announcement_bar` | `announcement_bar` (v2) | 1 | Storewide announcement bar with tone, links, secondary and locale copy |
| `home_hero_carousel` | `promo_slide` (v1) | 5 | Rotating promo slides at top of homepage with themes and stretched links |
| `home_hero_side` | `feature_banner` (v1) | 1 | Side banner adjacent to hero carousel |
| `home_service_strip` | `service_item` (v1) | 4 | Four value proposition highlights with icons |
| `home_categories_header` | `section_header` (v1) | 1 | Section heading above category browse grid |
| `home_featured_header` | `section_header` (v1) | 1 | Section heading above featured products |
| `home_collection_banner` | `feature_banner` (v1) | 1 | Mid-page collection highlight banner |
| `home_new_arrivals_header` | `section_header` (v1) | 1 | Section heading above new arrivals products |
| `home_brand_strip` | `brand_strip` (v1) | 1 | Brand logo / name strip near footer |

### Initial tables (temporary schema, built to production conventions)

`content_blocks`
- `id` bigint PK, `public_id` ULID unique
- `placement` varchar(64), `type` varchar(64), `schema_version` smallint default 1
- `payload` jsonb not null default `'{}'`
- `status` varchar(16) CHECK in (`draft`,`published`,`archived`)
- `is_enabled` boolean default true (the quick show/hide switch)
- `sort_order` int CHECK >= 0, `starts_at` / `ends_at` timestamptz null, CHECK `ends_at > starts_at`
- `created_by` / `updated_by` FK users null, timestamps
- Index on (`placement`, `status`, `is_enabled`, `sort_order`)

`menus`: `id`, `public_id`, `key` varchar unique, `label` (admin name), `title` nullable (heading shown on the storefront), timestamps.

`menu_items`: `id`, `public_id`, `menu_id` FK, `parent_id` FK self null, `label` varchar(80), `url` varchar(2048), `icon` varchar(64) null (from an allowed list), `opens_in_new_tab` bool, `is_enabled` bool, `sort_order` int CHECK >= 0, timestamps. Maximum depth 2, enforced in the Action.

Settings and activity log tables come from their packages. Phase 1 content is platform-owned. Retailer-owned content adds a nullable `retailer_id` through a later migration.

### Block rules

- A block type key is permanent. Never rename it. Deprecate and add a new type instead.
- Every block has a `schema_version`. When a Data class changes shape, bump the version and add an upgrader. Old stored payloads must still load.
- Fields are constrained: short text with max lengths, enums (for example `Tone`), validated links, booleans. **No raw HTML, CSS or JavaScript fields.** Rich text, when needed, is sanitized and its allowed tags are listed in the Data class.
- Colours and spacing come from enums that the storefront maps to its own CSS tokens. Design tokens stay in the storefront.
- Links must be a relative path starting with `/` or an `https://` URL. Reject `javascript:` and other schemes.
- Visibility is decided server-side: only `published`, `is_enabled` blocks inside their `starts_at`/`ends_at` window reach the API.
- Phase 1 has no revisions: editing a published block changes the live site. Draft preview and revisions arrive in phase 2.

### Adding a block type (checklist)

1. `app/Domain/Content/Blocks/<Name>/<Name>Data.php` (validated payload) and `<Name>Block.php` (type key, version, Data class, sample payload).
2. Register it in `ContentServiceProvider`. Registration is an explicit list, not auto-discovery.
3. `app/Filament/Blocks/<Name>Form.php` with the form schema.
4. Allow it in the relevant `Placement`.
5. Factory state, tests (payload validation, API output, visibility rules), update `docs/contracts/content-api.md` and its fixture.

## 7. Content API v1

`GET /api/v1/content/layout` returns everything the layout needs in one response. Public, read-only, rate limited.

```json
{
  "data": {
    "settings": {
      "site_name": "string",
      "default_meta_description": "string",
      "search_placeholder": "string",
      "footer_brand_text": "string"
    },
    "placements": {
      "announcement_bar": [
        {
          "id": "01J...",
          "type": "announcement_bar",
          "version": 1,
          "data": { "text": "string", "link_url": null, "tone": "info" }
        }
      ]
    },
    "menus": {
      "main_nav": {
        "title": null,
        "items": [
          { "id": "01J...", "label": "Phones", "url": "/shop?category=phones", "icon": null, "new_tab": false, "children": [] }
        ]
      }
    }
  },
  "meta": { "api_version": 1, "revision": "hash", "generated_at": "ISO-8601" }
}
```

Contract rules:
- Every placement and menu key is always present (empty array or empty items when there is no content).
- Within v1, changes are additive only. Removing or renaming a field means `/api/v2`.
- Responses carry an `ETag` from `meta.revision`. Application caching is added only when measured, and invalidated by events raised from Actions.
- Storefront rules (stated here so every consumer follows them): use hardcoded defaults if the API is down or slow, ignore unknown block types and unknown fields, and never crash on an empty placement or menu.
- The contract is documented in `docs/contracts/content-api.md` and guarded by a contract test against `tests/Fixtures/contracts/`.

## 8. Filament conventions

- Panel `admin` at `/admin`. Resources are grouped under `app/Filament/Admin/Resources/<Model>Resource/`.
- Reusable resource actions (such as lifecycle transitions shared between page headers and table rows) live in `app/Filament/Admin/Resources/<Model>Resource/Actions/`.
- Form and table definitions live in their own schema classes. Block form schemas live in `app/Filament/Blocks/` so a future retailer panel can reuse them.
- Create, update, delete and bulk operations call Actions. Status is never a free form field. Use dedicated actions such as Publish, Archive, Enable and Disable.
- Authorization uses Policies. Content policies extend `BaseContentPolicy`. Phase 1 admin access is a single `is_platform_admin` flag checked in `canAccessPanel`. Roles and permissions arrive with the retailer panel.
- Customize only through documented extension points. Pin the Filament version and upgrade in its own PR.
- Every resource has a smoke test that loads its list, create and edit pages.
- Browser history convention: table state interactions (search, sort, and filters inherit `history: false`, pagination configured with `history: false`) replace the current browser history entry (`history: false`), while inter-page navigation pushes a new history entry. Intra-table state changes never pollute browser history.

## 9. Database conventions (PostgreSQL)

- Bigint primary keys plus a ULID `public_id` for anything exposed externally. Never expose incremental IDs.
- Foreign keys everywhere, indexes for query paths, `CHECK` constraints for statuses and non-negative numbers.
- Migrations are append-only once merged. Use expand/contract for destructive changes.
- Seeders are idempotent. `ContentSeeder` runs only in `local`/`testing` and reproduces the values currently hardcoded in the storefront layout. Real environments start on migration placeholders until edited via the CMS.
- Tests run against Postgres (`phone_store_test`), never SQLite.

## 10. Security

- Admin panel requires authentication and `canAccessPanel`. No default or shared credentials outside local.
- The public API exposes only published, enabled, in-window content and no internal IDs.
- Content that is rendered by consumers is plain data. No stored HTML or script.
- Sensitive state changes (publish, disable, menu edits, settings) are written to the activity log.
- Secrets live only in environment variables. `.env` is never committed.

## 11. Testing

- Pest. Unit tests for pure logic (visibility window, payload upgraders). Feature tests for Actions, the API and Filament resources. Architecture tests for the rules in section 5.
- Extra depth: visibility rules (status, enabled, schedule), payload validation and link safety, contract fixture, schema version upgrades.
- `composer check` (Pint, Larastan, Pest) must pass before every merge. A pre-push git hook runs it.

## 12. Decisions

| ID | Decision |
|---|---|
| D1 | CMS is its own project and database. Storefronts consume the Content API. No shared database. |
| D2 | Placements, menu keys and block types are defined in code. Content lives in the database. |
| D3 | Design tokens stay in the storefront. Blocks choose from enums, never raw colours. |
| D4 | Category data stays with the catalog source. Menus link by URL until a Catalog context exists. |
| D5 | Phase 1 has no images. Media library arrives with the first image block in phase 2. |
| D6 | Phase 1 admin access is one platform-admin flag. Roles come with the retailer panel. |
| D7 | Published content edits go live immediately in phase 1. Revisions and preview come in phase 2. |
| D8 | MaxCount is enforced at API output limit, not publish lock. Tie-break: `sort_order ASC, starts_at DESC NULLS LAST, id DESC`. One block = one message. |
| D9 | Concurrency & Locks: Content blocks use last-write-wins for payloads, pessimistic locking for state transitions. Menus use whole-tree atomic replacement under a row lock, last-write-wins. |

Open decisions (record as ADRs in `docs/adr/` when settled):
- React frontend shape: Inertia inside this app versus a separate SPA or SSR app. The Content API is the contract either way.
- Retailer-owned content: approval flow and allowed block types.
- Production hosting and deployment method.
- API authentication for storefronts if the API stops being public.

## 13. Later phases (sketch only, do not build)

Accounts (users, business accounts, verification), Catalog (brands, models, variants, categories), Retail (retailers, offers, inventory), Sales (cart, orders), Negotiation (chat, quotes), Procurement (purchase orders), Payments (ledger, escrow, commission, payouts), Disputes. When a phase starts, add its section here and its context under `app/Domain/`.
