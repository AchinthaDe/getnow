[P1-DOC2 | BRIEF | Claude]

Goal
Make AGENTS.md, ARCHITECTURE.md and the step workflow work with the area-guide system (docs/README.md and docs/areas/).
- AGENTS.md becomes the single home of the rule IDs and gains documentation rules.
- The workflow stops carrying its own rule register and builds guide reading, guide updates and trigger checks into the step lifecycle.
- ARCHITECTURE.md gets a pointer and two corrected decisions.
Docs only.

Depends on: P1-DOC1a ACCEPTED and merged to main.

Read first
AGENTS.md, ARCHITECTURE.md, the workflow doc (current file: docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md), docs/README.md, docs/areas/*.md, docs/adr/0001, 0003, 0004, docs/blueprints/rule-register.md (read only).
For D8 and D9 also read PublishContentBlock, EnableContentBlock, UpdateContentBlock, GetLayoutContent and UpdateMenu. Code wins over prose.

In scope
1. AGENTS.md edits (A1 to A7 below).
2. Workflow edits (W1 to W11 below), then git mv to docs/workflow.md.
3. ARCHITECTURE.md edits (R1 to R4 below).

Out of scope
- Any other ARCHITECTURE.md change. The Phase 1 progress list, the section 4 demo note, the section 5 tree and all slimming stay for P1-DOC3.
- docs/README.md, any guide, any ADR, docs/blueprints/** (rule-register.md stays until P1-DOC3), .gitignore.
- Making rules context-agnostic ("Content" wording): P1-SCALE1.
- Changing the meaning of any rule. Any code, config, test or dependency.
- The workflow's section 3 artifact-location line and section 11 checkbox bullet (P1-DOC3).

Decisions made (do not re-decide)
- Rule IDs live in AGENTS.md only, as a bold prefix: "- **S1** Business logic lives ...". Rule wording stays untouched, except X7 (A5).
- ID set: S1-S7, C1-C8, K1-K9, DB1-DB6, SEC1-SEC5, T1-T5, G1-G3, X1-X7, E1, plus new DOC1-DOC4.
- Map each ID to its AGENTS.md bullet using the old register (section 4 of the workflow on main).
- Where one bullet carries two IDs, split it at the sentence boundary with the wording unchanged.
- A bullet with no register ID (workflow bullets, Step protocol) gets none.
- A register ID with no AGENTS.md bullet goes to plan section 13. Do not invent a bullet.
- The workflow's compressed register text is not canonical. AGENTS.md wording is.

A1  Intro: after "Read ARCHITECTURE.md before any structural change." add "Before changing an area, read its guide (see Documentation)."
A2  Workflow item 1: "Read the relevant part of ARCHITECTURE.md, the owning area guide and the nearby code before editing."
A3  Workflow item 5: "Update the owning area guide when behavior, invariants, tables or limits change. Update ARCHITECTURE.md only for map-level changes. Add an ADR in docs/adr/ for significant decisions."
A4  New "## Documentation" section after Workflow, exactly:
    Map: ARCHITECTURE.md (phases, stack, structure, decisions), docs/README.md (area map), docs/areas/<area>.md (one guide per area), docs/adr/, docs/contracts/, docs/workflow.md (step process).
    - **DOC1** Before changing files in an area, read its guide. docs/README.md maps areas to guides.
    - **DOC2** A change to files matching a guide's `paths:` updates that guide in the same PR. If a `triggers:` entry matches, answer its checklist in the PR description.
    - **DOC3** Guides state only what is true now: no step IDs, plans, or copies of code, schemas or contract payloads. Each invariant names the test that enforces it, or `Review only`.
    - **DOC4** ARCHITECTURE.md changes only for map-level facts: phases, stack, code structure, dependency rules, decisions. If a guide and the code disagree, the code wins and the guide is fixed. If a guide conflicts with a rule here or in ARCHITECTURE.md, stop and ask.
A5  X7 becomes: "Owning area guides, docs/contracts/content-api.md and .env.example updated where relevant (DOC1 to DOC4)."
A6  Definition of done: replace the line "ARCHITECTURE.md, docs/contracts/content-api.md, .env.example updated where relevant" with "Owning area guide(s) updated and trigger checklists answered; ARCHITECTURE.md only for map-level changes; docs/contracts/content-api.md and .env.example where relevant". Every other checkbox stays.
A7  Step protocol: add "- Step artifacts (brief, plan, reports) are working files, not product docs. Lasting facts go into the owning guide. The step process is in docs/workflow.md."

W1  Header: add "Product docs: docs/README.md. Read the guide of every area a step touches."
W2  Section 2: delete the "Proposed remainder of Phase 1" table and the line after it. Keep the ID format.
W3  Section 4: replace the table with: "Plans and reviews cite the rule IDs defined in AGENTS.md (S, C, K, DB, SEC, T, G, X, E, DOC). The plan's rule matrix has one row per ID in AGENTS.md."
W4  Section 5 (Brief): "Read first" adds the area guides. Add the field "Guides to update: guide files and what changes, or None".
W5  Section 6 (Plan): section 2 includes the owning guide of every manifest file (docs/README.md map). Section 4 adds a column "Owning guide". Section 11 becomes: exact edits to each owning guide (section and new text); trigger checklists answered for every matching `triggers:` entry; ARCHITECTURE.md only for map-level changes; content-api.md, fixture, .env.example, ADRs; or None with reason.
W6  Section 7 (Plan review): checklist item 8 adds "owning guides identified, exact guide edits listed, trigger checklists answered, no step IDs or plan prose destined for a guide".
W7  Section 9 (IMPL-REPORT): item 7 becomes "Docs diffs: each owning guide, ARCHITECTURE.md (map-level only), content-api.md, fixture, .env.example, ADRs; raw output of every edited guide's tests: commands; trigger checklist answers".
W8  Section 10A: after item 11 add "(a) Select-String -Path docs/areas/*.md -Pattern 'P1-[A-Z]+[0-9]|blueprint|\.php:\d+' (expect none, DOC3)" and "(b) every changed file that matches a guide's paths has that guide in git diff --name-status (DOC2)".
W9  Section 10B: add the bullet "guides changed in the diff are reviewed as deliverables: statements checked against code, Enforced by names a test or Review only, no plan prose, D numbers and rule IDs resolve".
W10 Section 11 (step closeout): add "guides updated in the step's PR (DOC2); lasting facts from the closeout note go into the guide's Known limits or History".
W11 Section 12 (prompts): Plan request now begins "Read AGENTS.md, ARCHITECTURE.md and docs/README.md, then the guides the brief names, then the Step Brief below." The Implementation prompt adds "Update the guides named in plan section 11 in the same PR." Delete section 13. git mv the file to docs/workflow.md and update every reference to the old name in tracked files outside docs/blueprints.

R1  ARCHITECTURE.md header: add "Area detail lives in the guides under docs/areas/ (index: docs/README.md). Rules and their IDs live in AGENTS.md. The step process is in docs/workflow.md."
R2  D8: reword to state, as the code and ADRs say, the read-time tie-break and maxCount slice (ADR 0001) and the write-time live-capacity enforcement (ADR 0004). Keep ID D8 and "One block = one message" if still true.
R3  D9: content blocks last-write-wins for payloads and pessimistic locking for state transitions (ADR 0003). Menus: the submitted tree is reconciled in one transaction under a row lock. Items matched by public_id update in place, new items are inserted, omitted items are deleted. Last-write-wins. Verify each clause in code.
R4  Section 5 docs line becomes "docs/{adr,areas,contracts}/   # areas = area guides (index docs/README.md); contracts/content-api.md is the API contract".

Plan relaxations (docs only)
- Sections 5, 6, 7, 10 and 14 are one line: "N/A, docs only".
- Section 8 lists only the IDs that apply.
- Section 3 quotes the current text at each edit location.
- Section 11 is the real plan: exact new text for every edit above, plus the ID mapping table (ID | old register text | AGENTS.md bullet text, both quoted).

Acceptance criteria
AC-1  git diff --name-status main...HEAD shows exactly: M AGENTS.md, M ARCHITECTURE.md, R docs/GetNow CMS Step Workflow (...).md to docs/workflow.md.
AC-2  Every rule bullet in AGENTS.md carries its ID. Raw output of a script comparing the ID set in AGENTS.md with the register's ID set from git show on main. They are equal, plus DOC1 to DOC4. No ID appears twice.
AC-3  Rule wording is unchanged, except X7 and bullets split at a sentence boundary. Proof: git diff -U0 for AGENTS.md reviewed hunk by hunk in the report; every removed line reappears with only an ID prefix.
AC-4  A1 to A7 are applied with exactly the text above. AGENTS.md is at most 160 lines.
AC-5  W1 to W11 are applied. The old register table, the remainder table and section 13 are gone. docs/workflow.md is at most 365 lines. Raw Select-String proof for each removal and addition.
AC-6  git diff -U0 main...HEAD -- ARCHITECTURE.md shows exactly 4 hunks (R1 to R4). D8 and D9 are quoted against the ADR or code lines that support each clause.
AC-7  No stale reference. Every docs/ path and every rule ID mentioned in AGENTS.md, ARCHITECTURE.md, docs/workflow.md and docs/areas/*.md exists. Raw script output. Include the IDs cited by the guides: K1, K2, K3, K4, K7, K8, SEC4, SEC5, DB4, C5, C6, S2.
AC-8  Nothing outside the 3 files changed. git status --short shows only the pre-existing untracked paths.
AC-9  composer check passes and its output was read.

Rules most at risk: X4, G1 to G3, E1, the evidence rule, and "do not change the meaning of any rule".

Known traps
- ID prefixes must not alter wording or order. Where the register is a compressed paraphrase, the AGENTS.md text stays.
- D8 and D9 wording comes from code and ADRs, not from the direction above. If the code disagrees with the direction, the code wins. If the fix needs more than D8 and D9, send a CR.
- Do not touch the progress checklist, the demo note, the section 5 tree or any other ARCHITECTURE.md text.
- Use git mv so the rename is detected. List the references to the old file name found in tracked files, and any you left under docs/blueprints.
- Stage by explicit path. Do not stage the untracked docs/blueprints/** directories or p1-ui7.diff.
- Branch chore/docs-process-integration.

Size guard: 3 files. Split trigger: the D8 or D9 reading shows the code differs from the ADRs in more than wording. Stop and send a CR.

Manual UI script: none.

Step-specific checks (Layer A)
1. git diff --name-status main...HEAD
2. git diff main...HEAD --stat
3. the ID comparison script and its output (AC-2)
4. git diff -U0 main...HEAD -- ARCHITECTURE.md
5. line counts of AGENTS.md and docs/workflow.md
6. Select-String for the old workflow file name across tracked files
7. Select-String -Path AGENTS.md -Pattern 'P1-[A-Z]+[0-9]' (expect none)
8. composer check