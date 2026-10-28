# Documentation System

Normative area guides capture architectural rules, invariants, change workflows, and limits that code cannot express.

## Area Map

| Area | Status | Guide File | One-Line Scope |
|---|---|---|---|
| content-blocks | active | [content-blocks.md](areas/content-blocks.md) | Content block domain, registry, schemas, placements, serving health, and previews. |
| menus | active | [menus.md](areas/menus.md) | Navigation menus, tree caps, whole-tree updates, and Filament menu editor. |
| site-settings | active | [site-settings.md](areas/site-settings.md) | Storefront global scalar settings, Spatie settings repository, and Filament page. |
| content-api | active | [content-api.md](areas/content-api.md) | Public layout HTTP endpoint, rate limiting, and revision ETag caching. |
| admin-panel | planned (P1-DOC1b) | planned | Filament panel configuration, user authentication, and admin access control. |
| auth-scaffold | planned (P1-DOC1b) | planned | Temporary Phase 0 starter-kit authentication scaffolding and views. |
| data | planned (P1-DOC1b) | planned | PostgreSQL schema, database migrations, constraints, seeders, and conventions. |
| quality | planned (P1-DOC1b) | planned | Static analysis, architecture tests, Pint formatting, and Pest quality gates. |
| demo-storefront | planned (P1-DOC1b) | planned | Temporary local demo storefront harness and teardown procedures. |

## Guide Structure

Every area guide defines normative YAML front matter followed by an introduction and six sections:

```yaml
---
kind: normative
area: <area-slug>
status: active | planned
summary: <one-line summary>
paths: [<glob patterns owned by this area>]
decisions: [<decision IDs or ADR links>]
depends-on: [<area dependencies>]
tests: [<test commands validating enforcers>]
triggers:
  - paths: [<trigger globs>]
    reconsider: <actionable review checklist>
---
```

Sections:
1. Introduction (2 to 4 lines under `# <Title>`)
2. `## How it works now`
3. `## Invariants` (# | Rule | Why | Enforced by, max 8 rows)
4. `## Changing it`
5. `## Running and testing`
6. `## Known limits and open questions`
7. `## History`

## Update Rule

Any pull request modifying files that match an area's `paths:` globs must update that area's guide in the same pull request. When running test suites, always execute `php artisan config:clear` before `vendor/bin/pest <path>` to prevent cached environment configuration from targeting the development database.
