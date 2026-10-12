# Implementation Plan: P1-UI3 (v4.1)

**Step ID**: P1-UI3  
**Version**: v4.1  
**Branch**: `feat/ui-block-form-registry`  
**Goal**: Add `BlockFormRegistry` and `AnnouncementBarForm` (`app/Filament/Blocks`), then `CreateContentBlock` and `EditContentBlock` pages for `ContentBlockResource` calling existing Actions (`CreateContentBlock`, `UpdateContentBlock`). Form state for block fields is nested under `payload.*`.

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

## 1. Context & Scope

### Active Scope
- Active phase: Phase 1 (Layout CMS).
- Scope boundaries: Filament admin panel forms for content blocks. View page, Publish/Archive/Enable/Disable controls (UI4), menus, API changes, and other block types remain strictly out of scope.
- In-domain changes: Limited strictly to adding `public const int TEXT_MAX_LENGTH = 120;` to `AnnouncementBarData` (D-c, AC-15) and updating `ContentBlockPolicy::update` to disallow archived blocks (AC-12, AC-15). No Actions are modified.

---

## 2. Sources Read

| Source File | What in it constrains P1-UI3 |
|---|---|
| `ARCHITECTURE.md` | Sections 5, 6, 8, 12 (D7, D9): Single panel `admin`, block rules, immediate live updates for published content (D7), pessimistic locking on state transitions (D9). |
| `AGENTS.md` | Rule register, no direct model writes in Filament, strict types, final classes, Action delegation. |
| `docs/blueprints/rule-register.md` | Canonical rule register (`E1, S1..S7, C1..C8, K1..K9, DB1..DB6, SEC1..SEC5, T1..T5, G1..G4, X1..X7`). |
| `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php` | Defines `text`, `link_url`, `tone`. Needs `public const int TEXT_MAX_LENGTH = 120;` as single source of truth for max length. |
| `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php` | Definition for type `announcement_bar`, schema version 1, data class mapping. |
| `app/Domain/Content/Contracts/BlockDefinition.php` | Contract defining `typeKey()`, `schemaVersion()`, `dataClass()`, `upgradePayload()`. |
| `app/Domain/Content/Contracts/BlockRegistry.php` | Contract defining `get(string)` and `all()`. |
| `app/Domain/Content/Services/ContentBlockRegistry.php` | Production singleton registry. |
| `app/Domain/Content/Actions/CreateContentBlock.php` | Takes `CreateContentBlockData`, `array $rawPayload`, `?User $causer`. Throws `UnknownBlockTypeException`, `ValidationException`. |
| `app/Domain/Content/Actions/UpdateContentBlock.php` | Takes `int $id`, `UpdateContentBlockData`, `array $rawPayload`, `?User $causer`. Throws `IllegalStateTransitionException`, `UnknownBlockTypeException`, `UnsupportedSchemaVersionException`, `ValidationException`. |
| `app/Domain/Content/Actions/GetUpgradedBlockPayload.php` | Hydration resolver. Throws `UnknownBlockTypeException`, `UnsupportedSchemaVersionException`, `UpgradeFailedException`, `InvalidPayloadException`. |
| `app/Domain/Content/Data/CreateContentBlockData.php` | DTO requiring `Placement`, `string $type`, `?CarbonInterface $starts_at`, `?CarbonInterface $ends_at`. |
| `app/Domain/Content/Data/UpdateContentBlockData.php` | DTO requiring `?CarbonInterface $starts_at`, `?CarbonInterface $ends_at`. |
| `app/Domain/Content/Models/ContentBlock.php` | Model with `public_id` (ULID), casts, scopes, and `isLive()` helper. |
| `app/Domain/Content/Policies/ContentBlockPolicy.php` | Policy requiring update to return `false` for archived blocks (AC-12). |
| `app/Domain/Content/Enums/Placement.php` | Enum defining allowed block types for each placement slot. |
| `app/Domain/Content/Enums/Tone.php` | Domain enum with cases `INFO`, `WARNING`, `SUCCESS`. |
| `app/Filament/Admin/Resources/ContentBlockResource.php` | Resource needing route key name `public_id`, `form()` wiring, and pages mapping. |
| `app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php` | List page needing header `CreateAction`. |
| `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` | Table needing row `EditAction` via `recordActions()`. |
| `app/Filament/Admin/Schemas/StorefrontSettingsForm.php` | Reference schema pattern using `Filament\Schemas\Components\Component`. |
| `app/Filament/Admin/Pages/ManageStorefrontSettings.php` | Reference page pattern calling an Action and remapping state. |
| `app/Filament/Admin/Services/AdminTimezoneResolver.php` | Timezone resolver service used for labels and pickers. |
| `app/Support/Rules/SafeLink.php` | Validation rule enforcing relative path `/` or `https://`. |
| `tests/Arch/FilamentWritesTest.php` | Architecture test enforcing zero direct model writes in `app/Filament`. |
| `tests/Support/FilamentSmokeTest.php` | Helper trait needing `public_id` route key resolution in `assertEditPageRenders()`. |
| `tests/Support/TestBlocks.php` | Test helper registering `test_failed_upgrade_block`. |
| `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php` | Existing table feature tests. |
| `database/factories/ContentBlockFactory.php` | Contains factory states: `unknownType()`, `unsupportedVersion()`, `invalidAfterUpgrade()`, `failedUpgrade()`. |

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

### 3.10 Baseline Test Output
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

## 5. Architecture & Component Design

All new PHP files declare `declare(strict_types=1);` and all classes are `final` (C1, C2).

### 5.1 Domain Layer Modifications
1. **`app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php`**:
   - Add `public const int TEXT_MAX_LENGTH = 120;`.
   - Update attribute: `#[Min(1), Max(self::TEXT_MAX_LENGTH), Rule(new NoHtml)] public string $text`.
2. **`app/Domain/Content/Policies/ContentBlockPolicy.php`**:
   - Update `update(User $user, mixed $model): bool`:
     ```php
     if ($model instanceof ContentBlock && $model->status === PublishStatus::ARCHIVED) {
         return false;
     }

     return $user->isAdmin();
     ```

### 5.2 Block Forms & Form Registry
1. **`app/Filament/Blocks/BlockForm.php`**:
   ```php
   namespace App\Filament\Blocks;

   use Filament\Schemas\Components\Component;

   interface BlockForm
   {
       /**
        * @return array<int, Component>
        */
       public static function schema(): array;
   }
   ```
2. **`app/Filament/Blocks/AnnouncementBarForm.php`**:
   - Implements `BlockForm`.
   - Components:
     - `TextInput::make('text')->required()->trim()->maxLength(AnnouncementBarData::TEXT_MAX_LENGTH)` (m1: relies on domain `NoHtml` rule alone).
     - `TextInput::make('link_url')->nullable()->trim()` (plain text input, no `->url()` call per AC-3, AC-5).
     - `Select::make('tone')->options(collect(Tone::cases())->mapWithKeys(fn (Tone $t) => [$t->value => ucfirst($t->value)])->all())->required()->default(Tone::INFO->value)` (D-e).
3. **`app/Filament/Blocks/BlockFormRegistry.php`**:
   ```php
   final class BlockFormRegistry
   {
       /**
        * @var array<string, class-string<BlockForm>>
        */
       private const array FORMS = [
           'announcement_bar' => AnnouncementBarForm::class,
       ];

       /**
        * @return array<int, string>
        */
       public static function types(): array
       {
           return array_keys(self::FORMS);
       }

       public static function has(string $type): bool
       {
           return isset(self::FORMS[$type]);
       }

       /**
        * @return array<int, Component>
        */
       public static function get(string $type): array
       {
           if (! self::has($type)) {
               throw new UnknownBlockTypeException("Block type [{$type}] has no form registered.");
           }

           return self::FORMS[$type]::schema();
       }
   }
   ```

### 5.3 Resource Schema: `ContentBlockForm`
**File**: `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php`

#### Commit 3 State (Shared & Edit Components Only - m1)
- `public static function form(Schema $schema): Schema`:
  - Resolved timezone: `$timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));`
  - **Edit Fields** (`visibleOn('edit')`):
    - `TextEntry::make('placement_display')->label('Placement')->state(function (?ContentBlock $record): string { if ($record === null) { return ''; } return $record->placement?->value ?? (string) ($record->getRawOriginal('placement') ?? ''); })->visibleOn('edit')` (M4: supported replacement for deprecated Placeholder; M3c: explicit null-safe fallback).
    - `TextEntry::make('type_display')->label('Type')->state(function (?ContentBlock $record): string { if ($record === null) { return ''; } return Str::headline($record->type); })->visibleOn('edit')` (M4, M5).
    - `Callout::make('Published block')->description('Changes made to this block will go live immediately on the storefront.')->warning()->visible(fn (?ContentBlock $record): bool => $record?->status === PublishStatus::PUBLISHED)->visibleOn('edit')` (M1: constructor heading, warning status, typed record closure).
  - **Dynamic Payload Group**:
    - Sourcing `$type`:
      Direct single lookup `$get('type')` (B1, M3b).
      ```php
      Group::make()
          ->statePath('payload')
          ->schema(function (Get $get, ?ContentBlock $record, string $operation): array {
              $type = $operation === 'create' ? $get('type') : $record?->type;
              if (blank($type) || ! BlockFormRegistry::has((string) $type)) {
                  return [];
              }

              return BlockFormRegistry::get((string) $type);
          })
      ```
  - **Schedule Pickers**:
    - `DateTimePicker::make('starts_at')->label("Starts at ({$timezone})")->timezone($timezone)->nullable()` (AC-11).
    - `DateTimePicker::make('ends_at')->label("Ends at ({$timezone})")->timezone($timezone)->nullable()` (AC-11).

#### Commit 4 Additions (Create-Only Selects - m1)
In Commit 4, the following components are prepended to the schema:
- `Select::make('placement')`:
  - Options (M5): `collect(Placement::cases())->mapWithKeys(fn (Placement $p) => [$p->value => Str::headline($p->value)])->all()`
  - `required()->live()->visibleOn('create')`
  - `afterStateUpdated(function (Set $set): void { $set('type', null); $set('payload', []); })`
- `Select::make('type')`:
  - Options closure (M3a, M5):
    ```php
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
        ->visibleOn('create')
        ->afterStateUpdated(function (Set $set): void {
            $set('payload', []);
        })
    ```

### 5.4 Concerns Trait: `InteractsWithContentBlockActions`
**File**: `app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php` (M2, M3, m1, m3):

#### Commit 3 State (Update & Formatting Helpers Only - m1)
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

#### Commit 4 Additions (`prepareCreateData` Added - m1, M3)
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

### 5.5 Resource Wiring & Pages
1. **`ContentBlockResource`**:
   - `protected static ?string $recordRouteKeyName = 'public_id';` (M2, DB5).
   - `public static function form(Schema $schema): Schema { return ContentBlockForm::form($schema); }`
   - In Commit 3: maps `'index'` and `'edit'`.
   - In Commit 4: maps `'index'`, `'create'`, and `'edit'`.
2. **`EditContentBlock` Page** (Commit 3):
   - Extends `Filament\Resources\Pages\EditRecord`.
   - Uses `InteractsWithContentBlockActions`.
   - Aliases action: `use App\Domain\Content\Actions\UpdateContentBlock as UpdateContentBlockAction;`.
   - Overrides `getHeaderActions(): array { return []; }` (m2: no comments mentioning forbidden action names).
   - Overrides `mutateFormDataBeforeFill(array $data): array` (M4, AC-8, m1):
     ```php
     /** @var ContentBlock $record */
     $record = $this->getRecord();

     try {
         $upgraded = app(GetUpgradedBlockPayload::class)($record->type, $record->schema_version, $record->payload);
         $data['payload'] = $upgraded->toArray();
         return $data;
     } catch (UnknownBlockTypeException $e) {
         $msg = 'The block type is not recognized.';
     } catch (UnsupportedSchemaVersionException $e) {
         $msg = 'The block schema version is not supported.';
     } catch (UpgradeFailedException $e) {
         $msg = 'The block payload could not be upgraded.';
     } catch (InvalidPayloadException $e) {
         $msg = 'The block payload is invalid.';
     }

     Log::warning("Content block [{$record->public_id}] failed hydration: " . get_class($e));
     Notification::make()->danger()->title('Cannot edit content block')->body($msg)->send();
     $this->redirect(static::getResource()::getUrl('index'));
     $data['payload'] = [];
     return $data;
     ```
   - Overrides `handleRecordUpdate(Model $record, array $data): Model` (M1, B2, m6):
     ```php
     [$actionData, $rawPayload] = $this->prepareUpdateData($data);
     $causer = Filament::auth()->user();

     try {
         return app(UpdateContentBlockAction::class)((int) $record->id, $actionData, $rawPayload, $causer);
     } catch (ValidationException $e) {
         $this->throwRemappedValidationException($e);
     } catch (IllegalStateTransitionException $e) {
         Notification::make()->danger()->title('Cannot Update Block')->body('This block has been archived and cannot be edited.')->send();
         $this->redirect(static::getResource()::getUrl('index'));
         throw new Halt;
     } catch (UnknownBlockTypeException | UnsupportedSchemaVersionException $e) {
         Notification::make()->danger()->title('Update Failed')->body('The block cannot be updated due to a schema mismatch.')->send();
         $this->redirect(static::getResource()::getUrl('index'));
         throw new Halt;
     }
     ```
3. **`CreateContentBlock` Page** (Commit 4):
   - Extends `Filament\Resources\Pages\CreateRecord`.
   - Uses `InteractsWithContentBlockActions`.
   - Aliases action: `use App\Domain\Content\Actions\CreateContentBlock as CreateContentBlockAction;`.
   - Overrides `handleRecordCreation(array $data): Model`:
     ```php
     [$actionData, $rawPayload] = $this->prepareCreateData($data);
     $causer = Filament::auth()->user();

     try {
         return app(CreateContentBlockAction::class)($actionData, $rawPayload, $causer);
     } catch (ValidationException $e) {
         $this->throwRemappedValidationException($e);
     } catch (UnknownBlockTypeException $e) {
         Notification::make()->danger()->title('Invalid Block Type')->body('The selected block type is not registered.')->send();
         throw new Halt;
     }
     ```
   - Overrides `getRedirectUrl(): string`: returns `static::getResource()::getUrl('edit', ['record' => $this->getRecord()])` (D-g).
4. **`ListContentBlocks` Page**:
   - Commit 3: `getHeaderActions(): array { return []; }`.
   - Commit 4: adds `CreateAction::make()`.
5. **`ContentBlockTable`**:
   - `->recordActions([EditAction::make()])` (Commit 3).

---

## 6. Data and Migrations

**None**. No schema changes, no migrations, no seed modifications.

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

## 8. Rule Compliance Matrix

| Rule ID | Verbatim Requirement from Register (`docs/blueprints/rule-register.md`) | App? | Compliance Implementation in P1-UI3 |
|---|---|---|---|
| **E1** | Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`. | Y | All CLI executions run one at a time using PowerShell syntax. |
| **S1** | Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too. | Y | No business logic added to Filament. Form handlers invoke existing `CreateContentBlock` and `UpdateContentBlock` Actions. |
| **S2** | Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output. | Y | Pages prepare DTOs and raw payloads, call Actions, and handle presentation/notifications. Zero model update logic in pages. |
| **S3** | `app/Domain` must never import from `App\Filament` or `App\Http`. | Y | `AnnouncementBarData` and `ContentBlockPolicy` contain zero imports from `App\Filament` or `App\Http`. Verified by `ArchTest.php`. |
| **S4** | Filament never calls `Model::create/update/delete` directly. It calls Actions. | Y | Overridden `handleRecordCreation()` and `handleRecordUpdate()` delegate directly to Domain Actions. Verified by `FilamentWritesTest.php`. |
| **S5** | Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`. | Y | `AnnouncementBarForm.php` and `BlockFormRegistry.php` are placed strictly in `app/Filament/Blocks/`. |
| **S6** | Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths. | Y | Resource, schemas, and pages are placed strictly under `app/Filament/Admin/Resources/ContentBlockResource/`. |
| **S7** | Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder. | Y | No new top-level folders created. Reusable Action interaction logic placed in `Pages/Concerns/InteractsWithContentBlockActions.php`. |
| **C1** | `declare(strict_types=1);` in every PHP file. | Y | Present as the first statement in every new and modified file. |
| **C2** | Type every parameter, return value and property. Prefer `final` classes. | Y | All methods and properties have explicit strict types. All new classes are `final`. |
| **C3** | Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers. | Y | Uses `Placement`, `Tone`, `PublishStatus`, `CreateContentBlockData`, and `UpdateContentBlockData`. |
| **C4** | Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field. | Y | Neither `status` nor `is_enabled` is present in `ContentBlockForm`. Create hardcodes status draft and is_enabled true in Action; update preserves them. |
| **C5** | The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action. | Y | Persisted state modifications for content blocks delegate exclusively to `CreateContentBlock` and `UpdateContentBlock`. |
| **C6** | Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API. | Y | Model authorization delegates to `ContentBlockPolicy`. Payload validation delegates to `AnnouncementBarData` via the Action. |
| **C7** | Follow Pint and Larastan. Do not add baseline entries without approval. | Y | Code is strictly typed; `BlockFormRegistry` uses typed array `array<string, class-string<BlockForm>>`. Zero new baseline entries. |
| **C8** | Comments explain why, not what. No commented-out code. | Y | All code clean and documented focusing on non-obvious rationale (e.g. why DateTimeStateCast dehydrates into app timezone). |
| **K1** | Block type keys are permanent. Never rename one. | Y | Uses permanent key `announcement_bar`. |
| **K2** | Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load. | Y | Edit page hydrates through `GetUpgradedBlockPayload` to sequentially upgrade payloads before form fill. |
| **K3** | No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans. | Y | `AnnouncementBarForm` uses plain `TextInput` and `Select`. No rich editor, markdown, code, or raw HTML fields. |
| **K4** | Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme. | Y | Link input is validated by Domain `SafeLink` rule inside `AnnouncementBarData` (rejects `javascript:`, `http://`, etc.). |
| **K5** | Placements, menu keys and block types are defined in code. Admins never create them. | Y | Placement and block type choices are fixed code enums and registry definitions. |
| **K6** | Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery. | Y | `BlockFormRegistry` uses explicit static map `FORMS`. No auto-discovery. |
| **K7** | Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content. | N/A | API layer is untouched in this step. |
| **K8** | The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change. | N/A | API contract is untouched in this step. |
| **K9** | Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps. | Y | Form schema created in `app/Filament/Blocks/AnnouncementBarForm.php` completing step 3 of checklist. |
| **DB1** | PostgreSQL only. Tests run against Postgres, never SQLite. | Y | All Pest tests run against PostgreSQL test database (`phone_store_test`). |
| **DB2** | Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration. | N/A | No migrations created or modified. |
| **DB3** | No destructive changes (drop or rename column or table) in the same release that stops using them. | N/A | No database modifications. |
| **DB4** | Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers. | N/A | No database modifications. |
| **DB5** | Expose `public_id` (ULID) externally, never raw incremental IDs. | Y | `ContentBlockResource::$recordRouteKeyName = 'public_id'`. Edit URLs use ULID. Raw ID returns 404. |
| **DB6** | Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases. | N/A | No seeders modified. |
| **SEC1** | Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`. | Y | No secrets or environment modifications. |
| **SEC2** | Do not disable authorization, policies, strict types or static analysis rules to make code pass. | Y | Full authorization enforced; zero rules suppressed. |
| **SEC3** | Do not log sensitive data. | Y | Hydration failures log error message with `public_id` and exception class without dumping raw block payload. |
| **SEC4** | Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization. | Y | `canAccessPanel` is untouched in provider; `ContentBlockPolicy` is the extra server-side authorization layer for model actions. |
| **SEC5** | Write sensitive content changes (publish, disable, menu and settings edits) to the activity log. | Y | Create and Edit Actions write activity log entries for 'created' and 'updated'. No log written on no-op save. |
| **T1** | Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`. | Y | Form registry tested in Feature test; Create and Edit tested with full Livewire interactions. |
| **T2** | Every Filament resource has a smoke test that loads list, create and edit pages. | Y | `FilamentSmokeTest` updated and asserted for list, create, and edit pages of `ContentBlockResource`. |
| **T3** | Every block type has tests for payload validation, API output and visibility rules. | Partial | Payload validation tested. API output and visibility rules are N/A in UI3. |
| **T4** | Use factories, not hand-built rows. Tests must be independent and deterministic. | Y | Tests use `ContentBlock::factory()` with existing states (`draft`, `published`, `archived`, `unknownType`, `unsupportedVersion`, `invalidAfterUpgrade`, `failedUpgrade`). |
| **T5** | Do not lower coverage, skip tests or mark them incomplete to pass checks. | Y | No tests skipped or marked incomplete. (Existing skip in `FilamentWritesTest` is known baseline hit). |
| **G1** | Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`. | Y | Working on branch `feat/ui-block-form-registry`. |
| **G2** | Conventional Commits: `feat(content): add announcement bar block`. | Y | All 4 planned commits follow Conventional Commits specification. |
| **G3** | Never push to `main` directly or bypass git hooks. | Y | Working strictly on feature branch. |
| **G4** | PR description: what changed, why, how it was tested, and risks (migrations, API contract, security). | Y | Standard PR documentation followed. |
| **X1** | Add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval. | Y | Zero new dependencies added. |
| **X2** | Introduce Docker, Sail, docker-compose or any container configuration. | Y | Native Windows environment strictly preserved. |
| **X3** | Add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments. | Y | No deployment pipelines introduced. |
| **X4** | Nothing outside the active phase was added. Do not create folders, contexts or tables for later phases. | Y | Only Phase 1 content management artifacts touched. Zero scaffolding for later phases. |
| **X5** | Modify the storefront repository or assume its internals. The API contract is the only interface. | Y | Storefront repository is untouched. |
| **X6** | Definition of done: `composer check` passes and the output was read. | Y | `composer check` output will be run and read at each checkpoint. |
| **X7** | Definition of done: `ARCHITECTURE.md`, `docs/contracts/content-api.md`, `.env.example` updated where relevant. | Y | `ARCHITECTURE.md` progress updated upon step completion. |

---

## 9. Validation, Error Handling & Re-keying

### Error Re-keying Protocol (AC-9, m2)
Action `ValidationException` error keys: `payload.text`, `payload.link_url`, `payload.tone`, `ends_at`, `type`, `placement`.  
Filament state path: `data`.  
`InteractsWithContentBlockActions::throwRemappedValidationException()`:
- `payload.text` -> `data.payload.text`
- `payload.link_url` -> `data.payload.link_url`
- `payload.tone` -> `data.payload.tone`
- `ends_at` -> `data.ends_at`
- `type` -> `data.type`
- `placement` -> `data.placement`

### Action Exception Matrix (B2, M2, m1, m6)
| Action / Method | Exception Thrown | Page Handler | User-Facing Result & Internal Action |
|---|---|---|---|
| `CreateContentBlock` | `ValidationException` | `throwRemappedValidationException` | Form field error messages displayed on input components. |
| `CreateContentBlock` | `UnknownBlockTypeException` | Catch in `handleRecordCreation` | Danger notification ("The selected block type is not registered"), throws `Halt`. |
| `GetUpgradedBlockPayload` (Edit hydration) | `UnknownBlockTypeException` | Catch in `mutateFormDataBeforeFill` | Danger notification ("The block type is not recognized"), logged without payload (SEC3), redirected to index. |
| `GetUpgradedBlockPayload` (Edit hydration) | `UnsupportedSchemaVersionException` | Catch in `mutateFormDataBeforeFill` | Danger notification ("The block schema version is not supported"), logged without payload (SEC3), redirected to index. |
| `GetUpgradedBlockPayload` (Edit hydration) | `UpgradeFailedException` | Catch in `mutateFormDataBeforeFill` | Danger notification ("The block payload could not be upgraded"), logged without payload (SEC3), redirected to index. |
| `GetUpgradedBlockPayload` (Edit hydration) | `InvalidPayloadException` | Catch in `mutateFormDataBeforeFill` | Danger notification ("The block payload is invalid"), logged without payload (SEC3), redirected to index. |
| `UpdateContentBlock` | `ValidationException` | `throwRemappedValidationException` | Form field error messages displayed on input components. |
| `UpdateContentBlock` | `IllegalStateTransitionException` | Catch in `handleRecordUpdate` | Action's internal DB transaction rolls back on exception. Page sends danger notification ("This block has been archived and cannot be edited"), redirects to index, halts Filament lifecycle via `Halt`. |
| `UpdateContentBlock` | `UnknownBlockTypeException` | Catch in `handleRecordUpdate` | Action rolls back. Page sends danger notification, redirects to index, throws `Halt`. |
| `UpdateContentBlock` | `UnsupportedSchemaVersionException` | Catch in `handleRecordUpdate` | Action rolls back. Page sends danger notification, redirects to index, throws `Halt`. |

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

## 11. Documentation Updates

- Update `ARCHITECTURE.md` section 1 progress:
  - Mark `- [x] UI Step 3 (BlockFormRegistry, Create/Edit forms)` as complete upon step acceptance.
- No other docs changes required in this step.

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

---

## 13. Risks, Assumptions & Conflicts

- **Risk**: Timezone offset mismatch during DateTimePicker dehydration.
  - *Mitigation*: Section 5.4 explicit parsing code using `config('app.timezone')` ('UTC') backed by vendor citation `DateTimeStateCast:L51-L56` and non-UTC timezone test.
- **Risk**: Form state pollution when switching block types.
  - *Mitigation*: Section 5.3 explicit state reset (`$set('payload', [])` on type change).
- **Risk**: Race condition between reading and saving an archived block.
  - *Mitigation*: Section 5.5 / 7 / 9 handled both via `authorizeAccess()` 403 on re-hydrated model, and caught `IllegalStateTransitionException` in `handleRecordUpdate`, notifying user and halting.
- **Assumptions**: Native Windows environment, PostgreSQL native database.

---

## 14. Dependencies

From `composer.json`:
- `filament/filament: ^5.0`
- `filament/spatie-laravel-settings-plugin: ^5.10`
- `spatie/laravel-activitylog: ^4.12`
- `spatie/laravel-data: ^4.23`
- `spatie/laravel-settings: ^3.9`
- `pestphp/pest: ^4.7`
- Zero new composer packages or npm packages.
