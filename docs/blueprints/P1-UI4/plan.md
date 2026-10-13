# Implementation Plan: P1-UI4 (v3)

**Step ID**: P1-UI4  
**Version**: v3  
**Branch**: `feat/ui-content-block-view-and-transitions`  
**Goal**: Provide administrators with a read-only View page for content blocks and four lifecycle transitions (Publish, Archive, Enable, Disable) implemented as reusable Filament actions available on both the View page header and the List table rows. Every transition delegates to an existing domain Action, enforces authorization through `ContentBlockPolicy`, requires confirmation modals for public-facing or irreversible changes, handles domain exception rejections with fixed user notifications without exposing internal exception details, and records activity log entries containing the causer.

---

## Change Log (Review Feedback Mapping: v2 -> v3)

| Finding ID | Reviewer Issue | Resolution in Plan v3 | Section Changed |
|---|---|---|---|
| **M1** | AC-13, MUT-3 lack vendor call/mount evidence; 4 no-op tests contradict hidden rules | Quoted vendor `InteractsWithActions::mountAction/callMountedAction` and `TestsActions::callAction/assertActionVisible` in Section 3.6. Replaced 4 no-op tests with single parametrized stub test asserting success copy, zero activity entry, zero 500 when domain returns unchanged record. Fixed modal race test to assert execution runs, catches `IllegalStateTransitionException`, sends danger notification, and halts. Sourced container stub binding via `app()->bind()`. | **Section 3.6, Section 7.4, Section 9, Section 10.4** |
| **M2** | Domain Action behavior, locking, early returns, and activity log properties unquoted | Quoted verbatim `PublishContentBlock`, `ArchiveContentBlock`, `EnableContentBlock`, and `DisableContentBlock` transactions, `lockForUpdate()`, early returns, and `activity()` calls with exact property arrays and line numbers in Section 3.7. Aligned Section 7 and Section 10 tests strictly to domain reality. | **Section 3.7, Section 7.4, Section 7.5, Section 7.6, Section 9, Section 10.4** |
| **M3** | Mutable static `$statusCache` leaks state between tests and blocks re-render assertion | Removed static `$statusCache`. Restructured `TextEntry::make('serving_status')` to evaluate `GetBlockServingStatus` once via `state()`, format reason via `formatStateUsing()`, and derive color via `color()`. Quoted `GetBlockServingStatus` and `ResolveServablePayload` proving `$log = false` prevents duplicate log lines. | **Section 3.8, Section 5.2** |
| **M4** | `Filament::auth()->user()?->can()` fails Larastan Level 8; unauthorized execution unverified | Replaced closure with ability string `->authorize('publish')` leveraging Filament's `CanBeAuthorized` trait which automatically evaluates `Gate::check($ability, [$record])`. Quoted vendor `CanBeAuthorized` and `CanBeHidden` showing unauthorized actions evaluate to `isHidden() = true` and abort execution. Added non-admin direct execution rejection test. | **Section 3.9, Section 5.3, Section 10.4** |
| **M5** | AC-10 unservable payload tests specified on View page header where broken payloads redirect | Clarified that AC-10 Publish and Enable rejection tests execute strictly on the table row action surface where broken payloads do not redirect. | **Section 9, Section 10.4** |
| **M6** | Edit schedule picker configuration unquoted; parity assertion style unverified | Quoted `ContentBlockForm` DateTimePicker lines and `ContentBlockEditTest` UI3 schedule assertion (`assertSet('data.starts_at', '2026-10-15 09:00:00')`). Specified parity assertion comparing Edit's hydrated state with View's rendered text entry. | **Section 3.10, Section 10.4** |
| **M7** | Missing vendor evidence for header action `$record` binding and table re-query | Quoted `InteractsWithRecord::getDefaultActionRecord()` and `ViewRecord` showing header actions receive `$this->record`. Quoted Livewire lifecycle and `HasRecords::getTableRecords()` showing fresh table queries on action completion. | **Section 3.11, Section 5.4** |
| **m1** | Non-User authentication in `executeTransition` causes null causer in activity log | Added strict narrowing: if `! $authUser instanceof User`, call `abort(403)`. | **Section 5.3** |
| **m2** | Visibility matrix dataset missing 6th row | Added `archived + is_enabled=true (tamper) -> []` row to Section 10.4 dataset. | **Section 10.4** |
| **m3** | No spy for Publish domain action in AC-9 | Replaced spy assertion with: assert exactly one activity row exists for that subject ID and `'published'` event. | **Section 9, Section 10.4** |
| **m4** | Commit 4 lists `ContentBlockViewTest.php` without naming tests | Dropped `ContentBlockViewTest.php` from Commit 4 (Commit 4 touches only `ContentBlockTransitionActions.php` and `ViewContentBlock.php`). | **Section 4, Section 12** |
| **m5** | "e.g." remains in Section 10.1 policy test line | Removed "e.g." and reworded to exact case. | **Section 10.1** |
| **Q1** | User confirmation status of open decisions | Recorded explicit confirmation from Achintha for Option B, OD-1, OD-2, OD-3, and OD-4. | **Section 13** |

---

## 1. Context & Scope

### Active Scope
- Active phase: Phase 1 (Layout CMS).
- Scope boundaries:
  - Add `ViewContentBlock` page to `ContentBlockResource`, mapped to route key `public_id`.
  - Display block metadata (public ID, placement, type, status, enabled state, serving status with structured failure reason, schedule in admin timezone, schema version, created by, updated by, created at, updated at).
  - Display block payload in read-only mode by reusing `BlockFormRegistry` component schemas within a disabled form container.
  - Create reusable transition actions (`Publish`, `Archive`, `Enable`, `Disable`) in `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php`.
  - Expose Edit, Publish, Enable, Disable, Archive header actions on `ViewContentBlock` gated by policy and state legality.
  - Expose View row action and grouped transition actions on `ContentBlockTable`. Set table row click to open the View page.
  - Add policy abilities `publish`, `archive`, `enable`, `disable` to `ContentBlockPolicy`.
  - Update route-set assertions in `ContentBlockCreateTest` and `ContentBlockEditTest` to include `view`.
  - Add `assertViewPageRenders` helper to `tests/Support/FilamentSmokeTest.php` and assert View page rendering.
  - Update `ARCHITECTURE.md` section 1 progress and sections 5 and 8 folder conventions at closeout.

### Out of Scope
- Modifications to `app/Domain` other than `ContentBlockPolicy`.
- Displaying activity log history on the View page.
- Modifying Create or Edit page header actions (Edit page retains zero header actions).
- New transitions, unarchive, delete, force delete, bulk operations, or block duplication.
- Database migrations, seed alterations, composer packages, or container configuration.

---

## 2. Sources Read

| Source File | What in it constrains P1-UI4 |
|---|---|
| [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) | Sections 1, 5, 6, 8, 12 (D6, D7, D8, D9): Single admin panel, Action delegation, pessimistic locks for state transitions (D9), platform admin authorization (D6). |
| [AGENTS.md](file:///c:/dev/getnow-cms/AGENTS.md) | Single concern, strict types, final classes, Action delegation, zero direct model writes in Filament, no leaked exception messages, definition of done. |
| [rule-register.md](file:///c:/dev/getnow-cms/docs/blueprints/rule-register.md) | Canonical rule register (`E1, S1..S7, C1..C8, K1..K9, DB1..DB6, SEC1..SEC5, T1..T5, G1..G4, X1..X7`). |
| [0002-content-block-serving-health.md](file:///c:/dev/getnow-cms/docs/adr/0002-content-block-serving-health.md) | Serving health outcomes (`Servable`, `UnknownType`, `UnsupportedVersion`, `InvalidAfterUpgrade`, `UpgradeFailed`), View page displays structured failure reason, Publish and Enable reject unservable payloads. |
| [0003-content-block-concurrency.md](file:///c:/dev/getnow-cms/docs/adr/0003-content-block-concurrency.md) | Pessimistic locking (`lockForUpdate()`) inside atomic transition Actions. |
| [cr-01-get-url-override.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-UI3/cr-01-get-url-override.md) | `ContentBlockResource::getUrl()` override extracting `public_id` from model instances. |
| [cr-02-smoke-helper.md](file:///c:/dev/getnow-cms/docs/blueprints/P1-UI3/cr-02-smoke-helper.md) | Dedicated TestCase smoke class pattern avoiding Pest closure `$this` static analysis failures. |
| [ContentBlockResource.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource.php) | Resource route definitions (`getPages()`), `getUrl()` override, route key configuration (`$recordRouteKeyName = 'public_id'`). |
| [ContentBlockTable.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php) | Table column definitions, status color helpers (`publishStatusColor`, `servingStatusColor`), current row actions. |
| [ContentBlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php) | Edit and Create form layout, admin timezone resolution pattern, dynamic payload Group. |
| [InteractsWithContentBlockActions.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/Concerns/InteractsWithContentBlockActions.php) | Date parsing helper `parseScheduleDatetime`, validation error re-keying helper. |
| [EditContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php) | Hydration error handling pattern in `mutateFormDataBeforeFill`, empty header actions array. |
| [CreateContentBlock.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/CreateContentBlock.php) | Create execution flow, Action error handling. |
| [ListContentBlocks.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Resources/ContentBlockResource/Pages/ListContentBlocks.php) | List page header actions. |
| [BlockForm.php](file:///c:/dev/getnow-cms/app/Filament/Blocks/BlockForm.php) | Block form interface returning schema components. |
| [BlockFormRegistry.php](file:///c:/dev/getnow-cms/app/Filament/Blocks/BlockFormRegistry.php) | Block form registry mapping block type strings to form classes. |
| [AnnouncementBarForm.php](file:///c:/dev/getnow-cms/app/Filament/Blocks/AnnouncementBarForm.php) | Form schema definition for `announcement_bar` payload fields (`text`, `link_url`, `tone`). |
| [ContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Models/ContentBlock.php) | Model columns (`id`, `public_id`, `placement`, `type`, `schema_version`, `payload`, `status`, `is_enabled`, `sort_order`, `starts_at`, `ends_at`, `created_by`, `updated_by`, timestamps), `isLive()` helper. |
| [ContentBlockPolicy.php](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/ContentBlockPolicy.php) | Existing authorization abilities (`viewAny`, `view`, `create`, `update`, `reorder`, `replicate`). |
| [BaseContentPolicy.php](file:///c:/dev/getnow-cms/app/Domain/Content/Policies/BaseContentPolicy.php) | Base policy defining default platform admin check. |
| [PublishContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/PublishContentBlock.php) | Invokable Action: `__invoke(int $id, ?User $causer = null): ContentBlock`. Throws `IllegalStateTransitionException` if archived, throws `UnservablePayloadException` if payload is not servable, logs `'published'` event. |
| [ArchiveContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/ArchiveContentBlock.php) | Invokable Action: `__invoke(int $id, ?User $causer = null): ContentBlock`. Sets status archived and `is_enabled` false, logs `'archived'` event. |
| [EnableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/EnableContentBlock.php) | Invokable Action: `__invoke(int $id, ?User $causer = null): ContentBlock`. Throws `IllegalStateTransitionException` if archived, throws `UnservablePayloadException` if not servable, logs `'enabled'` event. |
| [DisableContentBlock.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/DisableContentBlock.php) | Invokable Action: `__invoke(int $id, ?User $causer = null): ContentBlock`. Throws `IllegalStateTransitionException` if archived, logs `'disabled'` event. |
| [GetBlockServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetBlockServingStatus.php) | Action computing serving status cache result: `__invoke(ContentBlock $block): BlockServingStatusResult`. |
| [GetUpgradedBlockPayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/GetUpgradedBlockPayload.php) | Hydration resolver: `__invoke(string $type, int $schemaVersion, array $payload): BlockPayload`. Throws `UnknownBlockTypeException`, `UnsupportedSchemaVersionException`, `UpgradeFailedException`, `InvalidPayloadException`. |
| [BlockServingStatusResult.php](file:///c:/dev/getnow-cms/app/Domain/Content/Data/BlockServingStatusResult.php) | DTO containing `ServingStatus $status` and nullable `ServingHealthOutcome $healthOutcome`. |
| [PublishStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/PublishStatus.php) | Enum with cases `DRAFT`, `PUBLISHED`, `ARCHIVED`. |
| [ServingStatus.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/ServingStatus.php) | Enum with cases `Live`, `NotLive`, `PayloadError`. |
| [ServingHealthOutcome.php](file:///c:/dev/getnow-cms/app/Domain/Content/Enums/ServingHealthOutcome.php) | Enum with cases `Servable`, `UnknownType`, `UnsupportedVersion`, `InvalidAfterUpgrade`, `UpgradeFailed`. |
| [IllegalStateTransitionException.php](file:///c:/dev/getnow-cms/app/Domain/Content/Exceptions/IllegalStateTransitionException.php) | Exception thrown on invalid domain state transition. |
| [UnservablePayloadException.php](file:///c:/dev/getnow-cms/app/Domain/Content/Exceptions/UnservablePayloadException.php) | Exception thrown on publish or enable of unservable block payload. |
| [UnknownBlockTypeException.php](file:///c:/dev/getnow-cms/app/Domain/Content/Exceptions/UnknownBlockTypeException.php) | Exception thrown when block type is not registered. |
| [ResolveServablePayload.php](file:///c:/dev/getnow-cms/app/Domain/Content/Services/ResolveServablePayload.php) | Service evaluating block payload servability and logging failures. |
| [AdminTimezoneResolver.php](file:///c:/dev/getnow-cms/app/Filament/Admin/Services/AdminTimezoneResolver.php) | Timezone resolver handling fallback to UTC. |
| [FilamentSmokeTest.php](file:///c:/dev/getnow-cms/tests/Support/FilamentSmokeTest.php) | Trait containing `assertListPageRenders`, `assertPageRenders`, `assertCreatePageRenders`, `assertEditPageRenders`. |
| [ContentBlockPolicyTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php) | Feature tests for policy abilities. |
| [ContentBlockCreateTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php) | Create tests and route set assertion `expect(array_keys(ContentBlockResource::getPages()))->toEqualCanonicalizing(['index', 'create', 'edit'])`. |
| [ContentBlockEditTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php) | Edit tests and route set assertion `expect(ContentBlockResource::getPages())->toHaveKeys(['index', 'edit'])`. |
| [ListContentBlocksTableTest.php](file:///c:/dev/getnow-cms/tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php) | Table tests for list rendering, column states, badges, filters. |
| [BlockFormRegistryTest.php](file:///c:/dev/getnow-cms/tests/Feature/Content/BlockFormRegistryTest.php) | Form registry validation and component scan test for forbidden strings. |
| `vendor/filament/filament/src/Resources/Pages/ViewRecord.php` | Base class for View page: `fillFormWithDataAndCallHooks`, `defaultForm`, `mutateFormDataBeforeFill`, `authorizeAccess`. |
| `vendor/filament/filament/src/Resources/Pages/Page.php` | Resource page url generator mapping `ViewAction` to resource `view` route. |
| `vendor/filament/tables/src/Table/Concerns/HasRecordUrl.php` | Table record URL configuration handling row clicks. |
| `vendor/filament/actions/src/ViewAction.php` | Action class for viewing records. |

---

## 3. Verified Current State

### 3.1 Pinned Package Versions & Vendor Inspection
- Filament version: `v5.10.0`
- PHP version: `8.3.16`
- Sourced vendor file paths:
  ```
  vendor/filament/filament/src/Resources/Pages/ViewRecord.php
  vendor/filament/actions/src/Action.php
  vendor/filament/actions/src/ActionGroup.php
  vendor/filament/actions/src/ViewAction.php
  vendor/filament/actions/src/EditAction.php
  vendor/filament/schemas/src/Components/Section.php
  vendor/filament/infolists/src/Components/TextEntry.php
  vendor/filament/infolists/src/Components/IconEntry.php
  vendor/filament/actions/src/Concerns/CanBeAuthorized.php
  vendor/filament/actions/src/Concerns/CanBeHidden.php
  vendor/filament/actions/src/Testing/TestsActions.php
  ```

### 3.2 ViewRecord Architecture in Filament 5
- Vendor inspection (`vendor/filament/filament/src/Resources/Pages/ViewRecord.php:L67-76`):
  ```php
  public function mount(int | string $record): void
  {
      $this->record = $this->resolveRecord($record);
      $this->authorizeAccess();
      if (! $this->hasInfolist()) {
          $this->fillForm();
      }
  }
  ```
- Vendor inspection (`vendor/filament/filament/src/Resources/Pages/ViewRecord.php:L163-175`):
  ```php
  public function defaultForm(Schema $schema): Schema
  {
      if (! $schema->hasCustomColumns()) {
          $schema->columns($this->hasInlineLabels() ? 1 : 2);
      }

      return $schema
          ->disabled()
          ->inlineLabel($this->hasInlineLabels())
          ->model($this->getRecord())
          ->operation('view')
          ->statePath('data');
  }
  ```
- Finding: `ViewRecord` invokes `defaultForm()` which marks the schema container disabled and sets operation to `'view'`. Form schemas rendered within this container are read-only.
- Vendor inspection (`vendor/filament/filament/src/Resources/Pages/ViewRecord.php:L104-116`):
  ```php
  protected function fillFormWithDataAndCallHooks(Model $record, array $extraData = []): void
  {
      $this->callHook('beforeFill');
      $data = $this->mutateFormDataBeforeFill([
          ...$record->attributesToArray(),
          ...$extraData,
      ]);
      $this->form->fill($data);
      $this->callHook('afterFill');
  }
  ```
- Finding: Overriding `mutateFormDataBeforeFill(array $data): array` intercepts record hydration before the form fills, providing the exact extension point to upgrade stored payloads and catch hydration failures.

### 3.3 Verified Interface Signatures (M3)

#### (a) BlockFormRegistry (`app/Filament/Blocks/BlockFormRegistry.php:L27-42`)
```php
public static function has(string $type): bool
public static function get(string $type): array // returns array<int, Component>
```
Called in `ContentBlockForm.php:L101-108`:
```php
$type = $operation === 'create' ? $get('type') : $record?->type;

if (blank($type) || ! BlockFormRegistry::has((string) $type)) {
    return [];
}

return BlockFormRegistry::get((string) $type);
```

#### (b) Type Headline Formatting (`app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php:L81-89`)
```php
TextEntry::make('type_display')
    ->label('Type')
    ->state(function (?ContentBlock $record): string {
        if ($record === null) {
            return '';
        }

        return Str::headline($record->type);
    })
```
No duck typing via `BlockRegistry` or `method_exists($definition, 'label')` is used.

#### (c) ServingStatus::label (`app/Domain/Content/Enums/ServingStatus.php:L13-20`)
```php
public function label(): string
{
    return match ($this) {
        self::Live => 'Live',
        self::NotLive => 'Not live',
        self::PayloadError => 'Payload error',
    };
}
```

#### (d) ContentBlock Model Casts & Relationships (`app/Domain/Content/Models/ContentBlock.php:L44-53`)
```php
protected $casts = [
    'placement' => TolerantEnum::class.':'.Placement::class,
    'status' => PublishStatus::class,
    'payload' => 'array',
    'is_enabled' => 'boolean',
    'schema_version' => 'integer',
    'sort_order' => 'integer',
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
];
```
Properties `created_by` and `updated_by` are nullable integers. Zero Eloquent relationship methods (`creator()`, `updater()`) exist on `ContentBlock`.

#### (e) ContentBlockFactory States (`database/factories/ContentBlockFactory.php:L38-115`)
- `draft()` (L38)
- `published()` (L45)
- `archived()` (L52)
- `enabled()` (L59)
- `disabled()` (L66)
- `unknownType()` (L81)
- `unsupportedVersion()` (L88)
- `failedUpgrade()` (L95)
- `invalidAfterUpgrade()` (L108)
All states exist in the existing factory file. No factory modifications are required.

#### (f) ContentBlockPolicy Signatures & User::isAdmin (`app/Domain/Content/Policies/ContentBlockPolicy.php:L18-35`, `app/Models/User.php:L57-60`)
```php
// ContentBlockPolicy
public function view(User $user, mixed $model): bool
public function update(User $user, mixed $model): bool

// User
public function isAdmin(): bool
{
    return $this->is_platform_admin;
}
```
Existing policy methods receive `(User $user, mixed $model)` without a default value.

#### (g) ContentBlockTable Static Helpers (`app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php:L25-49`)
```php
public static function servingStatusColor(?ServingStatus $state): string
public static function publishStatusColor(?PublishStatus $state): string
```

#### (h) Timezone Configuration & Resolver (`app/Filament/Admin/Services/AdminTimezoneResolver.php:L13-29`)
```php
public function resolve(?string $timezone): string
```
Default fallback is `'UTC'`. Called in `ContentBlockTable.php:L53` and `ContentBlockForm.php:L27`:
```php
$timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));
```
Parsing in `InteractsWithContentBlockActions.php:L69-77`:
```php
protected function parseScheduleDatetime(mixed $value): ?CarbonInterface
{
    if (blank($value)) {
        return null;
    }

    // DateTimePicker dehydrates into the app timezone
    return Carbon::parse((string) $value, (string) config('app.timezone'));
}
```

### 3.4 Sourced Smoke Test Pattern: assertEditPageRenders (M4)
- Sourced from `tests/Support/FilamentSmokeTest.php:L45-57`:
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

### 3.5 Sourced Hydration Catch Pattern (m11)
- Sourced from `app/Filament/Admin/Resources/ContentBlockResource/Pages/EditContentBlock.php:L50-65`:
  ```php
  Log::warning("Content block [{$record->public_id}] failed hydration: ".get_class($e));
  Notification::make()->danger()->title('Cannot edit content block')->body($msg)->send();
  $this->redirect(self::getResource()::getUrl('index'));
  $data['payload'] = [];

  return $data;
  ```

### 3.6 Sourced Action Execution, Modal Lifecycle & Testing Mechanics (M1, M6, M7)
- Vendor inspection (`vendor/filament/actions/src/Concerns/InteractsWithActions.php:L140-167`):
  ```php
  public function mountAction(string $name, array $arguments = [], array $context = []): mixed
  {
      ...
      $action = $this->getMountedAction();
      if (! $action) {
          $this->unmountAction(cancelParentActions: false);
          return null;
      }
      if ($action->isDisabled()) {
          $this->unmountAction(cancelParentActions: false);
          return null;
      }
  ```
- Vendor inspection (`vendor/filament/actions/src/Concerns/InteractsWithActions.php:L277-375`):
  ```php
  public function callMountedAction(array $arguments = []): mixed
  {
      $action = $this->getMountedAction();
      if (! $action) {
          return null;
      }
      if ($action->isDisabled()) {
          return null;
      }
      if (
          $action->hasAuthorizationNotification() &&
          (! $action->isAuthorized())
      ) {
          return null;
      }
      ...
      try {
          $action->beginDatabaseTransaction();
          ...
          $result = $action->call([...]);
          $result = $action->callAfter() ?? $result;
          (match ($action->getStatus()) {
              ActionStatus::Success => function () use ($action): void {
                  $action->sendSuccessNotification();
                  $action->dispatchSuccessRedirect();
              },
              ...
          })();
      } catch (Halt $exception) {
          $exception->shouldRollbackDatabaseTransaction() ?
              $action->rollBackDatabaseTransaction() :
              $action->commitDatabaseTransaction();

          return null;
      }
  ```
- Vendor inspection (`vendor/filament/actions/src/Action.php:L696-699`):
  ```php
  public function halt(bool $shouldRollBackDatabaseTransaction = false): void
  {
      throw (new Halt)->rollBackDatabaseTransaction($shouldRollBackDatabaseTransaction);
  }
  ```
- Finding: `callMountedAction()` executes the action callback `$action->call()`. It does not re-evaluate `isVisible()` before execution. If the domain throws `IllegalStateTransitionException`, the catch block sends the danger notification and invokes `$action->halt()`. Throwing `Halt` rolls back any pending action transaction and returns `null`, skipping `ActionStatus::Success` notifications.
- Vendor inspection (`vendor/filament/actions/src/Testing/TestsActions.php:L78-95` and `L217-228`):
  ```php
  public function callAction(): Closure
  {
      return function (string | TestAction | array $actions, array $data = [], array $arguments = []): static {
          $this->assertActionVisible($actions, $arguments);
          ...
      };
  }

  public function assertActionVisible(): Closure
  {
      return function (string | TestAction | array $actions, array $arguments = []): static {
          $this->assertActionExists(
              $actions,
              checkActionUsing: fn (Action $action): bool => $action->isVisible(),
              generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] is visible on the [{$livewireClass}] component.",
              arguments: $arguments,
          );
          return $this;
      };
  }
  ```
- Finding: Filament's testing method `callAction()` enforces `$this->assertActionVisible($actions, $arguments)` as its very first step. Calling `callAction()` on an action that is hidden fails with a test assertion failure.

### 3.7 Verified Domain Action Behavior, Locking & Activity Logging (M2)

#### (a) PublishContentBlock (`app/Domain/Content/Actions/PublishContentBlock.php:L23-55`)
```php
return DB::transaction(function () use ($id, $causer) {
    $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

    if ($block->status === PublishStatus::ARCHIVED) {
        throw new IllegalStateTransitionException('Cannot publish an archived block.');
    }

    if ($block->status === PublishStatus::PUBLISHED) {
        return $block;
    }

    $health = ($this->resolveServablePayload)($block);
    if (! $health->isServable()) {
        throw new UnservablePayloadException($health->outcome);
    }

    $oldStatus = $block->status;
    $block->status = PublishStatus::PUBLISHED;
    $block->updated_by = $causer?->id;
    $block->save();

    activity('content')
        ->performedOn($block)
        ->causedBy($causer)
        ->withProperties([
            'old_status' => $oldStatus?->value,
            'new_status' => PublishStatus::PUBLISHED->value,
        ])
        ->event('published')
        ->log('published');

    return $block;
});
```

#### (b) ArchiveContentBlock (`app/Domain/Content/Actions/ArchiveContentBlock.php:L16-44`)
```php
return DB::transaction(function () use ($id, $causer) {
    $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

    if ($block->status === PublishStatus::ARCHIVED) {
        return $block;
    }

    $oldStatus = $block->status;
    $oldEnabled = $block->is_enabled;

    $block->status = PublishStatus::ARCHIVED;
    $block->is_enabled = false;
    $block->updated_by = $causer?->id;
    $block->save();

    activity('content')
        ->performedOn($block)
        ->causedBy($causer)
        ->withProperties([
            'old_status' => $oldStatus?->value,
            'new_status' => PublishStatus::ARCHIVED->value,
            'old_is_enabled' => $oldEnabled,
            'new_is_enabled' => false,
        ])
        ->event('archived')
        ->log('archived');

    return $block;
});
```

#### (c) EnableContentBlock (`app/Domain/Content/Actions/EnableContentBlock.php:L23-55`)
```php
return DB::transaction(function () use ($id, $causer) {
    $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

    if ($block->status === PublishStatus::ARCHIVED) {
        throw new IllegalStateTransitionException('Cannot enable an archived block.');
    }

    if ($block->is_enabled === true) {
        return $block;
    }

    $health = ($this->resolveServablePayload)($block);
    if (! $health->isServable()) {
        throw new UnservablePayloadException($health->outcome);
    }

    $oldEnabled = $block->is_enabled;
    $block->is_enabled = true;
    $block->updated_by = $causer?->id;
    $block->save();

    activity('content')
        ->performedOn($block)
        ->causedBy($causer)
        ->withProperties([
            'old_is_enabled' => $oldEnabled,
            'new_is_enabled' => true,
        ])
        ->event('enabled')
        ->log('enabled');

    return $block;
});
```

#### (d) DisableContentBlock (`app/Domain/Content/Actions/DisableContentBlock.php:L18-44`)
```php
return DB::transaction(function () use ($id, $causer) {
    $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

    if ($block->status === PublishStatus::ARCHIVED) {
        throw new IllegalStateTransitionException('Cannot disable an archived block.');
    }

    if ($block->is_enabled === false) {
        return $block;
    }

    $oldEnabled = $block->is_enabled;
    $block->is_enabled = false;
    $block->updated_by = $causer?->id;
    $block->save();

    activity('content')
        ->performedOn($block)
        ->causedBy($causer)
        ->withProperties([
            'old_is_enabled' => $oldEnabled,
            'new_is_enabled' => false,
        ])
        ->event('disabled')
        ->log('disabled');

    return $block;
});
```

### 3.8 Sourced Serving Status Logging & Caching (M3)
- Vendor/Domain inspection (`app/Domain/Content/Actions/GetBlockServingStatus.php:L38-52`):
  ```php
  return Cache::store('array')->rememberForever($key, function () use ($block) {
      if (! $block->isLive()) {
          return new BlockServingStatusResult(ServingStatus::NotLive);
      }

      $healthResult = ($this->resolveServablePayload)($block, false);

      if ($healthResult->outcome === ServingHealthOutcome::Servable) {
          $status = ServingStatus::Live;
      } else {
          $status = ServingStatus::PayloadError;
      }

      return new BlockServingStatusResult($status, $healthResult->outcome);
  });
  ```
- Vendor/Domain inspection (`app/Domain/Content/Services/ResolveServablePayload.php:L24-40`):
  ```php
  public function __invoke(ContentBlock $block, bool $log = true): ServingHealthResult
  {
      ...
      } catch (UnknownBlockTypeException $e) {
          if ($log) {
              $this->logFailure($block, ServingHealthOutcome::UnknownType);
          }
          return new ServingHealthResult(ServingHealthOutcome::UnknownType);
      }
  ```
- Finding: `GetBlockServingStatus` explicitly calls `($this->resolveServablePayload)($block, false)` with `$log = false`. It never writes log entries. It internally caches evaluation in array cache based on a hash of the block's attributes.

### 3.9 Sourced Authorization Mechanics (M4)
- Vendor inspection (`vendor/filament/actions/src/Concerns/CanBeAuthorized.php:L34-53`, `L80-89`, `L120-128`):
  ```php
  public function authorize(mixed $abilities, Model | string | array | null $arguments = null): static
  {
      if (is_string($abilities) || is_array($abilities)) {
          $this->authorization = [
              'type' => 'all',
              'abilities' => Arr::wrap($abilities),
              'arguments' => Arr::wrap($arguments),
          ];
      }
      return $this;
  }
  protected function parseAuthorizationArguments(array $arguments): array
  {
      if ($record = $this->getRecord()) {
          array_unshift($arguments, $record);
      } elseif ($model = $this->getModel()) {
          array_unshift($arguments, $model);
      }
      return $arguments;
  }
  protected function resolveIsAuthorized(): bool
  {
      ...
      $abilities = $this->authorization['abilities'] ?? [];
      $arguments = $this->parseAuthorizationArguments($this->authorization['arguments'] ?? []);
      return match ($type) {
          'all' => Gate::check($abilities, $arguments),
          ...
      };
  }
  ```
- Vendor inspection (`vendor/filament/actions/src/Concerns/CanBeHidden.php:L74-99`):
  ```php
  protected function resolveIsHiddenInGroup(): bool
  {
      if ($this->evaluate($this->isHidden)) {
          return true;
      }
      if (! $this->evaluate($this->isVisible)) {
          return true;
      }
      ...
      return ! $this->isAuthorizedOrNotHiddenWhenUnauthorized();
  }
  public function isVisible(): bool
  {
      return ! $this->isHidden();
  }
  ```
- Finding: Calling `->authorize('publish')` causes Filament to evaluate `Gate::check('publish', [$record])`. If authorization fails, `isAuthorized()` returns `false`, which directly makes `isHidden()` return `true` and `isVisible()` return `false`.

### 3.10 Sourced Edit Schedule Picker Configuration & Parity (M6)
- Sourced from `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php:L110-118`:
  ```php
  DateTimePicker::make('starts_at')
      ->label("Starts at ({$timezone})")
      ->timezone($timezone)
      ->nullable(),

  DateTimePicker::make('ends_at')
      ->label("Ends at ({$timezone})")
      ->timezone($timezone)
      ->nullable(),
  ```
- Sourced UI3 assertion from `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php:L91-114`:
  ```php
  Config::set('admin.timezone', 'Asia/Colombo');
  ...
  $startsAtUtc = Carbon::parse('2026-10-15 03:30:00', 'UTC');
  ...
  Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
      ->assertSet('data.starts_at', '2026-10-15 09:00:00')
      ->assertSet('data.ends_at', '2026-10-15 11:00:00');
  ```
- Finding: `ContentBlockForm` configures `DateTimePicker` with `->timezone($timezone)`. Filament's DateTimePicker dehydrates UTC timestamps into wall-clock strings matching `$timezone` ('2026-10-15 09:00:00').

### 3.11 Sourced Header Action Record Binding & Livewire Re-render (M7)
- Vendor inspection (`vendor/filament/actions/src/Concerns/InteractsWithRecord.php:L133`):
  ```php
  return ($withDefault && ($this instanceof Action)) ? $this->ensureCorrectRecordType($this->getHasActionsLivewire()?->getDefaultActionRecord($this)) : null;
  ```
- Vendor inspection (`vendor/filament/filament/src/Resources/Pages/Concerns/InteractsWithRecord.php:L138-141`):
  ```php
  public function getDefaultActionRecord(Action $action): ?Model
  {
      return $this->hasRecord() ? $this->getRecord() : null;
  }
  ```
- Finding: In `ViewRecord`, `getDefaultActionRecord()` returns the page's exact `$this->record` model instance. When an action executes `$record->refresh()`, it refreshes the attributes on this instance in place. During the subsequent Livewire re-render, all metadata schema closures evaluate against this refreshed instance.
- Vendor inspection (`vendor/filament/tables/src/Concerns/HasRecords.php:L95-160`):
  In table listings, `$cachedTableRecords` is request-scoped (`null` on every fresh Livewire request). Upon action execution, Livewire processes the component render cycle and re-evaluates `getTableRecords()`, executing a fresh database query.

### 3.12 Table Row Action Routing
- Vendor inspection (`vendor/filament/filament/src/Resources/Pages/Page.php:L487-495`):
  ```php
  if (
      ($action instanceof ViewAction) &&
      (static::getResource()::hasPage('view')) &&
      (! $this instanceof ViewRecord) &&
      (blank($actionModel) || ($actionModel === static::getResource()::getModel()))
  ) {
      return $this->getResourceUrl('view', ['record' => $action->getRecord()]);
  }
  ```
- Finding: `ViewAction::make()` resolves to `getResourceUrl('view', ['record' => $action->getRecord()])`. `ContentBlockResource::getUrl()` intercepts this and produces the route with `public_id`.
- Vendor inspection (`vendor/filament/filament/src/Resources/Pages/ListRecords.php:L161-194`):
  `recordUrl()` configures row click navigation.

### 3.13 Baseline Verification Check
- Command: `composer check`
- Raw unedited output:
  ```
  {"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

   INFO Configuration cache cleared successfully. 

  {"tool":"pest","result":"passed","tests":247,"passed":247,"assertions":748,"duration_ms":123320}
  ```

---

## 4. File Manifest

Total unique files touched or created across the step: **14 files** (strictly within the approved 15-file hard split ceiling).

| # | Path | Operation | Layer | Rules & IDs | Commits Touching |
|---|---|---|---|---|---|
| 1 | `app/Domain/Content/Policies/ContentBlockPolicy.php` | Modify | Domain | C1, C2, C6, D6 | Commit 1 |
| 2 | `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php` | Modify | Testing | C1, C2, T1, T4 | Commit 1 |
| 3 | `tests/Support/FilamentSmokeTest.php` | Modify | Testing | C1, C2, T2 | Commit 2 |
| 4 | `app/Filament/Admin/Resources/ContentBlockResource.php` | Modify | Filament | C1, C2, S6, DB5 | Commit 3 |
| 5 | `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockMetadataSchema.php` | New | Filament | C1, C2, S6, K3, K4 | Commit 3 |
| 6 | `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php` | New | Filament | C1, C2, S2, S6 | Commit 3, Commit 4 |
| 7 | `tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php` | Modify | Testing | C1, C2, T1 | Commit 3 |
| 8 | `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` | Modify | Testing | C1, C2, T1 | Commit 3 |
| 9 | `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php` | New | Testing | C1, C2, T1, T2, T4 | Commit 3 |
| 10 | `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php` | New | Filament | C1, C2, S2, S4, S6, C4, C5 | Commit 4 |
| 11 | `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` | Modify | Filament | C1, C2, S6 | Commit 5 |
| 12 | `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php` | Modify | Testing | C1, C2, T1 | Commit 5 |
| 13 | `tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php` | New | Testing | C1, C2, T1, T4 | Commit 6 |
| 14 | `ARCHITECTURE.md` | Modify | Docs | X7 | Commit 7 |

---

## 5. Architecture & Component Design

All PHP files declare `declare(strict_types=1);` and all classes are `final`.

### 5.1 Domain Layer: `ContentBlockPolicy`
**File**: `app/Domain/Content/Policies/ContentBlockPolicy.php`
- Add authorization abilities answering role permission only, with zero state rules (D6, AC-7):
  ```php
  public function publish(User $user, mixed $model): bool
  {
      return $user->isAdmin();
  }

  public function archive(User $user, mixed $model): bool
  {
      return $user->isAdmin();
  }

  public function enable(User $user, mixed $model): bool
  {
      return $user->isAdmin();
  }

  public function disable(User $user, mixed $model): bool
  {
      return $user->isAdmin();
  }
  ```
- Existing `view` ability (`return $user->isAdmin();`) and `update` ability (returns false for archived blocks) remain untouched.

### 5.2 Metadata Schema: `ContentBlockMetadataSchema` (M3, m2, m3)
**File**: `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockMetadataSchema.php`
- Component structure displaying all metadata items specified in AC-2 with zero mutable static state:
  ```php
  final class ContentBlockMetadataSchema
  {
      public static function humanServingReason(?ServingHealthOutcome $outcome): string
      {
          if ($outcome === null) {
              return '';
          }

          return match ($outcome) {
              ServingHealthOutcome::Servable => 'Servable',
              ServingHealthOutcome::UnknownType => 'Unknown type',
              ServingHealthOutcome::UnsupportedVersion => 'Unsupported version',
              ServingHealthOutcome::InvalidAfterUpgrade => 'Invalid payload after upgrade',
              ServingHealthOutcome::UpgradeFailed => 'Upgrade failed',
          };
      }

      /**
       * @return array<int, Component>
       */
      public static function schema(): array
      {
          $timezone = app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'));

          return [
              TextEntry::make('public_id')
                  ->label('Public ID')
                  ->state(fn (?ContentBlock $record): string => $record?->public_id ?? ''),

              TextEntry::make('placement')
                  ->label('Placement')
                  ->state(function (?ContentBlock $record): string {
                      if ($record === null) {
                          return '';
                      }
                      return $record->placement instanceof Placement
                          ? Str::headline($record->placement->value)
                          : (string) ($record->getRawOriginal('placement') ?? '');
                  }),

              TextEntry::make('type')
                  ->label('Type')
                  ->state(fn (?ContentBlock $record): string => $record !== null ? Str::headline($record->type) : ''),

              TextEntry::make('status')
                  ->label('Status')
                  ->badge()
                  ->color(fn (?ContentBlock $record): string => ContentBlockTable::publishStatusColor($record?->status))
                  ->state(fn (?ContentBlock $record): string => $record?->status ? ucfirst($record->status->value) : ''),

              IconEntry::make('is_enabled')
                  ->label('Enabled')
                  ->boolean()
                  ->state(fn (?ContentBlock $record): bool => (bool) $record?->is_enabled),

              TextEntry::make('serving_status')
                  ->label('Serving Status')
                  ->badge()
                  ->state(fn (?ContentBlock $record): ?BlockServingStatusResult => $record !== null ? app(GetBlockServingStatus::class)($record) : null)
                  ->formatStateUsing(function (?BlockServingStatusResult $result): string {
                      if ($result === null) {
                          return '';
                      }
                      $label = $result->status->label();
                      if ($result->healthOutcome !== null && $result->healthOutcome !== ServingHealthOutcome::Servable) {
                          $label .= ' (' . self::humanServingReason($result->healthOutcome) . ')';
                      }
                      return $label;
                  })
                  ->color(fn (?BlockServingStatusResult $state): string => ContentBlockTable::servingStatusColor($state?->status)),

              TextEntry::make('starts_at')
                  ->label("Starts at ({$timezone})")
                  ->state(fn (?ContentBlock $record): string => $record?->starts_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),

              TextEntry::make('ends_at')
                  ->label("Ends at ({$timezone})")
                  ->state(fn (?ContentBlock $record): string => $record?->ends_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),

              TextEntry::make('schema_version')
                  ->label('Schema Version')
                  ->state(fn (?ContentBlock $record): string => (string) ($record?->schema_version ?? 1)),

              TextEntry::make('created_by')
                  ->label('Created by')
                  ->state(function (?ContentBlock $record): string {
                      if ($record === null || $record->created_by === null) {
                          return '—';
                      }
                      $user = User::find($record->created_by);
                      return $user?->name ?? $user?->email ?? '—';
                  }),

              TextEntry::make('updated_by')
                  ->label('Updated by')
                  ->state(function (?ContentBlock $record): string {
                      if ($record === null || $record->updated_by === null) {
                          return '—';
                      }
                      $user = User::find($record->updated_by);
                      return $user?->name ?? $user?->email ?? '—';
                  }),

              TextEntry::make('created_at')
                  ->label("Created at ({$timezone})")
                  ->state(fn (?ContentBlock $record): string => $record?->created_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),

              TextEntry::make('updated_at')
                  ->label("Updated at ({$timezone})")
                  ->state(fn (?ContentBlock $record): string => $record?->updated_at?->copy()->setTimezone($timezone)->format('Y-m-d H:i:s') ?? '—'),
          ];
      }
  }
  ```
- Evaluates `app(GetBlockServingStatus::class)($record)` once per render inside `state()`. `GetBlockServingStatus` internally caches in array cache using the block's attribute state hash and executes with `$log = false`, preventing log duplication.

### 5.3 Shared Transition Actions: `ContentBlockTransitionActions` (M2, M4, M6, m1, m6)
**File**: `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php`
- Defines four static factory methods and one consolidated execution runner:
  ```php
  final class ContentBlockTransitionActions
  {
      private static function executeTransition(
          ContentBlock $record,
          Action $action,
          Closure $domainCall,
          string $actionVerb,
          string $pastVerb,
      ): void {
          $authUser = Filament::auth()->user();
          if (! $authUser instanceof User) {
              abort(403);
          }
          $causer = $authUser;

          try {
              $domainCall($causer);
          } catch (UnservablePayloadException $e) {
              Notification::make()
                  ->danger()
                  ->title("{$actionVerb} Failed")
                  ->body('The content block payload cannot be safely served.')
                  ->send();
              $action->halt();
          } catch (IllegalStateTransitionException $e) {
              Notification::make()
                  ->danger()
                  ->title('Action Failed')
                  ->body("This block cannot be {$pastVerb} in its current state.")
                  ->send();
              $record->refresh();
              $action->halt();
          }

          $record->refresh();
          Notification::make()
              ->success()
              ->title("Content Block {$pastVerb}")
              ->body("The content block has been {$pastVerb}.")
              ->send();
      }

      public static function publish(): Action
      {
          return Action::make('publish')
            ->label('Publish')
            ->color('success')
            ->icon('heroicon-o-arrow-up-circle')
            ->requiresConfirmation()
            ->modalHeading('Publish Content Block')
            ->modalDescription('This content block will become eligible to show on the storefront within its schedule.')
            ->modalSubmitActionLabel('Publish')
            ->authorize('publish')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status === PublishStatus::DRAFT)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    domainCall: fn (User $causer) => app(PublishContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Publish',
                    pastVerb: 'published',
                );
            });
      }

      public static function archive(): Action
      {
          return Action::make('archive')
            ->label('Archive')
            ->color('danger')
            ->icon('heroicon-o-archive-box')
            ->requiresConfirmation()
            ->modalHeading('Archive Content Block')
            ->modalDescription('Archiving cannot be undone from the panel. The block will be removed from display and cannot be edited.')
            ->modalSubmitActionLabel('Archive')
            ->authorize('archive')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status !== PublishStatus::ARCHIVED)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    domainCall: fn (User $causer) => app(ArchiveContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Archive',
                    pastVerb: 'archived',
                );
            });
      }

      public static function enable(): Action
      {
          return Action::make('enable')
            ->label('Enable')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->modalHeading('Enable Content Block')
            ->modalDescription('This content block will become eligible to show on the storefront within its schedule.')
            ->modalSubmitActionLabel('Enable')
            ->authorize('enable')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status === PublishStatus::PUBLISHED && ! $record->is_enabled)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    domainCall: fn (User $causer) => app(EnableContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Enable',
                    pastVerb: 'enabled',
                );
            });
      }

      public static function disable(): Action
      {
          return Action::make('disable')
            ->label('Disable')
            ->color('gray')
            ->icon('heroicon-o-x-circle')
            ->authorize('disable')
            ->visible(fn (?ContentBlock $record): bool => $record !== null && $record->status === PublishStatus::PUBLISHED && $record->is_enabled)
            ->action(function (ContentBlock $record, Action $action): void {
                self::executeTransition(
                    record: $record,
                    action: $action,
                    domainCall: fn (User $causer) => app(DisableContentBlock::class)((int) $record->id, $causer),
                    actionVerb: 'Disable',
                    pastVerb: 'disabled',
                );
            });
      }
  }
  ```

### 5.4 View Page: `ViewContentBlock` (M6, M7, m11)
**File**: `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php`
- Extends `Filament\Resources\Pages\ViewRecord`.
- Static resource: `protected static string $resource = ContentBlockResource::class;`.
- Header actions:
  ```php
  protected function getHeaderActions(): array
  {
      return [
          EditAction::make(),
          ContentBlockTransitionActions::publish(),
          ContentBlockTransitionActions::enable(),
          ContentBlockTransitionActions::disable(),
          ContentBlockTransitionActions::archive(),
      ];
  }
  ```
- Hydration failure handling in `mutateFormDataBeforeFill(array $data): array` (mirroring `EditContentBlock.php:L50-65`):
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

  Log::warning("Content block [{$record->public_id}] failed hydration: ".get_class($e));
  Notification::make()->danger()->title('Cannot view content block')->body($msg)->send();
  $this->redirect(self::getResource()::getUrl('index'));
  $data['payload'] = [];

  return $data;
  ```
- Form schema construction in `form(Schema $schema): Schema`:
  ```php
  public function form(Schema $schema): Schema
  {
      return $schema->components([
          Section::make('Metadata')
              ->schema(ContentBlockMetadataSchema::schema())
              ->columns(2),

          Section::make('Payload')
              ->statePath('payload')
              ->schema(function (?ContentBlock $record): array {
                  if ($record === null || ! BlockFormRegistry::has($record->type)) {
                      return [];
                  }

                  return BlockFormRegistry::get($record->type);
              }),
      ]);
  }
  ```
- Re-render mechanism: `ViewRecord` provides `$this->record` as the default action record through `InteractsWithRecord::getDefaultActionRecord()`. When `executeTransition` calls `$record->refresh()`, the model instance held by the page is refreshed directly. During the subsequent Livewire component render cycle, the `ContentBlockMetadataSchema` entry closures and header action `visible()` closures execute against this refreshed instance.

### 5.5 Table Updates: `ContentBlockTable`
**File**: `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`
- Modify `recordActions()`:
  ```php
  ->recordActions([
      ViewAction::make(),
      EditAction::make(),
      ActionGroup::make([
          ContentBlockTransitionActions::publish(),
          ContentBlockTransitionActions::enable(),
          ContentBlockTransitionActions::disable(),
          ContentBlockTransitionActions::archive(),
      ]),
  ])
  ```
- Configure row click to open the View page (OD-4):
  ```php
  ->recordUrl(fn (ContentBlock $record): string => ContentBlockResource::getUrl('view', ['record' => $record]))
  ```

### 5.6 Resource Routes: `ContentBlockResource`
**File**: `app/Filament/Admin/Resources/ContentBlockResource.php`
- Add `'view'` page mapping to `getPages()`:
  ```php
  public static function getPages(): array
  {
      return [
          'index' => Pages\ListContentBlocks::route('/'),
          'create' => Pages\CreateContentBlock::route('/create'),
          'view' => Pages\ViewContentBlock::route('/{record}'),
          'edit' => Pages\EditContentBlock::route('/{record}/edit'),
      ];
  }
  ```

---

## 6. Data and Migrations

**Zero database modifications**. No tables created, altered, dropped, or migrated. No database seeds modified.

---

## 7. Behavior & Flows

### 7.1 State Legality & Visibility Matrix (M2)

Governed strictly by Option B: Enable and Disable are visible only on published blocks. Draft blocks display Edit, Publish, Archive.

| Status | `is_enabled` | `Edit` (Policy) | `Publish` | `Archive` | `Enable` | `Disable` | Visible Actions |
|---|---|---|---|---|---|---|---|
| `draft` | `true` | Visible | Visible | Visible | Hidden | Hidden | Edit, Publish, Archive |
| `draft` | `false` | Visible | Visible | Visible | Hidden | Hidden | Edit, Publish, Archive |
| `published` | `true` | Visible | Hidden | Visible | Hidden | Visible | Edit, Disable, Archive |
| `published` | `false` | Visible | Hidden | Visible | Visible | Hidden | Edit, Enable, Archive |
| `archived` | `false` | Hidden | Hidden | Hidden | Hidden | Hidden | (None; read-only View only) |
| `archived` | `true` (tamper) | Hidden | Hidden | Hidden | Hidden | Hidden | (None; read-only View only) |

### 7.2 Use Case 1: View Content Block Render (AC-2, AC-3)
- **Path**: Platform admin navigates to `/admin/content-blocks/{public_id}`.
- **Resolution**: Mount resolves record via `public_id`. Integer ID or unknown ULID returns 404.
- **Authorization**: Policy checks `canView()`. Non-admin receives 403.
- **Hydration**: `mutateFormDataBeforeFill()` upgrades payload into memory.
- **Rendering**: Metadata section displays all fields in read-only format. Schedule pickers render in resolved admin timezone. Payload renders through registered form schema in disabled format. `link_url` renders as plain disabled text, not an anchor tag.
- **Activity log**: none.
- **Concurrency note (D9)**: Read-only operation. Zero database lock acquired.

### 7.3 Use Case 2: Broken Payload View Attempt (AC-4)
- **Path**: Admin attempts to view a block containing unupgradable or unregistered payload data.
- **Failure**: `mutateFormDataBeforeFill()` catches hydration exception (`UnknownBlockTypeException`, `UnsupportedSchemaVersionException`, `UpgradeFailedException`, `InvalidPayloadException`).
- **Notification**: Danger notification sent with fixed user message. Log entry written without raw payload (SEC3).
- **Redirection**: Redirects to index without a 500 error.
- **Activity log**: none.
- **Concurrency note (D9)**: Read-only operation. Zero lock acquired.

### 7.4 Use Case 3: Publish Transition (AC-9, AC-10)
- **Path**: Admin clicks Publish on View header or table row for draft block. Confirmation modal submitted.
- **Execution**: Action invokes `PublishContentBlock($record->id, $causer)`.
- **Happy path**: Status changes to `published`. Activity log written with:
  - Event: `'published'`
  - Causer: `$admin`
  - Properties: `['old_status' => 'draft', 'new_status' => 'published']`
  - Success notification shown (`Content Block published`). Header actions update to published set (`Edit, Disable, Archive`).
- **Race window early return (M1)**: If a concurrent request published the block first, `PublishContentBlock` returns the block unchanged without writing an activity log (lines 30-32). In this race window, the action completes and displays the standard success copy with zero 500 and zero duplicate activity entry, which is accepted behavior.
- **Unservable rejection**: `UnservablePayloadException` caught. Danger notification sent: Title `'Publish Failed'`, Body `'The content block payload cannot be safely served.'`. Action halts via `$action->halt()`.
- **Activity log on rejection**: none.
- **Stale state**: If block was archived concurrently, Action throws `IllegalStateTransitionException`. Caught: danger notification Title `'Action Failed'`, Body `'This block cannot be published in its current state.'`. Record refreshed, action halted.
- **Activity log on stale state**: none.
- **Concurrency note (D9)**: Atomic transition protected by `lockForUpdate()` inside `DB::transaction()`.

### 7.5 Use Case 4: Archive Transition (AC-11)
- **Path**: Admin clicks Archive on View header or table row for draft or published block. Confirmation modal submitted.
- **Execution**: Action invokes `ArchiveContentBlock($record->id, $causer)`.
- **Happy path**: Status changes to `archived`, `is_enabled` changes to `false`. Activity log written with:
  - Event: `'archived'`
  - Causer: `$admin`
  - Properties: `['old_status' => $oldStatus->value, 'new_status' => 'archived', 'old_is_enabled' => $oldEnabled, 'new_is_enabled' => false]`
  - Success notification shown. Transition actions disappear.
- **Broken payload**: Archive succeeds from table row even if payload cannot be upgraded (AC-12).
- **Activity log**: written with event `'archived'`.
- **Concurrency note (D9)**: Atomic transition protected by `lockForUpdate()` inside `DB::transaction()`.

### 7.6 Use Case 5: Enable & Disable Transitions (AC-12)
- **Disable flow**:
  - Admin clicks Disable on published enabled block. Zero confirmation modal (OD-3 fast takedown).
  - Invokes `DisableContentBlock($record->id, $causer)`.
  - `is_enabled` set to `false`. Activity log written with:
    - Event: `'disabled'`
    - Causer: `$admin`
    - Properties: `['old_is_enabled' => true, 'new_is_enabled' => false]`
  - Success notification displayed. Header action updates from Disable to Enable.
  - Succeeds on table row for blocks with broken payload.
- **Enable flow**:
  - Admin clicks Enable on published disabled block. Confirmation modal submitted.
  - Invokes `EnableContentBlock($record->id, $causer)`.
  - If unservable, throws `UnservablePayloadException`. Caught, danger notification sent, zero state change, activity log: none.
  - If servable, `is_enabled` set to `true`. Activity log written with:
    - Event: `'enabled'`
    - Causer: `$admin`
    - Properties: `['old_is_enabled' => false, 'new_is_enabled' => true]`
  - Success notification displayed. Header action updates from Enable to Disable.
- **Concurrency note (D9)**: Atomic transitions protected by `lockForUpdate()` inside `DB::transaction()`.

### 7.7 Exception Mapping Register (Moved from Section 9 - M1)

| Invoked Method / Action | Exception Thrown | Caught By | User-Facing Notification & Side Effects |
|---|---|---|---|
| `ViewContentBlock::mutateFormDataBeforeFill` | `UnknownBlockTypeException` | View Page Catch | Danger: `"The block type is not recognized."` Logged without payload (SEC3). Redirects to index. Zero 500 error. |
| `ViewContentBlock::mutateFormDataBeforeFill` | `UnsupportedSchemaVersionException` | View Page Catch | Danger: `"The block schema version is not supported."` Logged without payload. Redirects to index. |
| `ViewContentBlock::mutateFormDataBeforeFill` | `UpgradeFailedException` | View Page Catch | Danger: `"The block payload could not be upgraded."` Logged without payload. Redirects to index. |
| `ViewContentBlock::mutateFormDataBeforeFill` | `InvalidPayloadException` | View Page Catch | Danger: `"The block payload is invalid."` Logged without payload. Redirects to index. |
| `ContentBlockTransitionActions::publish` | `UnservablePayloadException` | Action Catch | Danger: Title `"Publish Failed"`, Body `"The content block payload cannot be safely served."` Zero state mutation, zero activity log. |
| `ContentBlockTransitionActions::publish` | `IllegalStateTransitionException` | Action Catch | Danger: Title `"Action Failed"`, Body `"This block cannot be published in its current state."` Halts, refreshes record. |
| `ContentBlockTransitionActions::enable` | `UnservablePayloadException` | Action Catch | Danger: Title `"Enable Failed"`, Body `"The content block payload cannot be safely served."` Zero state mutation, zero activity log. |
| `ContentBlockTransitionActions::enable` | `IllegalStateTransitionException` | Action Catch | Danger: Title `"Action Failed"`, Body `"This block cannot be enabled in its current state."` Halts, refreshes record. |
| `ContentBlockTransitionActions::disable` | `IllegalStateTransitionException` | Action Catch | Danger: Title `"Action Failed"`, Body `"This block cannot be disabled in its current state."` Halts, refreshes record. |
| `ContentBlockTransitionActions::archive` | `IllegalStateTransitionException` | Action Catch | Danger: Title `"Action Failed"`, Body `"This block cannot be archived in its current state."` Halts, refreshes record. |

---

## 8. Rule Compliance Matrix

| Rule ID | Verbatim Requirement from Register (`docs/blueprints/rule-register.md`) | App? | Compliance Implementation in P1-UI4 |
|---|---|---|---|
| **E1** | Developer machine is Windows, shell is PowerShell. Run commands one at a time. Do not use bash-only syntax and do not chain with `&&`. | Y | All CLI checks run individually with PowerShell commands. |
| **S1** | Business logic lives in `app/Domain/Content/Actions/`, one invokable class per use case. Read use cases (such as building the API response) are Actions too. | Y | State transitions invoke existing `PublishContentBlock`, `ArchiveContentBlock`, `EnableContentBlock`, `DisableContentBlock`. Zero logic added to Filament. |
| **S2** | Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output. | Y | Pages and action closures delegate directly to Domain Actions, handling UI notifications and redirection only. |
| **S3** | `app/Domain` must never import from `App\Filament` or `App\Http`. | Y | `ContentBlockPolicy` contains zero imports from `App\Filament` or `App\Http`. Enforced by `tests/Arch`. |
| **S4** | Filament never calls `Model::create/update/delete` directly. It calls Actions. | Y | State changes execute strictly through Domain Actions. Enforced by `tests/Arch/FilamentWritesTest.php`. |
| **S5** | Block definitions go in `app/Domain/Content/Blocks/<Name>/`. Their Filament form schemas go in `app/Filament/Blocks/`. | Y | Payload schema presentation reuses `app/Filament/Blocks/AnnouncementBarForm.php` through `BlockFormRegistry`. |
| **S6** | Panel code goes under `app/Filament/Admin/`. Do not put files in `app/Filament/Resources` or other default paths. | Y | Resource, pages, schemas, and action classes are placed under `app/Filament/Admin/Resources/ContentBlockResource/`. |
| **S7** | Put code in the correct place. If nothing fits, ask before creating a new context or top-level folder. | Y | Reusable transition actions are placed in `app/Filament/Admin/Resources/ContentBlockResource/Actions/`. No new root context created. |
| **C1** | `declare(strict_types=1);` in every PHP file. | Y | Declared at line 1 of every new and modified file. |
| **C2** | Type every parameter, return value and property. Prefer `final` classes. | Y | Strict typing applied throughout. All new classes marked `final`. |
| **C3** | Use enums for fixed value sets (`Placement`, `MenuKey`, `PublishStatus`, `Tone`) and `spatie/laravel-data` classes for structured input and output between layers. | Y | Uses `PublishStatus`, `ServingStatus`, `ServingHealthOutcome`, and `Placement`. |
| **C4** | Status changes (draft/published/archived) and is_enabled changes happen only through Actions (Publish, Archive, Enable, Disable). Neither is ever a form field. | Y | View page displays status and enabled as read-only text and icon entries. Transitions occur strictly through dedicated Actions. |
| **C5** | The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action. | Y | All model writes delegate to domain transition Actions. |
| **C6** | Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API. | Y | All resource interactions authorize against `ContentBlockPolicy` abilities (`view`, `publish`, `archive`, `enable`, `disable`). |
| **C7** | Follow Pint and Larastan. Do not add baseline entries without approval. | Y | Code conforms to Pint and Larastan Level 8. Zero baseline entries added. |
| **C8** | Comments explain why, not what. No commented-out code. | Y | Comments document non-obvious rationale only. |
| **K1** | Block type keys are permanent. Never rename one. | Y | Uses permanent key `announcement_bar`. |
| **K2** | Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load. | Y | View page hydrates payload via `GetUpgradedBlockPayload` to sequentially upgrade payloads before display. |
| **K3** | No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans. | Y | Metadata and payload fields render as plain text or select options. Zero raw HTML components. |
| **K4** | Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme. | Y | Link URLs in payload render as plain text entries, never as clickable anchors. |
| **K5** | Placements, menu keys and block types are defined in code. Admins never create them. | Y | Uses code-defined placements and block types. |
| **K6** | Block registration is an explicit list in `ContentServiceProvider`. No auto-discovery. | Y | Explicit registration preserved. |
| **K7** | Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content. | N/A | Public API is untouched in this step. |
| **K8** | The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change. | N/A | Public API contract is untouched in this step. |
| **K9** | Adding a block type follows the checklist in `ARCHITECTURE.md` section 6. Do not skip steps. | Y | Reuses block form schema directly. No per-block-type View classes required. |
| **DB1** | PostgreSQL only. Tests run against Postgres, never SQLite. | Y | All Pest tests run against PostgreSQL test database. |
| **DB2** | Never edit a migration that has been merged. Add a new one. You may edit your own unmerged migration. | N/A | Zero migrations touched. |
| **DB3** | No destructive changes (drop or rename column or table) in the same release that stops using them. | N/A | Zero database modifications. |
| **DB4** | Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers. | N/A | Zero database modifications. |
| **DB5** | Expose `public_id` (ULID) externally, never raw incremental IDs. | Y | Route registered as `/{record}` with `$recordRouteKeyName = 'public_id'`. Direct integer lookup returns 404. |
| **DB6** | Seeders must be idempotent. Do not run destructive database commands against anything except the local `phone_store` and `phone_store_test` databases. | N/A | Zero seeders modified. |
| **SEC1** | Never commit secrets, `.env` files, real keys or production data. Add new variables to `.env.example`. | Y | Zero secret or environment changes. |
| **SEC2** | Do not disable authorization, policies, strict types or static analysis rules to make code pass. | Y | Full authorization enforced across all actions. Zero rules disabled. |
| **SEC3** | Do not log sensitive data. | Y | Hydration exceptions log error name with `public_id` without dumping raw payload. Notifications use fixed copy with zero exception leakage. |
| **SEC4** | Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization. | Y | `canAccessPanel` enforced in panel provider; `ContentBlockPolicy` checks `isAdmin()` on all abilities. |
| **SEC5** | Write sensitive content changes (publish, disable, menu and settings edits) to the activity log. | Y | Transition actions record activity logs with event name and causer id. |
| **T1** | Pest. Feature tests for Actions, the API and Filament resources. Unit tests for pure logic. Architecture tests in `tests/Arch`. | Y | Feature tests for View page, transition actions, policy, and table integration. |
| **T2** | Every Filament resource has a smoke test that loads list, create and edit pages. | Y | `FilamentSmokeTest` updated with `assertViewPageRenders`. Smoke test asserts View page renders 200 OK. |
| **T3** | Every block type has tests for payload validation, API output and visibility rules. | N/A | Block definition untouched. |
| **T4** | Use factories, not hand-built rows. Tests must be independent and deterministic. | Y | Tests utilize `ContentBlock::factory()` states (`draft`, `published`, `archived`, `enabled`, `disabled`, `failedUpgrade`). |
| **T5** | Do not lower coverage, skip tests or mark them incomplete to pass checks. | Y | Zero tests skipped or marked incomplete. |
| **G1** | Branch from `main`: `feat/<short-desc>`, `fix/...`, `chore/...`. | Y | Operating on `feat/ui-content-block-view-and-transitions`. |
| **G2** | Conventional Commits: `feat(content): add announcement bar block`. | Y | All commits adhere to Conventional Commits specification. |
| **G3** | Never push to `main` directly or bypass git hooks. | Y | Commits created locally on feature branch. |
| **G4** | PR description: what changed, why, how it was tested, and risks (migrations, API contract, security). | Y | Standard PR documentation followed. |
| **X1** | Do not add dependencies without asking and justifying them. Packages not listed in `ARCHITECTURE.md` section 3 need approval. | Y | Zero dependencies added. |
| **X2** | Do not introduce Docker, Sail, docker-compose or any container configuration. | Y | Native environment preserved. |
| **X3** | Do not add deployment pipelines. A minimal GitHub Actions workflow is permitted strictly for running the same checks as local (`composer check`), but the project does not use CI/CD for deployments. | Y | Zero pipeline modifications. |
| **X4** | Nothing outside the active phase was added. Do not create folders, contexts or tables for later phases. | Y | Phase 1 CMS artifacts touched only. |
| **X5** | Do not modify the storefront repository or assume its internals. The API contract is the only interface. | Y | Storefront repository untouched. |
| **X6** | Definition of done: `composer check` passes and the output was read. | Y | `composer check` validated at every checkpoint. |
| **X7** | Definition of done: `ARCHITECTURE.md`, `docs/contracts/content-api.md`, `.env.example` updated where relevant. | Y | `ARCHITECTURE.md` updated at closeout. |

---

## 9. Acceptance Criteria Traceability (M1, M5, m3)

| AC ID | Brief Requirement Summary | Target Implementation Files | Test File & Test Method Name | How Verified |
|---|---|---|---|---|
| **AC-1** | Registers `index`, `create`, `edit`, `view`. Route key is `public_id`, numeric returns 404. UI3 assertions updated. Edit page header empty. | `ContentBlockResource.php`, `ContentBlockCreateTest.php`, `ContentBlockEditTest.php` | `ContentBlockViewTest`: `it resolves view route with public_id and returns 404 for numeric id`; `ContentBlockEditTest`: `it exposes no header actions on edit page and registers index create edit and view routes` | GET `/admin/content-blocks/{public_id}` returns 200; GET `/admin/content-blocks/{id}` returns 404; route array keys asserted. |
| **AC-2** | View page renders for platform admin for draft, published, archived blocks. Shows metadata, serving status with reason, schedule in admin timezone. | `ViewContentBlock.php`, `ContentBlockMetadataSchema.php` | `ContentBlockViewTest`: `it renders all metadata fields on view page across draft published and archived blocks` | `assertSeeText` verifies public ID, placement, type, status, enabled, serving status with mapped reason, admin timezone timestamps. |
| **AC-3** | Payload rendered read-only via registered schema. No inputs editable. Link is plain text, not anchor. Forbidden scan passes. | `ViewContentBlock.php`, `BlockFormRegistry.php` | `ContentBlockViewTest`: `it renders announcement bar payload read only with plain text link` | Asserts payload fields disabled; verifies HTML does not contain `<a>` tag for link; verifies absent input names. |
| **AC-4** | Broken payload view attempt gives danger notification and index redirect. Zero 500 error. No exception leakage. | `ViewContentBlock.php` | `ContentBlockViewTest`: `it redirects to index with danger notification when stored payload fails hydration` | Dataset test on `unknownType`, `unsupportedVersion`, `invalidAfterUpgrade`, `failedUpgrade` asserting notification sent and redirect. |
| **AC-5** | `view` ability allowed for platform admin (including archived), denied for non-admin (403). | `ContentBlockPolicy.php`, `ViewContentBlock.php` | `ContentBlockPolicyTest`: `it allows admin to view archived blocks and denies non admin`; `ContentBlockViewTest`: `it forbids non admin from viewing block` | Policy unit assertions plus HTTP GET returning 403 for non-admin. |
| **AC-6** | List table has View row action for every status. Smoke test loads View page. Row click opens View (OD-4). | `ContentBlockTable.php`, `FilamentSmokeTest.php` | `ContentBlockViewTest`: `ContentBlockViewSmokeTest::test_renders_view_page_for_platform_admin_via_smoke_test`; `ListContentBlocksTableTest`: `it renders view row action and configures row click url to view page` | Smoke helper test plus table instance asserting action exists and `getRecordUrl($record)` equals View URL. |
| **AC-7** | Abilities `publish`, `archive`, `enable`, `disable` on `ContentBlockPolicy`: true for platform admin, false for non-admin. Zero state logic. | `ContentBlockPolicy.php` | `ContentBlockPolicyTest`: `it allows admin to transition blocks and denies non admin with zero state logic` | Policy assertions across all 4 abilities for platform admin and regular user. |
| **AC-8** | Visible actions on View header and table row match Section 7.1 matrix. Hidden from non-admin. | `ContentBlockTransitionActions.php`, `ViewContentBlock.php`, `ContentBlockTable.php` | `ContentBlockStateActionsTest`: `it renders visible actions matching exact matrix across all statuses and enabled states` | Dataset test asserting exact visible action names on header and table rows; mount 403 asserted for non-admin. |
| **AC-9** | Publish transitions draft block, writes activity entry with causer, shows success notification, asks confirmation. Page updates without full reload. | `ContentBlockTransitionActions.php`, `ContentBlockTable.php` | `ContentBlockStateActionsTest`: `it publishes draft block with confirmation modal and logs activity` | Livewire action execution asserting DB state, exactly one activity row for subject and event, notification, and updated actions. |
| **AC-10** | Publish and Enable on unservable payload leave state unchanged, write zero activity log, show danger notification with fixed copy. | `ContentBlockTransitionActions.php`, `ContentBlockTable.php` | `ContentBlockStateActionsTest`: `it rejects publish and enable on unservable block payload without writing activity log` | Tests on table row action surface for `invalidAfterUpgrade` draft (Publish) and disabled published (Enable) asserting status unchanged, zero activity log, danger notification. |
| **AC-11** | Archive calls Action once on both surfaces, sets archived and disabled, writes activity log with causer. Actions update to archived set. | `ContentBlockTransitionActions.php`, `ContentBlockTable.php` | `ContentBlockStateActionsTest`: `it archives block from view header and table row and updates visible actions` | Execution on draft and published blocks asserting DB status `archived`, `is_enabled` false, activity log, zero actions remaining. |
| **AC-12** | Enable and Disable flip `is_enabled`, write activity log. Disable and Archive succeed for broken payload from table row. | `ContentBlockTransitionActions.php`, `ContentBlockTable.php` | `ContentBlockStateActionsTest`: `it enables and disables published block and succeeds on table row for broken payload` | Execution of Disable and Archive on table row for `failedUpgrade()` block asserting state change and activity log. |
| **AC-13** | Stale state transition caught (`IllegalStateTransitionException`), shows danger notification with fixed copy, zero change, zero log, refreshes page. | `ContentBlockTransitionActions.php` | `ContentBlockStateActionsTest`: `it handles stale state transition gracefully with danger notification and record refresh` | Container stub throwing `IllegalStateTransitionException` asserting caught notification, zero DB change, zero activity log, refreshed record. Parametrized no-op stub test asserting success copy, zero extra log, zero 500. |

---

## 10. Testing Strategy & Fixtures

### 10.1 Feature Test: `ContentBlockPolicyTest.php` (Commit 1 - m5)
- Assert `publish`, `archive`, `enable`, `disable` return `true` for platform admin.
- Assert `publish`, `archive`, `enable`, `disable` return `false` for non-admin user.
- Assert `archive` returns `true` for an already archived block to verify role authorization is decoupled from domain state legality.

### 10.2 Smoke Test Trait: `FilamentSmokeTest.php` (Commit 2 - M4)
- Add `assertViewPageRenders(string $pageClass, Model $record): void` mirroring `assertEditPageRenders`:
  ```php
  protected function assertViewPageRenders(string $pageClass, Model $record): void
  {
      $admin = $this->getPlatformAdmin();

      $url = $pageClass::getUrl(['record' => $record]);
      expect($url)->toContain($record->getAttribute('public_id'));

      $this->actingAs($admin)
          ->get($url)
          ->assertSuccessful();

      /** @var class-string<\Filament\Resources\Resource> $resource */
      $resource = $pageClass::getResource();
      $routeKeyName = $resource::getRecordRouteKeyName() ?? $record->getRouteKeyName();

      Livewire::actingAs($admin)
          ->test($pageClass, ['record' => $record->getAttribute($routeKeyName)])
          ->assertSuccessful();
  }
  ```

### 10.3 Feature Test: `ContentBlockViewTest.php` (Commit 3)
- Dedicated smoke test class `ContentBlockViewSmokeTest extends TestCase` using `FilamentSmokeTest` calling `assertViewPageRenders`.
- AC-1: Route registration: `ContentBlockResource::getPages()` contains `view`. GET `/admin/content-blocks/{public_id}` returns 200. GET `/admin/content-blocks/{id}` returns 404.
- AC-2: View page renders for platform admin across `draft`, `published`, and `archived` states:
  - Asserts all metadata values render on page: public ID, placement headline, type headline, status badge, enabled boolean, serving status badge with reason, starts_at and ends_at formatted in admin timezone, schema_version, created_by, updated_by, created_at, updated_at.
- AC-3: Payload rendering:
  - Renders `text`, `link_url`, `tone` for `announcement_bar`.
  - Asserts all payload inputs are disabled/read-only.
  - Asserts `link_url` is rendered as plain text, not an `<a>` tag.
  - Asserts zero inputs named `status`, `is_enabled`, `sort_order`, `schema_version`, or `type` exist within the payload container.
- AC-4: Hydration failure handling:
  - Block with `unknownType()` factory state -> sends danger notification, redirects to index, zero 500.
  - Block with `unsupportedVersion()` factory state -> sends danger notification, redirects to index.
  - Block with `invalidAfterUpgrade()` factory state -> sends danger notification, redirects to index.
  - Block with `failedUpgrade()` factory state -> sends danger notification, redirects to index.
- AC-5: Authorization:
  - Non-platform admin receives 403 on View page URL.
  - Platform admin can view archived blocks.

### 10.4 Feature Test: `ContentBlockStateActionsTest.php` (Commit 6 - M1, M2, M4, M5, M6, m2, m3)
- AC-8: Visibility Matrix Dataset Test (m2):
  - Dataset covering all 6 combinations of status (`draft`, `published`, `archived`) and `is_enabled` (`true`, `false`):
    - `draft` + `is_enabled: true` -> `['edit', 'publish', 'archive']`
    - `draft` + `is_enabled: false` -> `['edit', 'publish', 'archive']`
    - `published` + `is_enabled: true` -> `['edit', 'disable', 'archive']`
    - `published` + `is_enabled: false` -> `['edit', 'enable', 'archive']`
    - `archived` + `is_enabled: false` -> `[]`
    - `archived` + `is_enabled: true` (tamper) -> `[]`
  - Asserts exact visible header actions on `ViewContentBlock` matching Section 7.1 matrix.
  - Asserts exact visible row actions on `ContentBlockTable` matching Section 7.1 matrix.
  - Asserts that for every state, zero visible actions are actions that would throw `IllegalStateTransitionException`.
  - Non-admin authorization: Asserts GET `/admin/content-blocks` and GET `/admin/content-blocks/{public_id}` return 403 Forbidden for non-admin user.
  - Direct execution authorization check: Non-admin calling action directly via Livewire `call('callMountedAction')` receives 403, state remains unchanged, zero activity log (M4).
- AC-9: Publish Action (m3):
  - Invoking Publish from View page header transitions status from `draft` to `published`.
  - Asserts exactly one activity row exists for that subject ID and `'published'` event (`expect(Activity::forSubject($block)->where('event', 'published')->count())->toBe(1)`).
  - Asserts activity log properties: `['old_status' => 'draft', 'new_status' => 'published']`.
  - Asserts success notification sent (`Content Block published`).
  - Asserts page reflects published state and header actions update without full page reload.
  - Same assertions executed for Publish row action on `ContentBlockTable`.
- AC-10: Unservable Payload Rejection on Table Row Surface (M5):
  - Draft block with `draft()->invalidAfterUpgrade()` factory state.
  - Admin clicks Publish on table row: Action catches `UnservablePayloadException`, sends danger notification `'The content block payload cannot be safely served.'`.
  - Asserts status remains `draft`. Zero activity log entry written.
  - Disabled published block with `published()->disabled()->invalidAfterUpgrade()` factory state.
  - Admin clicks Enable on table row: Action catches `UnservablePayloadException`, sends danger notification `'The content block payload cannot be safely served.'`.
  - Asserts `is_enabled` remains `false`. Zero activity log written.
- AC-11: Archive Action:
  - Invoking Archive on draft block and on published block transitions status to `archived` and sets `is_enabled` to `false`.
  - Asserts activity log written: event `'archived'`, causer = admin, properties: `['old_status' => $oldStatus->value, 'new_status' => 'archived', 'old_is_enabled' => $oldEnabled, 'new_is_enabled' => false]`.
  - Asserts header actions on View page change to zero transition actions.
- AC-12: Enable, Disable & Broken Payload Table Actions:
  - Invoking Disable flips `is_enabled` to `false`, writes activity log event `'disabled'` with `['old_is_enabled' => true, 'new_is_enabled' => false]`.
  - Invoking Enable flips `is_enabled` to `true`, writes activity log event `'enabled'` with `['old_is_enabled' => false, 'new_is_enabled' => true]`.
  - Broken payload table row execution: Block with `draft()->failedUpgrade()` factory state archived via table row action: asserts status transitions to `archived`, activity log written.
  - Block with `published()->enabled()->failedUpgrade()` factory state disabled via table row action: asserts `is_enabled` transitions to `false`, activity log written.
- AC-13: Stale State Handling, Idempotency & Direct Calls (M1):
  - Container stub test (MUT-3):
    ```php
    app()->bind(PublishContentBlock::class, fn () => new class
    {
        public function __invoke(int $id, ?User $causer = null): ContentBlock
        {
            throw new IllegalStateTransitionException('Cannot publish an archived block.');
        }
    });
    ```
    Admin executes Publish on draft block. Asserts caught: danger notification sent `'This block cannot be published in its current state.'`, zero state mutation, zero activity log, `$record->refresh()` executed. (This is the test that fails under MUT-3).
  - Modal race test: Mount Publish modal on draft block via `mountAction('publish')`, update database directly via `DB::table('content_blocks')->where('id', $block->id)->update(['status' => 'archived'])`, call mounted action via `callMountedAction()`. Asserts execution callback runs, domain throws `IllegalStateTransitionException`, caught by `executeTransition()`, danger notification sent, zero state change, zero activity log, refreshed record.
  - Parametrized no-op stub test: Bind stub returning unchanged record without database update or activity log (simulating race window where target state was reached concurrently). Execute visible action. Asserts success notification sent, zero extra activity log rows, zero 500 error.
  - Direct call on hidden action: Assert that calling `callAction('publish')` on an archived block fails with Filament testing assertion `assertActionVisible` failure, ensuring hidden actions cannot be triggered.
- Timezone Display Parity Test (M6):
  - Set `config(['admin.timezone' => 'Asia/Colombo'])`. Block with `starts_at = 2026-10-15 03:30:00 UTC`.
  - Mount Edit page: `Livewire::actingAs($admin)->test(EditContentBlock::class, ['record' => $block->public_id])->assertSet('data.starts_at', '2026-10-15 09:00:00')`.
  - Mount View page: `Livewire::actingAs($admin)->test(ViewContentBlock::class, ['record' => $block->public_id])->assertSeeText('2026-10-15 09:00:00')`.
  - Asserts View page wall time equals Edit form wall time for the identical instant.

### 10.5 Feature Test Updates: `ContentBlockCreateTest.php`, `ContentBlockEditTest.php`, `ListContentBlocksTableTest.php` (B1, m9)
- `ContentBlockCreateTest.php` (Moved to Commit 3 - B1):
  - Update route-set assertion: `expect(array_keys(ContentBlockResource::getPages()))->toEqualCanonicalizing(['index', 'create', 'edit', 'view']);`.
- `ContentBlockEditTest.php` (Moved to Commit 3 - B1):
  - Update route-set assertion: `expect(ContentBlockResource::getPages())->toHaveKeys(['index', 'create', 'edit', 'view']);`.
  - Assert Edit page still has zero header actions: `expect($instance->getCachedHeaderActions())->toBeEmpty();`.
- `ListContentBlocksTableTest.php` (Commit 5 - m9):
  - Row click test: assert `$component->instance()->getTable()->getRecordUrl($record)` equals `ContentBlockResource::getUrl('view', ['record' => $record])`.
  - Assert View action exists for draft, published, and archived rows.
  - Assert Edit action is hidden for archived rows.
  - Assert transition actions execute and update table state reactively.

### 10.6 Mutation Spot-Checks
- **MUT-1**: Remove `$user->isAdmin()` from `ContentBlockPolicy::publish()`. Run `vendor/bin/pest tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`. Expect failure. Restore.
- **MUT-2**: Drop status condition from Publish action visibility closure in `ContentBlockTransitionActions.php`. Run `vendor/bin/pest tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php`. Expect failure on visibility matrix test. Restore.
- **MUT-3**: Remove `IllegalStateTransitionException` catch from `executeTransition()` in `ContentBlockTransitionActions.php`. Run `vendor/bin/pest tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php`. Expect failure on stale-state test. Restore.

---

## 11. Documentation Updates (m10)

- `ARCHITECTURE.md` section 1 (Phases & Progress, line 23):
  - Replace `- [ ] UI Step 4 (View page, header/row actions)` with `- [x] UI Step 4 (View page, header/row actions)`.
- `ARCHITECTURE.md` section 5 (Code structure, line 82):
  - Update resource tree annotation:
    `│       ├── Resources/          # eg: Resources/ContentBlockResource/{Actions,Pages,Schemas,Tables}`
- `ARCHITECTURE.md` section 8 (Filament conventions, line 216):
  - Add bullet:
    `- Reusable resource actions (such as lifecycle transitions shared between page headers and table rows) live in app/Filament/Admin/Resources/<Model>Resource/Actions/.`

---

## 12. Commit Order & Checkpoints (B1, m1, m4)

### Checkpoint A (CP-A): Policy, Smoke Test Helper, Metadata Schema, View Page & Routes
1. **Commit 1**: `feat(content): add publish, archive, enable and disable policy abilities`
   - Files:
     - `app/Domain/Content/Policies/ContentBlockPolicy.php`
     - `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php`
2. **Commit 2**: `feat(testing): add assertViewPageRenders method to FilamentSmokeTest`
   - Files:
     - `tests/Support/FilamentSmokeTest.php`
3. **Commit 3**: `feat(filament): add content block metadata schema, view page and resource view route`
   - Files:
     - `app/Filament/Admin/Resources/ContentBlockResource.php`
     - `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockMetadataSchema.php`
     - `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php`
     - `tests/Feature/Admin/ContentBlock/ContentBlockCreateTest.php` (update route set assertion to include view - B1)
     - `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` (update route set assertion to include view - B1)
     - `tests/Feature/Admin/ContentBlock/ContentBlockViewTest.php`
4. **CP-A Verification**:
   - Run `composer check` (asserts green after Commit 3).
   - Verify View page renders and hydration error handling redirects to index.

### Checkpoint B (CP-B): Shared Transition Actions, Table Integration & Full Test Suite
5. **Commit 4**: `feat(filament): add shared content block transition actions and wire to view page`
   - Files:
     - `app/Filament/Admin/Resources/ContentBlockResource/Actions/ContentBlockTransitionActions.php`
     - `app/Filament/Admin/Resources/ContentBlockResource/Pages/ViewContentBlock.php`
6. **Commit 5**: `feat(filament): add view row action, grouped transition actions and row click to content block table`
   - Files:
     - `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php`
     - `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`
7. **Commit 6**: `test(filament): add state transition feature tests`
   - Files:
     - `tests/Feature/Admin/ContentBlock/ContentBlockStateActionsTest.php`
8. **CP-B Verification**:
   - Run all Layer A checks:
     - `composer check` (Pint, PHPStan, Pest: asserts green after every commit).
     - `php artisan route:list --path=admin/content-blocks` (asserts 4 routes: index, create, edit, view).
     - `git diff main...HEAD --stat -- database composer.json composer.lock` (asserts empty).
     - `git diff main...HEAD --stat -- app/Domain` (asserts only `ContentBlockPolicy.php`).
     - File scan: zero forbidden strings (`RichEditor`, `MarkdownEditor`, `CodeEditor`, `KeyValue`, `->url(`, `DeleteAction`, `ForceDeleteAction`, `RestoreAction`).
     - Notification copy scan: zero occurrences of `->getMessage()`.

### Checkpoint C (CP-C): Documentation Updates
9. **Commit 7**: `docs(architecture): tick UI step 4 and document resource actions subfolder`
   - Files:
     - `ARCHITECTURE.md`
10. **CP-C Verification**:
    - Execute MUT-1, MUT-2, MUT-3 mutation spot-checks.
    - Compile raw command output into final Implementation Report.

---

## 13. Risks, Assumptions & Conflicts (M2, M5, Q1)

### Resolution 1: Enable & Disable Visibility on Draft Content Blocks (M2, Q1)
- **Confirmed by User**: Option B confirmed by Achintha. Enable and Disable actions are visible strictly on published blocks. Draft blocks display Edit, Publish, Archive. Visibility closures in `ContentBlockTransitionActions.php`, the Section 7.1 matrix, and the dataset tests in `ContentBlockStateActionsTest.php` are aligned to this rule.

### Resolution 2: Open Decision OD-1 (Single Step Execution) (Q1)
- **Confirmed by User**: Single step execution confirmed by Achintha. The file manifest contains exactly 14 files, remaining below the 15-file hard split ceiling.

### Resolution 3: Open Decision OD-2 (Activity Log Assertions) (Q1)
- **Confirmed by User**: Tests-only activity log assertions confirmed by Achintha. No activity log UI is added to the View page; activity entries are asserted in feature tests.

### Resolution 4: Open Decision OD-3 (Confirmation Modals) (Q1)
- **Confirmed by User**: Confirmation modal on Publish, Enable, and Archive with zero confirmation modal on Disable (fast takedown) confirmed by Achintha.

### Resolution 5: Open Decision OD-4 (Table Row Click Navigation) (Q1)
- **Confirmed by User**: Table row click opens the read-only View page confirmed by Achintha.

### Resolution 6: Timezone Configuration Paths (M6)
- **Parity Proven**:
  - Display path: Admin UI displays (`starts_at`, `ends_at`, `created_at`, `updated_at`) strictly use `app(AdminTimezoneResolver::class)->resolve(config('admin.timezone', 'UTC'))`.
  - Persistence / dehydration path: Stored database values are UTC, and DateTimePicker inputs dehydrate into `config('app.timezone')` (`UTC`).
  - Section 10.4 includes parity test asserting View page wall time equals Edit form wall time under non-UTC timezone config.

### Risk 7: Concurrency & Stale Livewire State During Transitions (M1, M2)
- **Risk**: An admin opens the View page. Another admin archives the block. The first admin clicks Publish.
- **Mitigation**: Domain Actions use `lockForUpdate()`. The Action throws `IllegalStateTransitionException`. The shared transition action catches this exception, sends a danger notification with fixed copy (`"This block cannot be published in its current state."`), does not mutate state or log activity, and calls `$record->refresh()` so the View page updates to the current database state.

### Risk 8: Forbidden Component Scan Match on Action Methods
- **Risk**: `tests/Feature/Content/BlockFormRegistryTest.php` executes a string scan across `app/Filament/` searching for forbidden strings including `->url(` and `DeleteAction`.
- **Mitigation**:
  - Shared transition actions and table definitions never invoke `->url()`.
  - URLs are generated through `ViewAction`, `EditAction`, and `recordUrl()`.
  - Zero forbidden strings or action names exist in the new files.

---

## 14. Dependencies

From `composer.json` (zero new dependencies added):
- `php: ^8.3`
- `filament/filament: ^5.0`
- `spatie/laravel-activitylog: ^4.12`
- `spatie/laravel-data: ^4.23`
- `pestphp/pest: ^4.7`
- `pestphp/pest-plugin-laravel: ^4.0`
- Zero new composer or npm packages required.
