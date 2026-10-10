# UI Step 2 Verification Report

## 1. CI and Hygiene

**GitHub Actions:**
- CI Run URL: [https://github.com/AchinthaDe/getnow/actions](https://github.com/AchinthaDe/getnow/actions) 

**Git Hygiene:**
- `git status` shows `nothing to commit, working tree clean`.
- `git log --oneline -10`:
```
0f98a6e test(content): add strict types to ServingHealthIntegrityTest
43de6b2 chore(docs): restore ui_step_1_report.md
a5c3d61 test(content): add navigation visibility test
58aedd8 test(content): add hide_archived interaction test
90909f8 chore(content): move AdminTimezoneResolver binding to AdminPanelProvider
ae5b7c7 fix(content): resolve ui step 2 verify blockers
262d9be chore: add ADMIN_TIMEZONE to .env.example
9b0d736 feat(content): scaffold Filament Resource and Table for Content Blocks
a5d89ce feat(content): implement ui prerequisites and test updates
494c028 Merge pull request #3 from AchinthaDe/feat/content-blocks-backend
```

- `git diff --stat a5d89ce..HEAD` (Changes since first implementation commit):
```
 .env.example                                       |   1 +
 .../Content/Actions/GetBlockServingStatus.php      |  41 +--
 app/Domain/Content/Policies/ContentBlockPolicy.php |  70 +++++
 .../Admin/Pages/ManageStorefrontSettings.php       |   4 +-
 .../Admin/Resources/ContentBlockResource.php       |  41 +++
 .../Pages/ListContentBlocks.php                    |  18 ++
 .../Tables/ContentBlockTable.php                   | 139 +++++++++
 .../Admin/Schemas/StorefrontSettingsForm.php       |   2 +-
 .../Admin/Services/AdminTimezoneResolver.php       |  38 +++
 app/Providers/ContentServiceProvider.php           |   5 +
 app/Providers/Filament/AdminPanelProvider.php      |   7 +
 config/admin.php                                   |   7 +
 docs/blueprints/step_2b.md                         |  15 +-
 docs/blueprints/ui_step_2_plan.md                  | 102 +++++++
 docs/blueprints/ui_step_2_report.md                | 186 ++++++++++++
 .../ContentBlock/ListContentBlocksTableTest.php    | 322 +++++++++++++++++++++
 .../Content/Actions/GetBlockServingStatusTest.php  |  29 ++
 .../Content/Policies/ContentBlockPolicyTest.php    |  66 +++++
 .../Domain/Content/ServingHealthIntegrityTest.php  |   1 +
 tests/Support/TestBlocks.php                       |   4 +-
 tests/Unit/Admin/ContentBlockTableColorTest.php    |  17 ++
 .../Admin/Services/AdminTimezoneResolverTest.php   |  32 ++
 22 files changed, 1111 insertions(+), 36 deletions(-)
```

**Composer Check:**
- `Pint`: passed.
- `PHPStan`: passed (Level 9).
- `Pest`: `{"tool":"pest","result":"passed","tests":207,"passed":207,"assertions":504,"duration_ms":37172}`.
  - Baseline was 174 tests. We now have 207 tests.
  - New Test Files:
    - `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`
    - `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`
    - `tests/Unit/Admin/ContentBlockTableColorTest.php`
    - `tests/Unit/Filament/Admin/Services/AdminTimezoneResolverTest.php`
- `git grep -n "phpstan-ignore" -- app tests` returns nothing.
- `git diff` of `phpstan-baseline.neon` is unchanged.
- `docs/blueprints/step_2b.md` diff updates `modifyQueryUsing` to `defaultSort`, updates `ContentBlockPolicy` rules, and correctly identifies paths for resolver and forms.

## 2. Policy

**Implementation:**
- `git grep -n "BaseContentPolicy"` confirms `class ContentBlockPolicy extends BaseContentPolicy` in `app/Domain/Content/Policies/ContentBlockPolicy.php`.
- Full file:
```php
class ContentBlockPolicy extends BaseContentPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function view(User $user, ContentBlock $contentBlock): bool { return $user->isAdmin(); }
    public function create(User $user): bool { return $user->isAdmin(); }
    public function update(User $user, ContentBlock $contentBlock): bool { return $user->isAdmin(); }
    public function delete(User $user, ContentBlock $contentBlock): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
    public function restore(User $user, ContentBlock $contentBlock): bool { return false; }
    public function restoreAny(User $user): bool { return false; }
    public function forceDelete(User $user, ContentBlock $contentBlock): bool { return false; }
    public function forceDeleteAny(User $user): bool { return false; }
    public function reorder(User $user): bool { return $user->isAdmin(); }
    public function replicate(User $user, ContentBlock $contentBlock): bool { return $user->isAdmin(); }
}
```
- Registered in `ContentServiceProvider.php:26`: `Gate::policy(ContentBlock::class, ContentBlockPolicy::class);`.
- Confirmed no `before()` method exists. All write abilities use `$user->isAdmin()`, and all delete abilities strictly return `false`.

**Tests:**
- `ContentBlockPolicyTest.php` contains:
  - `it('is registered for ContentBlock')` verifying `Gate::getPolicyFor()`.
  - Matrix tests: `allows admin to view, create, update, reorder and replicate` and `denies non-admin everywhere`.
  - Non-admin UI visibility: `expect(\App\Filament\Admin\Resources\ContentBlockResource::canViewAny())->toBeFalse()->and(\App\Filament\Admin\Resources\ContentBlockResource::canCreate())->toBeFalse();` (Tested natively).

## 3. Timezone

**Implementation:**
- `.env.example` line added: `ADMIN_TIMEZONE=UTC`
- `config/admin.php`: `return ['timezone' => env('ADMIN_TIMEZONE', 'UTC')];`
- `app/Filament/Admin/Services/AdminTimezoneResolver.php`:
```php
final class AdminTimezoneResolver
{
    private bool $hasLogged = false;

    public function resolve(?string $timezone): string
    {
        if (empty($timezone)) { $timezone = 'UTC'; }
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            if (! $this->hasLogged) {
                Log::warning("Invalid ADMIN_TIMEZONE '{$timezone}' provided. Falling back to UTC.");
                $this->hasLogged = true;
            }
            return 'UTC';
        }
        return $timezone;
    }
}
```
- Bound as a singleton in `ContentServiceProvider.php`.
- `git grep -n "env(" -- app` -> Empty.
- `git grep -n "date_default_timezone_set" -- app` -> Empty.
- Applied strictly on a per-component level via `TextColumn::make('starts_at')->timezone($timezone)`.

**Tests:**
- `AdminTimezoneResolverTest.php` verifies empty string fallback, valid values, and strictly asserts the `Log::warning` fires exactly `once()` for invalid zones.
- `ListContentBlocksTableTest.php::respects timezone configuration in labels and formatted outputs without modifying database`:
  - Starts a block at 12:00:00 UTC.
  - Expects rendering at +05:30 in Filament (`M j, Y, g:i A`).
  - Strict assertion: `$block->refresh()->starts_at->format('H:i:s')` remains `12:00:00` unmodified.
- `GetBlockServingStatusTest` dynamically mutates configurations and validates `isLive()` behaves flawlessly regardless of `ADMIN_TIMEZONE`.

## 4. Resource and Table

**Resource (`ContentBlockResource.php`):**
- Typed perfectly: `protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';`
- `$navigationGroup = 'Content'` and `$navigationSort = 1` are set.
- `getModelLabel()` returns `Content Block`.
- `getPages()` only returns `'index'`. There are no edit/create row actions.
- Panel Navigation Test: `it('registers the resource in the panel navigation')` hits `route('filament.admin.pages.dashboard')` and asserts `$admin` can see `Content Block`.

**Table (`ContentBlockTable.php`):**
- **Default Sort:** `->defaultSort(fn (Builder $query) => $query->orderBy('placement')->orderBy('sort_order'))`.
- **Filters:** 
  - `hide_archived` dynamically inspects `$livewire` component state via `data_get($livewire, 'tableFilters.status.value') !== 'archived'` avoiding the silent blank-out bug.
  - `SelectFilter::make('placement')`, `SelectFilter::make('status')`.
  - `TernaryFilter::make('is_enabled')` handles booleans exactly as required.
- **Serving Status Badge:** 
  - `->formatStateUsing(fn (?ServingStatus $state) => $state?->label())`
  - Typed closures: `->color(fn (?ServingStatus $state) => match ($state) ...)` mapped to Live (success), NotLive (gray), PayloadError (danger).
- **Status Enum Badge:** `->color(fn (?PublishStatus $state) => match ($state?->value) ...)` Draft (gray), Published (success), Archived (warning).
- **Type Column:** Safely resolves labels via `$registry->get($state)->label()` utilizing the core `BlockRegistry`. If a block type is permanently deleted from the registry (unknown), the column safely renders the raw string key (`some_removed_type`) without crashing.
- `git grep -n "Filament" -- app/Domain` -> Empty.

## 5. Serving Status and Memo

**Memoization Implementation (`GetBlockServingStatus.php`):**
```php
$key = sprintf(
    'serving_status_%d_%s_%d_%s_%d_%d_%s_%d_%d',
    $block->id ?? 0,
    $block->type,
    $block->updated_at !== null ? $block->updated_at->timestamp : 0,
    $block->status ? $block->status->value : '',
    $block->is_enabled ? 1 : 0,
    $block->schema_version ?? 1,
    md5(json_encode($block->payload) ?: ''),
    $block->starts_at !== null ? $block->starts_at->timestamp : 0,
    $block->ends_at !== null ? $block->ends_at->timestamp : 0
);

return Cache::store('array')->rememberForever($key, function () use ($block) { ... });
```
- **Store:** `Cache::store('array')`.
- **Process Scope:** The array cache is per-process and harmless under PHP-FPM, automatically clearing between requests.
- **Callers:**
  - `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php:82: ->getStateUsing(fn (ContentBlock $record) => app(GetBlockServingStatus::class)($record)->status)`

## 6. Table Tests

**Location:** `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`
- **Smoke test**: View rendering gracefully completes for admins and non-admins receive `assertForbidden()`.
- **Unknown type**: Explicitly asserts `assertTableColumnFormattedStateSet('serving_status', 'Payload error')`.
- **Default Hidden/Clearing Filter**: Confirms archived block hides by default, clearing via `$component->set('tableFilters.hide_archived.isActive', false)` forces it back onto the view successfully.
- **Default Sort**: `assertCanSeeTableRecords([$block3, $block2, $block1], inOrder: true)` ensures multiple sorts properly layer.
- **N+1 Strict Guard:** We execute two identical queries (`N=10` and `N=20`). The closure tracks internal DB execution specifically testing `expect($queries20)->toBe($queries10)`.
- **Colour Check**: Verified colour mapping over `$table->getColumn()->getColor($block)` directly.

## 7. Deviations

1. **TestBlocks Idempotency**: `TestBlocks.php` was modified to explicitly check for `test_failed_upgrade_block` before registering it, preventing test failures due to duplication.
2. **Step 1 Report**: Unintentionally modified earlier, but now properly restored/reverted to its original state.
3. **Commit History**: Included force pushes / amends `ae5b7c7` to clean up the iterative blockers.
4. **`modifyQueryUsing` to `defaultSort`**: The blueprint mandated `modifyQueryUsing`, but this forces the table to *always* sort by this column and overrides end-user column clicking. Refactored natively to `defaultSort`.
5. **`Filter::make('is_enabled')` to `TernaryFilter`**: Boolean columns natively demand tri-state filters (All, Yes, No). Upgraded from standard Toggle Filter.
6. **`AdminTimezoneResolver` static context**: The previous plan relied on static `$hasLogged` states. Transformed into an instantiated service resolved as an `app()->singleton()`.

---
**READY** for Checkpoint 3.
