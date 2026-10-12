# CR-P1-UI3-02: Smoke Test Helper Pattern Mirroring Existing Caller

**Step ID:** P1-UI3  
**Status:** Approved & Implemented  
**Date:** 2026-10-12  

---

## 1. Context & Motivation

In review `[P1-UI3 | VERIFY r3]`, finding F1 was reopened because `composer stan` reported 3 `method.notFound` errors:
- `ContentBlockCreateTest.php:27`: `Call to an undefined method Pest\PendingCalls\TestCall::assertCreatePageRenders()`
- `ContentBlockEditTest.php:31`: `Call to an undefined method Pest\PendingCalls\TestCall::assertListPageRenders()`
- `ContentBlockEditTest.php:32`: `Call to an undefined method Pest\PendingCalls\TestCall::assertEditPageRenders()`

In Pest closures (`it(...)`), `$this` is statically inferred by PHPStan as `Pest\PendingCalls\TestCall`. Because `phpstan.neon` excludes `tests/Support` from its analyzed path list and does not include `pest-plugin-phpstan`, calling trait methods dynamically attached via `uses(FilamentSmokeTest::class)` cannot be resolved statically.

Reviewer approved **CR-02** with decision paths:
1. Run `git grep -n FilamentSmokeTest 8e8f68f -- tests`. If an existing caller pattern passes Larastan, mirror it exactly.
2. Otherwise convert `tests/Support/FilamentSmokeTest.php` to a final class with public static methods. If updating call sites touches any file outside the 17-file manifest, stop and send a CR.

---

## 2. Evidence from Base `8e8f68f`

**Command:** `git grep -n FilamentSmokeTest 8e8f68f -- tests`
```
8e8f68f:tests/Feature/Content/StorefrontSettingsTest.php:17:use Tests\Support\FilamentSmokeTest;
8e8f68f:tests/Feature/Content/StorefrontSettingsTest.php:22:    use FilamentSmokeTest, RefreshDatabase;
8e8f68f:tests/Support/FilamentSmokeTest.php:11:trait FilamentSmokeTest
```

### Analysis of the Existing Pattern
`tests/Feature/Content/StorefrontSettingsTest.php` is a PHPUnit test class extending `Tests\TestCase` that uses `FilamentSmokeTest` as a trait and calls `$this->assertPageRenders(...)`.
Running `./vendor/bin/phpstan analyse tests/Feature/Content/StorefrontSettingsTest.php` passes with **0 errors**.

If `FilamentSmokeTest.php` were converted into a `final class`, `StorefrontSettingsTest.php` would fail with `Fatal error: Cannot use Tests\Support\FilamentSmokeTest as trait because it is a class`. Updating `StorefrontSettingsTest.php` would touch an 18th file, violating the frozen 17-file manifest ceiling.

---

## 3. Resolution (Following Decision 1)

Following Decision 1, `tests/Support/FilamentSmokeTest.php` remains a trait with protected instance methods and `$this->actingAs()`.
In `ContentBlockCreateTest.php` and `ContentBlockEditTest.php`, the smoke tests mirror `StorefrontSettingsTest` by defining dedicated smoke test classes:
- `ContentBlockCreateSmokeTest extends \Tests\TestCase` using `FilamentSmokeTest`
- `ContentBlockEditSmokeTest extends \Tests\TestCase` using `FilamentSmokeTest`

All other feature tests remain standard Pest `it(...)` blocks.

### Rule Compliance & Integrity
- **C1, C2:** Strict types preserved.
- **C7:** Pint and Larastan pass with 0 errors without any baseline entries.
- **T2:** Smoke tests execute and assert 200 OK across List, Create, and Edit pages.
- **Manifest:** Zero files touched outside the approved 17.
