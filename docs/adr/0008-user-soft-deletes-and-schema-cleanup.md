# 0008. User soft deletes and schema cleanup

- **Status**: Accepted
- **Date**: 2026-10-23
- **Source**: getnow-auth2 ADR 0003

## Context

`ARCHITECTURE.md` specifies `deleted_at` for soft deletes so that domain entities (such as orders and content blocks) can maintain referential integrity with historical user accounts.

In the original `getnow-auth2` standalone repository, starter-kit columns (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, and `remember_token`) were scheduled for removal. However, when reconciling into `getnow-cms`, Fortify and Filament panel authentication remain active, so those columns are retained in the database and preserved during migration reconcile.

## Decision

1. Adopt soft deletes for user records:
   - Add a nullable `deleted_at` timestamp column to `users` via `softDeletes()` in additive migration `2026_10_30_070000_add_accounts_columns_to_users_table`.
   - Add the `Illuminate\Database\Eloquent\SoftDeletes` trait to `App\Models\User`.
2. Schema reconcile coexistence:
   - Retain existing starter-kit columns (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, and `remember_token`) for Filament and Fortify compatibility.
   - Migration `2026_10_30_070000_add_accounts_columns_to_users_table` adds `public_id`, `role`, and `deleted_at` with deterministic backfill.
   - Support clean bidirectional rollback in the migration's `down()` method.

## Consequences

1. User records are soft-deleted when `delete()` is called, setting `deleted_at` and excluding them from standard queries while preserving foreign key relationships.
2. Filament admin panel authentication and Fortify continue to operate with their expected columns.
3. Rollback operations remain fully backward-compatible and bidirectional.
