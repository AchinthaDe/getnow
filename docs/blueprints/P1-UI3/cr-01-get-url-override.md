# Change Request: CR-P1-UI3-01

**Title:** Override `getUrl()` in `ContentBlockResource` to serialize Eloquent models to ULID strings  
**Status:** Submitted for Reviewer Approval  
**File:** `app/Filament/Admin/Resources/ContentBlockResource.php` (+32 lines)  

---

## 1. Rationale & Vendor Inspection

1. **Vendor Code Path:**
   In Filament's `vendor/filament/filament/src/Resources/Resource/Concerns/CanGenerateUrls.php:L77`:
   ```php
   public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
   {
       ...
       $routeBaseName = static::getRouteBaseName($panel);

       return route("{$routeBaseName}.{$name}", $parameters, $isAbsolute);
   }
   ```
   Filament passes `$parameters` directly to Laravel's global `route()` helper. Filament's `Resource::getUrl()` never inspects or transforms Model parameters using `static::getRecordRouteKeyName()`.

2. **Laravel URL Generation Path:**
   When `route('filament.admin.resources.content-blocks.edit', ['record' => $record])` executes with an Eloquent `Model` instance `$record`, Laravel's `UrlGenerator` calls `$record->getRouteKey()`. On Eloquent models without an explicit override, this delegates to `$record->getKeyName()`, returning the integer primary key (`'id'`).

3. **Defect Without Override:**
   - Calling `ContentBlockResource::getUrl('edit', ['record' => $block])` or `$pageClass::getUrl(['record' => $block])` generated `http://localhost:8000/admin/content-blocks/24/edit` using raw numeric ID `24`.
   - Because Filament defines the route as `/{record}/edit` and binds via `ContentBlockResource::$recordRouteKeyName = 'public_id'`, visiting `/admin/content-blocks/24/edit` executes `WHERE public_id = '24'`, matching nothing and returning a 404 Not Found.
   - The test `ContentBlockEditTest > it resolves edit route with public_id and returns 404 for numeric id` failed asserting that the edit URL contains `public_id`.
   - Smoke tests calling `$pageClass::getUrl(['record' => $record])` failed with 404.
   - Table row links on `ListContentBlocks` generated `/24/edit`, breaking table row edit navigation.

---

## 2. Exact Override Implementation

In `app/Filament/Admin/Resources/ContentBlockResource.php`:

```php
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function getUrl(
        ?string $name = null,
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
        ?string $configuration = null,
    ): string {
        if (isset($parameters['record']) && $parameters['record'] instanceof Model) {
            $routeKeyName = static::getRecordRouteKeyName() ?? $parameters['record']->getRouteKeyName();
            $parameters['record'] = $parameters['record']->getAttribute($routeKeyName);
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters, $configuration);
    }
```

---

## 3. Architecture & Simplicity Assessment

- **Why not simpler?**  
  The only other solution would be overriding `getRouteKeyName()` directly on `ContentBlock` model (`app/Domain/Content/Models/ContentBlock.php`). However, modifying `ContentBlock.php`:
  1. Would touch an 18th file, breaching the frozen 17-file ceiling.
  2. Would alter default routing across the entire application for the Domain model to accommodate admin panel URL generation.
  Encapsulating the route key extraction in `ContentBlockResource::getUrl` keeps the concern strictly within the Filament Resource layer (respecting `static::getRecordRouteKeyName()`).
- **Type Safety & Robustness:**
  - Strictly typed signature matching parent `CanGenerateUrls::getUrl()`.
  - Null-safe check `isset($parameters['record']) && $parameters['record'] instanceof Model`.
  - Non-destructive: if `$parameters['record']` is already a string (e.g., `'01m4q9...'`), it passes through untouched.
  - Passes Larastan with 0 errors.

---

## 4. Test Coverage

- `ContentBlockEditTest > it resolves edit route with public_id and returns 404 for numeric id`: verifies `getUrl('edit', ['record' => $block])` contains `public_id` and raw numeric ID returns 404.
- `ContentBlockEditTest > it renders list and edit pages for platform admin via smoke test`: verifies `EditContentBlock::getUrl(['record' => $record])` renders 200 OK.
- `ContentBlockCreateTest > redirects to edit page after successful block creation`: verifies post-create redirection contains `public_id`.
- `ContentBlockCreateTest > renders create page for platform admin via smoke test`: verifies create URL renders 200 OK.
- `ContentBlockTable`: row edit action and table record URL resolution.
