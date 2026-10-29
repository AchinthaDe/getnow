# 0006: Context Architectural Styles and Phase 3 Scoping

Date: 2026-10-29

## Status
Accepted (Supersedes nothing)

## Context
Phase 1 established an Action-oriented architecture for the Content management context: all business logic resides in single-purpose invokable Action classes under `app/Domain/Content/Actions/` (rule S1).

Phase 3 introduces the Accounts module, merged from `getnow-auth2`. The Accounts module was designed, reviewed, and accepted under a Controller, Service, Repository, and Repository Interface pattern. Rewriting accepted Accounts code into Actions introduces regression risk, while forcing Services and Repositories onto other contexts contradicts the established simplicity of the CMS.

## Decision
1. Architectural patterns are scoped per bounded context:
   - **Accounts context** (`App\Domain\Accounts`, `App\Http\Controllers\Api\V1\{Auth,Users}`, `App\Http\Requests\{Auth,Users}`): Uses Controller, Service, Repository, and Repository Interface. Controllers call Services; Services coordinate business logic and depend on repository interfaces (`UserRepositoryInterface`); Repositories implement data access. Token issuance uses `PassportTokenIssuer` under `App\Domain\Accounts\Tokens` implementing contract `TokenIssuer`.
   - **Content context** (`App\Domain\Content`, `App\Http\Controllers\Api\V1\Content`): Retains Actions. Business logic resides in `app/Domain/Content/Actions/`, one invokable class per use case.
   - **Catalog and Retail contexts** (subsequent Phase 3 steps): Retain Actions. Business logic resides in `app/Domain/<Context>/Actions/`.
2. Rule S1 is reworded to reflect this per-context scoping.
3. Architecture tests in `tests/Arch/` are scoped per context: Content rules govern `App\Domain\Content`, while Accounts rules govern `App\Domain\Accounts`.
4. Accounts is formally recognized as active within Phase 3. Catalog and Retail remain forbidden under rule X4 until their respective implementation steps.
5. Context boundaries adhere to ARCHITECTURE.md dependency rule 5: contexts interact through Actions (or Services in Accounts), events, and contracts, never each other's models or repositories. Accounts exposes its Services (`UserService`, `AuthService`), DTOs/enums, and the `TokenIssuer` contract to other contexts—never `UserRepository` or `UserRepositoryInterface`.

## Consequences
- Accepted Accounts code merges without structural redesign or logic rewrites.
- Content, Catalog, and Retail remain clean, lightweight, and Action-based.
- Architecture tests evaluate each context against its defined structural rules.
- Rule S1 and rule X4 in AGENTS.md remain clear and enforceable.
