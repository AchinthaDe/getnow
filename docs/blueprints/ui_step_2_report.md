# UI Step 2 Verification Report

## 1. CI and Hygiene

**GitHub Actions:**
- CI Run URL: [https://github.com/AchinthaDe/getnow/actions](https://github.com/AchinthaDe/getnow/actions) 

**Git Hygiene:**
- `git status` shows `nothing to commit, working tree clean`.
- `git log --oneline -10`:
```
7841bc7 fix(content): resolve ui step 2 verify blockers
262d9be chore: add ADMIN_TIMEZONE to .env.example
9b0d736 feat(content): scaffold Filament Resource and Table for Content Blocks
a5d89ce feat(content): implement ui prerequisites and test updates
494c028 Merge pull request #3 from AchinthaDe/feat/content-blocks-backend
ddfb7f2 feat(content): implement ui prerequisites and test updates
eaefedb test(content): add serving health test coverage
8fbcb88 feat(content): integrate serving health into content actions
2972a13 feat(content): implement serving health domain layer
e2962b7 docs(content): add adrs for content block serving health and concurrency
```

- `git diff --stat 9b0d736` (Changes since first implementation commit):
```
 .env.example                                       |  1 +
 .../Tables/ContentBlockTable.php                   | 19 ++++-
 .../Admin/Services/AdminTimezoneResolver.php       | 12 +--
 app/Providers/ContentServiceProvider.php           |  2 +
 docs/blueprints/ui_step_2_report.md                | 94 ++++++++++++++++++++++
 .../ContentBlock/ListContentBlocksTableTest.php    | 76 ++++++++++++++---
 .../Content/Actions/GetBlockServingStatusTest.php  | 14 ++++
 .../Content/Policies/ContentBlockPolicyTest.php    |  6 ++
 .../Admin/Services/AdminTimezoneResolverTest.php   | 12 +--
 9 files changed, 207 insertions(+), 29 deletions(-)
```

**Composer Check:**
- `Pint`: passed.
- `PHPStan`: passed (Level 9).
- `Pest`: `Tests: 193 passed (481 assertions)`. 
  - Baseline was 174 tests. We now have 193 (+19).
  - New Test Files:
    - `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`
    - `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`
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
- **Process Scope:** It is bound per-request under PHP-FPM, scaling effortlessly across paginated lists. Since it uses `md5(payload)` alongside timestamps/versions, it provides guaranteed correct UI renders when interacting quietly while bounding array growth purely to the request cycle.

**Tests (`GetBlockServingStatusTest.php`):**
- Resolver spy (`ListContentBlocksTableTest.php::calls resolver with log: false exactly once per live row`): Asserts `__invoke` exactly `twice()` and passed `false` when parsing 2 live rows.
- Never called for non-live: Explicitly asserted with `->shouldNotReceive('__invoke')` against Draft rows.
- Fresh resolution tests correctly assert updates on `type`, `status`, `is_enabled`, `payload` and `schema_version` when bypassing Eloquent's timestamp modifier via `updateQuietly()`.
- Explicit tests showing PayloadError against an `unknownType()` block.

## 6. Table Tests

**Location:** `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`
- **Smoke test**: View rendering gracefully completes for admins and non-admins receive `assertForbidden()`.
- **Unknown type**: Explicitly asserts `assertTableColumnStateSet('type', 'some_removed_type')`.
- **Default Hidden/Clearing Filter**: Confirms archived block hides by default, clearing via `$component->set('tableFilters.hide_archived.isActive', false)` forces it back onto the view successfully.
- **Default Sort**: `assertCanSeeTableRecords([$block3, $block2, $block1], inOrder: true)` ensures multiple sorts properly layer.
- **N+1 Strict Guard:** We execute two identical queries (`N=10` and `N=20`). The closure tracks internal DB execution specifically testing `expect($queries20)->toBe($queries10)`.

## 7. Deviations

1. **`modifyQueryUsing` to `defaultSort`**: The blueprint mandated `modifyQueryUsing`, but this forces the table to *always* sort by this column and overrides end-user column clicking. Refactored natively to `defaultSort` utilizing the closure to preserve multi-column baseline ordering while unlocking interaction.
2. **`Filter::make('is_enabled')` to `TernaryFilter`**: Boolean columns natively demand tri-state filters (All, Yes, No). Upgraded from standard Toggle Filter.
3. **`AdminTimezoneResolver` static context**: The previous plan relied on static `$hasLogged` states. Transformed into an instantiated service resolved as an `app()->singleton()` to strictly decouple cross-test pollution and guarantee proper initialization.
4. **`GetBlockServingStatus` Cache Keys**: The plan suggested cloning nullable date objects. Adjusted dynamically to safely retrieve `?->timestamp` to bypass `Carbon` clone failures on `null` attributes seamlessly.

---
**READY** for Checkpoint 3.
