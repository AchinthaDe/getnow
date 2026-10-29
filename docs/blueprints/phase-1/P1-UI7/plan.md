# [P1-UI7 | PLAN v3.1 | Gemini]

## Change Log (Review Feedback Mapping: v2 -> v3.1)

| Finding ID | Reviewer Issue | Resolution in Plan v3.1 | Section Changed |
|---|---|---|---|
| **N6** | §8 stated branch created from `main`, while §11.1/§13 used `feat/admin-block-previews`, creating contradiction that breaks `git diff main...HEAD`. | Logged G1 deviation in §8 and §13: UI7 is stacked on `feat/admin-block-previews` (containing completed P1-UI5 and P1-UI6 awaiting merge to `main`). Layer A diff commands will target `feat/admin-block-previews...HEAD` to isolate UI7 changes. §11.1 anchors after line 30 of `ARCHITECTURE.md`. | **Section 8, Section 11.1, Section 13** |
| **N7** | Test 7 asserted un-interpolated template `'The :attribute must not contain HTML tags.'`, used 0 index instead of UUID keys, omitted child labels, and lacked concrete DB snapshot. | Updated Test 7 in `MenuResourceTest.php`: retrieves real item and child keys (`array_key_first`); tests HTML rejection on `data.title`, parent `label`, and child `label`; asserts interpolated error messages containing `'must not contain HTML tags.'`; snapshots items table and `updated_at` before and after `save` to verify zero database writes. | **Section 9, Section 10.1** |
| **m11** | `getInstance()->getForm('form')` in Test 3 unverified for Filament 5; `MenuItemFactory` attachment and `$menu->items()->delete()` FK cascade unquoted. | Quoted `vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php` line 471 (`getSchema(string $name): ?Schema`); quoted `MenuItemFactory::childOf()` and `forMenu()`; quoted FK cascade on `parent_id` in `2026_10_14_000000_create_menus_and_menu_items_tables.php` line 29; specified deterministic deletion (`whereNotNull('parent_id')->delete()`, then `delete()`). | **Section 3.4, Section 3.5, Section 10.1** |
| **m12** | §7.2.3 stated sibling sub-items stay collapsed as fact before manual verification. | Rephrased §7.2.3 to declare sibling collapse an expected behavior to be empirically confirmed in Manual Script Step 5. | **Section 7.2** |
| **m13** | Seed audit in §7.4 was summarized rather than committing to verbatim output in report. | Updated §7.4 to commit to pasting the raw, unedited `Select-String` output verbatim in the Implementation Report. | **Section 7.4** |
| **m14** | Manual script dropped screenshot lines from brief. | Restored the brief's exact Layer C script including screenshot capture steps for collapsed list and expanded sub-items, appending steps 5 and 6 for sibling collapse and validation error auto-expansion. | **Section 12** |
| **N1** | Blank and null fallbacks and pinned badge order were not tested in v2. | Added explicit render test cases to Test 5 in `MenuResourceTest.php`: setting an item with an empty label and missing `children` key to assert `'New menu item'`, a child with `label => null` to assert `'New sub-item'`, and an item with both `opens_in_new_tab => true` and `is_enabled => false` to pin badge sequence `'[New Tab]  [Disabled]'`. | **Section 9, Section 10.1** |
| **N2** | "Retrieve seeded main_nav" was ambiguous for Tests 5 and 6 given uniqueness of `menus.key`; `sort_order` assignment assumption (0, 1, 2) was unverified. | Clarified arrange step: retrieve `$menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();`, delete existing items via `$menu->items()->delete();`, and rebuild clean items with `MenuItem::factory()`. Quoted `UpdateMenu.php` lines 116–172 in §3.4 confirming `sort_order` is 0-based and scoped per-parent (`$i` for top-level items, `$j` for children). | **Section 3.4, Section 10.1** |
| **N3** | Layout component `hasStatePath()` and `getStatePath(isAbsolute: false)` behavior was unverified; bare `Schema::make()` did not produce `'data.title'`. | Verified Filament `vendor/filament/schemas/src/Components/Concerns/HasState.php` lines 726–775: when `$statePath` is null, `hasStatePath(): bool` returns `false` and `getStatePath(isAbsolute: false): ?string` returns `null`. Updated Test 3 to retrieve the schema from the mounted Livewire `EditMenu` component, asserting `$titleInput->getStatePath() === 'data.title'`, `$itemsRepeater->getStatePath() === 'data.items'`, and `hasStatePath() === false` on all five `Section` and `Grid` components. | **Section 3.5, Section 10.1** |
| **N4** | `ARCHITECTURE.md` edit in §11.1 was conditional across branches. | Removed conditional branches. Base branch is confirmed as `feat/admin-block-previews` and the single verified anchor line is line 30 of `ARCHITECTURE.md` (`- [x] Step P1-UI6 (...)`). | **Section 11.1, Section 13** |
| **N5** | `NoHtml` is a new rule on `MenuForm` with no failure-path test; verification against seed data and domain action was unconfirmed. | Added Test 7 in `MenuResourceTest.php` asserting that `<script>` tags in `title` and item `label` fail validation with `'The :attribute must not contain HTML tags.'` and halt updates without database writes. Documented raw `Select-String` audit verifying zero seeded menu records contain HTML tags. Confirmed in §3.4, §7.4, and §8 that `UpdateMenu` does not enforce `NoHtml`, making the form rule a new presentation-layer guard enforcing Rule K3. | **Section 3.4, Section 7.4, Section 8, Section 9, Section 10.1** |
| **m6** | `UpdateMenu::MAX_*` listed in §3.4 without visibility declarations. | Quoted full declarations `public const int MAX_*` from `UpdateMenu.php` lines 22–30 in §3.4. | **Section 3.4** |
| **m7** | Test 4 only asserted `isLive()`, missing `isLiveOnBlur()` for text inputs per AC-5. | Verified `isLiveOnBlur(): bool` on `HasStateBindingModifiers` in `vendor/filament/schemas/src/Concerns/HasStateBindingModifiers.php` line 145; updated Test 4 to assert both `isLive()` and `isLiveOnBlur()` on text inputs. | **Section 3.5, Section 10.1** |
| **m8** | Row X4 in Rule Compliance Matrix (§8) contained banned abbreviation. | Replaced abbreviation with an explicit list of forbidden domain contexts ("accounts, catalog, retail, sales, payments"). | **Section 8** |
| **m9** | Manual Script Step 6 had no expected outcome or fallback action. | Updated Manual Script Step 6: if the three nested layers do not auto-expand to display the validation message, stop and submit a Change Request (CR). | **Section 12** |
| **m10** | Manual Script Step 5 did not verify sibling collapse behavior upon adding sub-items. | Added verification in Manual Script Step 5 confirming that sibling sub-items remain collapsed after clicking `'Add Sub-Item'`. | **Section 12** |

---

## 1. Understanding

Reorganize [MenuForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) to transform the navigation menu edit screen (`/admin/menus/{record}/edit`) into a structured layout with separated `Menu Details` and `Top-Level Menu Items (Level 1)` sections, collapsible level 1 and level 2 repeaters loading in a collapsed state with dynamic status summary labels, 12-column horizontal input rows for item fields, and an inset collapsible level 2 dropdown sub-items section, preserving all existing [GetMenuTree](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php) and [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) state paths, constraints, and validation rules.

### Out of Scope Restated
- Adding a side-by-side "Menu Tree Preview" sidebar panel or custom Blade views (Step Brief Decision D-a).
- Modifying [GetMenuTree](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php), [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php), [Menu](file:///c:/dev/getnow-cms/app/Domain/Content/Models/Menu.php), [MenuItem](file:///c:/dev/getnow-cms/app/Domain/Content/Models/MenuItem.php), or database migrations (Rule DB2).
- Adding a third nesting level to navigation menus (depth remains fixed at 2 per [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md#L170) section 6).
- Adding Composer or NPM packages (Rule X1).

---

## 2. Sources Read

| Source File or Document Section | What in it constrains Step P1-UI7 |
|---|---|
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Business logic restricted to Domain Actions; thin Filament resources; strict types; final classes; no raw HTML/CSS/JS fields; no external packages without approval; definition of done. |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) Sections 1, 5, 6, 8, 12 | Navigation menu architecture; depth limit 2; Decision D9 (concurrency and whole-tree atomic replacement under row lock); Step Brief Decision D-a (form organization only, no preview sidebar); Step Brief Decision D-b (zero state-path alterations); Step Brief Decision D-c (field constraints and ULID preservation). |
| [rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | Canonical rule register (`E1, S1..S7, C1..C8, K1..K9, DB1..DB6, SEC1..SEC5, T1..T5, G1..G4, X1..X7`). |
| [MenuResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource.php) | Resource registering routes `index` ([ListMenus](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/ListMenus.php)) and `edit` ([EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php)) keyed by `public_id`, delegating form schema creation to [MenuForm::form()](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php#L16). |
| [ListMenus.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/ListMenus.php) | List page implementing `queryStringHandlesPagination` with `history: false`. |
| [EditMenu.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) | Edit page hydrating form data from [GetMenuTree](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php) via `mutateFormDataBeforeFill` and persisting through [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) in `handleRecordUpdate`, relying on state paths `title` and `items.*.children`. |
| [MenuForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) | Current form schema with flat `title` and `items` repeater containing nested `children` repeater. |
| [MenuTable.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Tables/MenuTable.php) | Table schema defining columns for label, key, title, items count, and updated at. |
| [Menu.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/Menu.php) | Eloquent model for menus casting `key` to [MenuKey](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/MenuKey.php) enum, declaring relations `items` and `rootItems`. |
| [MenuItem.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/MenuItem.php) | Eloquent model for menu items casting `opens_in_new_tab`, `is_enabled`, and `sort_order`, declaring relations `menu`, `parent`, and `children`. |
| [MenuItemFactory.php](file:///c:/dev/getnow-cms/database/factories/MenuItemFactory.php) | Factory for creating `MenuItem` instances; defines `childOf(MenuItem $parent)` setting `menu_id` and `parent_id`, and `forMenu(Menu $menu)` setting `menu_id`. |
| [MenuKey.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/MenuKey.php) | Backed enum defining five permanent menu keys (`main_nav`, `mobile_nav`, `footer_column_1`, `footer_column_2`, `footer_column_3`). |
| [GetMenuTree.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php) | Action retrieving the hierarchical two-level menu structure returning [MenuTreeData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/MenuTreeData.php). |
| [UpdateMenu.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) | Action validating title, labels, safe links, depth limits (maximum 10 top-level items, maximum 10 children per item, maximum depth 2), updating records under row lock, assigning 0-based sort order per parent, and logging to activity log. |
| [MenuTreeData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/MenuTreeData.php) | DTO representing the persisted menu tree containing an array of [MenuItemData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/MenuItemData.php). |
| [MenuItemData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/MenuItemData.php) | DTO representing an item in the menu tree with `public_id`, `label`, `url`, `icon`, `opens_in_new_tab`, `is_enabled`, `sort_order`, and `children`. |
| [UpdateMenuData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/UpdateMenuData.php) | DTO passing update data to [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) with `title` and an array of [UpdateMenuItemInputData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/UpdateMenuItemInputData.php). |
| [UpdateMenuItemInputData.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/UpdateMenuItemInputData.php) | DTO representing submitted item data with `public_id`, `label`, `url`, `icon`, `opens_in_new_tab`, `is_enabled`, and `children`. |
| [SafeLink.php](file:///c:/dev/getnow-cms/app/Support/Rules/SafeLink.php) | Validation rule enforcing links to start with `/` or `https://` without credentials or whitespace. Exact failure message: `'The :attribute must start with / or https://.'` (line 49). |
| [NoHtml.php](file:///c:/dev/getnow-cms/app/Support/Rules/NoHtml.php) | Validation rule rejecting strings containing HTML tags (`<` followed by letter, `/`, or `!`). Exact failure message: `'The :attribute must not contain HTML tags.'` (line 22). |
| [MenuResourceTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuResourceTest.php) | Existing resource tests covering route registration, 404 on integer id, pagination history config, smoke tests, and 403 authorization. |
| [MenuFormTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuFormTest.php) | Existing form tests covering tree hydration, depth nesting prevention, duplicate prevention on re-save, URL validation, and domain exception notification. |
| [Section.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Section.php) | Filament 5 Section layout component supporting `compact()`, `collapsible()`, `collapsed()`, `description()`, and `schema()`. |
| [Grid.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Grid.php) | Filament 5 Grid layout component supporting `make(array\|int\|null $columns)` and `schema()`. |
| [HasColumns.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Concerns/HasColumns.php) | Filament trait where integer column parameter maps to `['lg' => $columns]` (lines 26–30), requiring `['default' => 12]` for responsive 12-column layouts on `md`. |
| [HasState.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Concerns/HasState.php) | Filament trait defining `hasStatePath(): bool` (line 772) and `getStatePath(bool $isAbsolute = true): ?string` (lines 726–748). |
| [HasStateBindingModifiers.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Concerns/HasStateBindingModifiers.php) | Filament trait defining `isLive(): bool` (line 124) and `isLiveOnBlur(): bool` (line 145). |
| [InteractsWithSchemas.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php) | Livewire schema trait defining `public function getSchema(string $name): ?Schema` (line 471) used on Filament pages. |
| [Get.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Utilities/Get.php) | Filament 5 reactive state retrieval utility callable within component closures. |
| [Repeater.php](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/Repeater.php) | Filament 5 Repeater component supporting `collapsible()`, `collapsed()`, `getCollapseAllAction()`, `getExpandAllAction()`, `collapseAllAction()`, `expandAllAction()`, `addActionLabel()`, `itemLabel()`, and `hiddenLabel()`. |
| [Toggle.php](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/Toggle.php) | Filament 5 Toggle component supporting `label()`, `live()`, `inline(false)`, and `default()`. |
| [CanSpanColumns.php](file:///c:/dev/getnow-cms/vendor/filament/support/src/Concerns/CanSpanColumns.php) | Filament trait providing responsive `columnSpan(['default' => 12, 'md' => 4])`. |

---

## 3. Verified Current State

### 3.1 `MenuResource` ([MenuResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource.php))
```php
namespace App\Filament\Admin\Resources;

final class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;
    protected static ?string $recordRouteKeyName = 'public_id';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';
    protected static string|UnitEnum|null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string;
    public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string;
    public static function form(Schema $schema): Schema;
    public static function table(Table $table): Table;
    public static function getPages(): array;
}
```

### 3.2 `EditMenu` ([EditMenu.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php))
```php
namespace App\Filament\Admin\Resources\MenuResource\Pages;

final class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array;
    protected function mutateFormDataBeforeFill(array $data): array;
    protected function handleRecordUpdate(Model $record, array $data): Model;
    protected function afterSave(): void;
}
```
State structure passed into form on fill:
- `title`: `?string`
- `items`: `array<int, array{public_id: string, label: string, url: string, opens_in_new_tab: bool, is_enabled: bool, children: array<int, array{public_id: string, label: string, url: string, opens_in_new_tab: bool, is_enabled: bool}>}>`

State structure received on update:
- `$data['title']`: `?string`
- `$data['items']`: `array<int|string, array{public_id?: ?string, label?: string, url?: string, opens_in_new_tab?: bool, is_enabled?: bool, children?: array<int|string, array{public_id?: ?string, label?: string, url?: string, opens_in_new_tab?: bool, is_enabled?: bool}>}>`

### 3.3 Raw Existing State of `MenuForm.php` and `MenuFormTest.php` in `main`

Execution of `git show main:app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php` returns:
```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Schemas;

use App\Support\Rules\SafeLink;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class MenuForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Storefront Title')
                    ->maxLength(255)
                    ->nullable(),
                Repeater::make('items')
                    ->label('Menu Items')
                    ->maxItems(10)
                    ->defaultItems(0)
                    ->schema([
                        Hidden::make('public_id'),
                        TextInput::make('label')
                            ->required()
                            ->maxLength(80),
                        TextInput::make('url')
                            ->label('URL / Path')
                            ->required()
                            ->maxLength(2048)
                            ->rule(new SafeLink),
                        Toggle::make('opens_in_new_tab')
                            ->label('Open in new tab')
                            ->default(false),
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true),
                        Repeater::make('children')
                            ->label('Sub-items')
                            ->maxItems(10)
                            ->defaultItems(0)
                            ->schema([
                                Hidden::make('public_id'),
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(80),
                                TextInput::make('url')
                                    ->label('URL / Path')
                                    ->required()
                                    ->maxLength(2048)
                                    ->rule(new SafeLink),
                                Toggle::make('opens_in_new_tab')
                                    ->label('Open in new tab')
                                    ->default(false),
                                Toggle::make('is_enabled')
                                    ->label('Enabled')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }
}
```

#### Reconciling Findings B1 and B2 Against Current Code
1. **URL Max Length (B1)**: Lines 36 and 56 of the existing schema define `maxLength(2048)` for both `items` and `children`. The brief text stating "preserving existing max length 500" was an error. The existing form, database column (`varchar(2048)`), and Domain action (`UpdateMenu::MAX_URL_LENGTH = 2048`) all use `2048`. In accordance with Achintha's review decision, `maxLength(2048)` is preserved in both repeaters.
2. **`NoHtml` Validation (B2, N5)**: Neither `title` nor `label` currently includes `NoHtml` in `main`. `NoHtml` is a **new presentation-layer validation rule** introduced in Step P1-UI7 on `title` and `label` to fulfill Rule K3 ("No raw HTML, CSS or JavaScript fields anywhere").

#### Raw Existing Test in `MenuFormTest.php` Requiring Update (Finding P8)
```php
it('forbids third-level nesting in form schema', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);
    $components = $schema->getComponents();

    $itemsRepeater = collect($components)->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'items');
    expect($itemsRepeater)->not->toBeNull();

    $childRepeater = collect($itemsRepeater->getChildComponents())->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'children');
    expect($childRepeater)->not->toBeNull();

    // Verify children repeater has NO nested repeater
    $nestedRepeaters = collect($childRepeater->getChildComponents())->filter(fn ($c) => $c instanceof Repeater);
    expect($nestedRepeaters)->toBeEmpty();
});
```
Because `MenuForm` wraps `items` inside `Section::make('Top-Level Menu Items (Level 1)')`, line 53 above (`collect($components)->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'items')`) returns `null` because `$components` contains the two top-level `Section` instances. Updating `MenuFormTest.php` to locate `items` within Section 2 and locate `children` within the inset Section is required to maintain a passing test suite (`composer check` green).

### 3.4 Domain Models, Enums, and Actions

#### `UpdateMenu` Constants and Visibility (Finding m6)
From `app/Domain/Content/Actions/UpdateMenu.php` lines 22–30:
```php
public const int MAX_TOP_LEVEL_ITEMS = 10;

public const int MAX_CHILDREN_PER_PARENT = 10;

public const int MAX_LABEL_LENGTH = 80;

public const int MAX_URL_LENGTH = 2048;

public const int MAX_TITLE_LENGTH = 255;
```
All five constants have explicit `public const int` visibility.

#### `UpdateMenu` Sort Order Assignment and Validation Behavior (Finding N2, N5)
From `app/Domain/Content/Actions/UpdateMenu.php` lines 116–172:
```php
// Pass 1: Retained / New Top-Level Items (parent_id = null)
foreach ($data->items as $i => $item) {
    if ($item->public_id !== null && $existingItems->has($item->public_id)) {
        $record = $existingItems->get($item->public_id);
        $record->update([
            'parent_id' => null,
            'sort_order' => $i,
            'label' => trim($item->label),
            'url' => $item->url,
            'is_enabled' => $item->is_enabled,
            'opens_in_new_tab' => $item->opens_in_new_tab,
        ]);
        $submittedPublicIds[] = $record->public_id;
        $parentRecordIds[$i] = $record->id;
    } else {
        $newRecord = MenuItem::create([
            'menu_id' => $menu->id,
            'parent_id' => null,
            'sort_order' => $i,
            'label' => trim($item->label),
            'url' => $item->url,
            'is_enabled' => $item->is_enabled,
            'opens_in_new_tab' => $item->opens_in_new_tab,
        ]);
        $submittedPublicIds[] = $newRecord->public_id;
        $parentRecordIds[$i] = $newRecord->id;
    }
}

// Pass 2: Retained / New Child Items (parent_id = $parentRecordId)
foreach ($data->items as $i => $item) {
    $parentRecordId = $parentRecordIds[$i];
    foreach ($item->children as $j => $child) {
        if ($child->public_id !== null && $existingItems->has($child->public_id)) {
            $record = $existingItems->get($child->public_id);
            $record->update([
                'parent_id' => $parentRecordId,
                'sort_order' => $j,
                'label' => trim($child->label),
                'url' => $child->url,
                'is_enabled' => $child->is_enabled,
                'opens_in_new_tab' => $child->opens_in_new_tab,
            ]);
            $submittedPublicIds[] = $record->public_id;
        } else {
            $newRecord = MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $parentRecordId,
                'sort_order' => $j,
                'label' => trim($child->label),
                'url' => $child->url,
                'is_enabled' => $child->is_enabled,
                'opens_in_new_tab' => $child->opens_in_new_tab,
            ]);
            $submittedPublicIds[] = $newRecord->public_id;
        }
    }
}
```
**Key Sort Order Rule**: `sort_order` is **0-based** and scoped **per-parent**. For top-level items, `$i` is the sequential 0-based array index of `$data->items`. For child items, `$j` is the sequential 0-based index of `$item->children` under that parent.

**`NoHtml` Verification**: `UpdateMenu` does NOT enforce `NoHtml`. It validates length (`MAX_TITLE_LENGTH`, `MAX_LABEL_LENGTH`), item counts (`MAX_TOP_LEVEL_ITEMS`, `MAX_CHILDREN_PER_PARENT`), `SafeLink` URLs, and tree depth. The addition of `NoHtml` to `MenuForm` is therefore a **new presentation-layer validation guard** enforcing Rule K3.

#### `MenuItemFactory` Implementation ([MenuItemFactory.php](file:///c:/dev/getnow-cms/database/factories/MenuItemFactory.php)) (Finding m11)
```php
public function childOf(MenuItem $parent): self
{
    return $this->state(fn (array $attributes) => [
        'menu_id' => $parent->menu_id,
        'parent_id' => $parent->id,
    ]);
}

public function forMenu(Menu $menu): self
{
    return $this->state(fn (array $attributes) => [
        'menu_id' => $menu->id,
    ]);
}
```
Calling `MenuItem::factory()->forMenu($menu)->create([...])` attaches top-level parent items (`parent_id = null`). Calling `MenuItem::factory()->childOf($parent)->create([...])` attaches level-2 child items (`parent_id = $parent->id`).

#### Foreign Key Cascade and Tree Reset ([2026_10_14_000000_create_menus_and_menu_items_tables.php](file:///c:/dev/getnow-cms/database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php#L29)) (Finding m11)
Line 29 of the migration defines:
```php
$table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
```
`cascadeOnDelete()` is enforced on `parent_id`. In testing, to guarantee complete deterministic execution across database states, test resets delete children first, then parent items:
```php
$menu->items()->whereNotNull('parent_id')->delete();
$menu->items()->delete();
```

### 3.5 Filament Component Signatures & Behavior in `vendor/filament/`

#### Component State Path Signatures ([HasState.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Concerns/HasState.php#L726-L775)) (Finding N3)
In `vendor/filament/schemas/src/Components/Concerns/HasState.php`:
```php
public function getStatePath(bool $isAbsolute = true): ?string
{
    if (! $isAbsolute) {
        return ($this->statePath === null) ? null : preg_replace('/[<>"\'`\x00-\x1F\x7F]/', '', $this->statePath);
    }

    if (isset($this->cachedAbsoluteStatePath)) {
        return $this->cachedAbsoluteStatePath;
    }

    $pathComponents = [];

    if (filled($containerStatePath = $this->getContainer()->getStatePath())) {
        $pathComponents[] = $containerStatePath;
    }

    if ($this->hasStatePath()) {
        $pathComponents[] = $this->getStatePath(isAbsolute: false);
    }

    return $this->cachedAbsoluteStatePath = implode('.', $pathComponents);
}

public function hasStatePath(): bool
{
    return filled($this->statePath);
}
```
Both `Section` and `Grid` inherit from `Component` which uses `HasState`. Because neither `Section` nor `Grid` assigns a custom `$this->statePath`, calling `hasStatePath()` returns `false`, and calling `getStatePath(isAbsolute: false)` returns `null` (not an empty string). On a mounted Livewire form (where the root container state path is `'data'`), fields with state paths (such as `TextInput::make('title')` and `Repeater::make('items')`) return absolute paths `'data.title'` and `'data.items'`.

#### Schema Retrieval on Livewire Pages ([InteractsWithSchemas.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php#L471)) (Finding m11)
In `vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php`:
```php
public function getSchema(string $name): ?Schema;
```
On mounted Livewire page instances such as `EditMenu`, the form schema is retrieved via `$component->instance()->getSchema('form')` or `$component->instance()->form`.

#### State Binding Modifiers ([HasStateBindingModifiers.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Concerns/HasStateBindingModifiers.php#L124-L162)) (Finding m7)
```php
public function isLive(): bool;
public function isLiveOnBlur(): bool;
```
Calling `live(onBlur: true)` configures both `isLive()` to return `true` and `isLiveOnBlur()` to return `true`.

#### `Grid` Column Handling ([HasColumns.php](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Concerns/HasColumns.php#L26-L30))
Lines 26–30 of `HasColumns.php`:
```php
if (! is_array($columns)) {
    $columns = [
        'lg' => $columns,
    ];
}
```
Passing an integer `Grid::make(12)` assigns 12 columns only to the `lg` breakpoint. Below `lg` (including `md` 768px–1024px), the grid defaults to 1 column, collapsing `md => 4` child spans into full-width stacked rows. Specifying `Grid::make(['default' => 12])` configures a 12-column grid across all breakpoints, allowing child components with `columnSpan(['default' => 12, 'md' => 4])` to render as 12 columns on mobile and 4 columns on tablet and desktop (`md+`).

#### `Repeater` Collapse and Expand Actions ([Repeater.php](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/Repeater.php#L664-L715))
- `public function getCollapseAllAction(): Action` (line 664)
- `public function collapseAllAction(?Closure $callback): static` (line 682)
- `public function getExpandAllAction(): Action` (line 694)
- `public function expandAllAction(?Closure $callback): static` (line 712)
- `public function isCollapsible(): bool`
- `public function isCollapsed(?Schema $schema = null): bool`

#### Behavior of Newly Added Items ([Repeater.php](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/Repeater.php#L236))
In `Repeater::getAddAction()`:
```php
$component->collapsed(false, shouldMakeComponentCollapsible: false);
```
Filament explicitly expands newly added items upon creation so their inputs are immediately accessible. Existing saved items evaluated during hydration use `isCollapsed()`, which resolves to `true` when `->collapsed()` is configured on the component.

---

## 4. File Manifest

| Path | New or Modify | Layer | One-Line Responsibility | Rule IDs Satisfied |
|---|---|---|---|---|
| [MenuForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) | Modify | Admin UI | Declares structured, collapsible two-level menu form schema with compact grid rows and dynamic labels. | S2, S4, S6, C1, C2, C4, C7, K3, K4, K5 |
| [MenuResourceTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuResourceTest.php) | Modify | Testing | Feature tests verifying collapsible configurations, dynamic on-screen Livewire labels, grid arrangements, HTML validation, and persistence. | T1, T2, T4, T5, C1, C2, C7 |
| [MenuFormTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuFormTest.php) | Modify | Testing | Aligns schema nesting test with the reorganized Section structure to ensure full test suite greenness (planned deviation). | T1, T5, C1, C2, C7 |
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | Modify | Documentation | Records completion of Step P1-UI7 in Section 1 Phase 1 progress list. | X7 |

Total files: 4 files (under the 5-file size guard limit).

---

## 5. Interface Specs

### 5.1 `MenuForm` ([MenuForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php))

```php
declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Schemas;

use App\Domain\Content\Actions\UpdateMenu;
use App\Support\Rules\NoHtml;
use App\Support\Rules\SafeLink;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class MenuForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Menu Details')
                    ->schema([
                        TextInput::make('title')
                            ->label('Storefront Title')
                            ->helperText('Displayed as the heading on storefront footer menu columns.')
                            ->maxLength(UpdateMenu::MAX_TITLE_LENGTH)
                            ->rule(new NoHtml)
                            ->nullable(),
                    ]),

                Section::make('Top-Level Menu Items (Level 1)')
                    ->description('Up to ' . UpdateMenu::MAX_TOP_LEVEL_ITEMS . ' top-level items. Drag to reorder, or click an item to expand its fields and dropdown sub-items.')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->collapsible()
                            ->collapsed()
                            ->collapseAllAction(fn (Action $action): Action => $action->label('Collapse All'))
                            ->expandAllAction(fn (Action $action): Action => $action->label('Expand All'))
                            ->addActionLabel('Add Top-Level Item')
                            ->maxItems(UpdateMenu::MAX_TOP_LEVEL_ITEMS)
                            ->defaultItems(0)
                            ->itemLabel(
                                /** @param array<string, mixed> $state */
                                function (array $state): string {
                                    $label = filled($state['label'] ?? null) ? (string) $state['label'] : 'New menu item';
                                    $urlPart = filled($state['url'] ?? null) ? '  —  ' . (string) $state['url'] : '';
                                    /** @var array<int|string, mixed> $children */
                                    $children = isset($state['children']) && is_array($state['children']) ? $state['children'] : [];
                                    $childCount = count($children);
                                    $countPart = $childCount > 0 ? '  •  (' . $childCount . ' ' . ($childCount === 1 ? 'sub-item' : 'sub-items') . ')' : '';
                                    $newTabPart = ! empty($state['opens_in_new_tab']) ? '  [New Tab]' : '';
                                    $disabledPart = (array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']) ? '  [Disabled]' : '';

                                    return "{$label}{$urlPart}{$countPart}{$newTabPart}{$disabledPart}";
                                }
                            )
                            ->schema([
                                Hidden::make('public_id'),
                                Grid::make(['default' => 12])
                                    ->schema([
                                        TextInput::make('label')
                                            ->label('Label')
                                            ->required()
                                            ->maxLength(UpdateMenu::MAX_LABEL_LENGTH)
                                            ->rule(new NoHtml)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                        TextInput::make('url')
                                            ->label('URL / Path')
                                            ->required()
                                            ->maxLength(UpdateMenu::MAX_URL_LENGTH)
                                            ->rule(new SafeLink)
                                            ->live(onBlur: true)
                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                        Toggle::make('opens_in_new_tab')
                                            ->label('New tab')
                                            ->default(false)
                                            ->live()
                                            ->inline(false)
                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                        Toggle::make('is_enabled')
                                            ->label('Enabled')
                                            ->default(true)
                                            ->live()
                                            ->inline(false)
                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                    ]),
                                Section::make('Dropdown Sub-Items (Level 2)')
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed()
                                    ->description(function (Get $get): string {
                                        /** @var array<int|string, mixed>|null $children */
                                        $children = $get('children');
                                        $count = is_array($children) ? count($children) : 0;

                                        return "{$count} / " . UpdateMenu::MAX_CHILDREN_PER_PARENT . ' sub-items';
                                    })
                                    ->schema([
                                        Repeater::make('children')
                                            ->hiddenLabel()
                                            ->collapsible()
                                            ->collapsed()
                                            ->addActionLabel('Add Sub-Item')
                                            ->maxItems(UpdateMenu::MAX_CHILDREN_PER_PARENT)
                                            ->defaultItems(0)
                                            ->itemLabel(
                                                /** @param array<string, mixed> $state */
                                                function (array $state): string {
                                                    $label = filled($state['label'] ?? null) ? (string) $state['label'] : 'New sub-item';
                                                    $urlPart = filled($state['url'] ?? null) ? '  —  ' . (string) $state['url'] : '';
                                                    $newTabPart = ! empty($state['opens_in_new_tab']) ? '  [New Tab]' : '';
                                                    $disabledPart = (array_key_exists('is_enabled', $state) && ! (bool) $state['is_enabled']) ? '  [Disabled]' : '';

                                                    return "{$label}{$urlPart}{$newTabPart}{$disabledPart}";
                                                }
                                            )
                                            ->schema([
                                                Hidden::make('public_id'),
                                                Grid::make(['default' => 12])
                                                    ->schema([
                                                        TextInput::make('label')
                                                            ->label('Label')
                                                            ->required()
                                                            ->maxLength(UpdateMenu::MAX_LABEL_LENGTH)
                                                            ->rule(new NoHtml)
                                                            ->live(onBlur: true)
                                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                                        TextInput::make('url')
                                                            ->label('URL / Path')
                                                            ->required()
                                                            ->maxLength(UpdateMenu::MAX_URL_LENGTH)
                                                            ->rule(new SafeLink)
                                                            ->live(onBlur: true)
                                                            ->columnSpan(['default' => 12, 'md' => 4]),
                                                        Toggle::make('opens_in_new_tab')
                                                            ->label('New tab')
                                                            ->default(false)
                                                            ->live()
                                                            ->inline(false)
                                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                                        Toggle::make('is_enabled')
                                                            ->label('Enabled')
                                                            ->default(true)
                                                            ->live()
                                                            ->inline(false)
                                                            ->columnSpan(['default' => 6, 'md' => 2]),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
```

### 5.2 Component Constraints and Behavior Summary
- **No `->statePath(...)` on Layout Containers**: Section and Grid components act purely as visual grouping containers and do not specify state paths, ensuring form state remains mapped directly to `data.title`, `data.items`, and `data.items.*.children` (Step Brief Decision D-b).
- **Preserved URL Limit (2048)**: Both repeaters preserve `maxLength(2048)`, matching `UpdateMenu::MAX_URL_LENGTH` and the database column.
- **Enforced `NoHtml` (Rule K3)**: New validation rule on `title` and `label` rejecting HTML markup.
- **Responsive 12-Column Grid**: Using `Grid::make(['default' => 12])` guarantees that child components span 4 columns on tablet and desktop (`md+`) and 12 columns on mobile.
- **Null-Safe State Handling**: All closure parameters (`$state`, `$get`) check `filled()`, `isset()`, and `is_array()`, with `@param array<string, mixed> $state` annotations ensuring PHPStan Level 8 clean types.
- **Domain Constants Used**: `UpdateMenu::MAX_TOP_LEVEL_ITEMS` (10), `UpdateMenu::MAX_CHILDREN_PER_PARENT` (10), and `UpdateMenu::MAX_TITLE_LENGTH` (255) eliminate hardcoded numbers.

---

## 6. Data and Migrations

None. This step alters only the Filament form schema presentation layer. Database tables (`menus`, `menu_items`), columns, constraints, foreign keys, and existing migrations remain untouched.

### Backward-Compatibility Statement
All persisted database records and state paths are 100% backward compatible. Existing menu item records continue to load, reorder, and save without schema changes or data migrations.

---

## 7. Behavior and Flows

### 7.1 View/Edit Menu Page Load (Happy Path)
1. User navigates to `/admin/menus/{public_id}/edit`.
2. [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) authorizes request via policy.
3. `EditMenu::mutateFormDataBeforeFill()` invokes [GetMenuTree](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php) to fetch the hierarchical menu tree.
4. Form hydrates:
   - `Section::make('Menu Details')` renders with `title` value and helper text.
   - `Section::make('Top-Level Menu Items (Level 1)')` renders with description referencing `UpdateMenu::MAX_TOP_LEVEL_ITEMS`.
   - `Repeater::make('items')` initializes in a collapsed state: each top-level item displays as a single-line summary card showing `Label  —  URL  •  (N sub-items)` with optional badges `[New Tab]` and `[Disabled]`.
5. User clicks an item card to expand it:
   - Four fields (`Label`, `URL / Path`, `New tab`, `Enabled`) display horizontally across 12 grid columns (`4 + 4 + 2 + 2`).
   - Inset `Section::make('Dropdown Sub-Items (Level 2)')` renders collapsed with description showing current count (e.g. `6 / 10 sub-items`).
6. User expands the sub-items section:
   - Sub-items display collapsed as single-line summary cards showing `Label  —  URL` with optional `[New Tab]` and `[Disabled]` badges.

### 7.2 Field Edits and Real-Time Summary Updates
1. User changes `label` or `url` and blurs the input (`live(onBlur: true)`):
   - The repeater item header label immediately re-evaluates and updates to reflect the new label and URL.
2. User toggles `opens_in_new_tab` or `is_enabled` (`live()`):
   - The repeater item header label immediately displays or removes `[New Tab]` and `[Disabled]`.
3. User adds a sub-item via `'Add Sub-Item'`:
   - Filament's `Repeater::getAddAction()` marks the newly created sub-item item as expanded (`collapsed(false)`). Sibling sub-items are expected to remain collapsed, to be empirically confirmed in Manual Script Step 5 (Finding m12).
   - The parent section's description immediately increments (e.g. from `6 / 10 sub-items` to `7 / 10 sub-items`).
   - The parent top-level card label immediately increments its sub-item badge (e.g. `(7 sub-items)`).

### 7.3 Saving Changes (Happy Path)
1. User clicks `Save changes`.
2. Filament validates inputs (max length 255 and `NoHtml` on `title`, required, max length 80 and `NoHtml` on `label`, required, max length 2048 and `SafeLink` on `url`).
3. `EditMenu::handleRecordUpdate()` maps submitted `data` to [UpdateMenuData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/UpdateMenuData.php) and calls [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php).
4. [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) runs in a transaction with row lock on `menus` and `menu_items`, reconciles retained items by `public_id`, creates new records, deletes removed records, and logs changes to the activity log.
5. Notification displays success message and form re-fills with preserved `public_id` values.

### 7.4 Validation and Error Mapping (Failure Path) (Finding N5, m13)
1. User enters an invalid link (e.g. `javascript:alert(1)` or `//evil.com`) or HTML tag (e.g. `<script>`) and clicks `Save changes`.
2. Form validation fails:
   - Field displays error message under the respective input (`'The :attribute must start with / or https://.'` per [SafeLink.php#L49](file:///c:/dev/getnow-cms/app/Support/Rules/SafeLink.php#L49) or `'The :attribute must not contain HTML tags.'` per [NoHtml.php#L22](file:///c:/dev/getnow-cms/app/Support/Rules/NoHtml.php#L22)).
   - Whether all three nested collapsed layers (item card, inset sub-items section, and child item card) auto-expand upon a validation error is evaluated empirically in Manual Script Step 6. If they do not auto-expand, execution halts and a Change Request (CR) is submitted.
   - No database update occurs.

#### Seed Data Audit for `NoHtml` Compatibility (Finding N5, m13)
Execution of PowerShell `Select-String -Path database/seeders/*.php, database/migrations/*menu*.php -Pattern '<[a-zA-Z\/!]'` returned matches exclusively within docblock comments (`@param array<string, mixed>`, `@var Collection<string, MenuItem>`), with zero matches in menu seeds. Existing seeded menu rows contain pure text without HTML tags, ensuring zero failures when saving existing menus. The raw, unedited command output will be pasted verbatim in the Implementation Report.

### 7.5 Concurrency and Row Locking (Decision D9)
- [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) executes inside a database transaction acquiring pessimistic locks via `lockForUpdate()` on the [Menu](file:///c:/dev/getnow-cms/app/Domain/Content/Models/Menu.php) and its associated [MenuItem](file:///c:/dev/getnow-cms/app/Domain/Content/Models/MenuItem.php) records.
- Simultaneous saves from multiple users result in whole-tree atomic replacement on a last-write-wins basis with audit trail logging in `activity_log`.

---

## 8. Rule Compliance Matrix

| Rule ID | Applies (Y/N) | How Satisfied, or Reason if N/A |
|---|---|---|
| **E1** | Y | Commands run in PowerShell on Windows, executed one at a time without bash chaining or `&&`. |
| **S1** | Y | Business logic remains in [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) and [GetMenuTree](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php). [MenuForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) contains only schema definitions. |
| **S2** | Y | [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) and [MenuForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) remain thin schema wrappers delegating persistence to [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php). |
| **S3** | Y | Domain layer does not import from `App\Filament` or `App\Http`. |
| **S4** | Y | Filament code never calls `Model::create`, `update`, or `delete` directly. Saves call [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php). |
| **S5** | N/A | Navigation menus are site-level tree structures, not modular content blocks. |
| **S6** | Y | Panel code resides strictly in `app/Filament/Admin/Resources/MenuResource/`. |
| **S7** | Y | All modifications are made within existing directory structures. No new contexts or top-level folders created. |
| **C1** | Y | `declare(strict_types=1);` declared at the head of every modified file. |
| **C2** | Y | Strict typing applied to all properties, parameters, and closure returns. `final class MenuForm` preserved. |
| **C3** | Y | Enums ([MenuKey](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/MenuKey.php)) and Spatie Data classes ([UpdateMenuData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/UpdateMenuData.php), [UpdateMenuItemInputData](file:///c:/dev/getnow-cms/app/Domain/Content/Data/UpdateMenuItemInputData.php)) used for structured data transfer. |
| **C4** | Y | Menu items `is_enabled` toggle is an item-level visibility attribute within the menu tree passed to [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php). Model-level status transitions remain restricted to Actions. |
| **C5** | Y | All menu writes pass through [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php). |
| **C6** | Y | Form inputs validated with [NoHtml](file:///c:/dev/getnow-cms/app/Support/Rules/NoHtml.php), [SafeLink](file:///c:/dev/getnow-cms/app/Support/Rules/SafeLink.php), max lengths, and `MenuPolicy` authorization checks on [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php). |
| **C7** | Y | Pint and PHPStan Level 8 clean with zero baseline additions. Closures annotated with `@param array<string, mixed> $state`. |
| **C8** | Y | Explanatory comments focus on rationale and null-safety guards. No commented-out code. |
| **K1** | N/A | No block type keys involved. |
| **K2** | N/A | Menu tree data structure unchanged. |
| **K3** | Y | No raw HTML/CSS/JS fields. Text fields validated with newly introduced [NoHtml](file:///c:/dev/getnow-cms/app/Support/Rules/NoHtml.php) and length constraints (title: 255, label: 80, url: 2048). Failure path verified via dedicated Pest test; seed data verified clean. |
| **K4** | Y | URLs validated with [SafeLink](file:///c:/dev/getnow-cms/app/Support/Rules/SafeLink.php) (relative `/` or `https://`). Plain text inputs used without `->url()`. |
| **K5** | Y | Menu keys defined in [MenuKey](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/MenuKey.php) enum in code. Admins cannot create menu keys. |
| **K6** | N/A | No block types registered in this step. |
| **K7** | N/A | Menu visibility logic remains in [GetMenuTree](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetMenuTree.php) and `GetLayoutContent`. |
| **K8** | N/A | Content API contract shape remains unchanged. |
| **K9** | N/A | No new block types added. |
| **DB1** | Y | Tests run against PostgreSQL (`phone_store_test`), never SQLite. |
| **DB2** | Y | No migrations created or modified. |
| **DB3** | Y | No destructive database operations. |
| **DB4** | N/A | No database schema changes. |
| **DB5** | Y | Form schema preserves `Hidden::make('public_id')` exposing ULIDs externally instead of incremental IDs. |
| **DB6** | Y | Seeders remain idempotent. Dev DB operations restricted to `phone_store` and `phone_store_test`. |
| **SEC1** | Y | No secrets, credentials, or `.env` entries modified. |
| **SEC2** | Y | Authorization, policies, strict typing, and static analysis checks remain active. |
| **SEC3** | Y | No sensitive data logged. |
| **SEC4** | Y | Admin access checked in `canAccessPanel` and `MenuPolicy`. |
| **SEC5** | Y | Menu modifications logged to `activity_log` by [UpdateMenu](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php). |
| **T1** | Y | Pest feature tests added to [MenuResourceTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuResourceTest.php) and [MenuFormTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuFormTest.php). Architecture tests pass. |
| **T2** | Y | Existing smoke tests in [MenuResourceTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuResourceTest.php) continue to cover [ListMenus](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/ListMenus.php) and [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php). |
| **T3** | N/A | No block types in this step. |
| **T4** | Y | Factories used for test setup (`Menu::factory()`, `MenuItem::factory()`, `User::factory()`). |
| **T5** | Y | No tests skipped, weakened, or marked incomplete. |
| **G1** | Y (Deviation Logged) | Branching from `feat/admin-block-previews` (containing completed P1-UI5 and P1-UI6 awaiting merge into `main`). Layer A diff commands will diff against `feat/admin-block-previews...HEAD` to verify the 4-file manifest and zero Domain/DB changes (Finding N6). |
| **G2** | Y | Conventional Commits used (`feat(content): ...`, `docs(content): ...`). |
| **G3** | Y | Commits pushed according to protocol without bypassing git hooks. |
| **G4** | Y | PR description documents changes, tests, and verification evidence. |
| **X1** | Y | No new packages or dependencies added. |
| **X2** | Y | No Docker, Sail, or container configs introduced. |
| **X3** | Y | No deployment pipelines or infrastructure changes added. |
| **X4** | Y | No scope from later phases (accounts, catalog, retail, sales, payments) introduced. |
| **X5** | Y | Storefront repository remains untouched. |
| **X6** | Y | `composer check` run and verified passing green before completion. |
| **X7** | Y | [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) Section 1 Phase 1 checklist updated to mark Step P1-UI7 complete. |

---

## 9. AC Traceability

| AC | Requirement | Files Changed | Test Names in `MenuResourceTest.php` and `MenuFormTest.php` | How Verified |
|---|---|---|---|---|
| **AC-1** | `MenuForm` separates `Section::make('Menu Details')` (`title`) from `Section::make('Top-Level Menu Items (Level 1)')` (`items`). | `MenuForm.php` | `it('configures MenuForm with separate Menu Details and Top-Level Menu Items sections')` | Component inspection on form schema asserting two `Section` components with exact headings. |
| **AC-2** | Both `Repeater::make('items')` and `Repeater::make('children')` are configured with `->collapsible()` and `->collapsed()`, and `items` provides `'Collapse All'` and `'Expand All'` actions. (Mapped to `MUT-1`) | `MenuForm.php` | `it('configures top-level items and nested children repeaters as collapsible and collapsed by default with collapse and expand actions')` | Inspection of `isCollapsible()`, `isCollapsed()`, `getCollapseAllAction()`, and `getExpandAllAction()`. Removing `->collapsed()` causes this test to fail (`MUT-1`). |
| **AC-3** | `items` `itemLabel` returns `"{label}  —  {url}  •  ({N} sub-item\|sub-items)  [New Tab]  [Disabled]"` when populated, falls back to `'New menu item'` when blank/null, and orders badges `[New Tab]  [Disabled]`. (Mapped to `MUT-2`) | `MenuForm.php` | `it('renders top-level and level-2 itemLabels, fallbacks, and sub-item counts in EditMenu Livewire view')` | Livewire rendering on `EditMenu` asserting on-screen presence of formatted strings, fallbacks, and pinned badge order. Hardcoding child count to 0 causes this test to fail (`MUT-2`). |
| **AC-4** | `children` `itemLabel` returns `"{label}  —  {url}  [New Tab]  [Disabled]"` when populated and falls back to `'New sub-item'` when blank/null. | `MenuForm.php` | `it('renders top-level and level-2 itemLabels, fallbacks, and sub-item counts in EditMenu Livewire view')` | Livewire rendering on `EditMenu` asserting on-screen presence of formatted strings and `'New sub-item'` fallback for children. |
| **AC-5** | `Hidden::make('public_id')` preserved; `label`, `url`, `opens_in_new_tab`, `is_enabled` arranged in 12-col `Grid(['default' => 12])` (`4 + 4 + 2 + 2` on `md+`) with live reactivity (`isLive()` and `isLiveOnBlur()`). | `MenuForm.php` | `it('arranges top-level and level-2 item fields in 12-column grids preserving public_id hidden input')` | Schema inspection verifying `Hidden` component, columnSpan configuration (`md => 4` for text, `md => 2` for toggles), `isLive()` / `isLiveOnBlur()` settings, and 12-col default grid. |
| **AC-6** | `children` repeater nested inside collapsible, compact `Section::make('Dropdown Sub-Items (Level 2)')` with dynamic description `"{count} / 10 sub-items"`. | `MenuForm.php` | `it('renders top-level and level-2 itemLabels, fallbacks, and sub-item counts in EditMenu Livewire view')` | Livewire rendering on `EditMenu` asserting on-screen presence of `"6 / 10 sub-items"`, `"1 / 10 sub-items"`, `"0 / 10 sub-items"`. |
| **AC-7** | All tests pass, `NoHtml` rejection verified on parents and children with zero DB mutation, `ARCHITECTURE.md` §1 updated, `composer check` clean. (Mapped to `MUT-3`) | `MenuForm.php`, `MenuResourceTest.php`, `MenuFormTest.php`, `ARCHITECTURE.md` | `it('persists menu structure updates through create, reorder, edit, and delete preserving item and child public_ids')`, `it('fails validation and halts update when title or item label contains HTML tags')`, plus full test suite | Execution of `composer check` (Pint, PHPStan Level 8, Pest including `tests/Arch`). Omitting `Hidden::make('public_id')` causes persistence test to fail (`MUT-3`). Entering HTML tags fails validation without database writes. |

---

## 10. Test Plan

### 10.1 `tests/Feature/Admin/Menu/MenuResourceTest.php`

1. `it('configures MenuForm with separate Menu Details and Top-Level Menu Items sections')`
   - **Arrange**: Authenticate platform admin using `User::factory()->platformAdmin()->create()` and instantiate [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) Livewire component schema.
   - **Act**: Call [MenuForm::form($schema)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php#L16) and retrieve top-level components using `$schema->getComponents()`.
   - **Assert**: Assert component count is 2, both components are instances of [Section](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Section.php), first section heading is `'Menu Details'` containing `title` input, second section heading is `'Top-Level Menu Items (Level 1)'` containing `items` repeater.

2. `it('configures top-level items and nested children repeaters as collapsible and collapsed by default with collapse and expand actions')` [Covers AC-2, `MUT-1`]
   - **Arrange**: Retrieve `items` repeater from Section 2 and `children` repeater from the nested Section component schema.
   - **Act**: Call `isCollapsible()`, `isCollapsed()`, `getCollapseAllAction()`, and `getExpandAllAction()`.
   - **Assert**: Assert both `items` and `children` repeaters return `true` for `isCollapsible()` and `isCollapsed()`, and `items` repeater returns collapse/expand actions labeled `'Collapse All'` and `'Expand All'`.

3. `it('verifies Section and Grid layout components do not define statePath')` [Covers Step Brief Decision D-b, P6, N3, m11]
   - **Arrange**: Authenticate platform admin and mount Livewire component via `Livewire::test(EditMenu::class, ['record' => $menu->public_id])`. Retrieve `$schema = $component->instance()->getSchema('form');`. From `$schema`, retrieve Section 1 (`Menu Details`), Section 2 (`Top-Level Menu Items (Level 1)`), `$titleInput`, `$itemsRepeater`, the item field [Grid](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Grid.php), the inset Section (`Dropdown Sub-Items (Level 2)`), and the child field Grid.
   - **Act**: Call `getStatePath()` on `$titleInput` and `$itemsRepeater`. Call `hasStatePath()` and `getStatePath(isAbsolute: false)` on all five layout components.
   - **Assert**:
     - `$titleInput->getStatePath()` equals `'data.title'`.
     - `$itemsRepeater->getStatePath()` equals `'data.items'`.
     - `hasStatePath()` returns `false` on Section 1, Section 2, the item field Grid, the inset Section, and the child field Grid.
     - `getStatePath(isAbsolute: false)` returns `null` on all five layout components.

4. `it('arranges top-level and level-2 item fields in 12-column grids preserving public_id hidden input')` [Covers AC-5, m7]
   - **Arrange**: Retrieve component schemas of both `items` and `children` repeaters.
   - **Act**: Search components for `Hidden` and [Grid](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Grid.php) instances. Inspect `label` and `url` text inputs, and `opens_in_new_tab` and `is_enabled` toggles.
   - **Assert**: Assert `Hidden::make('public_id')` exists in both repeaters, assert `Grid` has `columns(['default' => 12])` in both repeaters, assert inputs `label` and `url` have columnSpan `['default' => 12, 'md' => 4]` and both `isLive()` and `isLiveOnBlur()` return `true`, assert toggles `opens_in_new_tab` and `is_enabled` have columnSpan `['default' => 6, 'md' => 2]` and `isInline()` false.

5. `it('renders top-level and level-2 itemLabels, fallbacks, and sub-item counts in EditMenu Livewire view')` [Covers AC-3, AC-4, AC-6, P2, N1, N2, m11, `MUT-2`]
   - **Arrange**:
     - Authenticate platform admin using `User::factory()->platformAdmin()->create()`.
     - Fetch seeded menu: `$menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();`.
     - Reset tree deterministically (deleting children then parents):
       ```php
       $menu->items()->whereNotNull('parent_id')->delete();
       $menu->items()->delete();
       ```
     - Seed 3 distinct parent items and children via `MenuItem::factory()`:
       - Parent 1: `label => 'All Categories'`, `url => '/shop'`, `opens_in_new_tab => false`, `is_enabled => true`, with 6 child items.
       - Parent 2: `label => 'Phones'`, `url => '/shop/phones'`, `opens_in_new_tab => true`, `is_enabled => true`, with 1 child item (`label => 'Smartphones'`, `url => '/shop/phones/smartphones'`, `opens_in_new_tab => true`).
       - Parent 3: `label => 'Deals'`, `url => '/deals'`, `opens_in_new_tab => true`, `is_enabled => false`, with 0 child items.
   - **Act**: Mount [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) component via `$component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);`.
   - **Assert (Populated items and badge order)**: Assert the rendered component HTML contains the exact formatted strings:
     - `'All Categories  —  /shop  •  (6 sub-items)'`
     - `'6 / 10 sub-items'`
     - `'Phones  —  /shop/phones  •  (1 sub-item)  [New Tab]'`
     - `'Smartphones  —  /shop/phones/smartphones  [New Tab]'`
     - `'1 / 10 sub-items'`
     - `'Deals  —  /deals  [New Tab]  [Disabled]'` (pins badge sequence `[New Tab]  [Disabled]`)
     - `'0 / 10 sub-items'`
   - **Act & Assert (Fallbacks for empty and null labels)**:
     - Update component state with an unpopulated parent and child:
       ```php
       $component->set('data.items', [
           [
               'public_id' => null,
               'label' => '',
               'url' => '',
               'opens_in_new_tab' => false,
               'is_enabled' => true,
               'children' => [
                   [
                       'public_id' => null,
                       'label' => null,
                       'url' => '',
                       'opens_in_new_tab' => false,
                       'is_enabled' => true,
                   ],
               ],
           ],
       ]);
       ```
     - Assert on-screen presence of fallback strings:
       - Assert sees `'New menu item'`
       - Assert sees `'New sub-item'`

6. `it('persists menu structure updates through create, reorder, edit, and delete preserving item and child public_ids')` [Covers AC-7, P5, N2, m11, `MUT-3`]
   - **Arrange**:
     - Authenticate platform admin.
     - Fetch seeded menu: `$menu = Menu::where('key', MenuKey::MAIN_NAV->value)->firstOrFail();`.
     - Reset tree:
       ```php
       $menu->items()->whereNotNull('parent_id')->delete();
       $menu->items()->delete();
       ```
     - Seed Parent 1 (with Child 1) and Parent 2 (with Child 2) via `MenuItem::factory()`. Record initial ULIDs of all 4 records.
   - **Act**: Mount [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) Livewire component:
     - Fetch hydrated items from component state: `$items = $component->get('data.items');`.
     - Reorder: swap parent positions so Parent 2 precedes Parent 1.
     - Edit: modify Parent 1 label to `'Updated Categories'` and toggle `is_enabled` to `false`.
     - Create Child: append a new child under Parent 2 (`public_id => null`, `label => 'New Sub'`, `url => '/new-sub'`).
     - Create Top-Level: append a new top-level item (`public_id => null`, `label => 'Brand New Root'`, `url => '/brand-new'`).
     - Delete: unset Child 1 from Parent 1's children array.
     - Set updated structure back via `$component->set('data.items', $items)` and invoke `call('save')->assertHasNoFormErrors();`.
   - **Assert**:
     - Parent 1 and Parent 2 retain their exact initial `public_id` values.
     - Child 2 retains its exact initial `public_id` value.
     - Child 1 is deleted from the database.
     - The newly created child and top-level item have new, valid ULIDs in the database.
     - Database `sort_order` reflects the new sequence: Parent 2 has `sort_order = 0`, Parent 1 has `sort_order = 1`, and the new root item has `sort_order = 2` (matching `UpdateMenu` 0-based sort order assignment).

7. `it('fails validation and halts update when title or item label contains HTML tags')` [Covers AC-7, Rule K3, Finding N5, Finding N7]
   - **Arrange**:
     - Authenticate platform admin.
     - Fetch seeded `main_nav` menu.
     - Mount [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) Livewire component.
     - Snapshot database state before mutation:
       ```php
       $beforeUpdatedAt = $menu->fresh()->updated_at;
       $beforeItems = $menu->items()->orderBy('id')->get()->toArray();
       ```
     - Inspect hydrated item and child keys from component state:
       ```php
       $items = $component->get('data.items');
       $parentKey = array_key_first($items);
       $childKey = array_key_first($items[$parentKey]['children'] ?? []);
       ```
   - **Act**: Fill form with HTML tags in `title`, parent item `label`, and child item `label`:
     ```php
     $component
         ->set('data.title', 'Title with <script>alert(1)</script>')
         ->set("data.items.{$parentKey}.label", '<script>parent()</script>')
         ->set("data.items.{$parentKey}.children.{$childKey}.label", '<script>child()</script>')
         ->call('save');
     ```
   - **Assert**:
     - Assert form error keys exist on `data.title`, `"data.items.{$parentKey}.label"`, and `"data.items.{$parentKey}.children.{$childKey}.label"`.
     - Assert error messages are interpolated correctly from error bag:
       ```php
       $errors = $component->errors();
       expect($errors->first('data.title'))->toContain('must not contain HTML tags.')
           ->and($errors->first("data.items.{$parentKey}.label"))->toContain('must not contain HTML tags.')
           ->and($errors->first("data.items.{$parentKey}.children.{$childKey}.label"))->toContain('must not contain HTML tags.');
       ```
     - Assert zero database records were created, modified, or updated:
       ```php
       expect($menu->fresh()->updated_at->equalTo($beforeUpdatedAt))->toBeTrue();
       expect($menu->items()->orderBy('id')->get()->toArray())->toEqual($beforeItems);
       ```

### 10.2 `tests/Feature/Admin/Menu/MenuFormTest.php`

- Path: [MenuFormTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuFormTest.php)
- `it('forbids third-level nesting in form schema')`:
  - **Arrange**: Authenticate platform admin, instantiate [EditMenu](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Pages/EditMenu.php) component, and generate `$schema` via [MenuForm::form($schema)](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php#L16).
  - **Act**: Retrieve `items` repeater from Section 2 (`Top-Level Menu Items (Level 1)`) and `children` repeater from the nested Section (`Dropdown Sub-Items (Level 2)`).
  - **Assert**: Assert `children` repeater child components contain zero instances of [Repeater](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/Repeater.php).

---

## 11. Docs and Contract Updates

### 11.1 `ARCHITECTURE.md` (Finding N4, N6)
Edit Section 1 (`Phase 1 progress`) in [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md#L30).
Verified base branch is `feat/admin-block-previews`.
Verified single anchor line is line 30:
`- [x] Step P1-UI6 (admin block previews: isolated view preview, split-screen edit preview, shared storefront partials, ADR 0005)`

Directly after line 30, insert:
```markdown
- [x] Step P1-UI7 (menu form reorganization: collapsible level 1 & 2 repeaters, dynamic item labels, compact 12-column grid rows, and inset sub-item sections)
```

### 11.2 Other Documentation
- `docs/contracts/content-api.md`: None. Reason: Step P1-UI7 introduces no modifications to the Content API contract.
- Contract fixtures (`tests/Fixtures/contracts/`): None. Reason: API output shape is identical.
- `.env.example`: None. Reason: No new environment variables added.
- ADRs: None. Reason: Architectural decisions conform to existing ADRs and Step Brief Decisions D-a, D-b, and D-c.

---

## 12. Implementation Order

### Checkpoint 1 (Single Checkpoint)

#### Commit 1: `feat(content): reorganize menu form with collapsible repeaters and compact grid rows`
- **Files**:
  - `app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php`
  - `tests/Feature/Admin/Menu/MenuResourceTest.php`
  - `tests/Feature/Admin/Menu/MenuFormTest.php`
- **State after commit**:
  - [MenuForm](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php) reorganized into Section 1 (`Menu Details`) and Section 2 (`Top-Level Menu Items (Level 1)`).
  - Collapsible configurations, dynamic item labels, compact 12-column grid rows, and inset sub-item sections fully implemented.
  - New and updated tests in [MenuResourceTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuResourceTest.php) and [MenuFormTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuFormTest.php) passing.
  - `composer check` passes green with zero errors (Pint, Larastan Level 8, Pest including `tests/Arch`).

#### Commit 2: `docs(content): record step P1-UI7 completion in architecture progress`
- **Files**:
  - `ARCHITECTURE.md`
- **State after commit**:
  - Section 1 Phase 1 checklist updated in [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md#L30).
  - `composer check` passes green with zero errors.

---

## 13. Risks, Assumptions, Conflicts

### Planned Deviations and Assumptions
1. **Planned Deviation — 4 Files (Finding P8)**: The brief estimated 3 files (`MenuForm.php`, `MenuResourceTest.php`, `ARCHITECTURE.md`). A 4th file, [MenuFormTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/Menu/MenuFormTest.php), must be updated because its test `it('forbids third-level nesting in form schema')` examined the root components of `$schema->getComponents()`. Because Section 2 now encloses `items`, navigating through Section 2 in `MenuFormTest.php` preserves the intent of that test and keeps `composer check` green. 4 files remains safely within the 5-file size guard limit.
2. **Planned Deviation — Stacked Feature Branch on `feat/admin-block-previews` (Finding N6)**: UI7 branches from `feat/admin-block-previews` which contains completed P1-UI5 and P1-UI6 pending PR merge into `main`. Layer A manifest and scope checks target `feat/admin-block-previews...HEAD` to isolate UI7 changes.
3. **Toggle Label Change (Finding m3)**: The toggle label changed from `'Open in new tab'` to `'New tab'`. Codebase grep confirmed that zero existing feature or architecture tests assert the string `'Open in new tab'`.
4. **NoHtml Rule Introduction (Finding B2, N5)**: `NoHtml` was not present on `title` or `label` in `main`. It is introduced in this step to comply with Rule K3. `UpdateMenu` does not enforce `NoHtml`.
5. **URL Max Length Retention (Finding B1)**: `maxLength(2048)` is preserved in both repeaters, correcting the brief's error.
6. **Collapsed Sub-Items Section Default**: `Section::make('Dropdown Sub-Items (Level 2)')` is configured with `->collapsed()`, maintaining a compact 1-line appearance until intentionally expanded by the admin.
7. **Branch and Architecture Anchor (Finding N4)**: UI7 base branch is confirmed as `feat/admin-block-previews`. Line 30 of `ARCHITECTURE.md` is the single verified anchor.

### Conflicts
None. All instructions in the Step Brief and review feedback align with [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) and [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md).

### Open Questions
None.

---

## 14. Dependencies

No new packages or external dependencies required. All functionality uses native Filament 5 components ([Section](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Section.php), [Grid](file:///c:/dev/getnow-cms/vendor/filament/schemas/src/Components/Grid.php), [Repeater](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/Repeater.php), [TextInput](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/TextInput.php), [Toggle](file:///c:/dev/getnow-cms/vendor/filament/forms/src/Components/Toggle.php), `Hidden`) and existing Laravel / Spatie utilities already present in `composer.json`.

---

## Manual Verification Script (Layer C) (Finding m14)

1. Run `php artisan db:seed --no-interaction` (if not already seeded) and start `php artisan serve`.
2. Open `/admin/menus` and click `Edit` on `Main Navigation` (`main_nav`):
   - Verify `Menu Details` (`Title`) is in its own top section and `Top-Level Menu Items (Level 1)` is in a separate section below it.
   - Verify all 6 top-level items load **collapsed** as clean 1-line summary cards showing `Label  —  URL  •  (N sub-items)` (e.g., `All Categories  —  /shop  •  (6 sub-items)`).
   - Capture a screenshot of the collapsed top-level menu list.
   - Resize browser window to ~800px width (tablet view) and verify summary bars remain clean and responsive.
3. Click to expand `All Categories`:
   - Verify `Label`, `URL / Path`, `New tab`, and `Enabled` sit on a single horizontal row across 12 grid columns at ~800px width and desktop.
   - Verify `Dropdown Sub-Items (Level 2)` appears as an inset collapsible section showing `6 / 10 sub-items`.
   - Expand `Dropdown Sub-Items (Level 2)` and verify the 6 sub-items appear as collapsed 1-line summary cards, and expanding one shows its 4 fields on a single horizontal row.
   - Capture a screenshot of the expanded item and its sub-items.
4. Test Live wire reactivity:
   - Toggle `Enabled` off on one item and click outside a modified `Label` field.
   - Verify the card's summary header immediately updates with the new label and `[Disabled]`.
5. Test adding items:
   - Click `'Add Sub-Item'` and observe that the newly added sub-item arrives **expanded** (ready for input).
   - Verify that all sibling sub-items remain collapsed (Finding m10, m12).
   - Verify the parent section description increments to `7 / 10 sub-items` and top card badge updates to `(7 sub-items)`.
6. Test error expansion (Validation failure check):
   - In a sub-item URL field, enter `javascript:evil` and click `Save changes`.
   - Observe if the parent card, inset section, and child item card auto-expand to show the red validation message `'The :attribute must start with / or https://.'`.
   - If the three layers do not auto-expand to reveal the validation error, stop and send a Change Request (CR) (Finding m9).
7. Correct the URL to a valid path (e.g. `/shop/sale`), click `Save changes`, reload the page, and verify all items, reordered sequences, and ULIDs persist cleanly. Stop `php artisan serve`.
