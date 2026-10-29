[P1-DOC1a | BRIEF | Claude]

Goal
Create the foundation of the documentation system: docs/README.md, which defines the area-guide format and maps all areas, and the first four area guides (content-blocks, menus, site-settings, content-api), written to the format of the re-attached reference-guide.md. Guides hold what code cannot say: why, invariants, how to change safely, known limits. They never copy what code, migrations or contracts already state. Docs only. No existing file is modified or deleted.

Depends on
P1-DOC0 AUDIT-REPORT sections 4, 5, 6, 7 and 9, with the corrections in this brief.

Read first
AGENTS.md, ARCHITECTURE.md (all), docs/adr/0001 to 0005, docs/contracts/content-api.md, the P1-DOC0 AUDIT-REPORT, reference-guide.md (re-attached), and every file a guide cites. Read the code; do not rely on the audit or on ARCHITECTURE.md prose. Where they disagree with the code, the code wins and the conflict goes in plan section 13.

In scope
- docs/README.md (new, max 60 lines): how guides work, the front matter and section skeleton, the nine-area map table (area | status | guide file | one-line scope), the update rule.
- docs/areas/content-blocks.md, menus.md, site-settings.md, content-api.md (new).

Out of scope
- Editing, moving or deleting any existing file (ARCHITECTURE.md, AGENTS.md, the workflow doc, blueprints, ADRs). Temporary duplication with ARCHITECTURE.md is accepted until P1-DOC2.
- Guides for admin-panel, auth-scaffold, data, quality, demo-storefront (P1-DOC1b).
- Any code, test, config or dependency change.
- Any guide, folder or text for Catalog, Retail or other later phases (X4).
- Fixing any audit finding.

Decisions made (do not re-decide)
- Nine areas: content-blocks, menus, site-settings, content-api, admin-panel, auth-scaffold, data, quality, demo-storefront. Two differ from the audit. site-settings is its own area: StorefrontSettings, UpdateStorefrontSettings, its Data class, its Filament page, database/settings migrations and their tests. auth-scaffold is the Phase 0 starter-kit auth code (Fortify, Livewire, Flux views, passkeys, users table), marked temporary because a separate auth module will replace it.
- Sources of truth stay where they are: the Placement enum for placements, docs/contracts/content-api.md for the JSON shape, migrations for the schema. Guides link to them and never copy tables of them.
- The D1 to D9 table stays in ARCHITECTURE.md. Guides cite D numbers and ADR links and restate no decision text beyond one clause.
- Guides cite classes and methods, never line numbers.
- Facts true now only. No step IDs, no "added in", no plans. Future-phase mentions are allowed only inside Known limits and History.
- Guide format: front matter (kind: normative, area, status, summary, paths, decisions, depends-on, tests, triggers), then intro (2 to 4 lines), How it works now, Invariants (table: # | Rule | Why | Enforced by, max 8 rows), Changing it, Running and testing, Known limits and open questions, History. Max 150 lines per guide excluding front matter.
- "Enforced by" names a test file path (and test name where useful) or the literal `Review only`. A source file alone is not an enforcer.
- A trigger's `reconsider` is a checklist of things to verify, not a description.
- Branch chore/docs-area-guides. Commits use Conventional Commits, type docs.

Plan relaxations (docs-only step)
Plan sections 5, 6, 7, 10 and 14 may each be one line: "N/A, docs only". Section 3 lists, per guide, the classes, test files and enforcers it will cite, verified by reading. Section 8 lists only the rule IDs that apply. Section 12 gives the commit order, one commit per guide.

Acceptance criteria
AC-1  Only these 5 files are added and nothing is modified or deleted: docs/README.md and the four guides. (git diff --name-status main...HEAD)
AC-2  Each guide's front matter has every key above and at least 2 triggers, each with paths and reconsider.
AC-3  Every glob in every guide's paths: matches at least one existing file, and no file is matched by two of the four guides. Raw output of a throwaway check script; show the script, do not commit it.
AC-4  Every command in each guide's tests: runs and passes. Raw output.
AC-5  Every invariant's Enforced by follows the definition above. Each `Review only` row is listed under Gaps in the IMPL-REPORT.
AC-6  Every statement in How it works now and Invariants has a claim-source row in the IMPL-REPORT: claim | file and class::method | read this session. Claude spot-checks 10.
AC-7  Each guide has all seven sections and at most 150 lines. No match for P1-[A-Z]+[0-9], blueprint, or \.php:\d+ anywhere in docs/areas.
AC-8  Known limits contain these facts, each re-verified in code:
      content-blocks: policies check only isAdmin and have no owner scope; advisory-lock SQL is inline in three Actions with CRC32 keys; the preview controller hardcodes panel admin.
      menus: items link by plain URL (D4); UpdateMenu recreates all items with new public_ids on every save.
      content-api: the rate limiter is registered for content-api only; no caching beyond ETag; give a per-request query count only if an existing test asserts it, otherwise leave it out.
      site-settings: what you find, or "None found".
AC-9  menus.md fixes the audit draft: tests as enforcers (MenuPolicyTest, the UpdateMenu rejection tests, a link-safety test), D4 worded as "the current decision; replacing it needs an ADR", History linking the migration and ADR files by relative path.
AC-10 docs/README.md lists all nine areas, marks the five unwritten ones "planned (P1-DOC1b)", and states the rule: a change to files matching an area's paths updates that area's guide in the same PR.

Rules most at risk: X4 (no later-phase content), E1, G1 to G3, the evidence rule.

Known traps
- Do not stage the untracked docs/blueprints/P1-DOC0/, docs/blueprints/P1-UI7/ or p1-ui7.diff. Stage by explicit path only; never git add .
- Do not commit reference-guide.md.
- Do not copy ARCHITECTURE.md paragraphs. Write from the code.
- Check that `composer test` forwards arguments before putting it in Running and testing; otherwise use vendor/bin/pest --filter=...
- content-blocks owns about 134 files by glob. The guide documents rules, not files.
- Invariants are rules whose breakage would hurt, not descriptions of behavior.

Size guard: 5 files, docs only. If a guide cannot fit in 150 lines, stop and raise a CR; do not drop invariants to fit.

Manual UI script: none.

Step-specific checks (Layer A, after the standard ones)
1. git diff --name-status main...HEAD
2. Line count per guide, one command at a time
3. Select-String for the AC-7 patterns over docs/areas (expect none)
4. The AC-3 glob script and its output
5. Each tests: command from the guides