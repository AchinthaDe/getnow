---
kind: normative
area: accounts
status: active
summary: Accounts domain managing user identity, roles, credential authentication, and user administration.
paths:
  - app/Domain/Accounts/**
  - app/Http/Controllers/Api/V1/Auth/**
  - app/Http/Controllers/Api/V1/Users/**
  - app/Http/Requests/Auth/**
  - app/Http/Requests/Users/**
  - app/Providers/AccountsServiceProvider.php
  - routes/api/auth.php
  - routes/api/users.php
decisions:
  - 0006-context-architectural-styles.md
  - 0007-passport-token-issuance-and-refresh-rotation.md
  - 0008-user-soft-deletes-and-schema-cleanup.md
  - 0009-token-revocation-on-account-changes.md
depends-on: []
tests:
  - vendor/bin/pest tests/Feature/Accounts
  - vendor/bin/pest tests/Feature/Contract
  - vendor/bin/pest tests/Arch/AccountsArchTest.php
triggers:
  - paths:
      - app/Domain/Accounts/Enums/UserRole.php
    reconsider: >-
      Did user roles change? Verify database CHECK constraint in users reconcile
      migration and check authorization matrix in UserPolicy and Filament access rules.
  - paths:
      - app/Domain/Accounts/Services/UserService.php
      - app/Domain/Accounts/Tokens/PassportTokenIssuer.php
    reconsider: >-
      Did token revocation triggers change? Verify ADR 0009 atomicity and test
      UserTokenRevocationTest suite.
---

# Accounts

The Accounts area owns user identity, role-based authorization, credential authentication, and administrative user management.

## How it works now

Accounts follows the Controller, Service, and Repository pattern established in ADR 0006.

1. **Layering & Boundaries**:
   - `app/Domain/Accounts/Services/` contains domain business logic (`UserService`, `AuthService`).
   - `app/Domain/Accounts/Repositories/` contains data persistence implementations (`UserRepository`) implementing contracts in `Contracts/`.
   - Controllers (`Api\V1\Auth\AuthController`, `Api\V1\Users\UserController`) are thin and delegate to domain services.
   - External contexts consume Accounts only via `UserService`, `AuthService`, DTOs, and the `TokenIssuer` interface. `UserRepositoryInterface` remains internal.
2. **Authentication & Token Seam**:
   - Authentication mints OAuth2 access and rotating refresh tokens via `PassportTokenIssuer` implementing `TokenIssuer`.
   - Modifying user passwords, roles, or soft-deleting an account triggers atomic fail-closed token revocation across all client sessions (ADR 0009).
3. **Role Hierarchy**:
   - Defined by `UserRole` (`superadmin`, `retailer`, `customer`).
   - Gated via `UserPolicy` on API endpoints. Superadmin accounts cannot be created, demoted, or deleted via the public REST API.
   - Panel access is gated via `is_platform_admin` on `User::canAccessPanel`.

## Invariants

| # | Rule | Why | Enforced by |
|---|---|---|---|
| 1 | Accounts business logic resides in Services and Repositories, never in Controllers or Actions | Scoped context architectural style (ADR 0006) | `tests/Arch/AccountsArchTest.php` |
| 2 | Domain services never import HTTP primitives | Domain layer isolation from transport | `tests/Arch/AccountsArchTest.php` |
| 3 | Domain services depend on UserRepositoryInterface, never UserRepository | Inversion of control and repository testability | `tests/Arch/AccountsArchTest.php` |
| 4 | Superadmin accounts cannot be created, promoted, or deleted via REST API | Protect administrative access from API privilege escalation | `tests/Feature/Accounts/UserControllerTest.php` |
| 5 | Role column is constrained to superadmin, retailer, and customer | Prevent invalid role assignment in persisted state | `tests/Feature/Accounts/UserRoleTest.php` |
| 6 | Soft delete, password changes, and role changes revoke all user tokens atomically | Prevent stale credential usage after account mutation (ADR 0009) | `tests/Feature/Accounts/UserTokenRevocationTest.php` |
| 7 | Registration always assigns customer role regardless of payload | Prevent unauthorized privilege self-assignment | `tests/Feature/Accounts/AuthEndpointsTest.php` |
| 8 | External identifier is public_id ULID; internal autoincrement ID is never exposed | Opaque external references (DB5) | `tests/Feature/Accounts/UserControllerTest.php` |

## Changing it

- **Adding a role**: Add enum case to `UserRole`, update `users_role_check` constraint via new additive migration, update `UserPolicy` authorization matrix, and add test cases in `UserRoleTest`.
- **Modifying token lifetime**: Adjust `PASSPORT_ACCESS_TOKEN_TTL_SECONDS` and `PASSPORT_REFRESH_TOKEN_TTL_DAYS` in `.env.example` and test via `PassportSetupTest`.
- **Extending user fields**: Add nullable column via additive migration, update `UserData` DTO, add validation in `StoreUserRequest` / `UpdateUserRequest`, and update `UserResponseData`.

## Running and testing

Always execute `php artisan config:clear` before running tests against PostgreSQL:
```powershell
php artisan config:clear
vendor/bin/pest tests/Feature/Accounts
vendor/bin/pest tests/Feature/Contract
vendor/bin/pest tests/Arch/AccountsArchTest.php
```

## Known limits and open questions

- `POST /api/v1/users` can persist users with the retailer role, but retailer company profiles are not yet modeled in the database.
- Admin panel access (`canAccessPanel`) is controlled strictly by the `is_platform_admin` boolean flag, not the `role` enum.
- Email verification and password resets are not implemented on the API.

## History

- [ADR 0006](../adr/0006-context-architectural-styles.md): defined context architectural scoping for Accounts using Services, Repositories, Contracts, and DTOs.
- [ADR 0007](../adr/0007-passport-token-issuance-and-refresh-rotation.md): installed Laravel Passport, registered `TokenServiceProvider`, and imported token issuance and rotation stream.
- [ADR 0008](../adr/0008-user-soft-deletes-and-schema-cleanup.md): established soft delete support on users and schema reconcile conventions.
- [ADR 0009](../adr/0009-token-revocation-on-account-changes.md): established token revocation triggers and transactional fail-closed boundaries on account changes.
