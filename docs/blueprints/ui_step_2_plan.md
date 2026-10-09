# UI Step 2 Implementation Plan (Checkpoint)

This document outlines the detailed step-by-step technical plan to implement UI Step 2: **Build Policy + Timezone Config + Filament List table + table badges**.

## 1. GetBlockServingStatus Hardening

**File**: `app/Domain/Content/Actions/GetBlockServingStatus.php`

**Implementation details**:
- The blueprint demands `type` and `status` in the key, but the current code omits them. We will add `$block->type` and `$block->status?->value` to the memo key immediately.
- The memo cache store will explicitly use `Cache::store('array')` (or another isolated request-bound strategy) to prevent leaks across long-running workers.

**Tests**: 
- Add a test that updating `status` via `updateQuietly()` correctly forces a fresh resolution.
- Add a test that updating `type` via `updateQuietly()` correctly forces a fresh resolution.

## 2. ContentBlock Policy

**File**: `app/Domain/Content/Policies/ContentBlockPolicy.php`

**Implementation details**:
- Extend `BaseContentPolicy`.
- Register the policy explicitly via `Gate::policy(ContentBlock::class, ContentBlockPolicy::class)` (e.g. in `AppServiceProvider`).
- Implement methods explicitly avoiding default inheritance flaws:
  - `viewAny`, `view`, `create`, `update`, `reorder`, `replicate` should return `$user->isAdmin()`.
  - `delete`, `deleteAny`, `restore`, `restoreAny`, `forceDelete`, `forceDeleteAny` should explicitly return `false` because the block lifecycle relies strictly on archiving, not hard deletions.
  - Do NOT use `before()` to avoid bypassing method-level denials.

**Tests**: 
- `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`: assert `Gate::getPolicyFor(ContentBlock::class)` returns the correct instance.
- Livewire 403 Test (`tests/Feature/Admin/ContentBlock/ListContentBlocksTest.php`): assert a non-admin gets a `403` strictly when loading the list page.

## 2. Timezone Configuration

**File**: `config/admin.php` and `app/Filament/Admin/Services/AdminTimezoneResolver.php` (placed properly under Admin context)

**Implementation details**:
- Do not apply `date_default_timezone_set` or mutate `app.timezone` since storage and domain logic must strictly remain in UTC. Timezone offsets apply solely to the Admin Panel display and inputs.
- Add `ADMIN_TIMEZONE="UTC"` to `.env.example`.
- Bind this inside `config/admin.php` => `'timezone' => env('ADMIN_TIMEZONE', 'UTC')`.
- Create `AdminTimezoneResolver` with a pure `resolve(?string $value)` method that the caller passes the config value into. If empty string or invalid, it returns `'UTC'`.
- Use this resolver to explicitly apply `->timezone(AdminTimezoneResolver::resolve(config('admin.timezone')))` onto all `starts_at` / `ends_at` table columns and form pickers (I have verified Filament natively supports the `->timezone()` method on columns and pickers).
- In a Service Provider (e.g. `AdminPanelProvider`), log a warning once per process if the config value is invalid, rather than on every boot.

**Tests**:
- Unit test the pure `resolve` method without booting the app.
- Ensure an invalid or empty string timezone triggers a `Log::warning` spy via an injected logger or feature test.
- Add a positive feature test proving that `Asia/Colombo` renders `starts_at` shifted by +05:30 in the UI output, while ensuring the storage layer and `isLive()` evaluations strictly remain UTC.

## 3. Filament Resource & Files

Following `docs/blueprints/step_2b.md` and ARCHITECTURE conventions, we will scaffold:

1. **Resource**: `App\Filament\Admin\Resources\ContentBlockResource` (`app/Filament/Admin/Resources/ContentBlockResource.php`)
2. **Table Configuration Class**: `App\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable` (`app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`)
3. **List Page**: `App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks` (`app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php`)

**Implementation details**:
- `ContentBlockResource`:
  - Set `$model = ContentBlock::class`.
  - Provide `protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';` and `protected static string|UnitEnum|null $navigationGroup = 'Content';`.
  - Add `protected static ?int $navigationSort = ...;` if needed, and configure `getModelLabel()` or `$modelLabel`.
  - Configure `table(Table $table)` to cleanly delegate: `return ContentBlockTable::table($table);`.
  - Register `getPages()` mapping exclusively to the index route: `'index' => Pages\ListContentBlocks::route('/')`.

## 4. The List Table & Badges

**File**: `App\Filament\Admin\Resources\ContentBlockResource\Tables\ContentBlockTable.php`

**Implementation details**:
- `final class ContentBlockTable` using `public static function table(Table $table): Table`.
- **Query & Features**:
  - `->defaultSort(fn (Builder $q) => $q->orderBy('placement')->orderBy('sort_order'))` (or `modifyQueryUsing`).
  - Paginated list (`->paginated([10, 25, 50])`) to cap resolver executions per re-render.
  - Filters: `placement`, `status`, and `is_enabled` (configured to default to hiding archived blocks).
  - Row / header actions do not exist yet (as per blueprint layout).
- **Columns**:
  - `TextColumn::make('type')`: Retrieve the label from `BlockRegistry` (via `BlockDefinition`). If a definition has no explicit label method, we will add one to `BlockDefinition`. If the type is removed/missing (lookup returns null), fallback to the raw type key string.
  - `TextColumn::make('placement')`: Display placement label.
  - `TextColumn::make('status')`: Badge displaying Draft (gray), Published (green), Archived (warning).
  - `IconColumn::make('is_enabled')->boolean()`.
  - `TextColumn::make('starts_at')` & `ends_at`: Display date/time, labeled explicitly stating the resolved timezone.
  - **Serving Status Badge (The Core Logic)**:
    - Create a computed column: `TextColumn::make('serving_status')` which is explicitly `sortable(false)->searchable(false)`.
    - `getStateUsing(fn (ContentBlock $record) => app(GetBlockServingStatus::class)($record)->status)` (Returns Enum).
    - Apply `.badge()`.
    - Apply `.formatStateUsing(fn ($state) => $state?->label())` and `.color(fn ($state) => match($state) { ... })`. (Avoid implementing Filament contracts on the domain enums!).
    - Because `GetBlockServingStatus` checks `$block->isLive()` *before* invoking the resolver, broken blocks that are currently drafted or out-of-window strictly output `NotLive` without leaking error states.

**Tests (`tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`)**:
- List smoke test asserting the table renders correctly.
- Ensure an unknown block type doesn't crash the list rendering.
- Query-count check to explicitly enforce no N+1 issues when loading the table.
- A test explicitly mapping each Serving Status outcome (`Live`, `NotLive`, `PayloadError`) to verify the correct badge state renders in the UI array/HTML.
- A spy asserting `ResolveServablePayload` is NEVER called for non-live blocks, and that it is called passing `log: false` exactly once per live row on the table.

## Definition of Done (for this checkpoint)
1. `composer check` passes flawlessly with strict 0 exit code, maintaining the test baseline (174 baseline).
2. The `ContentBlockPolicy` covers all authorization paths in the correct Domain namespace.
3. Timezone resolution logic is fully tested and purely UI-scoped.
4. The Admin Panel list view is resilient (no crashes on missing types), properly queried, limits N+1, and perfectly adheres to the blueprint UI constraints.
5. All revisions, discrepancies, and deviations are documented transparently in `ui_step_2_report.md` alongside raw CI outputs.
