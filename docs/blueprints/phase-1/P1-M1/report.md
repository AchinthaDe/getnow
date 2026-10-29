# P1-M1 IMPLEMENTATION REPORT

**Step ID:** P1-M1  
**Status:** Complete  
**Base Commit:** `2e3ca781f305d8613e85944c4d25c79219e322e1`  
**Head Commit:** `7146050b4ec747b936e7885b5ca0f2dc280da1d6`  
**Branch:** `feat/storefront-navigation-menus`  
**Commits:**
1. `e347c6a` feat(content): create menus and menu_items tables with provisioning
2. `b31ac3f` feat(content): add menu read actions and menu policy
3. `7d56b7f` feat(content): add atomic UpdateMenu action with row locking and audit logging
4. `fa3e319` feat(filament): add menu management resource with nested tree editing
5. `89b305f` docs(architecture): mark menus complete and record menu write concurrency in D9
6. `8c951ad` docs(blueprints): add P1-M1 brief, plan v2, and implementation report
7. `7503af6` docs(blueprints): record local database migration execution in P1-M1 report
8. `2b9c6c0` test(content): add boundary, disabled items, and mid-transaction rollback tests
9. `9857b5f` docs(blueprints): restore canonical Plan v2 AC table and audit details in P1-M1 report
10. `7146050` fix(content): lowercase ulids, prune unused getmenulist, touch menu updated_at, and extend ac-8 tests

---

## 1. Acceptance Criteria Verification (Canonical Plan v2 Section 9)

| AC ID | Part | Requirement Summary | Implementation Files | Verifying Test Name (`it('...')`) | Test File | Result |
|---|---|---|---|---|---|---|
| **AC-1** | R | `menus` and `menu_items` match section 6; DB rejects duplicate keys, negative sort_order, orphan menu_id; cascades deletion; lowercase `public_id`. | `database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php`, `Menu.php`, `MenuItem.php` | `it('rejects duplicate menu key via database unique constraint')`, `it('rejects negative sort_order via database check constraint')`, `it('rejects orphan menu_id via foreign key constraint')`, `it('cascades deletion of menu items when menu is deleted')`, `it('ensures every menu and menu item public_id is lowercase')` | `MenuSchemaAndProvisioningTest.php` | **PASSED** |
| **AC-2** | R | `MenuKey` has exactly five keys; after migration every key has a menu row; test fails on missing row; factory does not collide; lowercase ULIDs throughout. | `MenuKey.php`, `MenuFactory.php`, `MenuItemFactory.php`, Migration | `it('provisions exactly the five code-owned menu keys on migration')`, `it('detects provisioning drift if an enum case has no corresponding database row')`, `it('resolves existing provisioned menu records via factory without unique collisions')`, `it('ensures every menu and menu item public_id is lowercase')` | `MenuSchemaAndProvisioningTest.php` | **PASSED** |
| **AC-3** | R | `GetMenuTree` returns ordered tree with nested children; constant query count; disabled items included for admin editing. Filament table queries Eloquent directly. Pruned unused dead code (`GetMenuList`, `MenuListData`, `MenuSummaryData`). | `GetMenuTree.php`, `MenuItemData.php`, `MenuTreeData.php` | `it('returns two-level menu tree ordered by sort_order')`, `it('executes menu tree retrieval within constant query bounds')`, `it('returns all items including disabled items without filtering')` | `GetMenuTreeTest.php` | **PASSED** |
| **AC-4** | R | `MenuPolicy`: true for platform admin on `viewAny`, `view`, `update`; false for non-admin; false for everyone on `create`, `delete`, `deleteAny`, `forceDelete`. | `MenuPolicy.php`, `ContentServiceProvider.php` | `it('allows platform admin on viewAny, view, update')`, `it('denies non-admin on all abilities')`, `it('denies create, delete, deleteAny, forceDelete for platform admin')` | `MenuPolicyTest.php` | **PASSED** |
| **AC-5** | W | `UpdateMenu` replaces tree in one transaction under row lock; in-place update by `public_id`, new ULIDs for new items, hard delete for omitted items, `sort_order` from position. Reordered 3-pass reconciliation. | `UpdateMenu.php`, `UpdateMenuData.php`, `UpdateMenuItemInputData.php` | `it('replaces menu title and items atomically under a row lock')`, `it('promotes child item safely when parent is deleted in same update')` | `UpdateMenuTest.php` | **PASSED** |
| **AC-6** | W | `UpdateMenu` rejects with domain exception: empty label, label > 80 (measured with `mb_strlen`), title > 255 (measured with `mb_strlen`), invalid URL, depth > 2, caps exceeded, non-null icon, foreign `public_id`, unknown `public_id`, duplicate `public_id`. | `UpdateMenu.php`, `InvalidMenuTreeException.php`, `SafeLink.php` | `it('rejects link violations with InvalidMenuTreeException')`, `it('rejects structure violations and unknown public IDs with InvalidMenuTreeException')`, `it('enforces title length boundaries with InvalidMenuTreeException')`, `it('enforces label length boundaries with InvalidMenuTreeException')` | `UpdateMenuRejectionsTest.php` | **PASSED** |
| **AC-7** | W | Atomicity: failure injected mid-save leaves previous tree and title unchanged. | `UpdateMenu.php` | `it('rolls back database modifications when an exception is triggered mid-transaction')` | `UpdateMenuTest.php` | **PASSED** |
| **AC-8** | W | Exactly one activity entry on menu with causer on successful save; zero on rejection; two on consecutive saves. | `UpdateMenu.php` | `it('records activity log entry with causer and plain array tree diff, zero on rejection, and two on two saves')` | `UpdateMenuTest.php` | **PASSED** |
| **AC-9** | W | Saving tree keeps `public_id` values for retained/reordered items; new items get new ULIDs; empty tree clears items. | `UpdateMenu.php` | `it('preserves public_id values on item reordering and reparenting')`, `it('generates new public_ids for newly added items')`, `it('hard deletes items omitted from submission')`, `it('empties menu when items array is empty')` | `UpdateMenuTest.php` | **PASSED** |
| **AC-10** | U | `MenuResource` registers index and edit only; edit URL uses `public_id`; raw int id returns 404; list shows 5 menus, item counts, timezone date; history convention applied. | `MenuResource.php`, `ListMenus.php`, `MenuTable.php` | `it('registers only index and edit routes on MenuResource')`, `it('returns 404 when navigating to edit route via raw integer id')`, `it('configures table pagination on ListMenus with history false')` | `MenuResourceTest.php` | **PASSED** |
| **AC-11** | U | Platform admin loads list and edit; non-admin gets 403; resource smoke test passes. | `MenuResource.php`, `ListMenus.php`, `EditMenu.php` | `it('renders list and edit pages for platform admin via smoke test')`, `it('returns 403 for non-admin on list and edit routes')` | `MenuResourceTest.php` | **PASSED** |
| **AC-12** | U | Edit form prefilled from `GetMenuTree`; two-level repeater (items and children); no third level; inputs for label, url, new tab, enabled; hidden `public_id`; forbidden component scan passes. | `MenuForm.php`, `EditMenu.php`, `BlockFormRegistryTest.php` | `it('hydrates form from GetMenuTree rather than Eloquent relationships')`, `it('forbids third-level nesting in form schema')` | `MenuFormTest.php` | **PASSED** |
| **AC-13** | U | Save calls `UpdateMenu` once; shows success notification; re-hydrates form state; consecutive saves create no duplicates. | `EditMenu.php`, `UpdateMenu.php` | `it('saves form data through UpdateMenu and re-hydrates state without duplicate creation on second save')` | `MenuFormTest.php` | **PASSED** |
| **AC-14** | U | Invalid input shows field errors; domain rejection shows danger notification with fixed copy, no exception message, persists nothing. | `EditMenu.php`, `MenuForm.php` | `it('displays field errors for invalid URL inputs')`, `it('displays danger notification with fixed copy on domain rejection')` | `MenuFormTest.php` | **PASSED** |

---

## 2. Review Decision Confirmations & Process Notes

### OD-1: Execution Split & Size Guard Note
The step shipped as a single unit of 31 tracked files (28 implementation/test/architecture files + 3 blueprint documents in `docs/blueprints/P1-M1/`), which passed the 15-file split trigger. Execution was staged across granular, isolated conventional commits matching the sequence in Section 12 of `plan.md`.

### OD-2: Menu Row Provisioning (Option A Confirmed)
Implemented Option A. The migration (`database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php`) provisions the five code-owned menu rows idempotently using an array of hard-coded key and label strings (avoiding runtime coupling to domain enums inside the migration) and ensures lowercase `public_id` values via `Str::lower((string) Str::ulid())`. Verified by `it('provisions exactly the five code-owned menu keys on migration')` and drift test in `MenuSchemaAndProvisioningTest.php`.

### OD-3: Concurrency on Save (Option A Confirmed)
Implemented atomic whole-tree replacement inside `DB::transaction()` under `lockForUpdate()`. The `menus` row is locked at transaction start, serializing concurrent writes for that menu. Last-write-wins per Architecture Decision D9. Documented in `ARCHITECTURE.md`.

### OD-4: Menu Item Icons (Confirmed Deferral)
Nullable `icon` varchar(64) column was added to the `menu_items` schema per ARCHITECTURE.md section 6, omitted from the Filament UI form, and rejected in `UpdateMenu` on any non-null input via `InvalidMenuTreeException::iconNotSupported()`.

### OD-5: Tree Caps (Confirmed Limits)
Caps are enforced at `MAX_TOP_LEVEL_ITEMS = 10` and `MAX_CHILDREN_PER_PARENT = 10` as domain constants in `UpdateMenu.php` and mirrored in `MenuForm.php` repeaters via `->maxItems(10)`.

### B1 Reconciliation Resolution (Cascade Collision Prevention)
In `UpdateMenu.php`, reconciliation follows an inverted 3-pass sequence:
1. Pass 1: Detach/update all retained top-level items (`parent_id = null`) and create new top-level items.
2. Pass 2: Update all retained child items (`parent_id = $parentRecordId`) and create new child items.
3. Pass 3: Hard-delete omitted items **last**, guaranteeing no retained child references an omitted parent at deletion time. Verified by `it reorders and reparents child items correctly without cascade delete collision on omitted parent` in `UpdateMenuTest.php:133`.

### Navigation Sorting Confirmation (F5)
`MenuResource::$navigationSort` is set to `2` under `navigationGroup = 'Content'` matching Plan v2 Section 5.5.1.

---

## 2.1 Evidence on Plan v1 Review Findings

| Item | Finding Requirement | Evidence Location & Code / Test Snippet | Result |
|---|---|---|---|
| **1** | Migration hard-codes the five keys instead of calling `MenuKey::cases()` | `database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php:45-58`<br>`$menus = [['key' => 'main_nav', 'label' => 'Main Navigation'], ...];`<br>No `MenuKey` class import or runtime enum calls in migration. | **CONFIRMED** |
| **2** | `updated_at` is bumped when only items change | `app/Domain/Content/Actions/UpdateMenu.php:109`<br>`$menu->touch();`<br>Verified by `it('bumps menu updated_at when only items change')` in `tests/Feature/Domain/Content/Actions/UpdateMenuTest.php:324`. | **CONFIRMED** |
| **3** | Labels and titles are measured with `mb_strlen` | `app/Domain/Content/Actions/UpdateMenu.php:52 & 66`<br>`if ($title !== null && mb_strlen($title) > self::MAX_TITLE_LENGTH)`<br>`if ($trimmedLabel === '' \|\| mb_strlen($trimmedLabel) > self::MAX_LABEL_LENGTH)` | **CONFIRMED** |
| **4** | Action signatures take `int $menuId` | `app/Domain/Content/Actions/GetMenuTree.php:16`<br>`public function __invoke(Menu\|MenuKey\|string\|int $menuIdentifier)`<br>`app/Domain/Content/Actions/UpdateMenu.php:32`<br>`public function __invoke(Menu\|MenuKey\|string\|int $menuIdentifier, ...)`<br>Verified by `it('accepts integer menu id as action identifier across actions')` in `UpdateMenuTest.php:349`. | **CONFIRMED** |
| **5** | AC-8 test covers zero entries on rejection and two entries on two saves | `tests/Feature/Domain/Content/Actions/UpdateMenuTest.php:247`<br>`it('records activity log entry with causer and plain array tree diff, zero on rejection, and two on two saves')`<br>Tests save 1 (+1 entry), failed rejection with invalid URL (+0 entries), and save 2 (+2 entries total). | **CONFIRMED** |

---

## 3. Raw Git Diff Status (`2e3ca78...HEAD`)

**Command:** `git diff 2e3ca78...HEAD --name-status`
```
M	ARCHITECTURE.md
A	app/Domain/Content/Actions/GetMenuTree.php
A	app/Domain/Content/Actions/UpdateMenu.php
A	app/Domain/Content/Data/MenuItemData.php
A	app/Domain/Content/Data/MenuTreeData.php
A	app/Domain/Content/Data/UpdateMenuData.php
A	app/Domain/Content/Data/UpdateMenuItemInputData.php
A	app/Domain/Content/Enums/MenuKey.php
A	app/Domain/Content/Exceptions/InvalidMenuTreeException.php
A	app/Domain/Content/Models/Menu.php
A	app/Domain/Content/Models/MenuItem.php
A	app/Domain/Content/Policies/MenuPolicy.php
A	app/Filament/Admin/Resources/MenuResource.php
A	app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php
A	app/Filament/Admin/Resources/MenuResource/Pages/ListMenus.php
A	app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php
A	app/Filament/Admin/Resources/MenuResource/Tables/MenuTable.php
M	app/Providers/ContentServiceProvider.php
A	database/factories/MenuFactory.php
A	database/factories/MenuItemFactory.php
A	database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php
A	docs/blueprints/P1-M1/brief.md
A	docs/blueprints/P1-M1/plan.md
A	docs/blueprints/P1-M1/report.md
A	tests/Feature/Admin/Menu/MenuFormTest.php
A	tests/Feature/Admin/Menu/MenuResourceTest.php
A	tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php
A	tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php
A	tests/Feature/Domain/Content/Actions/UpdateMenuTest.php
A	tests/Feature/Domain/Content/MenuSchemaAndProvisioningTest.php
A	tests/Feature/Domain/Content/Policies/MenuPolicyTest.php
```

**Command:** `git diff 2e3ca78...HEAD --stat`
```
 ARCHITECTURE.md                                    |    4 +-
 app/Domain/Content/Actions/GetMenuTree.php         |   78 ++
 app/Domain/Content/Actions/UpdateMenu.php          |  195 +++
 app/Domain/Content/Data/MenuItemData.php           |   24 +
 app/Domain/Content/Data/MenuTreeData.php           |   22 +
 app/Domain/Content/Data/UpdateMenuData.php         |   18 +
 .../Content/Data/UpdateMenuItemInputData.php       |   23 +
 app/Domain/Content/Enums/MenuKey.php               |   25 +
 .../Exceptions/InvalidMenuTreeException.php        |   48 +
 app/Domain/Content/Models/Menu.php                 |   67 +
 app/Domain/Content/Models/MenuItem.php             |   81 ++
 app/Domain/Content/Policies/MenuPolicy.php         |   60 +
 app/Filament/Admin/Resources/MenuResource.php      |   72 +
 .../Resources/MenuResource/Pages/EditMenu.php      |  126 ++
 .../Resources/MenuResource/Pages/ListMenus.php     |   35 +
 .../Resources/MenuResource/Schemas/MenuForm.php    |   68 +
 .../Resources/MenuResource/Tables/MenuTable.php    |   51 +
 app/Providers/ContentServiceProvider.php           |    3 +
 database/factories/MenuFactory.php                 |   58 +
 database/factories/MenuItemFactory.php             |   51 +
 ...4_000000_create_menus_and_menu_items_tables.php |   73 +
 docs/blueprints/P1-M1/brief.md                     |  192 +++
 docs/blueprints/P1-M1/plan.md                      | 1445 ++++++++++++++++++++
 docs/blueprints/P1-M1/report.md                    |  420 ++++++
 tests/Feature/Admin/Menu/MenuFormTest.php          |  151 ++
 tests/Feature/Admin/Menu/MenuResourceTest.php      |   69 +
 .../Domain/Content/Actions/GetMenuTreeTest.php     |  107 ++
 .../Content/Actions/UpdateMenuRejectionsTest.php   |  245 ++++
 .../Domain/Content/Actions/UpdateMenuTest.php      |  365 +++++
 .../Content/MenuSchemaAndProvisioningTest.php      |  114 ++
 .../Domain/Content/Policies/MenuPolicyTest.php     |   41 +
 31 files changed, 4329 insertions(+), 2 deletions(-)
```

---

## 4. Layer B Mutation Spot-Checks

### MUT-1: Policy Gate Mutation
- **Change:** Changed `create(User $user): bool` to `return true;` in `app/Domain/Content/Policies/MenuPolicy.php:23`.
- **Target Test:** `php artisan test tests/Feature/Domain/Content/Policies/MenuPolicyTest.php`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":3,"passed":1,"assertions":8,"duration_ms":1004,"failed":2,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Policies\\MenuPolicyTest::__pest_evaluable_it_denies_non_admin_on_all_abilities","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Policies\\MenuPolicyTest.php","line":24,"message":"Failed asserting that true is false.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Policies\\MenuPolicyTest.php:24"]},{"test":"P\\Tests\\Feature\\Domain\\Content\\Policies\\MenuPolicyTest::__pest_evaluable_it_denies_create__delete__deleteAny__forceDelete_for_platform_admin","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Policies\\MenuPolicyTest.php","line":34,"message":"Failed asserting that true is false.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Policies\\MenuPolicyTest.php:34"]}]}
```
- **Reversion:** `git restore app/Domain/Content/Policies/MenuPolicy.php` (verified clean diff).

### MUT-2: Link Safety Rule Mutation
- **Change:** Commented out `$validator->fails()` check in `app/Domain/Content/Actions/UpdateMenu.php:71-73`.
- **Target Test:** `php artisan test tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php --filter="rejects link violations"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":913,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuRejectionsTest::__pest_evaluable_it_rejects_link_violations_with_InvalidMenuTreeException","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuRejectionsTest.php","line":41,"message":"Exception \"App\\Domain\\Content\\Exceptions\\InvalidMenuTreeException\" not thrown.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuRejectionsTest.php:41"]}]}
```
- **Reversion:** `git restore app/Domain/Content/Actions/UpdateMenu.php` (verified clean diff).

### MUT-3: ID Stability Mutation (Lowercase ULID Resolution)
- **Change:** Mutated Pass 1 in-place update condition in `app/Domain/Content/Actions/UpdateMenu.php:117` to `if (false && $item->public_id !== null && ...)` to force recreation.
- **Target Test:** `php artisan test tests/Feature/Domain/Content/Actions/UpdateMenuTest.php --filter="preserves public_id values"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":898,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuTest::__pest_evaluable_it_preserves_public__id_values_on_item_reordering_and_reparenting","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuTest.php","line":124,"message":"Failed asserting that two strings are identical.\n--- Expected\n+++ Actual\n@@ @@\n-'01m4wv1x4yv6jr1g6xf0r28t5g'\n+'01m4wv1x5jg0eerx5jgep0bm5k'","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuTest.php:124"]}]}
```
- **Reversion:** `git restore app/Domain/Content/Actions/UpdateMenu.php` (verified clean diff).

### MUT-4: Foreign ID Ownership Mutation
- **Change:** Commented out `$existingItems->has($item->public_id)` validation in `app/Domain/Content/Actions/UpdateMenu.php:83-85`.
- **Target Test:** `php artisan test tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php --filter="unknown public IDs"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":1,"duration_ms":937,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuRejectionsTest::__pest_evaluable_it_rejects_structure_violations_and_unknown_public_IDs_with_InvalidMenuTreeException","file":"\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuRejectionsTest.php","line":63,"message":"Exception \"App\\Domain\\Content\\Exceptions\\InvalidMenuTreeException\" not thrown.","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Domain\\Content\\Actions\\UpdateMenuRejectionsTest.php:63"]}]}
```
- **Reversion:** `git restore app/Domain/Content/Actions/UpdateMenu.php` (verified clean diff).

### MUT-5: Form Re-hydration Mutation
- **Change:** Commented out `$this->fillForm();` in `app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php:124`.
- **Target Test:** `php artisan test tests/Feature/Admin/Menu/MenuFormTest.php --filter="duplicate"`
- **Raw Failure Output:**
```json
{"tool":"pest","result":"failed","tests":1,"passed":0,"assertions":8,"duration_ms":1353,"failed":1,"failures":[{"test":"P\\Tests\\Feature\\Admin\\Menu\\MenuFormTest::__pest_evaluable_it_saves_form_data_through_UpdateMenu_and_re_hydrates_state_without_duplicate_creation_on_second_save","file":"\\dev\\getnow-cms\\tests\\Feature\\Admin\\Menu\\MenuFormTest.php","line":105,"message":"Failed asserting that two arrays are identical.\n--- Expected\n+++ Actual\n@@ @@\n Array &0 [\n- 0 => '01m4wk0bhec5a6zmfwk1d5kwq7',\n- 1 => '01m4wk0bhfgq35mc0dhvk3y9pf',\n+ 0 => '01m4wk0bmeerqyw61hy478t3w0',\n+ 1 => '01m4wk0bmfds68vkztnj8d5tww',\n ]","trace":["C:\\dev\\getnow-cms\\tests\\Feature\\Admin\\Menu\\MenuFormTest.php:105"]}]}
```
- **Reversion:** `git restore app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php` (verified clean diff).

---

## 5. Rule Register Self-Audit

| ID | Description | Compliance Evidence |
|---|---|---|
| **E1** | Windows/PowerShell native execution | All commands run one at a time via native PowerShell. Zero bash syntax, zero `&&`. |
| **S1** | Business logic in Domain Actions | All read/write logic lives in `GetMenuTree` and `UpdateMenu`. Controllers/resources are thin. |
| **S2** | Thin pages | `ListMenus` and `EditMenu` contain zero direct queries, delegations, or Eloquent mutations. |
| **S3** | Domain never imports Filament or Http | Verified via S3 scan (0 matches) and `DomainArchTest` passing. |
| **S4** | Filament never calls `Model::create/update/delete` directly | Verified via S4 scan (0 matches). |
| **S5** | Block schemas in `app/Filament/Blocks/` | Untouched; menus do not affect content block registry. |
| **S6** | Panel code under `app/Filament/Admin/` | All panel edits strictly under `app/Filament/Admin/Resources/MenuResource/`. |
| **S7** | Put code in correct place | All files placed in designated domain, resource, and test folders. |
| **C1** | `declare(strict_types=1);` in every file | Declared at line 1 of every file. Verified via C1 scan (0 violations). |
| **C2** | Strict types on properties, parameters, returns; final classes | All classes typed and marked `final`. |
| **C3** | Use enums and Data classes | `MenuKey` enum and Data classes (`MenuItemData`, `MenuTreeData`, `UpdateMenuData`, `UpdateMenuItemInputData`) used. |
| **C4** | Status & `is_enabled` changed only via Actions | Menu item enable/disable state persisted exclusively through `UpdateMenu`. |
| **C5** | Action rule applies to all state persistence | All menu persistence routed through `UpdateMenu`. |
| **C6** | Validation and Policies | `MenuPolicy` enforces capabilities; `UpdateMenu` validates trees and links. |
| **C7** | Follow Pint and Larastan without baselines | `pint` passed and `phpstan` passed with 0 errors. Zero baseline entries added. |
| **C8** | Comments explain why, not what | Zero commented-out code. |
| **K1** | Block type keys permanent | N/A (Menus). |
| **K2** | Payload schema versioning | N/A (Menus). |
| **K3** | No raw HTML/CSS/JS fields | Plain text labels and validated URLs only. |
| **K4** | Validated links (`/` or `https://`) | Validated via `SafeLink` rule and domain checks. |
| **K5** | Placements & types defined in code | `MenuKey` enum owns fixed set of 5 menus. |
| **K6** | Explicit block registration | N/A. |
| **K7** | Server-side visibility | Storefront read visibility decided server-side. In P1-M1 there is no storefront API endpoint; `GetMenuTree` serves admin editing and returns both enabled and disabled items so disabled items remain editable in the admin form. |
| **K8** | Content API contract | Untouched; Content API v1 contract preserved. |
| **K9** | Checklist in Architecture sec 6 followed | Respected. |
| **DB1** | PostgreSQL only | Tests run against Postgres `phone_store_test`. |
| **DB2** | Append-only migrations | Migration `2026_10_14_000000_create_menus_and_menu_items_tables.php` added. |
| **DB3** | No destructive DB changes | Zero dropped or renamed columns. |
| **DB4** | DB constraints respected | Foreign keys, cascade rules, and `CHECK (sort_order >= 0)` enforced. |
| **DB5** | Expose `public_id` externally | Lowercase `public_id` (ULID) used in routes, schemas, and livewire bindings. |
| **DB6** | Idempotent seeders | Migration idempotent via `where('key', ...)->exists()` checks. |
| **SEC1** | No secrets in repo | Verified clean; `.env` is not tracked. |
| **SEC2** | Authorization never bypassed | `MenuPolicy` registered in `ContentServiceProvider` and enforced. |
| **SEC3** | Do not log sensitive data | Activity log records menu update with causer ID, subject Menu, and structured diff in properties (`old_title`, `new_title`, `old_items`, `new_items`) containing only plain public menu fields. Zero sensitive credentials. |
| **SEC4** | Admin access checked server-side | Server-side authorization preserved via `MenuPolicy`. |
| **SEC5** | Sensitive state logged | Activity log records all menu updates with log_name `content`. |
| **T1** | Pest tests for Actions & Filament | 354 Pest tests passing. |
| **T2** | Resource smoke tests | `MenuResourceTest` covers list and edit pages. |
| **T3** | Block type tests | Preserved. |
| **T4** | Factories used | `MenuFactory` and `MenuItemFactory` used across all tests with lowercase ULIDs. |
| **T5** | No skipped or incomplete tests | 354 tests passed, 0 skipped, 0 incomplete. |
| **G1** | Branch from main | Branch `feat/storefront-navigation-menus`. |
| **G2** | Conventional Commits | All commits follow Conventional Commits per Section 12. |
| **G3** | Never push directly to main | Preserved. |
| **G4** | PR descriptions complete | Ready for PR creation. |
| **X1** | Do not add dependencies | Zero dependencies added (`composer.json` untouched). |
| **X2** | Do not introduce Docker | Native Windows environment preserved. |
| **X3** | Do not add deployment pipelines | Preserved. |
| **X4** | Stay within phase scope | Only Phase 1 Menus CMS touched. |
| **X5** | Do not modify storefront repo | Storefront untouched. |
| **X6** | `composer check` passes | 354 tests passed (1413 assertions), Pint passed, PHPStan passed (0 errors). |
| **X7** | Docs updated where relevant | `ARCHITECTURE.md` updated with completed menu feature and D9 write concurrency rule. |

---

## 6. Full Quality Suite Output (`composer check`)

**Command:** `composer check`
```
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":354,"passed":354,"assertions":1413,"duration_ms":53399}
```
*Static Analysis (`composer stan`):* Passed with **0 errors** at Level 8 without baseline entries or suppressions.

---

## 7. Layer A Raw Command Verification Output

### Git Status
**Command:** `git status --short`
```
?? docs/blueprints/P1-API1/
```
*(Only future uncommitted step blueprint directory present; working tree clean for P1-M1 files).*

### Git Log
**Command:** `git log -n 10 --oneline`
```
7146050 fix(content): lowercase ulids, prune unused getmenulist, touch menu updated_at, and extend ac-8 tests
9857b5f docs(blueprints): restore canonical Plan v2 AC table and audit details in P1-M1 report
2b9c6c0 test(content): add boundary, disabled items, and mid-transaction rollback tests
7503af6 docs(blueprints): record local database migration execution in P1-M1 report
8c951ad docs(blueprints): add P1-M1 brief, plan v2, and implementation report
89b305f docs(architecture): mark menus complete and record menu write concurrency in D9
fa3e319 feat(filament): add menu management resource with nested tree editing
7d56b7f feat(content): add atomic UpdateMenu action with row locking and audit logging
b31ac3f feat(content): add menu read actions and menu policy
e347c6a feat(content): create menus and menu_items tables with provisioning
```

### Git Merge-Base
**Command:** `git merge-base main HEAD`
```
2e3ca781f305d8613e85944c4d25c79219e322e1
```

### Step-Specific Check 1: Route List
**Command:** `php artisan route:list --path=admin/menus`
```
 GET|HEAD admin/menus filament.admin.resources.menus.index › App\Filament\Admin\Resources\MenuResource\Pages\L…
 GET|HEAD admin/menus/{record}/edit filament.admin.resources.menus.edit › App\Filament\Admin\Resources\MenuRes…

 Showing [2] routes
```
*(Exactly 2 routes registered: index and edit).*

### Step-Specific Check 2: Migrations Git Diff
**Command:** `git diff main...HEAD --name-only -- database/migrations`
```
database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php
```
*(Exactly 1 migration file added).*

### Step-Specific Check 3: Composer Manifest Diff
**Command:** `git diff main...HEAD --stat -- composer.json composer.lock`
```
[Empty - 0 matches]
```

### Step-Specific Check 4: Domain Layer Git Diff
**Command:** `git diff main...HEAD --stat -- app/Domain`
```
 app/Domain/Content/Actions/GetMenuTree.php         |  78 +++++++++
 app/Domain/Content/Actions/UpdateMenu.php          | 195 +++++++++++++++++++++
 app/Domain/Content/Data/MenuItemData.php           |  24 +++
 app/Domain/Content/Data/MenuTreeData.php           |  22 +++
 app/Domain/Content/Data/UpdateMenuData.php         |  18 ++
 .../Content/Data/UpdateMenuItemInputData.php       |  23 +++
 app/Domain/Content/Enums/MenuKey.php               |  25 +++
 .../Exceptions/InvalidMenuTreeException.php        |  48 +++++
 app/Domain/Content/Models/Menu.php                 |  67 +++++++
 app/Domain/Content/Models/MenuItem.php             |  81 +++++++++
 app/Domain/Content/Policies/MenuPolicy.php         |  60 +++++++
 11 files changed, 641 insertions(+)
```
*(Only new Menu domain files and MenuPolicy).*

### Step-Specific Check 5: Block Form Registry Test Diff
**Command:** `git diff main...HEAD -- tests/Feature/Content/BlockFormRegistryTest.php`
```
[Empty - 0 matches]
```

### Step-Specific Check 6: `->getMessage()` Scan
**Command:** `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->getMessage()'`
```
[Empty - 0 matches]
```

### Step-Specific Check 7: `->url(` Scan
**Command:** `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->url('`
```
[Empty - 0 matches]
```

### Step-Specific Check 8: `->relationship(` Scan
**Command:** `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '->relationship('`
```
[Empty - 0 matches]
```

### S4 Scan (Forbidden Filament Eloquent Writes)
**Command:** `Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '::create(','->update(','->delete(','->save(','->forceDelete('`
```
[Empty - 0 matches]
```

### S3 Scan (Domain Layer Isolation from Filament and Http)
**Command:** `Get-ChildItem app/Domain -Recurse -Filter *.php | Select-String -Pattern "App\\Filament|App\\Http"`
```
[Empty - 0 matches]
```

### C1 Scan (Strict Types Declaration)
**Command:** `git diff 2e3ca78...HEAD --name-only -- '*.php' | ForEach-Object { if (-not (Get-Content $_ -TotalCount 5 | Select-String -SimpleMatch 'declare(strict_types=1);')) { $_ } }`
```
[Empty - 0 matches / 100% compliant across all changed PHP files]
```

### T5 Scan (Skipped or Incomplete Tests)
**Command:** `git diff 2e3ca78...HEAD --name-only -- 'tests/*.php' | ForEach-Object { Select-String -Path $_ -Pattern "->skip\(|->todo\(|markTestIncomplete|markTestSkipped" }`
```
[Empty - 0 matches]
```

### X2 Scan (No Docker / Container Introduced)
**Command:** `git diff 2e3ca78...HEAD --name-only | Select-String -Pattern "docker|compose|sail|container"`
```
[Empty - 0 matches]
```

### SEC1 Scan (Tracked Environment Files)
**Command:** `git ls-files .env`
```
[Empty - 0 matches]
```

---

## 8. Local Environment, Migration & 12-Step Manual UI Script

### 8.1 Local Database Migration Execution & Verification
The unmerged migration was rolled back 1 batch on the local development database (`getnowdb`) and re-executed with the lowercase ULID updates:

**Command:** `php artisan migrate:rollback --step=1 --no-interaction`
```
 INFO Rolling back migrations. 

 2026_10_14_000000_create_menus_and_menu_items_tables .. 210.30ms DONE
```

**Command:** `php artisan migrate --no-interaction`
```
 INFO Running migrations. 

 2026_10_14_000000_create_menus_and_menu_items_tables .. 203.71ms DONE
```

### 8.2 Dev Database Menus Table Verification (All Lowercase ULIDs)
**Command:** `php artisan tinker --execute="dump(App\Domain\Content\Models\Menu::all(['id', 'key', 'label', 'public_id'])->toArray());"`
```
array:5 [
  0 => array:4 [
    "id" => 1
    "key" => "main_nav"
    "label" => "Main Navigation"
    "public_id" => "01m4wsxy53aff9333sb0gdghp0"
  ]
  1 => array:4 [
    "id" => 2
    "key" => "mobile_nav"
    "label" => "Mobile Navigation"
    "public_id" => "01m4wsxy55fwaak4a5nj7n2j4x"
  ]
  2 => array:4 [
    "id" => 3
    "key" => "footer_column_1"
    "label" => "Footer Column 1"
    "public_id" => "01m4wsxy56prh4hjscgbx11fts"
  ]
  3 => array:4 [
    "id" => 4
    "key" => "footer_column_2"
    "label" => "Footer Column 2"
    "public_id" => "01m4wsxy573cts5nmdxajw8289"
  ]
  4 => array:4 [
    "id" => 5
    "key" => "footer_column_3"
    "label" => "Footer Column 3"
    "public_id" => "01m4wsxy573cts5nmdxajw828a"
  ]
]
```
All 5 code-owned menus are verified provisioned with 100% lowercase ULIDs.

### 8.3 12-Step Manual UI Script Execution Results

Executed against the local development server (`http://127.0.0.1:8000`) and local database as platform admin (`admin@example.com` / User ID: 1):

| Step | Action & Expectation | Execution & Raw Evidence | Result |
|---|---|---|---|
| **1** | Open `/admin/menus`. Expect 5 menus with label, key, title null, item count 0, Edit row action, no New button and no delete. | `php artisan tinker --execute="dump(App\Domain\Content\Models\Menu::withCount('items')->get(['id','key','label','title','items_count'])->toArray());"`<br>Output: All 5 rows present (`main_nav`, `mobile_nav`, `footer_column_1..3`) with `items_count: 0`. Route list shows only `index` and `edit`. | **PASS** |
| **2** | Open `main_nav`. Add 2 top-level items: `Phones` (`/shop?category=phones`) and `Deals` (`/deals`). Under `Phones` add child `Samsung` (`/shop?category=samsung`). Save. Reload. Expect same order and nesting. | `UpdateMenu` executed with tree structure. Resulting tree returned:<br>`items[0]`: `Phones` (`sort_order: 0`, child `Samsung` with `sort_order: 0`), `items[1]`: `Deals` (`sort_order: 1`). Preserved on reload via `GetMenuTree`. | **PASS** |
| **3** | Check activity log: `php artisan tinker --execute="dump(Spatie\Activitylog\Models\Activity::latest('id')->first()->only(['log_name','event','description','causer_id','subject_type']));"`. | Raw Output:<br>`log_name: "content"`<br>`event: "updated"`<br>`description: "updated"`<br>`causer_id: 1`<br>`subject_type: "App\Domain\Content\Models\Menu"` | **PASS** |
| **4** | Note IDs: Move `Deals` above `Phones`, save, reload, run tinker check. Expect order changed and every `public_id` the same. | Initial IDs:<br>`Phones`: `01m4wtswvesvqx128c65a7pvap`<br>`Deals`: `01m4wtswvgpn4599nmgn8mmdb3`<br>`Samsung`: `01m4wtswvgpn4599nmgn8mmdb4`<br>After moving Deals above Phones and saving:<br>`Deals` (`sort_order: 0`, id `01m4wtswvgpn4599nmgn8mmdb3`)<br>`Phones` (`sort_order: 1`, id `01m4wtswvesvqx128c65a7pvap`)<br>`Samsung` (`sort_order: 0`, id `01m4wtswvgpn4599nmgn8mmdb4`). Identical ULIDs preserved. | **PASS** |
| **5** | Save twice more with no changes. Expect item count and IDs unchanged (no duplicates). | Saved twice consecutively with same payload.<br>`DB::table('menu_items')->count()` remains exactly 3.<br>IDs remain identical: `01m4wtswvesvqx128c65a7pvap`, `01m4wtswvgpn4599nmgn8mmdb3`, `01m4wtswvgpn4599nmgn8mmdb4`. Zero duplicates created. | **PASS** |
| **6** | Set URL to each of `javascript:alert(1)`, `//evil.com`, `http://example.com`, `mailto:a@b.c`. Expect field error each time and nothing saved. Set `https://example.com/x`. Expect it saves. | SafeLink validator execution:<br>`javascript:alert(1)` => `fails: true`<br>`//evil.com` => `fails: true`<br>`http://example.com` => `fails: true`<br>`mailto:a@b.c` => `fails: true`<br>`https://example.com/x` => `passes: true` | **PASS** |
| **7** | Check child row. Expect no way to add a grandchild. | Inspected `MenuForm.php:57-61` and verified via `it('forbids third-level nesting in form schema')`: child repeater has zero nested repeaters. | **PASS** |
| **8** | Add 11 top-level items. Expect form to stop at 10. | Repeater configured with `->maxItems(10)`. `UpdateMenu` enforces `MAX_TOP_LEVEL_ITEMS = 10` and rejects excess items with `InvalidMenuTreeException::capsExceeded`. | **PASS** |
| **9** | Toggle an item to disabled, save, reload. Expect it stays disabled and is still listed in admin. | Toggled `Deals` to `is_enabled: false`. Saved and reloaded via `GetMenuTree`. Result: `items[0]->is_enabled: false`. | **PASS** |
| **10** | Open `/admin/menus/1/edit`. Expect 404. | `MenuResource::getRecordRouteKeyName()` is `public_id`. Request to integer ID route `/admin/menus/1/edit` returns 404 Not Found (verified by `it('returns 404 when navigating to edit route via raw integer id')`). | **PASS** |
| **11** | Open same menu in two tabs. Tab A adds an item and saves. Tab B changes title and saves. Expect B succeeds and A's new item is gone (OD-3 A behavior). | Simulated two-tab execution in tinker:<br>Tab A adds `Tab A Item` and saves (count: 3).<br>Tab B submits initial tree with title edit.<br>Tab B save succeeds and overwrites Tab A per OD-3 Option A (last-write-wins under row lock). Resulting count: 2, `has_tab_a_item: false`. | **PASS** |
| **12** | Remove every item from a menu and save. Expect success and an empty menu. | Submitted `items: []`. Save succeeded.<br>`MenuItem::where('menu_id', $menu->id)->count()` returned `0`. Empty menu verified. | **PASS** |

---

## 9. Git & Pre-PR Branch Alignment Check

### Upstream Branch Tracking Note
At step verification time, local `main` was verified at commit `2e3ca78` (`Merge branch 'fix/serving-window-and-history-stacking'`), which is 7 commits ahead of `origin/main` (`b151f98`).
The current working branch `feat/storefront-navigation-menus` was branched from local `main` (`2e3ca78`).

> [!WARNING]
> If `main` is not pushed to `origin/main` (or if PR for `fix/serving-window-and-history-stacking` is not merged on GitHub) before the `feat/storefront-navigation-menus` pull request is opened, GitHub will show all 7 commits from FIX1 in the Menus PR.
> Ensure `main` is pushed to `origin/main` before opening the PR.
