---
kind: normative
area: content-blocks
status: active
summary: Content block domain models, schemas, placements, serving health resolution, and lifecycle actions.
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
decisions: [D2, D3, D5, D7, D8, D9]
depends-on: [admin-panel, data]
tests:
  - vendor/bin/pest tests/Feature/Domain/Content/ServingWindowTest.php
  - vendor/bin/pest tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php
  - vendor/bin/pest tests/Feature/Domain/Content/Rules/NoHtmlTest.php
  - vendor/bin/pest tests/Feature/Support/Rules/SafeLinkTest.php
  - vendor/bin/pest tests/Feature/Domain/Content/Models/DBConstraintsTest.php
  - vendor/bin/pest tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php
  - vendor/bin/pest tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php
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

Content blocks provide modular storefront layout components defined in code and configured by administrators. The area encapsulates block lifecycle states, schema validation, serving health resolution, and lifecycle actions.

## How it works now

- **Code-owned definitions.** Placements and block types are defined in PHP enums and classes, never in the database. `Placement` defines allowed block types and `maxCount`. Each block implements `BlockDefinition` and is registered in `ContentServiceProvider`.
- **Lifecycle and serving health.** A block is live when `ContentBlock::isLive()` evaluates to true (published, enabled, and inside `starts_at`/`ends_at`). Servability is checked separately via `ResolveServablePayload`, returning structured status codes (`Servable`, `UnknownType`, `UnsupportedVersion`, `InvalidAfterUpgrade`, `UpgradeFailed`).
- **Pessimistic row locking on transitions.** State mutations (`PublishContentBlock`, `EnableContentBlock`, `DisableContentBlock`, `ArchiveContentBlock`) execute under `lockForUpdate()` inside database transactions.
- **Immediate publish.** In accordance with Decision D7, published content edits go live immediately upon saving without an intermediate revision staging table.

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | **Lifecycle visibility rule.** Only published, enabled blocks inside their scheduling window are live. | Consumers must never receive draft, archived, disabled, or out-of-window content. | `tests/Feature/Domain/Content/ServingWindowTest.php` |
| 2 | **Deterministic tie-breaking.** Overlapping live blocks rank by `sort_order ASC`, `starts_at DESC NULLS LAST`, `id DESC`. | Guarantees deterministic ordering and predictable winner selection across consumers (D8). | `tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php` |
| 3 | **Pessimistic locking on state transitions.** State transitions acquire a row lock via `lockForUpdate()` in a transaction. | Prevents race conditions during simultaneous lifecycle mutations (D9). | `Review only` |
| 4 | **Strict text validation without raw HTML.** Payload string fields reject `<` and `>` characters. | Security rule K3; prevents stored XSS and presentation markup injection. | `tests/Feature/Domain/Content/Rules/NoHtmlTest.php` |
| 5 | **Link scheme safety.** URLs must be relative paths starting with `/` or an `https://` address. | Security rule K4; blocks `javascript:` execution and unencrypted external schemes. | `tests/Feature/Support/Rules/SafeLinkTest.php` |
| 6 | **Database CHECK constraints.** Table enforces non-negative sort order, valid status, ends_at after starts_at, and positive schema_version. | Defense in depth at the database engine level (DB4). | `tests/Feature/Domain/Content/Models/DBConstraintsTest.php` |
| 7 | **Permanent keys and payload upgrader stability.** Payload changes require a schema version bump and an upgrader. | Rules K1 and K2; historical payloads must continue loading safely without data loss. | `tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php` |
| 8 | **Platform admin authorization only.** Mutating actions require platform admin authorization and editing archived blocks is forbidden. | Security rules SEC4 and D6; prevents unauthorized content modifications. | `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php` |

## Changing it

- **Adding a new block type:**
  1. Create definition class and payload Data class under `app/Domain/Content/Blocks/<Name>/`.
  2. Register the definition class in `ContentServiceProvider`.
  3. Create the Filament form schema in `app/Filament/Blocks/<Name>Form.php`.
  4. Add the block type string to allowed block types on the corresponding `Placement` case.
  5. Add test coverage for payload validation, API output, and update contract fixtures.
- **Updating a block payload shape:**
  1. Increment `schemaVersion()` in the block definition.
  2. Implement an upgrader class implementing `PayloadUpgrader` to transform older payloads.
  3. Register the upgrader in the block definition's upgraders list.
  4. Add unit test asserting transformation of previous schema payloads.

## Running and testing

Run configuration cache clearing before executing tests to ensure test database settings from `phpunit.xml` apply:

```powershell
php artisan config:clear
vendor/bin/pest tests/Feature/Domain/Content/ServingWindowTest.php
```

## Known limits and open questions

- **Policies check platform admin flag only.** `ContentBlockPolicy` verifies `$user->isAdmin()` with no author or tenant scoping; fine-grained retailer permissions arrive in later phases (D6).
- **Last-write-wins concurrency.** Payloads use last-write-wins on save (D9); concurrent form submissions overwrite without conflict detection.

## History

- [ADR 0001](../adr/0001-content-api-tie-break.md): deterministic tie-breaking and maxCount enforcement at query time.
- [ADR 0002](../adr/0002-content-block-serving-health.md): fail-closed serving health checks and reason codes.
- [ADR 0003](../adr/0003-content-block-concurrency.md): last-write-wins payload saves and pessimistic locking for lifecycle actions.
