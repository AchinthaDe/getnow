# Change Request: CR-P1-S2-03

**Title:** Add `tests/Pest.php` to Commit 3 Manifest to Bind `TestCase::class` to `Unit` Test Directory  
**Status:** Submitted for Reviewer Approval  
**Step ID:** P1-S2  
**Files Affected:**
1. `tests/Pest.php` (Add to Commit 3 manifest)
2. `docs/blueprints/P1-S2/plan.md` (Update Section 4, 12 manifests)

---

## 1. Rationale & Problem Analysis

During execution and test suite verification of Commit 3 (`feat(content): add homepage content block definitions, data classes, and service icon enum`), `composer check` failed with container binding errors across the test suite:

### Finding: Spatie Data `validateAndCreate()` Requires Laravel Container in Unit Tests
- **Plan Reference:** Section 10.1 (Tests 2, 3, 4, 5, 6), Section 12 (Commit 3)
- **Problem:**
  The plan specifies 5 new unit tests under `tests/Unit/Domain/Content/Blocks/` to validate block payload DTOs (`PromoSlideDataTest`, `FeatureBannerDataTest`, `ServiceItemDataTest`, `SectionHeaderDataTest`, `BrandStripDataTest`).
  Each of these tests calls `DataClass::validateAndCreate($payload)`. Spatie Laravel Data validates payloads by resolving `config` and `validator` from the Laravel service container.
- **Collision:**
  `tests/Pest.php` currently binds `TestCase::class` only to `Feature`:
  ```php
  uses(
      TestCase::class,
      RefreshDatabase::class,
  )->in('Feature');
  ```
  `tests/Unit/` had no `TestCase::class` binding.
  When the new unit tests run without `TestCase`, they throw:
  `Target class [config] does not exist.`
  Furthermore, an existing unit test `tests/Unit/Filament/Admin/Services/AdminTimezoneResolverTest.php` already invoked the Laravel container (`app(AdminTimezoneResolver::class)` and `Log::shouldReceive()`), which previously only passed because no unit test executed PHPUnit's `tearDown()`. If individual block unit tests bind `uses(TestCase::class)` at the file level, `tearDown()` flushes the container, causing `AdminTimezoneResolverTest` to fail with `Target class [log] does not exist.`
- **Missing from Plan:**
  `tests/Pest.php` was omitted from the Commit 3 manifest.

---

## 2. Options Considered

### Option 1 (Recommended): Add `tests/Pest.php` to Commit 3 Manifest with `TestCase` Bound to `Unit`
- Add to `tests/Pest.php`:
  ```php
  uses(
      TestCase::class,
  )->in('Unit');
  ```
- **Commit 3 Manifest (20 files)**:
  1. `app/Domain/Content/Enums/PromoTheme.php`
  2. `app/Domain/Content/Enums/ServiceIcon.php`
  3. `app/Domain/Content/Blocks/PromoSlide/PromoSlideBlock.php`
  4. `app/Domain/Content/Blocks/PromoSlide/PromoSlideData.php`
  5. `app/Domain/Content/Blocks/FeatureBanner/FeatureBannerBlock.php`
  6. `app/Domain/Content/Blocks/FeatureBanner/FeatureBannerData.php`
  7. `app/Domain/Content/Blocks/ServiceItem/ServiceItemBlock.php`
  8. `app/Domain/Content/Blocks/ServiceItem/ServiceItemData.php`
  9. `app/Domain/Content/Blocks/SectionHeader/SectionHeaderBlock.php`
  10. `app/Domain/Content/Blocks/SectionHeader/SectionHeaderData.php`
  11. `app/Domain/Content/Blocks/BrandStrip/BrandStripBlock.php`
  12. `app/Domain/Content/Blocks/BrandStrip/BrandStripItemData.php`
  13. `app/Domain/Content/Blocks/BrandStrip/BrandStripData.php`
  14. `tests/Pest.php` (CR-03: binds `TestCase::class` to `Unit`)
  15. `tests/Unit/Domain/Content/Blocks/PromoSlideDataTest.php`
  16. `tests/Unit/Domain/Content/Blocks/FeatureBannerDataTest.php`
  17. `tests/Unit/Domain/Content/Blocks/ServiceItemDataTest.php`
  18. `tests/Unit/Domain/Content/Blocks/SectionHeaderDataTest.php`
  19. `tests/Unit/Domain/Content/Blocks/BrandStripDataTest.php`
  20. `tests/Unit/Domain/Content/Enums/ServiceIconTest.php`
- **Verification Evidence**:
  With this change, `composer check` passes completely green:
  - `Pint`: passed
  - `PHPStan`: passed with 0 errors
  - `Pest`: passed all 393 tests with 1894 assertions (`0 failed`)
- **Impact on ACs & Rules**: Standard Pest configuration; ensures all unit tests interacting with Laravel or Spatie Data run against an isolated application instance. Zero side effects.

### Option 2: Move the 5 Data Tests to `tests/Feature/Domain/Content/Blocks/`
- Change test paths from `tests/Unit/...` to `tests/Feature/...` (matching legacy `AnnouncementBarDataTest.php`).
- **Drawback**: Deviates from Plan §10.1 and §12 test paths and requires updating references across documentation and plans. Does not address `AdminTimezoneResolverTest.php` being in `tests/Unit`.

---

## 3. Recommendation

Adopt **Option 1**: Add `tests/Pest.php` to the Commit 3 manifest with `uses(TestCase::class)->in('Unit');`.
