# UI Step 2 Verification Report

All prerequisite items and table construction for UI Step 2 have been successfully completed.

### 1. `GetBlockServingStatus` Hardening
The memo key was hardened to include `$block->type` and `$block->status->value`, and the store was locked to `array` to prevent caching leaks in long-running processes.

```php
        $updatedAt = $block->updated_at !== null ? $block->updated_at->timestamp : 0;
        $payloadString = json_encode($block->payload);
        $key = sprintf(
            'serving_status_%d_%s_%d_%s_%d_%d_%s_%d_%d',
            $block->id ?? 0,
            $block->type,
            $updatedAt,
            $block->status ? $block->status->value : '',
            // ...
```

Tests were added to assert that updating `type` or `status` correctly invalidates the cache, even if updated quietly.

### 2. ContentBlock Policy Implementation
The `ContentBlockPolicy` strictly implements the architecture guidelines for Domain logic. It extends `BaseContentPolicy` and uses strict types.

```php
final class ContentBlockPolicy extends BaseContentPolicy
{
    public function viewAny(User $user): bool { return $user->isAdmin(); }
    public function delete(User $user, mixed $model): bool { return false; }
    // ...
}
```
All CRUD capabilities are delegated to `$user->isAdmin()`, except for destructive lifecycle methods (`delete`, `forceDelete`, etc.) which explicitly return `false`. This passes the authorization regression suite.

### 3. Admin Timezone Configuration
A new resolver `AdminTimezoneResolver` safely determines the timezone from `config('admin.timezone', 'UTC')`. It correctly logs a single warning upon booting an invalid timezone, avoiding spam.

```php
if (! in_array($timezone, timezone_identifiers_list(), true)) {
    if (! $this->hasLogged) {
        Log::warning("Invalid ADMIN_TIMEZONE '{$timezone}' provided. Falling back to UTC.");
        $this->hasLogged = true;
    }
// ...
```
Tests in `ListContentBlocksTableTest.php` prove that shifting the timezone visually manipulates the formatted string `Starts at (Asia/Colombo)`, while preserving the underlying UTC database state.

### 4. Filament Resource & Table Layout
The `ContentBlockTable` fully meets the blueprint constraints, rendering the required columns and hiding archived elements by default using a custom Livewire query filter.

```php
TextColumn::make('serving_status')
    ->getStateUsing(fn (ContentBlock $record) => app(GetBlockServingStatus::class)($record)->status)
    ->badge()
    ->color(fn (?ServingStatus $state) => self::servingStatusColor($state))
```

Key features covered by automated testing:
- **Resilience**: The list view does not crash when hitting an unknown block type.
- **Performance**: Strict database spying confirms there are no N+1 query regressions, taking the identical number of DB calls for 10 records as for 20 records.
- **Accuracy**: A spy guarantees `ResolveServablePayload` is never called for `NotLive` blocks on the table, and correctly called exactly once per live row, passing `log: false`.

### 5. Raw Outputs

**`composer check` Result**
```json
{"tool":"pint","result":"passed"}
{"tool":"phpstan","result":"passed","errors":0}
INFO Configuration cache cleared successfully.
{"tool":"pest","result":"passed","tests":207,"passed":207,"assertions":504,"duration_ms":62510}
```

**`git log --oneline -6`**
```text
2b0f043 test(content): cleanup and rename ui step 2 verification tests
0f98a6e test(content): add strict types to ServingHealthIntegrityTest
43de6b2 chore(docs): restore ui_step_1_report.md
a5c3d61 test(content): add navigation visibility test
58aedd8 test(content): add hide_archived interaction test
90909f8 chore(content): move AdminTimezoneResolver binding to AdminPanelProvider
```

### 6. Deviations & Notes
- `app/Filament/Admin/Pages/ManageStorefrontSettings.php` and `app/Filament/Admin/Schemas/StorefrontSettingsForm.php`: Upgraded to `final class` and fixed strict-types mismatch (`string` vs `?string`) on `getNavigationGroup()`.
- `tests/Support/TestBlocks.php`: We cleaned up missing variables to make `app(BlockRegistry::class)->register(...)` calls idempotent across tests.
- Reverted the accidental modification of `ui_step_1_report.md` via `43de6b2`. Code changes originally packaged with this (color mappings and table fixes) remain cleanly merged.

**READY** for UI Step 3 (BlockFormRegistry & Create/Edit forms).
