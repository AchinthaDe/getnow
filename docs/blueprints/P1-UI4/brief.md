[P1-UI4 | BRIEF | Claude]

Store at docs/blueprints/P1-UI4/brief.md. Paste the whole file to Gemini with the "Plan request" prompt from the workflow doc, section 12.

---

## Goal

Give admins a read-only View page for a content block and the four lifecycle transitions (Publish, Archive, Enable, Disable) as Filament actions on the View page header and on the List table rows. Every transition calls the existing domain Action, is authorized by a Policy ability, asks for confirmation where the live site changes, handles domain rejections without a 500 or a leaked exception message, and is proven by tests that assert the activity log entry. After this step an admin can take a draft block live and take it down again from the panel, without tinker.

## Depends on

Accepted: P1-UI3 (merged to main, head b355c7e). Gemini verifies every name below by reading the file. A mismatch goes to plan section 13, not into the plan as fact.

From P1-UI3:
- `ContentBlockResource` with pages `index`, `create`, `edit`, the `getUrl()` override (CR-01, route key is `public_id`) and `getRecordRouteKeyName()`
- `ContentBlockResource/Tables/ContentBlockTable.php` (EditAction hidden for archived, serving-status badges from UI2)
- `ContentBlockResource/Schemas/ContentBlockForm.php` and `app/Filament/Blocks/{BlockForm,BlockFormRegistry,AnnouncementBarForm}.php`
- `ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php` and the hydration-failure handling in `EditContentBlock.php`
- `ContentBlockPolicy` (`update` denies archived) and `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`
- `tests/Support/FilamentSmokeTest.php` and the CR-02 test-class pattern (`Livewire::actingAs($admin)->test(...)`)
- `ContentBlockCreateTest`, `ContentBlockEditTest`, `ListContentBlocksTableTest`, `BlockFormRegistryTest` (forbidden-component scan, UI3 AC-5)

From Step 2a/2b/UI2, as recorded in earlier reports:
- The four transition Actions in `app/Domain/Content/Actions` (Publish, Archive, Enable, Disable), `GetBlockServingStatus` and the `ServingStatus` enum, `GetUpgradedBlockPayload`
- `IllegalStateTransitionException`, the unservable-payload rejection from ADR 0002, `UnknownBlockTypeException`
- The admin timezone config used by the List table and the Edit form

## In scope

1. `view` page on `ContentBlockResource`: route registered, record resolved by `public_id`.
2. Read-only display: metadata (placement, type, status, enabled flag, serving status with its reason, schedule in the admin timezone, schema_version, public_id, created/updated by and timestamps) and the payload.
3. View page header actions: Edit (when the policy allows), then Publish, Enable, Disable, Archive as the visibility matrix allows.
4. List table: View row action, and the same four transition actions as row actions (grouped).
5. One shared definition of the four transition actions used by both surfaces.
6. Policy abilities `view`, `publish`, `archive`, `enable`, `disable` on `ContentBlockPolicy`.
7. Notifications for success and for domain rejection, with fixed copy.
8. Tests asserting state change, activity log entry, causer, authorization, stale state and hidden-action behavior.
9. Updating the two UI3 assertions that pin the exact route set (see traps), the List table tests, and the View page in the smoke test.
10. ARCHITECTURE.md: tick UI Step 4 at closeout, one-line addition to the section 5 and 8 resource folder description if a new `Actions/` subfolder is used.

## Out of scope

- Any change under `app/Domain` other than `ContentBlockPolicy`. If a transition Action does not log activity, raise a CR. Do not log from Filament.
- New transitions, restore or unarchive, delete, force delete, bulk actions, duplicate/clone.
- Showing the activity log on the View page (needs a new read Action, see OD-2).
- Changes to Create or Edit pages. The Edit page keeps zero header actions (UI3 AC-14).
- Per-block-type display classes, new block types, rich rendering of payload.
- Menus, settings page, Content API, seeder, retailer panel, roles or permissions (X4).
- Migrations, new packages, ADR changes, Docker, pipeline changes.

## Read first

- ARCHITECTURE.md: sections 5 (dependency rules), 6 (block rules), 8 (Filament conventions), 12 (D6, D7, D8, D9)
- AGENTS.md: Where code goes, Definition of Done, Step protocol
- Workflow doc: sections 4 (rule register), 6 (plan template), 10 (verification)
- docs/adr/ entries for serving health and unservable-payload rejection
- docs/blueprints/P1-UI3/: plan, cr-01-get-url-override.md, cr-02-smoke-helper.md
- The four transition Actions, `GetBlockServingStatus`, the exception classes
- Every file listed under "Depends on"

## Decisions made (Gemini must not re-decide)

1. Only domain transitions that already exist are exposed. The UI never invents a transition, and the Action is the authority. UI visibility is a convenience on top of it (K7, C4).
2. Policy abilities answer who, not what state. They are true for the platform admin only (D6). State legality stays in the domain Actions. `update` keeps its existing archived rule from UI3.
3. Visibility of each transition action = policy ability AND state legality. It never depends on serving health or payload hydration. Archive and Disable are the remedy for a broken live block, so they must stay available when the payload cannot be loaded or served.
4. The UI does not pre-compute serving health to gate Publish or Enable. If the domain rejects (ADR 0002), the click surfaces a danger notification.
5. The four transition actions are defined once (a shared class in a new `app/Filament/Admin/Resources/ContentBlockResource/Actions/` folder, a sibling of Pages, Schemas, Tables) and reused by the View page header and the table rows. Duplicated closures fail review.
6. Payload display reuses the registered block form schema in read-only mode, so no per-type display code exists and a new block type needs no extra View work (K9 checklist unchanged). If Filament 5 makes this infeasible, record it in plan section 13 and stop. Do not switch approach silently.
7. Notifications use fixed copy per outcome. Raw exception messages never reach the user (SEC3).
8. No Filament-side transactions or locks. D9 locking lives in the domain (S1).
9. Test conventions mirror UI3: factories and states, CR-02 test-class pattern, no Larastan baseline, no loosened scan.

## Open decisions (Achintha answers before G1)

- OD-1 Single step or split. This brief has 13 ACs and an expected 13 to 14 files, over the workflow's guideline of 10 and 10. Each AC is tagged V (view) or T (transitions). Recommendation: split at that seam into P1-UI4a (V, about 8 files) and P1-UI4b (T, about 7 files), since T carries the authorization and state-transition risk and needs the mutation checks. UI3 shipped as one step, so keeping one step is acceptable. If one step, the hard split trigger below applies.
- OD-2 Activity log. (A) Tests assert entries are written, nothing shown in the UI. (B) View page also lists recent entries through a new read Action. Recommendation: A. B is a domain change and belongs in its own step.
- OD-3 Confirmation modals. Recommendation: confirm Publish, Enable and Archive (each changes what the public sees or cannot be reversed in the UI). No confirmation on Disable, since it is the fast takedown.
- OD-4 Row click in the List opens View instead of Edit. Recommendation: yes, read first and edit deliberately. This changes `ListContentBlocksTableTest`.

## Acceptance criteria

V: view page. T: transitions.

- AC-1 (V) The resource registers exactly `index`, `create`, `edit`, `view`. The view URL uses `public_id`. A raw integer id returns 404. The two UI3 route-set assertions are updated to the new set and nothing else in them is weakened. The Edit page still has zero header actions.
- AC-2 (V) The View page renders for a platform admin for a draft, a published and an archived block. It shows placement, type, status, enabled flag, serving status with reason, schedule in the admin timezone, schema_version, public_id, created/updated by and timestamps.
- AC-3 (V) The payload is shown read-only through the registered form schema: for `announcement_bar`, text, link_url and tone with values. No input is editable. No status, is_enabled, sort_order, schema_version or type input exists. `link_url` renders as plain text, not an anchor. The UI3 forbidden-component scan passes unmodified.
- AC-4 (V) A block whose stored payload cannot be loaded (upgrade failure or unknown type) gives a danger notification and a redirect to the index. No 500. The notification contains no exception message.
- AC-5 (V) `view` is allowed for the platform admin, including archived blocks, and denied for a non-admin (403 on the route, policy test).
- AC-6 (V) The List table has a View row action for every status. The smoke test also loads the View page. Row click behavior follows OD-4.
- AC-7 (T) `publish`, `archive`, `enable`, `disable` abilities exist on `ContentBlockPolicy`: true for the platform admin, false for a non-admin. The policy contains no state logic.
- AC-8 (T) For every combination of status and is_enabled, the set of visible actions on the View header and on the table row equals the matrix in plan section 7, and that matrix equals the legality in the domain Actions. All four are hidden from a non-admin.
- AC-9 (T) Publish, on both the View header and the table row, calls the domain Publish Action once, results in `published`, writes the activity entry with the admin as causer, shows a success notification, and asks for confirmation (OD-3). The page or table reflects the new state without a manual reload.
- AC-10 (T) Publish and Enable on a block the domain rejects as unservable leave the state unchanged, write no transition activity entry, and show a danger notification with fixed copy.
- AC-11 (T) Archive calls the domain Archive Action once on both surfaces, results in `archived`, writes the activity entry with the causer, and afterwards the block shows only the actions the matrix allows for archived.
- AC-12 (T) Enable and Disable each call their Action once, flip is_enabled, and write the activity entry. Disable and Archive succeed for a block whose payload cannot be upgraded.
- AC-13 (T) Stale state: after another actor changes the block, invoking a transition the block no longer allows is caught (`IllegalStateTransitionException`), shows a danger notification with fixed copy, changes nothing, writes no activity entry, and the page refreshes to the real state.

## Rules most at risk

- S2, S4, C4, C5: the temptation is a closure that updates the model or sets status directly.
- C6, SEC2: abilities missing or bypassed on row actions, or `->authorize(false)` shortcuts.
- SEC3: exception messages in notifications.
- SEC5: transitions unlogged. Domain gap means CR.
- K3, K4: stored link rendered as a clickable anchor, or HTML in payload display.
- DB5: integer ids in any URL.
- T5, C7: loosening the UI3 scan or route assertions, or adding a Larastan baseline entry to get past typing on the new tests.
- S7: the new `Actions/` folder (decided above, record it in ARCHITECTURE.md).
- X4: bulk actions, restore, activity UI.

## Known traps

1. UI3 tests pin the exact route set and zero edit header actions (AC-14). Adding `view` breaks them by design. Update the assertions explicitly. Do not delete them.
2. `getUrl()` override (CR-01). Anything that builds a record URL (ViewAction, recordUrl, redirects) must produce `public_id`. Assert the ULID in a URL.
3. Published is not live. A published block with a future `starts_at` is not served. Confirmation copy must not say it goes live now. Say it becomes eligible to show within its schedule. Confirmation text claiming "cannot be undone" must match what the domain actually allows.
4. The UI3 forbidden-component scan matches `->url()`, `DeleteAction` and rich components. Do not use a literal `->url()` anywhere in Filament code. Use ViewAction, EditAction and recordUrl helpers, and run the scan.
5. A block with an unloadable payload cannot open the View page (AC-4). Its remedy is the row actions, so Archive and Disable on the table must not hydrate the payload.
6. Timezone display: the List table, the Edit form and this page must use the same configuration path. UI2 introduced an admin timezone setting and UI3's AC-11 references `app.timezone`. Read how they relate. If there are two paths, state it in section 13 and do not add a third.
7. Stale record: the page holds the record from mount. After an action the page must refresh the record so header visibility changes. Test the post-action UI state, not only the database.
8. Exception messages leaked into results were a 2b audit finding. Fixed copy only.
9. Filament 5 APIs (Action classes, namespaces, record refresh, modal configuration) are verified in vendor source, not recalled from Filament 3.
10. Pest closures type `$this` as `TestCall`, which failed Larastan in UI3 (CR-02). Reuse the same test-class pattern rather than rediscovering it.

## Size guard

Expected 13 to 14 files:

| Area | Files |
| --- | --- |
| Filament | ContentBlockResource (M), ViewContentBlock page (A), metadata schema class (A), shared transition actions class (A), ContentBlockTable (M) |
| Domain | ContentBlockPolicy (M) |
| Tests | ContentBlockViewTest (A), ContentBlockStateActionsTest (A), ContentBlockPolicyTest (M), ContentBlockCreateTest (M), ContentBlockEditTest (M), ListContentBlocksTableTest (M) |
| Docs | ARCHITECTURE.md (M, at closeout) |

Hard split trigger: the manifest passes 15 files, or the plan needs an ADR, or any change is needed under `app/Domain` other than the policy. Then stop and split into UI4a (AC-1 to AC-6) and UI4b (AC-7 to AC-13). The seam is already drawn in the AC tags.

## Manual UI script

Run on the local `phone_store` database as a platform admin.

1. Open /admin/content-blocks. Expect the list with status and serving badges, and a View row action on each row.
2. Create a draft announcement bar. Open it via View. Expect the fields read-only, status Draft, no editable inputs, and no link rendered as a clickable anchor. Header shows Edit, Publish, Archive.
3. Click Publish. Expect a confirmation modal. Confirm. Expect a success notification, status Published, and header actions changed to the published set.
4. Check the log: `php artisan tinker --execute="dump(Spatie\Activitylog\Models\Activity::latest('id')->first()->only(['log_name','event','description','causer_id']));"`. Expect a publish entry with your user as causer.
5. Click Disable. Expect no confirmation, is_enabled off, serving status not live. Click Enable. Expect a confirmation, then live again (inside its schedule).
6. Open the same block in two tabs. In tab A click Archive and confirm. In tab B click Disable. Expect a danger notification with generic copy, no error page, and tab B refreshed to Archived.
7. On the archived block, expect no Edit, Publish, Enable or Disable on the View page and in the list row. View remains.
8. In the list, publish a second draft from its row. Expect the badges to update without a reload.
9. Raw integer id check: open /admin/content-blocks/1 and /admin/content-blocks/1/edit. Expect 404 on both.
10. Broken payload, local database only: pick a draft block and run `php artisan tinker --execute="DB::table('content_blocks')->where('public_id','<ULID>')->update(['payload'=>json_encode(['tone'=>'info'])]);"`. Open its View page. Expect a danger notification and a redirect to the index. From the list row, Archive it. Expect success.

Mark pass or fail per line. Attach a screenshot of the View page for step 2.

## Step-specific checks (Layer A additions, PowerShell, one at a time)

1. `php artisan route:list --path=admin/content-blocks` (expect 4 routes: index, create, edit, view)
2. `git diff main...HEAD --stat -- app/Domain` (expect only ContentBlockPolicy.php)
3. `git diff main...HEAD --stat -- database composer.json composer.lock` (expect empty)
4. `git diff main...HEAD -- tests/Feature/Content/BlockFormRegistryTest.php` (expect empty, scan not loosened)
5. `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->getMessage()'` (every hit explained, none in notification copy)
6. `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->url('` (expect none)

Mutation spot-checks (required: authorization and state transitions). Gemini breaks each, shows the failing test, restores, shows a clean `git diff`:

- MUT-1: remove the platform-admin check from the `publish` policy ability. Expect the policy test and the non-admin visibility test to fail.
- MUT-2: drop the status condition from the Publish action's visibility. Expect the matrix test (AC-8) to fail.
- MUT-3: remove the `IllegalStateTransitionException` catch in the shared action. Expect the stale-state test (AC-13) to fail.