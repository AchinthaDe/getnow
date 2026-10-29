---
kind: normative
area: data
status: active
summary: The Postgres schema and its Liquibase migrations, seeds and contexts, and what personal data each table holds.
paths:
  - packages/backend/src/main/resources/db/**
  - packages/backend/src/test/java/com/manekin/backend/LiquibaseContextsIntegrationTest.java
decisions: [4]
depends-on: [backend-core]
tests:
  - ./gradlew :packages:backend:test --tests '*LiquibaseContextsIntegrationTest'
triggers:
  - paths:
      - packages/backend/src/main/resources/db/changelog/migrations/**
      - packages/backend/src/main/resources/db/changelog/db.changelog-master.yaml
    reconsider: >-
      A schema change. Check four things.
      (1) A new changeset, never an edited one, with a rollback.
      (2) Personal data in a new table or column: erasure must remove it (CaptureErasureService, UserService's account closure), and its retention must match the consent form (ADR 0004, migration 017).
      (3) Indexes on foreign keys and on columns read in WHERE or ORDER BY.
      (4) The owning area's guide names the new table.
  - paths:
      - packages/backend/src/main/resources/db/changelog/seeds/**
    reconsider: Seeds run only in the dev context. A seed never deletes or overwrites what it did not create (DATA-01).
---

# Data

One Postgres database holds everything the backend keeps. Liquibase builds it from SQL changesets at start-up. Each area owns its tables, and this guide owns how they are changed.

## How it works now

- **The changelog.** `packages/backend/src/main/resources/db/changelog/db.changelog-master.yaml` includes every file in order:
  - `migrations/<NNN>-<name>.sql`: the schema, in Liquibase formatted SQL.
  - `seeds/<1NN>-dev-<name>.sql`: development data, `context: dev` only.
- **Changesets.** Each one is `--changeset manekin:<number>-<slug> labels:<feature>`, followed by its `--rollback` statements. Numbers are unique across the repository; look at the highest in use before adding one.
- **Contexts** (`spring.liquibase.contexts`):
  - `default` runs no seed;
  - the dev profile asks for `dev`;
  - production asks for `prod`.
  
  Liquibase with no context would run every seed, so one is always named (DATA-01).
- **Postgres-specific features** are used on purpose: enums, JSONB, partial indexes, triggers (`012-triggers.sql`), and partitions (`011-audit-log.sql`). Tests therefore run on real Postgres, never H2.

**Tables by area:**

| Area | Tables | Migrations |
|---|---|---|
| [identity](identity.md) | `users` (Keycloak subject, verified email; closed accounts kept as anonymous tombstones) | 002, 018 |
| [retailer access](retailer-access.md) | `brands`, `brand_members`, `api_keys` | 003, 013, 019 |
| [capture](capture.md) | `fit_sessions`, `session_photos`, `pipeline_runs`, `measurement_profiles`, `user_consents`, `erasure_requests` | 006–008, 010, 014–017 |
| [size charts](size-charts.md) | `chart_transcriptions`, `chart_images`, `product_charts` | 020 |
| shared | `audit_log` (partitioned), the `updated_at` triggers | 011, 012 |

Migrations 004, 005 and 009 (subscriptions, the catalogue and its `size_charts`, webhooks) created tables that no code uses yet (DATA-03).

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | **Every schema change is a new changeset.** Never edit one that has run, and never run DDL against a running environment by hand. | Liquibase's checksums refuse an edited changeset, and every environment must be built the same way. | Liquibase; `LiquibaseContextsIntegrationTest` (`aChangedMigrationIsStillCaught`) |
| 2 | **Every changeset has a rollback.** | A release can be backed out. | Review. Migration 013, the oldest retailer table, has none, and is dropped by 019. |
| 3 | **Seeds run only in the dev context**, and never delete what they did not create. | DATA-01: seeds once deleted real data. | `LiquibaseContextsIntegrationTest` |
| 4 | **Personal data has an erasure path and a retention that match the consent form.** Anything a capture or an account adds is removed by `CaptureErasureService` or account closure. | The study's consent and the right to erasure (ADR 0004). | `ArmatureUploadIntegrationTest`, `UserIntegrationTest`; review for new tables |
| 5 | **Parameterised queries only:** JPA, or native queries with bound parameters, never string-built SQL. | SQL injection. | Review |
| 6 | **Integration tests own their database:** Testcontainers, or a throwaway Postgres named by `MANEKIN_TEST_DB_URL`. | Postgres-specific SQL makes H2 misleading. | `PostgresTestSupport` |

## Changing it

- **A new table or column:**
  1. Add `migrations/<NNN>-<short-name>.sql`: the next file number, a new changeset number, a `labels:` naming the feature, and a rollback.
  2. Include it at the end of the "migrations" part of `db.changelog-master.yaml`.
  3. Comment the change at the top of the file with why, as 017 and 020 do. The migration file is where a schema decision's reasons live.
  4. Name the table in the owning area's guide and in the table above.
- **Personal data:** say in the migration's header what it holds and how it is erased, and extend the erasure service and its test.
- **Enum columns:** follow the newest migrations' choice rather than mixing strategies (DATA-04 settles one).
- **Not here:** what a table means belongs to its area's guide.

## Running and testing

- Tests run inside WSL, from the repository root:

  ```bash
  ./gradlew :packages:backend:test --tests '*LiquibaseContextsIntegrationTest'
  ```

  Every backend integration test also runs the full changelog on a fresh database.
- On the dev stack, migrations run when the backend starts. Adminer is behind `docker compose --profile tools up adminer`, bound to 127.0.0.1.

## Known limits and open questions

- **There is no schema dump to read.** Liquibase is the schema; the stale pg_dump output at the root was removed on 2026-10-24 (DATA-04). To see the current schema, run the backend on the dev stack and look in Adminer.
- **About 15 tables have no code** (DATA-03), and enum strategies and naming are mixed (DATA-04): M4.
- **No backups or restore drill yet** (M5, OPS-01).
- **Participant measurements are in git history** (DATA-02). They are removed by the history purge in M5.

## History

- [ADR 0004](../decisions/0004-photo-originals-research-store.md): where photo originals may live.
- Migration headers: 016 (the V1 pipeline), 017 (retention until the study ends), 018 (Keycloak identity), 019 (retailer access), 020 (size-chart reads).
- [CODE_AUDIT](../plans/CODE_AUDIT.md) DATA-01 to DATA-04.