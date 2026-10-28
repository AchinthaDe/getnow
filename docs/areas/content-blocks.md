---
kind: normative
area: content-blocks
status: active
summary: Content block domain models, schemas, placements, serving health resolution, previews, and lifecycle actions.
paths:
  - app/Domain/Content/Blocks/**
  - app/Domain/Content/Contracts/**
  - app/Domain/Content/Services/**
  - app/Domain/Content/Casts/**
  - app/Domain/Content/Exceptions/**
  - app/Domain/Content/Models/ContentBlock.php
  - app/Domain/Content/Policies/ContentBlockPolicy.php
  - app/Domain/Content/Policies/BaseContentPolicy.php
  - app/Domain/Content/Actions/*ContentBlock*.php
  - app/Domain/Content/Actions/GetBlockServingStatus.php
  - app/Domain/Content/Actions/GetUpgradedBlockPayload.php
  - app/Domain/Content/Data/CreateContentBlockData.php
  - app/Domain/Content/Data/UpdateContentBlockData.php
  - app/Domain/Content/Data/BlockPayload.php
  - app/Domain/Content/Data/BlockServingStatusResult.php
  - app/Domain/Content/Data/ServingHealthResult.php
  - app/Domain/Content/Enums/Placement*.php
  - app/Domain/Content/Enums/PublishStatus.php
  - app/Domain/Content/Enums/ServingStatus.php
  - app/Domain/Content/Enums/ServingHealthOutcome.php
  - app/Domain/Content/Enums/ServiceIcon.php
  - app/Domain/Content/Enums/Tone.php
  - app/Domain/Content/Enums/PromoTheme.php
  - app/Filament/Blocks/*.php
  - app/Filament/Admin/Resources/ContentBlockResource.php
  - app/Filament/Admin/Resources/ContentBlockResource/**
  - app/Providers/ContentServiceProvider.php
  - app/Support/Rules/**
  - tests/Feature/Admin/ContentBlock/**
  - tests/Feature/Domain/Content/Blocks/**
  - tests/Feature/Domain/Content/Actions/*ContentBlock*.php
  - tests/Feature/Domain/Content/Actions/ActionCoverageTest.php
  - tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php
  - tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php
  - tests/Feature/Domain/Content/Models/ContentBlock*.php
  - tests/Feature/Domain/Content/Models/DBConstraintsTest.php
  - tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php
  - tests/Feature/Domain/Content/Models/VisibilityParityTest.php
  - tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php
  - tests/Feature/Domain/Content/Rules/**
  - tests/Feature/Domain/Content/Serving*.php
  - tests/Feature/Support/Rules/**
  - tests/Unit/Content/Services/**
  - tests/Unit/Domain/Content/Blocks/**
  - tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php
  - tests/Unit/Domain/Content/Enums/Placement*.php
  - tests/Unit/Policies/BaseContentPolicyTest.php
decisions: [D2, D3, D5, D7, D8]
depends-on: [admin-panel, data]
tests:
  - vendor/bin/pest tests/Feature/Domain/Content/ServingWindowTest.php tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php tests/Feature/Domain/Content/Rules/NoHtmlTest.php tests/Feature/Support/Rules/SafeLinkTest.php tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php
triggers:
  - paths:
      - app/Domain/Content/Blocks/**
      - app/Domain/Content/Contracts/BlockDefinition.php
    reconsider: >-
      Block type changes require checking three things:
      (1) Permanent block type keys are never renamed.
      (2) Payload shape changes require bumping schema_version and providing an upgrader.
      (3) Block types must be explicitly registered in ContentServiceProvider.
  - paths:
      - app/Domain/Content/Enums/Placement.php
    reconsider: >-
      Placement modifications require checking two things:
      (1) Allowed block types and maxCount constraints match layout requirements.
      (2) Placement keys are defined in code and never edited by administrators.
---

# Content Blocks

Content blocks provide modular storefront layout components defined in code and configured by administrators. The area encapsulates block lifecycle states, schema validation, live capacity enforcement, serving health resolution, and preview rendering.

## How it works now

- **Code-owned definitions.** Placements and block types are defined in PHP enums and classes, never in the database (D2). `Placement` defines allowed block types and `maxCount`. Each block implements `BlockDefinition` and is registered in `ContentServiceProvider`.
- **Lifecycle and serving health.** A block is live when `ContentBlock::isLive()` evaluates to true (published, enabled, and inside `starts_at`/`ends_at`). Servability is resolved via `ResolveServablePayload` returning outcomes defined in `ServingHealthOutcome` (ADR 0002).
- **Live capacity enforcement.** Mutating actions (`PublishContentBlock`, `EnableContentBlock`, `UpdateContentBlock`) enforce instantaneous concurrency against `Placement::maxCount()` under a PostgreSQL transaction advisory lock (`pg_advisory_xact_lock`) before updating state (ADR 0004).
- **Ephemeral admin previews.** View and edit pages offer visual preview approximation backed by ephemeral cache (`SaveContentBlockPreviewState`), allowing admins to inspect rendered blocks across responsive viewports without mutating persisted database state (ADR 0005).
- **Immediate publish.** In accordance with Decision D7, published content edits go live immediately upon saving without an intermediate revision staging table.

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | **Lifecycle visibility rule.** Only published, enabled blocks inside their scheduling window are live. | Consumers must never receive draft, archived, disabled, or out-of-window content (K7). | `tests/Feature/Domain/Content/ServingWindowTest.php` |
| 2 | **Deterministic tie-breaking.** Overlapping live blocks rank by `sort_order ASC`, `starts_at DESC NULLS LAST`, `id DESC`. | Guarantees deterministic ordering and predictable winner selection across consumers (D8). | `tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php` |
| 3 | **Write-time live capacity enforcement.** Activating a block enforces instantaneous schedule concurrency against `Placement::maxCount()` under a PostgreSQL transaction advisory lock (`pg_advisory_xact_lock`). | Prevents over-capacity scheduling race conditions and shadowed blocks (ADR 0004). | `Review only` |
| 4 | **Fail-closed serving health per block.** Unservable blocks are omitted while valid sibling blocks continue to render. | Architectural resiliency (ADR 0002); corrupted blocks or version mismatches must never crash layout rendering. | `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php` |
| 5 | **Placement allowed block types and maxCount.** Placements strictly constrain permitted block type keys and maximum live count. | Code-owned placement boundaries (D2); prevents layout distortion from unsupported components. | `Review only` |
| 6 | **Strict text validation without raw HTML.** Payload string fields reject `<` and `>` characters. | Security rule K3; prevents stored XSS and presentation markup injection. | `tests/Feature/Domain/Content/Rules/NoHtmlTest.php` |
| 7 | **Link scheme safety.** URLs must be relative paths starting with `/` or an `https://` address. | Security rule K4; blocks `javascript:` execution and unencrypted external schemes. | `tests/Feature/Support/Rules/SafeLinkTest.php` |
| 8 | **Platform admin policy authorization.** `ContentBlockPolicy` allows view and mutation methods only to platform admins (`is_platform_admin === true`) and denies deletion abilities for all users. | Authorization rules SEC4, D6, and C6; restricts Filament resource actions to platform admins. | `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php` |

## Changing it

- **Adding a new block type:**
  1. Create definition class implementing `BlockDefinition` and payload class extending `BlockPayload` under `app/Domain/Content/Blocks/<Name>/`.
  2. Register the definition class in `ContentServiceProvider`.
  3. Create the Filament form schema in `app/Filament/Blocks/<Name>Form.php`.
  4. Add the block type string to allowed block types on the corresponding `Placement` case.
  5. Add test coverage for payload validation, API output, and update contract fixtures.
- **Updating a block payload shape:**
  1. Increment `schemaVersion()` in the block definition class.
  2. Implement an upgrader class implementing `BlockPayloadUpgrader`.
  3. Delegate the upgrade in the block definition's `upgradePayload(int $fromVersion, array $payload): array` method.
  4. Add unit test asserting transformation of previous schema payloads.

## Running and testing

Run configuration cache clearing before executing tests to ensure test database settings from `phpunit.xml` apply:

```powershell
php artisan config:clear
vendor/bin/pest tests/Feature/Domain/Content/ServingWindowTest.php tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php tests/Feature/Domain/Content/Rules/NoHtmlTest.php tests/Feature/Support/Rules/SafeLinkTest.php tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php
```

## Known limits and open questions

- **Policies check platform admin flag only.** `ContentBlockPolicy` verifies `$user->isAdmin()` with no author or tenant scoping; fine-grained retailer permissions arrive in later phases (D6).
- **Last-write-wins concurrency.** Payloads use last-write-wins on save; concurrent form submissions overwrite without conflict detection (ADR 0003).
- **Advisory-lock SQL is inline.** `PublishContentBlock`, `EnableContentBlock`, and `UpdateContentBlock` execute raw PostgreSQL transaction-level advisory locks (`pg_advisory_xact_lock`) using CRC32-derived keys (`(int) (crc32('placement:' . $placement) & 0x7FFFFFFF)`) inline rather than through a dedicated connection service.
- **Preview controller hardcodes panel ID.** `ContentBlockPreviewController` hardcodes panel `'admin'` during authorization and view resolution.
- **Pending UI feature ownership.** This area guide owns `ContentBlockPreviewController`, `CountLiveBlocksForPlacement`, `ContentBlockPreviewData`, and `resources/views/filament/admin/resources/content-block-resource/**`. These files belong to the pending UI feature branch and will be claimed in `paths:` upon merge to `main`.

## History

- [ADR 0001](../adr/0001-content-api-tie-break.md): deterministic tie-breaking (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`) and read-side in-memory maxCount slicing.
- [ADR 0002](../adr/0002-content-block-serving-health.md): fail-closed serving health checks and reason codes.
- [ADR 0003](../adr/0003-content-block-concurrency.md): last-write-wins payload saves and pessimistic locking for lifecycle actions.
- [ADR 0004](../adr/0004-placement-live-capacity-enforcement.md): write-time live capacity guards using PostgreSQL transaction advisory locks.
- [ADR 0005](../adr/0005-admin-block-preview-approximation.md): ephemeral admin block preview approximation backed by cache.
- [ARCHITECTURE.md §12 Decisions](../../ARCHITECTURE.md#12-decisions): D2, D3, D5, D7, D8.
