# Change Request: CR-P1-S2-01

**Title:** Update Legacy Test Assertions for Announcement Bar v2 and Sort Order Plumbing in Phase P1-S2a  
**Status:** Submitted for Reviewer Approval  
**Step ID:** P1-S2  
**Files Affected:**
1. `tests/Feature/Content/BlockFormRegistryTest.php` (Add to Commit 1 manifest)
2. `tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php` (Add to Commit 2 manifest)
3. `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` (Add to Commit 2 manifest)
4. `docs/blueprints/P1-S2/plan.md` (Update Section 4, 10.1, 12 manifests)

---

## 1. Rationale & Problem Analysis

During pre-implementation verification of Phase P1-S2a (Commits 1 & 2), static and mechanical analysis of the test suite against the plan revealed three existing tests from prior steps (P1-UI3a and P1-UI3b) that hardcode assertions against the legacy v1 / non-sortable state of Content Blocks:

### Finding 1: `BlockFormRegistryTest` Hardcodes 3-Component AnnouncementBarForm Schema
- **Plan Reference:** Section 5.5.1, Section 12 (Commit 1)
- **Problem:** `app/Filament/Blocks/AnnouncementBarForm.php` adds `secondary_text` and `locale_text` inputs (raising schema count from 3 to 5).
- **Collision:** `tests/Feature/Content/BlockFormRegistryTest.php:26-51` explicitly asserts:
  ```php
  $schema = AnnouncementBarForm::schema();
  expect($schema)->toHaveCount(3);
  /** @var TextInput $linkUrl */
  $linkUrl = $schema[1];
  /** @var Select $tone */
  $tone = $schema[2];
  ```
- **Failure:** Running `composer check` on Commit 1 fails with `Failed asserting that actual size 5 matches expected size 3` and type mismatches on index 1 and 2.
- **Missing from Plan:** `BlockFormRegistryTest.php` was omitted from the Commit 1 manifest.

### Finding 2: `UpdateContentBlockDataTest` Hardcodes Exclusion of `sort_order`
- **Plan Reference:** Section 5.4.2, Section 12 (Commit 2)
- **Problem:** `app/Domain/Content/Data/UpdateContentBlockData.php` adds `public ?int $sort_order = null` to support sort order updates via actions.
- **Collision:** `tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php:7-13` asserts:
  ```php
  expect($properties)->toContain('starts_at', 'ends_at')
      ->and($properties)->not->toContain('type', 'placement', 'status', 'is_enabled', 'sort_order');
  ```
- **Failure:** Running `composer check` on Commit 2 fails with `Failed asserting that an array does not contain 'sort_order'`.
- **Missing from Plan:** `UpdateContentBlockDataTest.php` was omitted from the Commit 2 manifest.

### Finding 3: `ContentBlockEditTest` Hardcodes Absence of `sort_order` on Edit Form
- **Plan Reference:** Section 5.5, Section 12 (Commit 2)
- **Problem:** `app/Filament/Admin/Resources/ContentBlockResource/Schemas/ContentBlockForm.php` adds `TextInput::make('sort_order')` so admins can reorder blocks in Filament.
- **Collision:** `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php:338-353` asserts:
  ```php
  it('does not expose status, is_enabled, sort_order, schema_version or type fields on edit form', function () {
      ...
      $component->assertFormFieldDoesNotExist('status')
          ->assertFormFieldDoesNotExist('is_enabled')
          ->assertFormFieldDoesNotExist('sort_order')
          ->assertFormFieldDoesNotExist('schema_version')
          ->assertFormFieldDoesNotExist('type');
  });
  ```
- **Failure:** Running `composer check` on Commit 2 fails with `Failed asserting that field 'sort_order' does not exist on edit form`.
- **Missing from Plan:** `ContentBlockEditTest.php` was omitted from the Commit 2 manifest.

---

## 2. Options Considered

### Option 1 (Recommended): Add the 3 Tests to Phase P1-S2a Manifests
- **Commit 1**: Add `tests/Feature/Content/BlockFormRegistryTest.php` (now 10 files). Update test to assert 5 schema fields: `text`, `secondary_text`, `locale_text`, `link_url`, `tone`.
- **Commit 2**: Add `tests/Unit/Domain/Content/Data/UpdateContentBlockDataTest.php` and `tests/Feature/Admin/ContentBlock/ContentBlockEditTest.php` (now 8 files). Update `UpdateContentBlockDataTest` to expect `sort_order` as allowed, and update `ContentBlockEditTest` to assert `sort_order` exists on the edit form while `status`, `is_enabled`, `schema_version`, and `type` remain unexposed.
- **Impact on ACs & Rules**: Zero negative impact. Fulfills AC-1, AC-8, and Rule T1. Both commits remain disciplined and under size limits.

### Option 2: Remove Form and DTO `sort_order` from Phase P1-S2a
- Revert exposing `sort_order` in `UpdateContentBlockData` and `ContentBlockForm`.
- **Impact on ACs & Rules**: Fails AC-4 (reordering in Filament), fails §13 Decision D-f, breaks seeder reset path, and requires redesign of admin reordering.

---

## 3. Recommendation

Adopt **Option 1**: incorporate the 3 test updates into Commit 1 (1 file) and Commit 2 (2 files) manifests for Phase P1-S2a.
