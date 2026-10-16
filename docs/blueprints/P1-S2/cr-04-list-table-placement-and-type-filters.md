[P1-S2 | CR-04 | Gemini]

Plan section: Section 3, Section 4 (File Manifest), Section 5.5, Section 10.1 (Test 7)
Plan says: Plan manifest and architectural specifications omitted `app/Filament/Admin/Resources/ContentBlockResource/Tables/ContentBlockTable.php` and `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php`. The placement filter on the admin list table retained a hardcoded single option: `'announcement_bar' => 'Announcement Bar'`.
Reality requires: 
1. Build the placement filter options dynamically from all cases of `Placement::cases()` using `Str::headline()`.
2. Add a dynamic block type filter built from registered block definitions in `BlockRegistry::all()`.
3. Add a drift-guard test in `tests/Feature/Admin/ContentBlock/ListContentBlocksTableTest.php` asserting that for every `Placement` case, the filter offers it and filtering returns the expected block record.
4. Add upper bound (`maxValue(2147483647)`) and Action validation on `sort_order` (m1).
5. Add an environment guard in `ContentSeeder` restricting execution to `local` and `testing` (m4).

Evidence:
`ContentBlockTable.php` lines 126–129:
```php
SelectFilter::make('placement')
    ->options([
        'announcement_bar' => 'Announcement Bar',
    ]),
```
Grep across `app/` and `resources/` for `announcement_bar` confirmed this was the sole remaining hardcoded placement filter in the Filament UI.

Options (max 2) with impact on ACs, rules, other files:
1. (Recommended) Dynamically derive table filters from `Placement::cases()` and `BlockRegistry::all()`, add the drift-guard test in `ListContentBlocksTableTest`, and add `sort_order` upper-bound validation and seeder environment guard. No impact on existing API contracts or schema versions.
2. Keep hardcoded filter array and update manually with 9 strings. (Fragile, fails AC-2 extensibility and drift guard requirements).

Recommendation: Option 1.
