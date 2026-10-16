# Change Request: CR-P1-S2-02

**Title:** Update `ContentBlockFactory` and `UpdateContentBlockTest` for Announcement Bar Schema v2 in Commit 1  
**Status:** Submitted for Reviewer Approval  
**Step ID:** P1-S2  
**Files Affected:**
1. `database/factories/ContentBlockFactory.php` (Add to Commit 1 manifest)
2. `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php` (Add to Commit 1 manifest)
3. `docs/blueprints/P1-S2/plan.md` (Update Section 4, 10.1, 12 manifests)

---

## 1. Rationale & Problem Analysis

During test suite verification of Commit 1 (`feat(content): upgrade announcement bar block to schema v2 with v1 upgrader`), `composer test` failed with 8 test failures across two test suites (`ContentBlockEditTest` and `UpdateContentBlockTest`):

### Finding 1: `ContentBlockFactory` Defaults to `schema_version = 1`
- **Plan Reference:** Section 5.3.1, Section 12 (Commit 1)
- **Problem:** `AnnouncementBarBlock::schemaVersion()` is bumped from `1` to `2`. However, `database/factories/ContentBlockFactory.php:22-27` defaults to `'schema_version' => 1` and a 3-field payload (`text`, `link_url`, `tone`).
- **Collision:** Any test calling `ContentBlock::factory()->create()` without explicitly overriding `schema_version` creates a stored block at `schema_version = 1`. When `UpdateContentBlock` runs (either directly or via Filament's `EditContentBlock::save`), it executes:
  ```php
  if ($block->schema_version !== $definition->schemaVersion()) {
      $block->schema_version = $definition->schemaVersion();
      $changed[] = 'schema_version';
  }
  ```
  Because the block was created at `schema_version = 1` and the definition is at `2`, `UpdateContentBlock` updates `schema_version` to `2` and logs an activity.
- **Failures Caused:**
  1. `ContentBlockEditTest.php:88`: `it does not log activity on no-op save for block without schedule` fails (`Activity::count()` is 1 instead of 0 because saving the form upgraded `schema_version` from 1 to 2).
  2. `ContentBlockEditTest.php:116`: `it hydrates schedule wall time symmetrically and avoids activity logging on no-op save under non-UTC timezone` fails for the same reason.
  3. `ContentBlockEditTest.php:68`: `it updates payload through action and logs activity with changed payload` expects `$changed === ['payload']`, but receives `['payload', 'schema_version']`.
  4. `ContentBlockEditTest.php:154`: `it persists correct UTC schedule when editing ends_at` expects `['ends_at']`, but receives `['payload', 'ends_at', 'schema_version']`.
  5. `UpdateContentBlockTest.php:105`: `it does not log activity or update user if identical data is submitted` fails because `schema_version` upgrade marks block dirty.
  6. `UpdateContentBlockTest.php:192`: `it writes nothing if identical data resubmitted but jsonb order differs` fails because `schema_version` changes from 1 to 2.
  7. `UpdateContentBlockTest.php:311`: `it logs specific changed properties` expects `['payload']`, but receives `['payload', 'schema_version']`.
- **Missing from Plan:** `database/factories/ContentBlockFactory.php` was omitted from the Commit 1 manifest.

### Finding 2: `UpdateContentBlockTest` Hardcodes 3-Field Announcement Bar v1 Payload Assertions
- **Plan Reference:** Section 5.3.1, Section 12 (Commit 1)
- **Problem:** `AnnouncementBarData` schema v2 includes `secondary_text` and `locale_text` (defaulting to `null`).
- **Collision:**
  - `UpdateContentBlockTest.php:45` asserts:
    ```php
    expect($updated->payload)->toBe(['text' => 'new text', 'link_url' => null, 'tone' => 'success'])
    ```
    failing with `missing secondary_text, locale_text`.
  - `UpdateContentBlockTest.php:177` seeds a raw SQL string with only 3 keys:
    ```sql
    UPDATE content_blocks SET payload = '{"tone": "info", "text": "hello", "link_url": null}'::jsonb WHERE id = ?
    ```
    which causes a false-positive dirty check when compared against the 5-field v2 payload.
- **Missing from Plan:** `UpdateContentBlockTest.php` was omitted from the Commit 1 manifest.

---

## 2. Options Considered

### Option 1 (Recommended): Add `ContentBlockFactory.php` and `UpdateContentBlockTest.php` to Commit 1 Manifest
- **Commit 1 Manifest (12 files)**:
  1. `app/Domain/Content/Contracts/BlockPayloadUpgrader.php`
  2. `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarV1ToV2Upgrader.php`
  3. `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarData.php`
  4. `app/Domain/Content/Blocks/AnnouncementBar/AnnouncementBarBlock.php`
  5. `app/Filament/Blocks/AnnouncementBarForm.php`
  6. `database/factories/ContentBlockFactory.php` (update default definition to `schema_version = 2` and 5-field payload)
  7. `tests/Fixtures/contracts/content-layout-v1.json`
  8. `tests/Unit/Domain/Content/Blocks/AnnouncementBarV1ToV2UpgraderTest.php`
  9. `tests/Feature/Domain/Content/Actions/CreateContentBlockTest.php`
  10. `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php`
  11. `tests/Feature/Content/BlockFormRegistryTest.php` (approved in CR-01)
  12. `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php` (update lines 45 and 177 to expect 5 fields)
- **Verification Evidence**:
  With this change, `composer check` passes completely green:
  - `Pint`: passed
  - `PHPStan`: passed with 0 errors
  - `Pest`: passed all 387 tests with 1826 assertions (`0 failed`)
- **Impact on ACs & Rules**: Clean, minimal, preserves backward-compatibility tests (`ServingHealthIntegrityTest` explicitly tests old v1 blocks and passes). Zero scope creep.

### Option 2: Leave `ContentBlockFactory` at Version 1 and Override in Individual Tests
- Keep `ContentBlockFactory` creating v1 blocks and override `'schema_version' => 2` in dozens of test cases across `ContentBlockEditTest` and `UpdateContentBlockTest`.
- **Drawback**: Defeats the purpose of factories (which should generate valid current-schema domain models by default). Every future test would need boilerplate overrides or falsely trigger version upgrade logic.

---

## 3. Recommendation

Adopt **Option 1**: Add `database/factories/ContentBlockFactory.php` and `tests/Feature/Domain/Content/Actions/UpdateContentBlockTest.php` to the Commit 1 manifest.
