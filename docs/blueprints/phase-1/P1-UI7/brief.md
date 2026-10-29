[P1-UI7 | BRIEF | Claude]

Goal: Reorganize `MenuForm` (`app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php`)
  so that editing a navigation menu in `/admin/menus/{record}/edit` is visually structured
  and scannable instead of a long vertical list of inputs: separate `Menu Details` from
  `Top-Level Menu Items (Level 1)`, make top-level and level-2 repeater items collapsible
  (`collapsed()` by default) with dynamic summary labels (`label`, `url`, child count,
  `[New Tab]`, `[Disabled]`), arrange item fields in compact 12-column horizontal rows, and
  nest `children` inside a visually distinct collapsible `Dropdown Sub-Items (Level 2)`
  section while preserving all existing `GetMenuTree` / `UpdateMenu` state paths and
  validation rules.

Depends on (verify exact signatures from code in Section 3):
  MenuResource (`app/Filament/Admin/Resources/MenuResource.php`)
  ListMenus & EditMenu (`app/Filament/Admin/Resources/MenuResource/Pages/`)
  MenuForm (`app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php`)
  MenuTable (`app/Filament/Admin/Resources/MenuResource/Tables/MenuTable.php`)
  Menu & MenuItem models (`app/Domain/Content/Models/`)
  MenuKey enum (`app/Domain/Content/Enums/MenuKey.php`)
  GetMenuTree & UpdateMenu Actions (`app/Domain/Content/Actions/`)
  MenuTreeData, MenuTreeItemData, UpdateMenuData, UpdateMenuItemInputData
    (`app/Domain/Content/Data/`)
  SafeLink & NoHtml rules (`app/Support/Rules/`)
  Existing tests in `tests/Feature/Admin/Menu/MenuResourceTest.php`

In scope:
  1. Reorganize `app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php`:
     a) **Section 1 — `Section::make('Menu Details')`:**
        - Contains `TextInput::make('title')` (preserving existing max length `255`,
          `NoHtml`, and nullable handling) with helper text explaining that the title is
          displayed as the heading on storefront footer menu columns.
     b) **Section 2 — `Section::make('Top-Level Menu Items (Level 1)')`:**
        - Description indicating the maximum capacity (`'Up to 10 top-level items. Drag to reorder, or click an item to expand its fields and dropdown sub-items.'`).
        - Wraps `Repeater::make('items')` with:
          * `->collapsible()` and `->collapsed()` so existing top-level items load as
            compact summary bars.
          * `->collapseAllAction(...)` and `->expandAllAction(...)` with clear labels
            (`'Collapse All'` / `'Expand All'`).
          * `->addActionLabel('Add Top-Level Item')`.
          * Dynamic, null-safe `->itemLabel(function (array $state): string { ... })`
            closure that formats:
            - `$label`: `filled($state['label'] ?? null) ? (string) $state['label'] : 'New menu item'`
            - `$urlPart`: `filled($state['url'] ?? null) ? '  —  ' . (string) $state['url'] : ''`
            - `$children`: `isset($state['children']) && is_array($state['children']) ? $state['children'] : []`
            - `$countPart`: when `count($children) > 0`, append `"  •  (" . count($children) . ' ' . (count($children) === 1 ? 'sub-item' : 'sub-items') . ')'`
            - `$newTabPart`: when `! empty($state['opens_in_new_tab'])`, append `'  [New Tab]'`
            - `$disabledPart`: when `array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']`,
              append `'  [Disabled]'`
          * **Compact 12-Column Horizontal Row for Top-Level Item Fields:**
            - Preserve `Hidden::make('public_id')`!
            - Arrange the 4 visible inputs inside `Grid::make(12)`:
              1. `TextInput::make('label')->live(onBlur: true)->columnSpan(['default' => 12, 'md' => 4])`
                 (preserving `required()`, max length `80`, `NoHtml`).
              2. `TextInput::make('url')->live(onBlur: true)->columnSpan(['default' => 12, 'md' => 4])`
                 (preserving `required()`, max length `500`, `SafeLink`, plain text input
                 without `->url()`).
              3. `Toggle::make('opens_in_new_tab')->label('New tab')->live()->inline(false)->columnSpan(['default' => 6, 'md' => 2])`.
              4. `Toggle::make('is_enabled')->label('Enabled')->live()->inline(false)->columnSpan(['default' => 6, 'md' => 2])`.
          * **Inset `Section::make('Dropdown Sub-Items (Level 2)')` Inside Each Top-Level Item:**
            - `->compact()->collapsible()->collapsed()`
            - Dynamic `->description(function (Get $get): string { ... })` displaying the
              current sub-item count for this parent (e.g. `"{$count} / 10 sub-items"`).
            - Contains `Repeater::make('children')` with:
              1. `->hiddenLabel()` (since the enclosing Section header labels it).
              2. `->collapsible()` and `->collapsed()`.
              3. `->addActionLabel('Add Sub-Item')`.
              4. Dynamic, null-safe `->itemLabel(function (array $state): string { ... })`
                 formatting:
                 `"{label}{urlPart}{newTabPart}{disabledPart}"` (using `'New sub-item'` when
                 `label` is blank).
              5. Preserve `Hidden::make('public_id')` and arrange the 4 child inputs inside
                 `Grid::make(12)`:
                 - `TextInput::make('label')->live(onBlur: true)->columnSpan(['default' => 12, 'md' => 4])`
                 - `TextInput::make('url')->live(onBlur: true)->columnSpan(['default' => 12, 'md' => 4])`
                 - `Toggle::make('opens_in_new_tab')->label('New tab')->live()->inline(false)->columnSpan(['default' => 6, 'md' => 2])`
                 - `Toggle::make('is_enabled')->label('Enabled')->live()->inline(false)->columnSpan(['default' => 6, 'md' => 2])`
  2. Extend `tests/Feature/Admin/Menu/MenuResourceTest.php` with tests verifying:
     - Top-level `items` repeater and nested `children` repeater are configured as
       collapsible and collapsed by default (`isCollapsible()`, `isCollapsed()`).
     - Dynamic `itemLabel` closures on both `items` and `children` format populated items
       (with child counts, `[New Tab]`, and `[Disabled]` badges) and handle blank/null new
       items gracefully (`'New menu item'`, `'New sub-item'`).
     - Creating, reordering, editing, and saving a menu with top-level and level-2 items
       continues to persist through `UpdateMenu` and preserve `public_id` ULIDs.
  3. Update `ARCHITECTURE.md` Section 1 Phase 1 progress list to record `Step P1-UI7`.

Out of scope:
  - Adding a "Menu Tree Preview" sidebar panel or custom Blade views (explicitly rejected
    during `P1-UI5` review as unrequested scope creep).
  - Modifying `GetMenuTree`, `UpdateMenu`, `Menu`, `MenuItem`, or any database migration
    (`Rule DB2`).
  - Adding a 3rd nesting level to Menus (depth remains strictly 2 per `ARCHITECTURE.md` §6).
  - Adding any Composer or NPM package (`Rule X1`).

Read first:
  - `ARCHITECTURE.md` sections 1, 5, 6, 8, 12 (decisions D4, D7, D9).
  - `AGENTS.md` ('Where code goes', 'Coding standards', 'Content rules (critical)', 'Testing').
  - Inspect existing files in `C:\dev\getnow-cms`:
    * `app/Filament/Admin/Resources/MenuResource.php`
    * `app/Filament/Admin/Resources/MenuResource/Pages/ListMenus.php`
    * `app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php`
    * `app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php`
    * `app/Filament/Admin/Resources/MenuResource/Tables/MenuTable.php`
    * `app/Domain/Content/Data/UpdateMenuData.php` and `UpdateMenuItemInputData.php`
    * `tests/Feature/Admin/Menu/MenuResourceTest.php`
    * Verify Filament 5 `Repeater`, `Section`, `Grid`, and `Toggle` method signatures in
      `vendor/filament/` in Section 3 before writing Section 5.

Decisions made (do not re-decide):
  D-a Form Organization Only — No Menu Tree Sidebar Panel:
      Do NOT add a side-by-side Menu Structure Preview Blade panel or custom page view.
      Solve the menu organization problem entirely inside `MenuForm.php` using native
      Filament 5 `Section`, `Grid`, and collapsible `Repeater` components.
  D-b Zero State-Path Changes:
      Do NOT call `->statePath(...)` on any `Section` or `Grid` wrapper in `MenuForm.php`.
      All state paths (`data.title`, `data.items`, `data.items.*.public_id`,
      `data.items.*.label`, `data.items.*.url`, `data.items.*.opens_in_new_tab`,
      `data.items.*.is_enabled`, `data.items.*.children`,
      `data.items.*.children.*.public_id`, etc.) must remain 100% unchanged so `EditMenu`
      hydration, `UpdateMenu` persistence, and validation error remapping work untouched.
  D-c Preserve All Field Constraints & `Hidden::make('public_id')`:
      Keep `Hidden::make('public_id')` inside both `items` and `children` repeaters, keep
      plain text inputs with `SafeLink` on `url` (never `->url()`), and keep exact max
      lengths (`title`: 255, `label`: 80, `url`: 500) and repeater caps (`maxItems(10)`,
      `defaultItems(0)`).

Open decisions:
  None.

Acceptance criteria:
  AC-1  `MenuForm` separates `Section::make('Menu Details')` (`title`) from
        `Section::make('Top-Level Menu Items (Level 1)')` (`items`).
  AC-2  Both `Repeater::make('items')` and `Repeater::make('children')` are configured with
        `->collapsible()` and `->collapsed()`, and `items` provides `'Collapse All'` and
        `'Expand All'` actions.
  AC-3  The `itemLabel` closure on `Repeater::make('items')` returns
        `"{label}  —  {url}  •  ({N} sub-item|sub-items)  [New Tab]  [Disabled]"` when
        populated and falls back to `'New menu item'` when `label` is empty/null.
  AC-4  The `itemLabel` closure on `Repeater::make('children')` returns
        `"{label}  —  {url}  [New Tab]  [Disabled]"` when populated and falls back to
        `'New sub-item'` when `label` is empty/null.
  AC-5  Inside both `items` and `children` repeaters, `Hidden::make('public_id')` is
        preserved and `label`, `url`, `opens_in_new_tab`, and `is_enabled` are arranged in
        a 12-column `Grid` (`4 + 4 + 2 + 2` columns on `md+`) with live reactivity
        (`live(onBlur: true)` on text inputs, `live()` on toggles).
  AC-6  `Repeater::make('children')` is nested inside a collapsible, compact
        `Section::make('Dropdown Sub-Items (Level 2)')` whose description dynamically shows
        `"{count} / 10 sub-items"`.
  AC-7  All existing tests in `tests/Feature/Admin/Menu/MenuResourceTest.php` and the new
        collapsible/itemLabel/organization tests pass, `ARCHITECTURE.md` §1 is updated, and
        `composer check` (Pint, PHPStan Level 8, Pest including `tests/Arch`) passes with
        zero errors.

Mutation spot-checks (Layer B — must be executed and reported in `IMPL-REPORT`):
  - `MUT-1` (Collapsed default): In `MenuForm.php`, remove `->collapsed()` from
    `Repeater::make('items')` -> `MenuResourceTest` collapsible/collapsed configuration test
    must FAIL.
  - `MUT-2` (Dynamic itemLabel sub-item count): In `MenuForm.php`, hardcode `$childCount = 0`
    inside the `items` `itemLabel` closure -> `MenuResourceTest` dynamic item label test
    must FAIL.
  - `MUT-3` (Hidden `public_id` preservation): In `MenuForm.php`, comment out
    `Hidden::make('public_id')` on `items` -> `MenuResourceTest` existing item ULID
    preservation test on save must FAIL.

Rules most at risk:
  - S2 & S4 (`MenuForm` is pure schema configuration; all saves still go through `EditMenu`
    -> `UpdateMenu`).
  - K3 & K4 (`NoHtml` on `title`/`label`, `SafeLink` without Filament `->url()` on `url`).
  - C1, C2, C7 (`declare(strict_types=1);` and PHPStan Level 8 clean types on all closures
    in `MenuForm.php`).
  - T5 (zero existing tests in `MenuResourceTest.php` weakened or skipped).

Known traps:
  1. Filament `->url()` trap: Never add `->url()` to `TextInput::make('url')` because it
     rejects relative paths like `/shop`.
  2. Layout wrapper `statePath` trap: Never add `->statePath(...)` to `Section` or `Grid`
     wrappers inside `MenuForm`, or `EditMenu`'s `$data['items']` hydration and error
     remapping will break.
  3. Null/uninitialized repeater state in `itemLabel` and `Section::description`: When an
     item is newly added in Livewire, `$state['label']`, `$state['url']`, and
     `$state['children']` may be `null` or missing. Guard every array access with `?? null`
     and `is_array(...)` so PHPStan Level 8 and runtime rendering never fail.

Size guard and checkpoints:
  Expected 3 files (`MenuForm.php`, `MenuResourceTest.php`, `ARCHITECTURE.md`), single
  checkpoint (2 commits: Commit 1 for `MenuForm.php` + `MenuResourceTest.php`, Commit 2 for
  `ARCHITECTURE.md`). More than 5 files (excluding `docs/blueprints/P1-UI7/*`) means stop
  and send a CR.

Manual verification script (Layer C):
  1. Run `php artisan db:seed --no-interaction` (if not already seeded) and start
     `php artisan serve`.
  2. Open `/admin/menus` and click `Edit` on `Main Navigation` (`main_nav`):
     - Verify `Menu Details` (`Title`) is in its own top section and `Top-Level Menu Items (Level 1)`
       is in a separate section below it.
     - Verify all 6 top-level items load **collapsed** as clean 1-line summary cards showing
       `Label  —  URL  •  (N sub-items)` (e.g., `All Categories  —  /shop  •  (6 sub-items)`).
     - Capture a screenshot of the collapsed top-level menu list.
  3. Click to expand `All Categories`:
     - Verify `Label`, `URL`, `New tab`, and `Enabled` sit on a single horizontal row.
     - Verify `Dropdown Sub-Items (Level 2)` appears as an inset collapsible section showing
       `6 / 10 sub-items`.
     - Expand `Dropdown Sub-Items (Level 2)` and verify the 6 sub-items appear as collapsed
       1-line summary cards, and expanding one shows its 4 fields on a single horizontal row.
     - Capture a screenshot of the expanded item and its sub-items.
  4. Toggle `Enabled` off on one item and click outside a modified `Label` field:
     - Verify the card's summary header immediately updates with the new label and
       `[Disabled]`.
  5. Click `Save changes`, reload the page, and verify all items and ULIDs persist cleanly.
     Stop `php artisan serve`.

Step-specific checks (Layer A extras):
  git diff main...HEAD -- database composer.json composer.lock package.json
  git diff main...HEAD -- app/Domain
  Get-ChildItem app/Filament -Recurse -Filter *.php | Select-String -SimpleMatch '::create(','->update(','->delete(','->save(','->url('
