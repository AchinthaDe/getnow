# AGENTS.md

Instructions for AI coding agents working in this repository. Read `ARCHITECTURE.md` before any structural change. If this file and `ARCHITECTURE.md` disagree, stop and ask.

## Project

Laravel backend for a wholesale mobile and electronics marketplace. It currently contains only the **content management system**: admins edit storefront content in Filament, and storefronts read it from a versioned API. The storefront is a separate repository and is out of scope here.

Active scope: **Phase 0 (bootstrap) and Phase 1 (layout CMS)**, as defined in `ARCHITECTURE.md`. Do not build, scaffold or add packages for later phases (accounts, catalog, retail, sales, payments, chat, React). If a task seems to need them, ask.

## Environment

- Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`.
- PHP, Composer, Node and PostgreSQL are installed natively. No Docker, Sail or containers.
- Use non-interactive flags (`--no-interaction`) where a command supports them. Do not leave long-running servers or watchers running when you finish.

## Commands

| Task | Command |
|---|---|
| Install | `composer install` |
| Lint (check) | `composer lint` |
| Lint (fix) | `composer fix` |
| Static analysis | `composer stan` |
| Tests | `composer test` |
| All checks | `composer check` |
| Migrate | `php artisan migrate` |
| Reset local dev DB | `php artisan migrate:fresh --seed` (local `phone_store` database only) |
| Dev server | `php artisan serve` |

Before declaring any task done, `composer check` must pass and you must have read its output. Do not skip, weaken or delete checks to get green.

## Workflow

1. Read the relevant part of `ARCHITECTURE.md` and the nearby code before editing.
2. For anything touching more than three files, adding a dependency, or changing structure: write a short plan and wait for approval before coding.
3. Keep changes small: one concern per change. Do not mix refactors with features.
4. Write or update tests with the code. Bug fixes start with a failing test.
5. Update `ARCHITECTURE.md` when you change structure, flows, tables or the API contract. Add an ADR in `docs/adr/` for significant decisions.
6. If a requirement is ambiguous, missing, or conflicts with `ARCHITECTURE.md`, ask. Do not invent requirements.

## Where code goes

- Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too.
- Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output.
- `app/Domain` must never import from `App\Filament` or `App\Http`.
- Filament never calls `Model::create/update/delete` directly. It calls Actions.
- Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`.
- Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths.
- Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder.

## Coding standards

- `declare(strict_types=1);` in every PHP file.
- Type every parameter, return value and property. Prefer `final` classes.
- Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers.
- Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field.
- The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action.
- Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API.
- Follow Pint and Larastan. Do not add baseline entries without approval.
- Comments explain why, not what. No commented-out code.

## Content rules (critical)

- Block type keys are permanent. Never rename one.
- Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load.
- No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans.
- Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme.
- Placements, menu keys and block types are defined in code. Admins never create them.
- Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery.
- Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content.
- The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change.
- Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps.

## Database rules

- PostgreSQL only. Tests run against Postgres, never SQLite.
- Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration.
- No destructive changes (drop or rename column or table) in the same release that stops using them.
- Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers.
- Expose `public_id` (ULID) externally, never raw incremental IDs.
- Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases.

## Security

- Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`.
- Do not disable authorization, policies, strict types or static analysis rules to make code pass.
- Do not log sensitive data.
- Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization.
- Write sensitive content changes (publish, disable, menu and settings edits) to the activity log.

## Testing

- Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`.
- Every Filament resource has a smoke test that loads list, create and edit pages.
- Every block type has tests for payload validation, API output and visibility rules.
- Use factories, not hand-built rows. Tests must be independent and deterministic.
- Do not lower coverage, skip tests or mark them incomplete to pass checks.

## Git

- Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`.
- Conventional Commits: `feat(content): add announcement bar block`.
- Never push to `main` directly or bypass git hooks.
- PR description: what changed, why, how it was tested, and risks (migrations, API contract, security).

## Do not

- Add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval.
- Introduce Docker, Sail, docker-compose or any container configuration.
- Add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments.
- Change deployment or infrastructure configuration as a side effect of a feature.
- Put business logic in controllers, Filament resources, model boot methods or observers.
- Modify the storefront repository or assume its internals. The API contract is the only interface.
- Create folders, contexts or tables for later phases.
- Make destructive file or database operations outside migrations and the local reset command above.

## Definition of done

- [ ] `composer check` passes and the output was read
- [ ] Tests added or updated, including failure and authorization cases
- [ ] `tests/Arch` passes
- [ ] Migrations are backward compatible
- [ ] `ARCHITECTURE.md`, `docs/contracts/content-api.md`, `.env.example` updated where relevant
- [ ] Nothing outside the active phase was added
