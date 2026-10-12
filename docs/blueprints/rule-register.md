# Rule Register

Source of truth for rule IDs and text across blueprints, plans, and reviews.

## Environment
- **E1**: Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`.

## Structure (Where code goes)
- **S1**: Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too.
- **S2**: Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output.
- **S3**: `app/Domain` must never import from `App\Filament` or `App\Http`.
- **S4**: Filament never calls `Model::create/update/delete` directly. It calls Actions.
- **S5**: Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`.
- **S6**: Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths.
- **S7**: Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder.

## Coding standards
- **C1**: `declare(strict_types=1);` in every PHP file.
- **C2**: Type every parameter, return value and property. Prefer `final` classes.
- **C3**: Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers.
- **C4**: Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field.
- **C5**: The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action.
- **C6**: Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API.
- **C7**: Follow Pint and Larastan. Do not add baseline entries without approval.
- **C8**: Comments explain why, not what. No commented-out code.

## Content rules (critical)
- **K1**: Block type keys are permanent. Never rename one.
- **K2**: Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load.
- **K3**: No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans.
- **K4**: Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme.
- **K5**: Placements, menu keys and block types are defined in code. Admins never create them.
- **K6**: Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery.
- **K7**: Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content.
- **K8**: The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change.
- **K9**: Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps.

## Database rules
- **DB1**: PostgreSQL only. Tests run against Postgres, never SQLite.
- **DB2**: Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration.
- **DB3**: No destructive changes (drop or rename column or table) in the same release that stops using them.
- **DB4**: Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers.
- **DB5**: Expose `public_id` (ULID) externally, never raw incremental IDs.
- **DB6**: Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases.

## Security
- **SEC1**: Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`.
- **SEC2**: Do not disable authorization, policies, strict types or static analysis rules to make code pass.
- **SEC3**: Do not log sensitive data.
- **SEC4**: Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization.
- **SEC5**: Write sensitive content changes (publish, disable, menu and settings edits) to the activity log.

## Testing
- **T1**: Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`.
- **T2**: Every Filament resource has a smoke test that loads list, create and edit pages.
- **T3**: Every block type has tests for payload validation, API output and visibility rules.
- **T4**: Use factories, not hand-built rows. Tests must be independent and deterministic.
- **T5**: Do not lower coverage, skip tests or mark them incomplete to pass checks.

## Git
- **G1**: Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`.
- **G2**: Conventional Commits: `feat(content): add announcement bar block`.
- **G3**: Never push to `main` directly or bypass git hooks.
- **G4**: PR description: what changed, why, how it was tested, and risks (migrations, API contract, security).

## Do not
- **X1**: Do not add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval.
- **X2**: Do not introduce Docker, Sail, docker-compose or any container configuration.
- **X3**: Do not add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments.
- **X4**: Nothing outside the active phase was added. Do not create folders, contexts or tables for later phases.
- **X5**: Do not modify the storefront repository or assume its internals. The API contract is the only interface.
- **X6**: Definition of done: `composer check` passes and the output was read.
- **X7**: Definition of done: `ARCHITECTURE.md`, `docs/contracts/content-api.md`, `.env.example` updated where relevant.
