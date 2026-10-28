---
kind: normative
area: content-api
status: active
summary: Public content layout endpoint, rate limiting, and revision-based HTTP conditional caching.
paths:
  - app/Http/Controllers/Api/V1/Content/**
  - app/Domain/Content/Actions/GetLayoutContent.php
  - app/Domain/Content/Data/Layout*.php
  - routes/api.php
  - docs/contracts/content-api.md
  - tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php
  - tests/Feature/Api/V1/Content/**
  - tests/Fixtures/contracts/**
decisions: [D1, D3, D8]
depends-on: [content-blocks, menus, site-settings]
tests:
  - vendor/bin/pest tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php tests/Arch/ContentApiArchTest.php
triggers:
  - paths:
      - routes/api.php
      - app/Http/Controllers/Api/V1/Content/**
    reconsider: >-
      API controller or routing changes require checking three things:
      (1) Controllers remain thin without business logic or database calls (S2).
      (2) Endpoint adheres to the Content API v1 contract in docs/contracts/content-api.md.
      (3) All changes within v1 must be strictly additive (K8).
  - paths:
      - app/Domain/Content/Actions/GetLayoutContent.php
      - app/Domain/Content/Data/Layout*.php
    reconsider: >-
      Layout assembly changes require checking three things:
      (1) SHA-256 revision hash is computed from the canonical data JSON structure.
      (2) Every defined placement and menu key is always present in output.
      (3) Contract test fixture tests/Fixtures/contracts/content-layout-v1.json stays synchronized.
---

# Content API

The `content-api` area delivers the public layout graph for storefront rendering over `GET /api/v1/content/layout`. It exposes global settings, visible placement blocks, and enabled menus in a single response guarded by revision ETags and IP rate limiting.

## How it works now

- **Single layout endpoint.** `GetLayoutContentController` routes `GET /api/v1/content/layout` without authentication under `throttle:content-api` middleware, delegating layout graph assembly to `GetLayoutContent`.
- **Pre-indexed placement queries.** `GetLayoutContent` retrieves all published, enabled, in-window blocks with `ContentBlock::visible()->servingOrder()`, pre-indexing them by placement in memory before applying `ResolveServablePayload` and in-memory placement `maxCount()` slicing.
- **Revision ETag and HTTP 304.** Responses attach an `ETag` containing the SHA-256 hash of canonical layout data JSON. If the request's `If-None-Match` header matches the current revision or `*`, the controller returns an empty `304 Not Modified` response.
- **Fail-closed block resolution.** Unservable blocks (unsupported schema versions or invalid payloads) are omitted from layout output without failing sibling blocks or returning an HTTP error (ADR 0002).

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | **Public read-only layout endpoint.** `GET /api/v1/content/layout` requires no authentication credentials. | Storefront consumers must retrieve layout data publicly without token negotiation. | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` |
| 2 | **Deterministic revision ETag caching.** Responses carry an ETag matching SHA-256 of canonical data and return 304 on match. | Minimizes network payload transfer when storefront layout has not changed. | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` |
| 3 | **Fail-closed serving health per block.** Unservable blocks are omitted while valid sibling blocks are returned. | Resiliency decision ADR 0002; corrupted blocks must never crash storefront layout. | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` |
| 4 | **All placements and menus present.** Every defined Placement and MenuKey case is present in the output even when empty. | Contract consistency; storefront layout components must not crash on missing keys. | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` |
| 5 | **Additive changes only within v1.** Response schema fields cannot be removed or renamed in v1. | Backward compatibility rule K8; breaking contract changes require `/api/v2`. | `Review only` (monitored by `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php`) |
| 6 | **Per-minute IP rate limiting applies.** Endpoint returns HTTP 429 when requests exceed configured threshold. | Protects the CMS backend against traffic spikes and scraping denial-of-service. | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` |
| 7 | **Thin controller boundary.** Controller performs no validation or queries and only delegates to `GetLayoutContent`. | Architectural rule S2; enforces layer separation and prevents business logic in HTTP. | `tests/Arch/ContentApiArchTest.php` |

## Changing it

- **Extending the layout contract (additive only):**
  1. Add optional or defaulted property to the relevant `Layout*Data` DTO.
  2. Update `GetLayoutContent` assembly logic.
  3. Update `docs/contracts/content-api.md` specification.
  4. Update contract test fixture `tests/Fixtures/contracts/content-layout-v1.json`.
  5. Verify contract tests in `GetLayoutContentTest`.
- **Adjusting rate limiting:**
  1. Modify rate limit configuration in `config/content.php` or `CONTENT_API_RATE_LIMIT_PER_MINUTE`.
  2. Verify throttle threshold in `GetLayoutContentEndpointTest`.

## Running and testing

Run configuration cache clearing before executing tests to ensure test database settings from `phpunit.xml` apply:

```powershell
php artisan config:clear
vendor/bin/pest tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php tests/Arch/ContentApiArchTest.php
```

## Known limits and open questions

- **Rate limiting applies only to content-api route.** The `content-api` rate limiter is configured in `AppServiceProvider` specifically for `/api/v1/content/layout` with a default of 60 requests per minute (`CONTENT_API_RATE_LIMIT_PER_MINUTE`).
- **No application caching beyond HTTP ETags.** Layout responses rely entirely on HTTP conditional caching (`If-None-Match`); responses are not cached in Redis or Laravel memory stores.

## History

- [ADR 0001](../adr/0001-content-api-tie-break.md): deterministic tie-breaking (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`) and read-side in-memory maxCount slicing.
- [ADR 0002](../adr/0002-content-block-serving-health.md): fail-closed serving health checks and reason codes.
- [docs/contracts/content-api.md](../contracts/content-api.md): Content API v1 layout contract specification.
- [ARCHITECTURE.md §12 Decisions](../../ARCHITECTURE.md#12-decisions): D1, D3, D8.
