[P1-M1 | BRIEF | Claude]

The brief.md file is stored at docs/blueprints/P1-M1/brief.md. Paste the whole file to Gemini with the "Plan request" prompt from the workflow doc, section 12. If OD-1 is accepted, this one file is the brief for all three parts, and each plan request names the part (P1-M1a, P1-M1b or P1-M1c) and plans only that part.

---

## Goal

Let admins edit the storefront navigation menus from the panel. The five menus (`main_nav`, `mobile_nav`, `footer_column_1`, `footer_column_2`, `footer_column_3`) are defined in code and always exist. An admin edits a menu's title and its items (label, link, new-tab flag, enabled flag) as a two-level tree and saves. Every save goes through one domain Action that validates the tree, replaces it atomically, keeps item ids stable and writes an activity log entry. After this step the menu data that Content API v1 needs is stored, validated, authorized, audited and editable without tinker. The public API output is not part of this step.

## Depends on

Accepted: P1-UI4 and P1-FIX1 (merged to main, confirmed by Achintha). Gemini records the head commit in the plan and verifies every name below by reading the file. A mismatch goes to plan section 13, not into the plan as fact.

Existing, reused as patterns:
- The block domain Actions in `app/Domain/Content/Actions`: how they lock (S1), log activity with the causer, and throw domain exceptions with safe messages (2b audit finding).
- The link validation used by the announcement bar payload (relative path starting with `/`, or `https://`). Menus use that same rule, not a second copy.
- `BaseContentPolicy`, `ContentBlockPolicy`, `isAdmin()`, `canAccessPanel`.
- `ContentServiceProvider` and wherever `ContentBlockPolicy` is registered.
- The `ContentBlockResource` folder pattern (Pages, Schemas, Tables), the `getUrl()` override and `getRecordRouteKeyName()` (CR-01, route key is `public_id`), the admin timezone config path used by the block List table, and the browser history convention from ARCHITECTURE.md section 8 as applied in P1-FIX1.
- `tests/Support/FilamentSmokeTest.php`, the CR-02 test-class pattern, the test database reset strategy (Pest.php / TestCase), the existing `tests/Arch` rules, and `BlockFormRegistryTest` (forbidden-component scan).

Expected not to exist yet: `Menu`, `MenuItem`, `MenuKey`, the two tables, anything menu related. ARCHITECTURE.md lists them as target structure only. If any exists, say so in plan section 13.

## In scope

R, data and read (P1-M1a):
1. One migration creating `menus` and `menu_items` exactly as in ARCHITECTURE.md section 6, plus the five provisioned menu rows (OD-2).
2. `MenuKey` enum (five keys), `Menu` and `MenuItem` models, factories.
3. Two read Actions with output Data classes: one menu's tree, and the list of all menus with item counts.
4. `MenuPolicy`, registered the same way as `ContentBlockPolicy`.
5. Tests for schema constraints, provisioning drift, reads and policy.

W, write (P1-M1b):
6. `UpdateMenu` Action with an input Data class and domain exception(s): validation, one transaction under a row lock, in-place item update, activity log entry.
7. Tests for the Action, every rejection path, rollback and logging.

U, UI (P1-M1c):
8. `MenuResource` with pages `index` and `edit` only, `MenuTable`, `MenuForm` schema, `EditMenu` page, a navigation entry next to Content Blocks.
9. Edit form: title, and an items repeater with a nested children repeater (two levels).
10. Tests for list, edit, save, rejection handling, authorization. Update the smoke test to cover the menu list and edit pages.
11. ARCHITECTURE.md at closeout of the last part: tick Menus, and one line in D9 for the menu write rule if OD-3 is accepted as recommended. No new ADR.

## Out of scope

- Content API endpoint, `GetLayoutContent`, the public projection (enabled-only output, ETag, revision hash, caching).
- Real menu content and `ContentSeeder`. The seed data step fills the menus. This step only provisions empty menu rows.
- Settings page, retailer panel, roles or permissions (X4).
- Per-item Actions, a `MenuItemResource`, relation managers, drag-and-drop trees, third-party tree or sortable packages, any new package.
- Creating or deleting menus, renaming a menu's key or label, restore, soft delete, bulk actions, import/export.
- Draft or publish states for menus, revisions, preview.
- Category pickers or any catalog integration (D4: menus link by URL).
- Showing the activity log in the UI.
- Any change to the block domain, `ContentBlockResource`, or the block Actions. If the shared link rule has a gap, raise a CR (trap 3), do not fork it.
- Migrations other than the one menu migration. Docker, pipeline, ADR changes.

## Read first

- ARCHITECTURE.md: sections 5 (dependency rules), 6 (menu tables, link rules), 7 (API shape the stored data must serve), 8 (Filament conventions, history convention), 9 (placeholders, CHECKs), 10 (menu edits are logged), 12 (D2, D4, D6, D9)
- AGENTS.md: Where code goes, Definition of Done, Step protocol
- Workflow doc: sections 4 (rule register), 6 (plan template), 10 (verification)
- docs/adr/ entries on serving health and unservable-payload rejection (for the exception and message conventions)
- docs/blueprints/P1-UI3/, P1-UI4/ and P1-FIX1/: plans, change requests, implementation reports
- The block Actions, the link rule, the policies, `ContentServiceProvider`, the test setup
- Every file listed under "Depends on"

## Decisions made (Gemini must not re-decide)

1. Menu keys are code (D2). Admins cannot create or delete menus. There is no Create page. `label` is set at provisioning and is not editable. `title` is editable, nullable, plain text.
2. Menus link by URL (D4). A link is a relative path starting with `/`, or an `https://` URL, max 2048. Reject `javascript:`, `data:`, `http://`, `mailto:`, protocol-relative `//host`, backslashes, and anything else. The Action never trims or rewrites a URL, it accepts or rejects. The form may trim whitespace on input. The existing announcement bar rule is reused.
3. "Maximum depth 2" means two levels: top-level items, each with children, and children cannot have children. Enforced in the Action. The UI offers no third level.
4. One write path. `UpdateMenu` takes a menu and the full submitted tree and replaces it. No per-item Actions in this step.
5. Existing items are matched by `public_id` and updated in place, so ids exposed by the API stay stable. New items get new ULIDs. Items missing from the submission are hard deleted. Items may move between parents within the same menu. A submitted `public_id` that does not belong to this menu is a rejection, never adopted.
6. `sort_order` is derived from array position on save (0..n-1). There is no sort_order input.
7. The domain is the authority on validation (K7, C4). The form validates for usability using the same rule classes. Filament holds no business rule (ARCHITECTURE.md section 5, rule 2).
8. Domain rejections reach the user as a danger notification with fixed copy. Exception messages never reach the user (SEC3).
9. Filament never persists tree data itself. No `->relationship()` on any component, no model writes, no diffing in the page class (S2, S4, C5). The repeater state is a plain array, hydrated from the read Action, saved through `UpdateMenu`.
10. `MenuPolicy` abilities answer who, not what. `viewAny`, `view`, `update`: platform admin only. `create`, `delete`, `deleteAny`, `forceDelete`: false for everyone including admins (D2). Check what `BaseContentPolicy` returns by default and override explicitly.
11. A disabled parent hides its children in the public projection. Decided now so the Content API step follows it. Not built here.
12. Every successful save writes exactly one activity entry on the menu, with the admin as causer, written by the Action. Filament never logs (SEC5). No no-op detection in phase 1. Properties must be enough to tell what changed. With the caps in OD-5, a before/after tree is acceptable. Gemini proposes the shape, following the block entries' convention.
13. Test conventions mirror UI3 and UI4: factories and states, CR-02 test-class pattern, no Larastan baseline, no loosened scan.
14. Schema is exactly ARCHITECTURE.md section 6. No `created_by` or `updated_by` on menus (the activity log is the audit trail). Column types the section leaves open (`title`, `label` lengths) are chosen by Gemini in the plan and must match the validation limits (label 1 to 80).

## Open decisions (Achintha answers before G1)

- OD-1 Split. This brief has 14 ACs and about 30 files across the three parts, far over the workflow guideline of 10 and 10, and the data and write parts alone pass the hard trigger of 15 if combined. Each AC is tagged R, W or U. Recommendation: three steps, P1-M1a (R, about 13 files), P1-M1b (W, about 7 files), P1-M1c (U, about 10 files), in that order. W carries the data-integrity risk and needs the mutation checks. Alternative: two steps (R+W, then U), which breaks the hard trigger on the first. A single step is not offered.
- OD-2 Provisioning of the five menu rows. (A) The migration inserts one row per `MenuKey` with a fixed `label` and null `title`, matching ARCHITECTURE.md section 9 ("real environments start on migration placeholders"). A test fails if an enum case has no row. (B) Rows are created lazily on first save, and the list page synthesizes missing menus. Recommendation: A, because the Filament list needs real rows and B adds a code path that exists only for the empty case. Gemini must check that the test database strategy keeps migration-inserted rows (trap 1).
- OD-3 Concurrency on save. (A) Whole-tree replace under a row lock in the domain, last-write-wins on content, consistent with D9 for payloads. If two admins edit the same menu, the later save overwrites the earlier one. (B) Add an optimistic version token and reject stale saves. Recommendation: A for phase 1 (single platform admin flag, D6). B is a small follow-up if it ever bites. Under A, the manual script records the overwrite as known behavior, not a bug.
- OD-4 Icons. The schema has `icon` varchar(64) null "from an allowed list", but no list exists. Question for Achintha: which menu entries in the current storefront layout show an icon? If none, answer "defer". Recommendation: defer. The column exists, the Action accepts only null, there is no icon input, and the allowed list is added in the step that needs it.
- OD-5 Caps. Items per menu need limits to protect the API payload and the form. Recommendation: 10 top-level items and 10 children per parent, as named constants in the domain, enforced in the Action, mirrored as repeater limits, tested at the boundary and one past it.

## Acceptance criteria

R: data and read. W: write. U: UI.

- AC-1 (R) `menus` and `menu_items` match ARCHITECTURE.md section 6 (columns, ULID unique `public_id`, unique `key`, FKs including the self FK `parent_id`, CHECK `sort_order >= 0`, no other columns). Tests prove the database rejects each violation directly (duplicate key, negative sort_order, orphan menu_id).
- AC-2 (R) `MenuKey` has exactly the five keys. After migration every key has a menu row, and a test fails if an enum case has no row. Factories never collide with provisioned rows.
- AC-3 (R) The read Action returns one menu's title and tree as Data: ordered by `sort_order`, children nested under parents, disabled items included. A second read returns all menus with item counts, in a stable order. Reads write nothing and log nothing. The query count for a tree does not grow with the number of items (asserted).
- AC-4 (R) `MenuPolicy` abilities exist as in decision 10: true for the platform admin on `viewAny`, `view`, `update`, false for a non-admin, and false for everyone on `create`, `delete`, `deleteAny`, `forceDelete`. The policy contains no other logic.
- AC-5 (W) `UpdateMenu` replaces a menu's title and tree in one transaction under a row lock, per decisions 4 to 6: in-place update by `public_id`, new ULIDs for new items, hard delete for omitted items, `sort_order` from position, moves between parents. It returns the saved tree. `key` and `label` cannot change. A test follows the locking-test pattern used for block transitions.
- AC-6 (W) The Action rejects with a domain exception, leaves the database unchanged and writes no activity entry for each of: label empty or over 80, url failing decision 2 (assert `javascript:`, `JaVaScRiPt:`, `//evil.com`, `/\evil.com`, `http://x`, `data:`, `mailto:`, over 2048), more than two levels, over the caps, non-null icon (OD-4), a `public_id` from another menu, a duplicate `public_id` in the submission, a `parent` reference that does not exist in the submission.
- AC-7 (W) Atomicity: a failure injected after some writes (for example the last item invalid, or an exception forced mid-save) leaves the previous tree and title exactly as they were.
- AC-8 (W) A successful save writes exactly one activity entry on the menu with the causer (decision 12). A rejected save writes none. Two consecutive successful saves write two entries.
- AC-9 (W) Saving a tree that keeps, reorders or re-nests existing items keeps their `public_id` values. Only genuinely new items get new ids. A tree with every item removed is valid and leaves an empty menu.
- AC-10 (U) `MenuResource` registers exactly `index` and `edit`. The edit URL uses `public_id`, a raw integer id returns 404. The list shows the five menus (label, key, title, item count, last updated in the admin timezone, through the same configuration path as the block list), has an Edit row action, row click opens Edit, and has no create, delete or bulk action anywhere. Table state interactions follow the ARCHITECTURE.md section 8 history convention.
- AC-11 (U) A platform admin loads the list and edit pages. A non-admin gets 403 on both routes. The resource smoke test loads the menu list and edit pages (the shared helper must support a resource with no create page).
- AC-12 (U) The edit form is prefilled from the read Action, not from Eloquent relationships. It has: `title`, and a repeater of top-level items with a nested repeater of children. Each item has label, url, opens in new tab, enabled. Items can be added, removed and reordered, within the caps. The nested repeater has no further nesting. There is no input for key, label, sort_order, icon or id. The existing `public_id` travels as hidden form state. The UI3 forbidden-component scan passes unmodified.
- AC-13 (U) Save calls `UpdateMenu` once with a Data object built from the form, shows a success notification with fixed copy, writes the activity entry with the admin as causer, and re-hydrates the form from the returned tree. A second save with no changes keeps the same item count and the same `public_id` values (no duplicates of items created in the first save).
- AC-14 (U) Invalid input shows field errors and persists nothing: `javascript:alert(1)`, `//evil.com`, `http://example.com`, an empty label. A domain rejection (forced with a test double that throws) shows a danger notification with fixed copy, no exception message, no 500, and persists nothing.

## Rules most at risk

- S2, S4, C4, C5: a relationship repeater, or the page class diffing and writing items. Filament must only call `UpdateMenu`.
- S1: locking or transactions placed in Filament instead of the Action.
- C6, SEC2: `BaseContentPolicy` defaults letting an admin create or delete menus, abilities missing on the resource, `->authorize(false)` shortcuts.
- SEC3: exception messages in notifications or field errors.
- SEC5: logging from Filament, or a save path that does not log.
- K3: link safety. `//host` and backslash variants are valid under a naive "starts with /" check.
- DB5: integer ids in any URL. Also a hidden `public_id` in form state is user input and must be checked for ownership in the Action.
- T5, C7: loosening the UI3 scan or any existing assertion, or adding a Larastan baseline entry to get past typing on new tests.
- X4: roles, bulk actions, item-level actions, API output.
- S7: no new folder types. The resource follows the existing `Resources/<Model>Resource/{Pages,Schemas,Tables}` layout.

## Known traps

1. Provisioned rows versus the test database. If tests reset with truncation, or recreate the schema without running data inserts, the provisioned rows vanish, and drift and UI tests fail oddly. If rows survive, factories creating a menu with an existing key hit the unique constraint. Read the test setup first and design the factory state (for example "get or create by key") around it. Record the finding in plan section 13.
2. "Depth 2" is two levels, not three. A plan that allows grandchildren, or a repeater that nests a third level, fails AC-6 and AC-12.
3. The shared link rule may accept `//host` (it only checks the first character). If so, the same gap exists for the announcement bar link. Do not fork a second rule. State it in plan section 13 as a CR proposing to tighten the shared rule, with its test, and stop on that point for Achintha's decision.
4. Newly created items have no `public_id` in form state. If the form is not re-hydrated from the saved result, the next save sends them as new again and creates duplicates. Test the second save, not just the first (AC-13).
5. Filament repeater item keys are internal random keys. They are not `public_id`. Do not use them as ids.
6. Whole-tree replace must update matched items in place. A delete-all-and-recreate implementation passes naive tests and silently rotates every id the API exposes (AC-9, MUT-3).
7. Do not use a literal `->url()` anywhere in Filament code, including `TextInput::url()`. The UI3 scan matches it, and a relative path is a valid value anyway. Use the shared rule class for validation. `TextInput::make('url')` is fine.
8. `BaseContentPolicy` may return true for `create` or `delete` for an admin. Override to false and test it (AC-4).
9. Timezone display: reuse the configuration path the block list uses (UI4 trap 6). Do not add a third path. If there are two paths, state it in plan section 13.
10. Browser history: P1-FIX1 fixed history stacking for the block list. The menu table and edit page must use the same convention, so reuse what FIX1 did and do not rediscover it.
11. `FilamentSmokeTest` may assume list, create and edit pages. Check before reusing it for a resource without create.
12. Filament 5 and Livewire 4 APIs (nested repeaters, reorder, min/max items, record and state refresh, notification and Schema namespaces) are verified in vendor source, not recalled from Filament 3. If a two-level repeater cannot meet AC-12 and AC-13 without model access, record it in plan section 13 and stop. Do not switch approach silently.
13. Pest closures type `$this` as `TestCall`, which failed Larastan in UI3 (CR-02). Reuse the test-class pattern.
14. Provisioning is data in a schema migration. Make it idempotent (insert if the key is missing) so a re-run or a partially provisioned database cannot fail on the unique key.

## Size guard

Expected, per part:

| Part | Area | Files |
| --- | --- | --- |
| R | Migration (A), MenuKey (A), Menu (A), MenuItem (A), two factories (A), two output Data classes (A), read Actions (A, 1 to 2), MenuPolicy (A), policy registration (M) | about 12 |
| R | Tests: schema and provisioning, reads, policy | 3 |
| W | UpdateMenu (A), input Data (A), exception(s) (A) | about 3 |
| W | Tests: UpdateMenu behavior, rejections and rollback | 2 to 3 |
| U | MenuResource (A), ListMenus (A), EditMenu (A), MenuForm (A), MenuTable (A) | 5 |
| U | Tests: list, edit and save, rejections, authorization; smoke test (M); ARCHITECTURE.md (M) | 3 to 4 |

Hard split trigger, per part: the manifest passes 15 files, or the plan needs an ADR, or any change is needed to the block domain, `ContentBlockResource` or an existing test other than the smoke test. Then stop and split further. The seams are already drawn in the AC tags.

## Manual UI script (part U, P1-M1c)

Run on the local `phone_store` database as a platform admin. Parts R and W have no UI. Their manual check is `php artisan migrate` on the local database, then `php artisan tinker --execute="dump(DB::table('menus')->orderBy('id')->pluck('key')->all());"` and expect the five keys.

1. Open /admin/menus. Expect five menus with label, key, title, item count 0, an Edit row action, no New button and no delete.
2. Open `main_nav`. Add two top-level items: Phones with `/shop?category=phones`, then Deals with `/deals`. Under Phones add a child Samsung with `/shop?category=samsung`. Save. Expect a success notification. Reload. Expect the same order and nesting. Attach a screenshot of this form.
3. Check the log: `php artisan tinker --execute="dump(Spatie\Activitylog\Models\Activity::latest('id')->first()->only(['log_name','event','description','causer_id','subject_type']));"`. Expect a menu entry with your user as causer.
4. Note the ids: `php artisan tinker --execute="dump(DB::table('menu_items')->orderBy('id')->get(['public_id','label','parent_id','sort_order'])->all());"`. Move Deals above Phones, save, reload, and run the same command. Expect the order changed and every `public_id` the same.
5. Save twice more with no changes. Expect the item count and ids unchanged (no duplicates).
6. Set a url to each of `javascript:alert(1)`, `//evil.com`, `http://example.com`, `mailto:a@b.c`. Expect a field error each time and nothing saved. Set `https://example.com/x`. Expect it saves.
7. Check the child row. Expect no way to add a grandchild.
8. Add 11 top-level items. Expect the form to stop at 10 (OD-5, if accepted).
9. Toggle an item to disabled, save, reload. Expect it stays disabled and is still listed in the admin.
10. Open /admin/menus/1/edit. Expect 404.
11. Open the same menu in two tabs. In tab A add an item and save. In tab B change the title and save. Expect B succeeds and A's new item is gone. This is the OD-3 (A) behavior, record it as known, not as a bug.
12. Remove every item from a menu and save. Expect success and an empty menu.

Mark pass or fail per line.

## Step-specific checks (Layer A additions, PowerShell, one at a time)

1. `php artisan route:list --path=admin/menus` (part U: expect two routes, index and edit)
2. `git diff main...HEAD --name-only -- database/migrations` (part R: exactly one new file, other parts: empty)
3. `git diff main...HEAD --stat -- composer.json composer.lock` (expect empty)
4. `git diff main...HEAD --stat -- app/Domain` (R and W: only new Menu files and the policy registration. U: empty)
5. `git diff main...HEAD -- tests/Feature/Content/BlockFormRegistryTest.php` (expect empty, scan not loosened)
6. `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->getMessage()'` (every hit explained, none in notification copy)
7. `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->url('` (expect none)
8. `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->relationship('` (expect none in menu files)

Mutation spot-checks (required: authorization and data integrity). Gemini breaks each, shows the failing test, restores, shows a clean `git diff`:

- MUT-1 (R): make `create` return true for the platform admin in `MenuPolicy`. Expect the policy test (AC-4) to fail.
- MUT-2 (W): remove the protocol-relative (`//`) rejection from the link check used by `UpdateMenu`. Expect the rejection test (AC-6) to fail.
- MUT-3 (W): change the in-place update to delete and recreate matched items. Expect the id stability test (AC-9) to fail.
- MUT-4 (W): drop the check that a submitted `public_id` belongs to this menu. Expect the foreign-id test (AC-6) to fail.
- MUT-5 (U): remove the re-hydration of the form after save. Expect the second-save test (AC-13) to fail.