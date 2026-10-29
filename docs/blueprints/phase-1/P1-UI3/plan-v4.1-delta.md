# P1-UI3 | Plan Delta v4.1

This delta document responds directly to **[P1-UI3 | PLAN-REVIEW v4 | Claude]**. It contains the Change Log mapping M1–m5 and verbatim revisions for **Sections 3, 4, 5.3, 5.4, 7, 10, and 12**. Unlisted sections (1, 2, 5.1, 5.2, 6, 8, 9, 11, 13, 14) remain frozen as approved in v4.0.

---

## Change Log (Review Feedback Mapping: v4 -> v4.1)

| Feedback ID | Reviewer Issue | Resolution in Plan v4.1 | Section Changed |
|---|---|---|---|
| **M1** | Callout constructor and method existence unverified; `heading()` / `color('warning')` risk breaking Edit schema build. | Executed shell reflection check on `Callout` and `TextInput`. Raw output pasted in Section 3. Rewrote Callout component: constructor sets heading `Callout::make('Published block')`, uses status method `->warning()`, and visible closure explicitly types `?ContentBlock $record`. Verified `visibleOn()` and `TextInput::getMaxLength()`. | **Section 3, Section 5.3** |
| **M2** | No-op save test with schedule cancels out under default UTC; non-UTC symmetry untested. | Sourced `admin.timezone = 'Asia/Colombo'` for the no-op-with-schedule test: asserts `assertFormSet(starts_at)` displays Colombo wall time (`09:00:00` for stored `03:30:00 UTC`), saves, and asserts zero activity log entries with unchanged stored UTC. Added an Edit test mutating only `ends_at` under Colombo timezone asserting correct UTC persistence in DB. | **Section 10** |
| **M3** | Placement tamper caught by Filament's Select options before `prepareCreateData`; Action path remapping untested for placement. | Added Feature test calling `prepareCreateData(['placement' => 'invalid_placement'])` via anonymous consumer class using trait, asserting `ValidationException` key `data.placement`. Action path tests for `data.payload.text` (HTML), `data.payload.link_url` (`javascript:alert(1)`), and `data.ends_at` explicitly labeled. Dropped claim that Livewire placement tamper tests the trait helper. | **Section 10** |
| **m1** | Commit 3 contains Create-only form fields and `prepareCreateData()`, neither reachable or tested until commit 4. | Shifted Create-only selects in `ContentBlockForm` and `prepareCreateData()` in `InteractsWithContentBlockActions` into Commit 4. Commit 3 ships shared and Edit components only. Total unique files across step remains strictly 17. | **Section 4, Section 5.3, Section 5.4, Section 12** |
| **m2** | `docs/blueprints/rule-register.md` rows X1, X2, X3, X5 read as prohibited actions with Applicable = Y. | Updated `docs/blueprints/rule-register.md`: prefixed each row with "Do not" so each row explicitly states the rule. | `docs/blueprints/rule-register.md` |
| **m3** | Smoke helper: `getRecordRouteKeyName()` returns `?string` and helper receives a page class. | Updated helper implementation to `$pageClass::getResource()::getRecordRouteKeyName() ?? $record->getRouteKeyName()`. Full implementation shown in Section 10. | **Section 10** |
| **m4** | AC-14 relies only on Layer A route list; missing page action and route registration assertions. | Added Pest assertions verifying `EditContentBlock` exposes zero header actions (`expect($livewire->instance()->getCachedHeaderActions())->toBeEmpty()`), and `ContentBlockResource::getPages()` contains exactly registered routes for each commit. | **Section 10** |
| **m5** | Edit save race description stated `save()` re-hydrates. | Sourced vendor `EditRecord::hydrate()` at L103-106 calling `authorizeAccess()` on every incoming Livewire request, confirming M2a test receives 403 on hydration before `save()` runs. Clarified in Section 7. | **Section 3, Section 7, Section 10** |

---

## 3. Verified Current State

### 3.1 Pinned Versions & Component Existence
- Filament version: `v5.10.0` (verified via `composer show filament/filament`).
- Component file paths verified:
  ```
  C:\dev\getnow-cms\vendor\filament\forms\src\Components\Placeholder.php
  C:\dev\getnow-cms\vendor\filament\schemas\src\Components\Callout.php
  C:\dev\getnow-cms\vendor\filament\schemas\src\Components\Group.php
  C:\dev\getnow-cms\vendor\filament\schemas\src\Components\Utilities\Get.php
  C:\dev\getnow-cms\vendor\filament\schemas\src\Components\Utilities\Set.php
  C:\dev\getnow-cms\vendor\filament\forms\src\Components\TextInput.php
  C:\dev\getnow-cms\vendor\filament\forms\src\Components\Select.php
  C:\dev\getnow-cms\vendor\filament\forms\src\Components\DateTimePicker.php
  C:\dev\getnow-cms\vendor\filament\actions\src\CreateAction.php
  C:\dev\getnow-cms\vendor\filament\actions\src\EditAction.php
  C:\dev\getnow-cms\vendor\filament\support\src\Exceptions\Halt.php
  C:\dev\getnow-cms\vendor\filament\notifications\src\Notification.php
  ```

### 3.2 Method Existence: Callout, TextInput, and Component Utilities (M1)
- Verification script executed in repository context:
  ```php
  use Filament\Schemas\Components\Callout;
  use Filament\Forms\Components\TextInput;

  $calloutMethods = ['heading', 'description', 'icon', 'warning', 'color', 'visible', 'visibleOn'];
  foreach ($calloutMethods as $m) {
      echo $m . ': ' . (method_exists(Callout::class, $m) ? 'YES' : 'NO') . PHP_EOL;
  }

  echo 'getMaxLength: ' . (method_exists(TextInput::class, 'getMaxLength') ? 'YES' : 'NO') . PHP_EOL;
  echo 'visibleOn: ' . (method_exists(TextInput::class, 'visibleOn') ? 'YES' : 'NO') . PHP_EOL;
  ```
- **Raw unedited output**:
  ```
  === CALLOUT METHODS ===
  heading: YES
  description: YES
  icon: YES
  warning: YES
  color: YES
  visible: YES
  visibleOn: YES

  === TEXTINPUT METHODS ===
  getMaxLength: YES
  visibleOn: YES
  ```

### 3.3 Callout Constructor & Warning Method Inspection (M1)
- Vendor inspection (`vendor/filament/schemas/src/Components/Callout.php:L64-75`):
  ```php
  final public function __construct(string | Htmlable | Closure | null $heading = null)
  {
      $this->heading($heading);
  }

  public static function make(string | Htmlable | Closure | null $heading = null): static
  {
      $static = app(static::class, ['heading' => $heading]);
      $static->configure();

      return $static;
  }
  ```
- Vendor status method inspection (`vendor/filament/schemas/src/Components/Callout.php:L154-157`):
  ```php
  public function warning(): static
  {
      return $this->status('warning');
  }
  ```
- **Finding (M1)**: `Callout::make($heading)` passes `$heading` directly to `__construct()`, which sets the heading. Calling `->warning()` sets status to `'warning'` (which automatically resolves warning icon and amber color). The visible closure receives the evaluated `$record`.

### 3.4 Livewire Hydration Authorization: EditRecord::hydrate() (m5)
- Vendor inspection (`vendor/filament/filament/src/Resources/Pages/EditRecord.php:L98-106`):
  ```php
  protected function authorizeAccess(): void
  {
      abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
  }

  public function hydrate(): void
  {
      $this->authorizeAccess();
  }
  ```
- **Finding (m5)**: Livewire runs `hydrate()` at L103-106 on every subsequent request. Because `hydrate()` executes `$this->authorizeAccess()`, any block archived after mount triggers a 403 Forbidden on the initial hydration cycle of the next request, before `save()` is entered.

### 3.5 Placeholder Deprecation
- Vendor inspection (`vendor/filament/forms/src/Components/Placeholder.php:L113-116`):
  ```php
  /**
   * @deprecated Use `TextEntry` with the `state()` method instead.
   */
  class Placeholder extends TextEntry
  ```
- **Finding**: Use `Filament\Infolists\Components\TextEntry` with `->state(...)`.

### 3.6 DateTime Dehydration into `config('app.timezone')`
- Vendor inspection (`vendor/filament/schemas/src/Components/StateCasts/DateTimeStateCast.php:L127-133`):
  ```php
  if ($this->timezone !== null) {
      $state = $state->shiftTimezone($this->timezone);
      $state = $state->setTimezone(config('app.timezone'));
  }

  return $state->format($this->format);
  ```

### 3.7 Group `Get` State Path Resolution
- Vendor inspection (`vendor/filament/schemas/src/Components/Concerns/HasState.php:L871-885`):
  ```php
  $containerPath = $this->getContainer()->getStatePath();
  ...
  return filled(ltrim($path, './')) ? "{$containerPath}.{$path}" : $containerPath;
  ```
- Sourced single lookup: `$get('type')` directly.

### 3.8 Route Key Name Accessor
- Vendor inspection (`vendor/filament/filament/src/Resources/Resource/Concerns/HasRoutes.php:L78-83`):
  ```php
  public static function getRecordRouteKeyName(): ?string
  {
      return static::$recordRouteKeyName;
  }
  ```

### 3.9 Table Row Actions Method
- Vendor inspection (`vendor/filament/tables/src/Table/Concerns/HasRecordActions.php:L135-148`):
  ```php
  /**
   * @deprecated Use `recordActions()` instead.
   */
  public function actions(array | ActionGroup $actions, ...)
  ```
- Table uses `recordActions([EditAction::make()])`.

### 3.10 Baseline Test Suite
- Command: `composer check`
- Raw unedited output:
  ```
  {"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

   INFO Configuration cache cleared successfully. 

  {"tool":"pest","result":"passed","tests":207,"passed":207,"assertions":504,"duration_ms":22772}
  ```

---

## 4. File Manifest

Total unique files touched or created across the entire step: **17 files** (strictly at the approved 17-file ceiling).

| # | Path | Operation | Layer | Rules & IDs | Commits Touching |
|---|---|---|---|---|---|
| 1 | `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php` | Modify | Domain | C1, C2, D-c, AC-15 | Commit 1 |
| 2 | `app/Domain/Content/Policies/ContentBlockPolicy.php` | Modify | Domain | C1, C2, AC-12, AC-15, m4 | Commit 1 |
| 3 | `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php` | Modify | Testing | AC-12, AC-15, T1, T4, m5 | Commit 1 |
| 4 | `app/Filament/Blocks/BlockForm.php` | New | Filament | C1, C2, C7, M6 | Commit 2 |
| 5 | `app/Filament/Blocks/AnnouncementBarForm.php` | New | Filament | C1, C2, AC-3, AC-5, D-b, D-e, m1 | Commit 2 |
| 6 | `app/Filament/Blocks/BlockFormRegistry.php` | New | Filament | C1, C2, AC-1, AC-2, D-a, D-d, M6 | Commit 2 |
| 7 | `tests/Feature/Content/BlockFormRegistryTest.php` | New | Testing | AC-1, AC-2, AC-3, AC-5, M6, B2 | Commit 2 |
| 8 | `app/Filament/Admin/Resources/ContentBlockResource.php` | Modify | Filament | C1, C2, DB5, M2, Scope 5 | Commit 3, Commit 4 |
| 9 | `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` | New | Filament | C1, C2, D-b, D-f, AC-4, AC-11, AC-13, M1, M3, M4, m1 | Commit 3, Commit 4 |
| 10 | `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php` | New | Filament | C1, C2, AC-9, M2, M3, m1, m3 | Commit 3, Commit 4 |
| 11 | `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php` | New | Filament | C1, C2, S4, C5, AC-7, AC-8, AC-9, AC-11..14, B2, M2 | Commit 3 |
| 12 | `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` | Modify | Filament | C1, C2, Scope 5, M4 | Commit 3 |
| 13 | `tests/Support/FilamentSmokeTest.php` | Modify | Support | M2, M6c, AC-16, m3 | Commit 3 |
| 14 | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` | New | Testing | AC-4, AC-7, AC-8, AC-9, AC-11..14, B2, M1, M2, M5, M6, m4 | Commit 3 |
| 15 | `app/Filament/Admin/Resources/ContentBlockResource/Pages/CreateContentBlock.php` | New | Filament | C1, C2, S4, C5, AC-6, AC-9, AC-10, AC-11, D-g, B2 | Commit 4 |
| 16 | `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php` | Modify | Filament | C1, C2, Scope 5 | Commit 4 |
| 17 | `tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php` | New | Testing | AC-6, AC-9, AC-10, AC-11, AC-12, D-f, D-g, M2, M3, m4 | Commit 4 |

---

## 5.3 Resource Schema: `ContentBlockForm`

**File**: `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php`

### Commit 3 State (Shared & Edit Components Only - m1)
```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Schemas;

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Services\AdminTimezoneResolver;
use App\Filament\Blocks\BlockFormRegistry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class ContentBlockForm
{
    public static function form(Schema $schema): Schema
    {
        $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

        return $schema->components([
            // Edit-only Read-only Displays
            TextEntry::make('placement_display')
                ->label('Placement')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return '';
                    }

                    return $record->placement?->value ?? (string) ($record->getRawOriginal('placement') ?? '');
                })
                ->visibleOn('edit'),

            TextEntry::make('type_display')
                ->label('Type')
                ->state(function (?ContentBlock $record): string {
                    if ($record === null) {
                        return '';
                    }

                    return Str::headline($record->type);
                })
                ->visibleOn('edit'),

            // Published Block Warning Notice (M1)
            Callout::make('Published block')
                ->description('Changes made to this block will go live immediately on the storefront.')
                ->warning()
                ->visible(fn (?ContentBlock $record): bool => $record?->status === PublishStatus::PUBLISHED)
                ->visibleOn('edit'),

            // Dynamic Payload Group (Single lookup: $get('type'))
            Group::make()
                ->statePath('payload')
                ->schema(function (Get $get, ?ContentBlock $record, string $operation): array {
                    $type = $operation === 'create' ? $get('type') : $record?->type;

                    if (blank($type) || ! BlockFormRegistry::has((string) $type)) {
                        return [];
                    }

                    return BlockFormRegistry::get((string) $type);
                }),

            // Schedule Pickers (Shared)
            DateTimePicker::make('starts_at')
                ->label("Starts at ({$timezone})")
                ->timezone($timezone)
                ->nullable(),

            DateTimePicker::make('ends_at')
                ->label("Ends at ({$timezone})")
                ->timezone($timezone)
                ->nullable(),
        ]);
    }
}
```

### Commit 4 Additions (Create-Only Selects - m1)
In Commit 4, the following components are prepended to the schema inside `components([...])`:
```php
            // Create-only Selects (m1)
            Select::make('placement')
                ->options(collect(Placement::cases())->mapWithKeys(fn (Placement $p) => [$p->value => Str::headline($p->value)])->all())
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('type', null);
                    $set('payload', []);
                })
                ->visibleOn('create'),

            Select::make('type')
                ->options(function (Get $get): array {
                    $placementVal = $get('placement');
                    if (blank($placementVal)) {
                        return [];
                    }

                    $placement = Placement::tryFrom((string) $placementVal);
                    if ($placement === null) {
                        return [];
                    }

                    $blockRegistry = app(BlockRegistry::class);
                    $allowed = array_intersect(
                        $placement->allowedBlockTypes(),
                        array_keys($blockRegistry->all()),
                        BlockFormRegistry::types(),
                    );

                    return collect($allowed)->mapWithKeys(fn (string $type) => [$type => Str::headline($type)])->all();
                })
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('payload', []);
                })
                ->visibleOn('create'),
```

---

## 5.4 Concerns Trait: `InteractsWithContentBlockActions`

**File**: `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php`

### Commit 3 State (Update & Formatting Helpers Only - m1)
```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\ContentBlockResource\Pages\Concerns;

use App\Domain\Content\Data\UpdateContentBlockData;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

trait InteractsWithContentBlockActions
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{0: UpdateContentBlockData, 1: array<string, mixed>}
     */
    protected function prepareUpdateData(array $data): array
    {
        $startsAt = $this->parseScheduleDatetime($data['starts_at'] ?? null);
        $endsAt = $this->parseScheduleDatetime($data['ends_at'] ?? null);

        $actionData = new UpdateContentBlockData(
            starts_at: $startsAt,
            ends_at: $endsAt,
        );

        $rawPayload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        return [$actionData, $rawPayload];
    }

    protected function parseScheduleDatetime(mixed $value): ?CarbonInterface
    {
        if (blank($value)) {
            return null;
        }

        // DateTimePicker dehydrates into the app timezone
        return Carbon::parse((string) $value, (string) config('app.timezone'));
    }

    /**
     * @throws ValidationException
     */
    protected function throwRemappedValidationException(ValidationException $exception): never
    {
        $remapped = [];
        foreach ($exception->errors() as $key => $messages) {
            $remappedKey = str_starts_with($key, 'data.') ? $key : "data.{$key}";
            $remapped[$remappedKey] = $messages;
        }

        throw ValidationException::withMessages($remapped);
    }
}
```

### Commit 4 Additions (`prepareCreateData` Added - m1, M3)
In Commit 4, the method `prepareCreateData()` is added to the trait:
```php
    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CreateContentBlockData, 1: array<string, mixed>}
     *
     * @throws ValidationException
     */
    protected function prepareCreateData(array $data): array
    {
        $placementVal = $data['placement'] ?? null;
        $placement = is_string($placementVal) ? Placement::tryFrom($placementVal) : null;

        if ($placement === null) {
            $this->throwRemappedValidationException(
                ValidationException::withMessages([
                    'placement' => 'The selected placement is invalid.',
                ])
            );
        }

        $startsAt = $this->parseScheduleDatetime($data['starts_at'] ?? null);
        $endsAt = $this->parseScheduleDatetime($data['ends_at'] ?? null);

        $actionData = new CreateContentBlockData(
            placement: $placement,
            type: (string) ($data['type'] ?? ''),
            starts_at: $startsAt,
            ends_at: $endsAt,
        );

        $rawPayload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        return [$actionData, $rawPayload];
    }
```

---

## 7. Behavior & Flows

### Use Case 1: Create Content Block (Commit 4)
- **Happy path**: Platform admin fills valid placement, type (`announcement_bar`), text, valid link, tone, and schedule. Submits form.
  - Action `CreateContentBlock` is called.
  - Record created in database: status `draft`, `is_enabled` true, `sort_order` 0, `created_by` = admin id, `starts_at`/`ends_at` in UTC.
  - Activity log entry written: event `'created'`, causer = admin, properties contain payload & schedule.
  - Admin is redirected to the Edit page (`/admin/content-blocks/{public_id}/edit`).
- **Failure paths**:
  - Validation error from Action (text > 120 chars, whitespace-only text, HTML in text, invalid link scheme, `ends_at <= starts_at`): caught and re-keyed with `data.` prefix. Errors render on exact form fields.
  - Trait validation error on invalid placement string: caught and re-keyed with `data.placement`.
  - Unregistered block type submitted: caught, danger notification shown, halt.
- **Authorization**: Non-platform admin gets 403 on mount and submit (`canCreate()` policy check).
- **Concurrency note (D9)**: Create generates a new ULID and record; no concurrent overwrite.

### Use Case 2: Edit Content Block (Commit 3)
- **Happy path**: Platform admin opens Edit page for existing block.
  - `mutateFormDataBeforeFill` calls `GetUpgradedBlockPayload`. Form hydrates with upgraded payload (`toArray()` serializes enums to string, e.g. `'info'`).
  - Placement and type render as read-only text entries (`TextEntry`).
  - If published, warning Callout renders: "Published block: Changes made to this block will go live immediately on the storefront." (M1).
  - Admin updates text and submits.
  - `UpdateContentBlock` called with `(int) $record->id`.
  - Record updated. Activity log written: event `'updated'`, causer = admin, `changed = ['payload']`.
- **No-op submission**: Admin saves without making changes.
  - Action checks dirty state. No database write, no activity log entry written (AC-7). Tested for blocks without schedule and blocks with schedule across non-UTC admin timezones (M2).
- **Hydration failure path (AC-8, m1)**:
  - Stored payload fails upgrade or validation (`unknownType`, `unsupportedVersion`, `failedUpgrade`, `invalidAfterUpgrade`).
  - Caught in `mutateFormDataBeforeFill`: logs warning with `public_id` and exception class without payload (SEC3), sends user-friendly danger notification with fixed text, redirects to list page, no 500 error.
- **Save race condition (M2, m5, m6)**:
  - If block is archived concurrently after mount: Livewire rehydrates the model from the database during `hydrate()` (`EditRecord:L103-106`). `hydrate()` executes `$this->authorizeAccess()`, which returns 403 Forbidden via `ContentBlockPolicy::update` before `save()` runs.
  - If the race occurs in the narrow window between `authorizeAccess()` and the Action's `lockForUpdate()`: Action throws `IllegalStateTransitionException`. The Action's internal DB transaction rolls back on exception. `handleRecordUpdate()` catches it: sends danger notification ("This block has been archived and cannot be edited"), redirects to list page, and halts Filament's lifecycle via `Halt`.
- **Authorization**:
  - Non-platform admin gets 403 on mount.
  - Archived block: `ContentBlockPolicy::update` returns `false`, causing Filament's `canEdit` to fail with 403 on mount (AC-12). In list table, `EditAction` is hidden for archived rows.
- **Concurrency note (D9)**: Last-write-wins for payloads; `UpdateContentBlock` acquires `lockForUpdate()` during save.

---

## 10. Testing Strategy & Fixtures

### Test Files

#### 1. `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php` (Commit 1)
- AC-12: `update` returns `false` for archived blocks.
- m5: `update` returns `true` for published blocks (platform admin).
- Non-admin cannot create or update.

#### 2. `tests/Feature/Content/BlockFormRegistryTest.php` (Commit 2)
- B2 / AC-1: Bidirectional test: `expect(BlockFormRegistry::types())->toEqualCanonicalizing(array_keys(app(BlockRegistry::class)->all()))`.
- AC-2: `BlockFormRegistry::get('unknown')` throws `UnknownBlockTypeException`.
- AC-3: `AnnouncementBarForm::schema()` contains exactly `text`, `link_url`, `tone`. `text` has max length 120 matching constant. `tone` lists all `Tone::cases()`.
- AC-5 / M6g: File content scan across `app/Filament/Blocks/`, `app/Filament/Admin/Resources/ContentBlockResource/Schemas/`, and `app/Filament/Admin/Resources/ContentBlockResource/Pages/` asserting zero occurrences of `RichEditor`, `MarkdownEditor`, `CodeEditor`, `KeyValue`, `->url()`, `DeleteAction`, `ForceDeleteAction`, `RestoreAction` (m2: no code comments trip this).

#### 3. `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` (Commit 3)
- AC-7: Valid update invokes Action; activity logged with `changed = ['payload']`.
- M6e / M2 (Symmetric Timezone Hydration & No-op Save):
  - With `admin.timezone = 'Asia/Colombo'`:
    - Given block with `starts_at = 2026-10-15 03:30:00 UTC` and `ends_at = 2026-10-15 05:30:00 UTC`.
    - Mount Livewire: assert `assertFormSet(['starts_at' => '2026-10-15 09:00:00', 'ends_at' => '2026-10-15 11:00:00'])` (Colombo wall time).
    - Call `save()`: assert no activity log entry written, and database stored UTC values remain strictly `03:30:00` and `05:30:00`.
  - With `admin.timezone = 'Asia/Colombo'`:
    - Edit test that changes only `ends_at` to `'2026-10-15 12:00:00'` (Colombo wall time).
    - Call `save()`: assert stored UTC in database is `'2026-10-15 06:30:00'` UTC (12:00 - 5:30 = 06:30 UTC).
- M6e: No-op save writes no activity log for block without schedule.
- M6d: Edit hydration: `assertFormSet` asserts `payload.tone` equals `'info'` (string value).
- AC-8 / M5: Factory hydration failure tests:
  - `ContentBlock::factory()->unknownType()->create()` -> notification sent, redirect to index.
  - `ContentBlock::factory()->unsupportedVersion()->create()` -> notification sent, redirect to index.
  - `ContentBlock::factory()->invalidAfterUpgrade()->create()` -> notification sent, redirect to index.
  - `ContentBlock::factory()->failedUpgrade()->create()` -> notification sent, redirect to index.
- M2a (Livewire Hydration Re-check): Mount Livewire, archive block via `app(ArchiveContentBlock::class)($block->id)`, call `save()`, assert 403 Forbidden received (via `hydrate()` calling `authorizeAccess()`).
- M2b: Action exception stubs: bind stub for `UpdateContentBlockAction` in container throwing `IllegalStateTransitionException`, call `save()`, assert danger notification, redirect to index, block unmodified. Dataset test for `UnknownBlockTypeException` and `UnsupportedSchemaVersionException`.
- AC-12: Non-admin gets 403 on edit URL. Direct GET on archived block edit URL returns 403.
- M6a: Archived row in table has no Edit action (`assertTableActionHidden('edit', $archivedBlock)`).
- AC-13: Published block shows live-change warning Callout (`Callout::make('Published block')`); draft block does not.
- AC-14 / m4: Page actions and routes assertion:
  - Assert `expect(Livewire::test(EditContentBlock::class, ['record' => $block->public_id])->instance()->getCachedHeaderActions())->toBeEmpty();`.
  - Assert `expect(ContentBlockResource::getPages())->toHaveKeys(['index', 'edit']);`.
- AC-4: Edit form has no fields named `status`, `is_enabled`, `sort_order`, `schema_version`, or field named `type`.
- M3c: Placement fallback: displays raw stored string if TolerantEnum is null.
- M6c: Route test: `getUrl('edit')` contains `public_id`; direct GET by raw numeric ID returns 404.
- Smoke test: calls `assertListPageRenders` and `assertEditPageRenders`.

#### 4. `tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php` (Commit 4)
- AC-6: Valid create submission invokes Action, creates draft, sets causer, logs activity.
- M3 (Trait Re-key Helper Test):
  - Dedicated test calling `prepareCreateData(['placement' => 'invalid_placement'])` through an anonymous consumer class using the trait, asserting `ValidationException` thrown with error key `data.placement`.
- AC-9 (Action Path Validation Remapping Tests - labeled explicitly):
  - `[Action path] Text containing HTML tags is rejected with data.payload.text`: submit `<b>hello</b>`, assert `data.payload.text` has validation error.
  - `[Action path] Disallowed link scheme is rejected with data.payload.link_url`: submit `javascript:alert(1)`, assert `data.payload.link_url` has validation error.
  - `[Action path] Schedule ends_at before starts_at is rejected with data.ends_at`: submit inverted schedule, assert `data.ends_at` has validation error.
- M6b: Whitespace-only text (`'   '`) rejected with validation error on `data.payload.text`.
- AC-10: Accepts `/shop` and `https://example.com`; empty string becomes null.
- AC-11: Timezone test: with `admin.timezone = 'Asia/Colombo'`, submit `09:00:00`. Asserts database stores `03:30:00 UTC`.
- AC-12: Non-admin gets 403 on create URL.
- AC-14 / m4: Resource routes assertion:
  - Assert `expect(ContentBlockResource::getPages())->toHaveKeys(['index', 'create', 'edit']);`.
- D-f: `placement` and `type` inputs present on create.
- D-g / M6c: Redirects to `/admin/content-blocks/{public_id}/edit` after create.
- M3b: Test on Create: select placement and type, assert fields `payload.text`, `payload.link_url`, `payload.tone` exist on form.
- M6f: Tamper test: submit undeclared payload key through Livewire, assert it never reaches database.
- Smoke test: calls `assertCreatePageRenders`.

#### 5. `tests/Support/FilamentSmokeTest.php` (Commit 3 - m3)
- Final implementation for `assertEditPageRenders`:
  ```php
  protected function assertEditPageRenders(string $pageClass, Model $record): void
  {
      $this->actingAs($this->getPlatformAdmin())
          ->get($pageClass::getUrl(['record' => $record]))
          ->assertSuccessful();

      /** @var class-string<\Filament\Resources\Resource> $resource */
      $resource = $pageClass::getResource();
      $routeKeyName = $resource::getRecordRouteKeyName() ?? $record->getRouteKeyName();

      Livewire::test($pageClass, ['record' => $record->getAttribute($routeKeyName)])
          ->assertSuccessful();
  }
  ```

---

## 12. Commit Order & Checkpoints

### Checkpoint A (CP-A): Domain Updates, Form Registry, AnnouncementBarForm, Policy
1. **Commit 1**: `feat(content): add announcement bar text max length and disallow editing archived blocks in policy`
   - Files:
     - `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php`
     - `app/Domain/Content/Policies/ContentBlockPolicy.php`
     - `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`
2. **Commit 2**: `feat(filament): add block form contract, registry and announcement bar form schema`
   - Files:
     - `app/Filament/Blocks/BlockForm.php`
     - `app/Filament/Blocks/AnnouncementBarForm.php`
     - `app/Filament/Blocks/BlockFormRegistry.php`
     - `tests/Feature/Content/BlockFormRegistryTest.php`
3. **CP-A Verification**:
   - Run `composer check` and capture raw unedited output.
   - Run `git diff main...HEAD --stat -- app/Domain` (verifies only `AnnouncementBarData.php` and `ContentBlockPolicy.php` modified).
   - Produce CP-A mini report.

### Checkpoint B (CP-B): Edit Side, Create Side, Full Tests
4. **Commit 3 (Edit Side - Shared & Edit Only)**: `feat(filament): add content block edit page, table action and edit tests`
   - Files:
     - `app/Filament/Admin/Resources/ContentBlockResource.php` (sets `public_id` route key, `form()` wiring, routes: `index`, `edit`)
     - `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` (shared payload group, schedule pickers, edit TextEntry & Callout - m1)
     - `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php` (update preparation, date parsing, validation remapping - m1)
     - `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php` (edit page calling UpdateContentBlock and GetUpgradedBlockPayload)
     - `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` (row EditAction via recordActions)
     - `tests/Support/FilamentSmokeTest.php` (smoke helper route key lookup update - m3)
     - `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` (edit feature & smoke tests, AC-14 route assertion - m4)
5. **Commit 4 (Create Side)**: `feat(filament): add content block create page, header action and create tests`
   - Files:
     - `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` (adds create-only placement and type selects - m1)
     - `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php` (adds `prepareCreateData` - m1)
     - `app/Filament/Admin/Resources/ContentBlockResource.php` (adds `'create'` route mapping)
     - `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php` (adds header `CreateAction`)
     - `app/Filament/Admin/Resources/ContentBlockResource/Pages/CreateContentBlock.php` (create page calling CreateContentBlock)
     - `tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php` (create feature tests, trait re-key test, AC-14 route assertion - m4)
6. **CP-B Verification**:
   - Run all Layer A checks:
     - `composer check` (Pint, PHPStan, Pest).
     - `php artisan route:list --path=admin/content-blocks`.
     - `git diff main...HEAD --stat -- database composer.json composer.lock`.
     - `git diff main...HEAD --stat -- app/Domain`.
     - Component scan for forbidden strings (`DeleteAction`, `->url()`, rich editors).
   - Produce full IMPLEMENTATION REPORT with unedited command output.
