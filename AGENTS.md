# AGENTS.md

Instructions for AI coding agents working in this repository. Read `ARCHITECTURE.md` before any structural change. Before changing an area, read its guide (see Documentation). If this file and `ARCHITECTURE.md` disagree, stop and ask.

## Project

Laravel backend for a wholesale mobile and electronics marketplace. It currently contains only the **content management system**: admins edit storefront content in Filament, and storefronts read it from a versioned API. The storefront is a separate repository and is out of scope here.

Active scope: **Phase 0 (bootstrap), Phase 1 (layout CMS), and Phase 3: Accounts, then Catalog and Retail**, as defined in `ARCHITECTURE.md`. Accounts is permitted from P3-ACC1 onward; Catalog and Retail remain forbidden under X4 until their respective implementation steps. Do not build, scaffold or add packages for later phases (sales, payments, chat, React). If a task seems to need them, ask.

## Environment

- **E1** Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`.
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

1. Read the relevant part of `ARCHITECTURE.md`, the owning area guide and the nearby code before editing.
2. For anything touching more than three files, adding a dependency, or changing structure: write a short plan and wait for approval before coding.
3. Keep changes small: one concern per change. Do not mix refactors with features.
4. Write or update tests with the code. Bug fixes start with a failing test.
5. Update the owning area guide when behavior, invariants, tables or limits change. Update ARCHITECTURE.md only for map-level changes. Add an ADR in `docs/adr/` for significant decisions.
6. If a requirement is ambiguous, missing, or conflicts with `ARCHITECTURE.md`, ask. Do not invent requirements.

## Documentation

Map: ARCHITECTURE.md (phases, stack, structure, decisions), docs/README.md (area map), docs/areas/<area>.md (one guide per area), docs/adr/, docs/contracts/, docs/workflow.md (step process).

- **DOC1** Before changing files in an area, read its guide. docs/README.md maps areas to guides.
- **DOC2** A change to files matching a guide's `paths:` updates that guide in the same PR. If a `triggers:` entry matches, answer its checklist in the PR description.
- **DOC3** Guides state only what is true now: no step IDs, plans, or copies of code, schemas or contract payloads. Each invariant names the test that enforces it, or `Review only`.
- **DOC4** ARCHITECTURE.md changes only for map-level facts: phases, stack, code structure, dependency rules, decisions. If a guide and the code disagree, the code wins and the guide is fixed. If a guide conflicts with a rule here or in ARCHITECTURE.md, stop and ask.

## Where code goes

- **S1** Business logic placement is scoped per context (ADR 0006). In `Content` (and later `Catalog`, `Retail`), business logic lives in `app/Domain/<Context>/Actions/`, one invokable class per use case (read use cases included). In `Accounts`, business logic lives in Services, Repositories, Tokens, and Contracts under `app/Domain/Accounts/{Services,Repositories,Tokens,Contracts}/`.
- **S2** Controllers, Filament resources and pages are thin: validate, authorize, call an Action (or a Service or `TokenIssuer` in `Accounts`), return output.
- **S3** `app/Domain` must never import from `App\Filament` or `App\Http`.
- **S4** Filament never calls `Model::create/update/delete` directly. It calls Actions.
- **S5** Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`.
- **S6** Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths.
- **S7** Put code in the correct place: Content in `app/Domain/Content/`, Accounts in `app/Domain/Accounts/{Contracts,Data,Enums,Exceptions,Repositories,Services,Tokens}/`, Accounts controllers in `app/Http/Controllers/Api/V1/{Auth,Users}/`, Accounts requests in `app/Http/Requests/{Auth,Users}/`. If nothing fits, ask before creating a new context or top-level folder.

## Coding standards

- **C1** `declare(strict_types=1);` in every PHP file.
- **C2** Type every parameter, return value and property. Prefer `final` classes.
- **C3** Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers.
- **C4** Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field.
- **C5** The 'call an Action' rule applies to every write to persisted state in Content, Catalog, and Retail, including Spatie settings. Pages that save settings call an Action. Accounts writes go through Services and Repositories per ADR 0006.
- **C6** Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API.
- **C7** Follow Pint and Larastan. Do not add baseline entries without approval.
- **C8** Comments explain why, not what. No commented-out code.

## Content rules (critical)

- **K1** Block type keys are permanent. Never rename one.
- **K2** Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load.
- **K3** No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans.
- **K4** Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme.
- **K5** Placements, menu keys and block types are defined in code. Admins never create them.
- **K6** Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery.
- **K7** Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content.
- **K8** The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change.
- **K9** Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps.

## Database rules

- **DB1** PostgreSQL only. Tests run against Postgres, never SQLite.
- **DB2** Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration.
- **DB3** No destructive changes (drop or rename column or table) in the same release that stops using them.
- **DB4** Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers.
- **DB5** Expose `public_id` (ULID) externally, never raw incremental IDs.
- **DB6** Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases.

## Security

- **SEC1** Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`.
- **SEC2** Do not disable authorization, policies, strict types or static analysis rules to make code pass.
- **SEC3** Do not log sensitive data.
- **SEC4** Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization.
- **SEC5** Write sensitive content changes (publish, disable, menu and settings edits) to the activity log.

## Testing

- **T1** Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`.
- **T2** Every Filament resource has a smoke test that loads list, create and edit pages.
- **T3** Every block type has tests for payload validation, API output and visibility rules.
- **T4** Use factories, not hand-built rows. Tests must be independent and deterministic.
- **T5** Do not lower coverage, skip tests or mark them incomplete to pass checks.

## Git

- **G1** Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`.
- **G2** Conventional Commits: `feat(content): add announcement bar block`.
- **G3** Never push to `main` directly or bypass git hooks.
- PR description: what changed, why, how it was tested, and risks (migrations, API contract, security).

## Do not

- **X1** Add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval.
- **X2** Introduce Docker, Sail, docker-compose or any container configuration.
- **X3** Add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments.
- Change deployment or infrastructure configuration as a side effect of a feature.
- Put business logic in controllers, Filament resources, model boot methods or observers.
- **X5** Modify the storefront repository or assume its internals. The API contract is the only interface.
- **X4** Create folders, contexts or tables for phases that are not active, or for Catalog and Retail prior to their designated steps.
- Make destructive file or database operations outside migrations and the local reset command above.

## Definition of done

- [ ] **X6** `composer check` passes and the output was read
- [ ] Tests added or updated, including failure and authorization cases
- [ ] `tests/Arch` passes
- [ ] Migrations are backward compatible
- [ ] **X7** Owning area guide(s) updated and trigger checklists answered; ARCHITECTURE.md only for map-level changes; docs/contracts/content-api.md and .env.example where relevant (DOC1 to DOC4)
- [ ] Nothing outside the active phase was added

## Step protocol

- Work only from an approved plan identified by step ID and version. No approved plan, no code.
- If the plan is wrong or incomplete, stop and write a Change Request. Never deviate silently.
- A step ends with an IMPLEMENTATION REPORT containing raw, unedited command output. Summaries are not evidence.
- Do not mark a step done. Only the reviewer accepts it.
- Step artifacts (brief, plan, reports) are working files, not product docs. Lasting facts go into the owning guide. The step process is in docs/workflow.md.
