# [P1-DOC1a | IMPL-REPORT | Gemini]

---

## 1. Git Changes and Commit Verification

### 1.1 Branch and Commit Log
```text
PS C:\dev\getnow-cms> git log -n 14 --oneline
63340aa docs(menus): restore mutation trigger, add MenuFormTest enforcer, and update limits per verify r3
9e25126 docs(content-blocks): restore K1/K2/DB4 invariants, add registry recipe step, and add action trigger per verify r3
35f33ad docs(docs): clarify guide skeleton wording in docs README
1512151 docs(content-blocks): remove unmerged preview references and clarify capacity enforcement per verify r2
8667b33 docs(content-api): refine enforcers, add ADR 0002, and link architecture decisions per verify r1
d175d43 docs(site-settings): clean invariants, refine enforcers, and update recipe per verify r1
cc88d86 docs(menus): update enforcers, triggers, and history per verify r1
ae66747 docs(content-blocks): update invariants, enforcers, paths, and history per verify r1
6985d7d docs(content-api): add content-api area guide
166085a docs(site-settings): add site-settings area guide
7697257 docs(menus): add menus area guide
e95cd4e docs(content-blocks): add content-blocks area guide
8605dbc docs(docs): add documentation system index and area map in docs/README.md
6ad3622 Merge pull request #14 from AchinthaDe/feat/homepage-cms-blocks
```

### 1.2 Git Diff Against main (`git diff --name-status main...HEAD`)
```text
PS C:\dev\getnow-cms> git diff --name-status main...HEAD
A	docs/README.md
A	docs/areas/content-api.md
A	docs/areas/content-blocks.md
A	docs/areas/menus.md
A	docs/areas/site-settings.md
```

### 1.3 Git Status (`git status --short`)
```text
PS C:\dev\getnow-cms> git status --short
?? docs/blueprints/P1-DOC0/
?? docs/blueprints/P1-DOC1a/
?? docs/blueprints/P1-UI7/
?? p1-ui7.diff
```

---

## 2. Step-Specific Checks (Layer A)

### 2.1 File Line Count Checks
```text
PS C:\dev\getnow-cms> Get-ChildItem docs/areas/*.md | ForEach-Object { "$($_.Name): $((Get-Content $_.FullName).Count) lines" }
content-api.md: 92 lines
content-blocks.md: 144 lines
menus.md: 103 lines
site-settings.md: 85 lines

PS C:\dev\getnow-cms> (Get-Content docs/README.md).Count
50
```

### 2.2 Heading Structure Checks (`Select-String -Pattern '^## '`)
```text
PS C:\dev\getnow-cms> Get-ChildItem docs/areas/*.md | ForEach-Object { "$($_.Name):"; Select-String -Path $_.FullName -Pattern '^## ' | ForEach-Object { "  $($_.Line)" } }
content-api.md:
  ## How it works now
  ## Invariants
  ## Changing it
  ## Running and testing
  ## Known limits and open questions
  ## History
content-blocks.md:
  ## How it works now
  ## Invariants
  ## Changing it
  ## Running and testing
  ## Known limits and open questions
  ## History
menus.md:
  ## How it works now
  ## Invariants
  ## Changing it
  ## Running and testing
  ## Known limits and open questions
  ## History
site-settings.md:
  ## How it works now
  ## Invariants
  ## Changing it
  ## Running and testing
  ## Known limits and open questions
  ## History
```

### 2.3 Forbidden Patterns Check (AC-7)
```powershell
PS C:\dev\getnow-cms> Select-String -Path docs/areas/*.md -Pattern 'P1-[A-Z]+[0-9]|blueprint|\.php:\d+'
```
*(Zero matches returned)*

---

## 3. Glob Verification Script and Output (AC-3)

### 3.1 Script (`check_globs.ps1`)
```powershell
$root = (Get-Location).Path.Replace('\', '/')

function Get-GuidePaths($guidePath) {
    $lines = Get-Content $guidePath
    $inPaths = $false
    $globs = @()
    foreach ($line in $lines) {
        if ($line -match '^paths:') {
            $inPaths = $true
            continue
        }
        if ($inPaths) {
            if ($line -match '^[a-z0-9_-]+:') {
                break
            }
            if ($line -match '^\s*-\s*(.+)$') {
                $globs += $matches[1].Trim()
            }
        }
    }
    return $globs
}

$guides = [ordered]@{
    'content-blocks' = 'docs/areas/content-blocks.md'
    'menus'          = 'docs/areas/menus.md'
    'site-settings'  = 'docs/areas/site-settings.md'
    'content-api'    = 'docs/areas/content-api.md'
}

$areaFileSets = [ordered]@{}
$totalGlobs = 0
$emptyGlobs = 0

foreach ($area in $guides.Keys) {
    $guideFile = $guides[$area]
    $globs = Get-GuidePaths $guideFile
    $totalGlobs += $globs.Count
    Write-Host "[$area] has $($globs.Count) globs"
    $fileSet = @{}
    
    foreach ($g in $globs) {
        $resolved = @()
        if ($g.EndsWith('/**')) {
            $dir = $g.Substring(0, $g.Length - 3)
            if (Test-Path $dir) {
                $resolved = Get-ChildItem -Path $dir -Recurse -File | ForEach-Object {
                    $_.FullName.Replace('\', '/').Replace($root + '/', '')
                }
            }
        } else {
            $resolved = Get-ChildItem -Path $g -File -ErrorAction SilentlyContinue | ForEach-Object {
                $_.FullName.Replace('\', '/').Replace($root + '/', '')
            }
        }
        
        if ($resolved.Count -eq 0) {
            Write-Host "  EMPTY GLOB in ${area}: $g"
            $emptyGlobs++
        } else {
            foreach ($f in $resolved) {
                $fileSet[$f] = $true
            }
        }
    }
    $areaFileSets[$area] = $fileSet
    Write-Host "  Matches $($fileSet.Count) unique files"
}

Write-Host "`nTotal globs across all guides: $totalGlobs"
Write-Host "Total empty globs: $emptyGlobs"

$areaNames = @($areaFileSets.Keys)
$collisions = 0
for ($i = 0; $i -lt $areaNames.Count; $i++) {
    for ($j = $i + 1; $j -lt $areaNames.Count; $j++) {
        $a1 = $areaNames[$i]
        $a2 = $areaNames[$j]
        foreach ($f in $areaFileSets[$a1].Keys) {
            if ($areaFileSets[$a2].ContainsKey($f)) {
                $collisions++
                Write-Host "COLLISION: $f matches both $a1 and $a2"
            }
        }
    }
}

Write-Host "Total cross-guide collisions: $collisions"
```

### 3.2 Script Output
```text
PS C:\dev\getnow-cms> powershell -ExecutionPolicy Bypass -File C:\Users\achin\.gemini\antigravity-ide\brain\cad81ee0-6ffa-47ef-b518-fbc47d36f12c\scratch\check_globs.ps1
[content-blocks] has 47 globs
  Matches 113 unique files
[menus] has 14 globs
  Matches 22 unique files
[site-settings] has 7 globs
  Matches 7 unique files
[content-api] has 8 globs
  Matches 14 unique files

Total globs across all guides: 76
Total empty globs: 0
Total cross-guide collisions: 0
```

---

## 4. Front Matter Test Commands (AC-4, Condition C1)

### 4.1 Content Blocks Tests
```text
PS C:\dev\getnow-cms> php artisan config:clear
INFO Configuration cache cleared successfully.

PS C:\dev\getnow-cms> vendor/bin/pest tests/Feature/Domain/Content/ServingWindowTest.php tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php tests/Feature/Domain/Content/Rules/NoHtmlTest.php tests/Feature/Support/Rules/SafeLinkTest.php tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php tests/Feature/Domain/Content/Models/DBConstraintsTest.php tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php
{"tool":"pest","result":"passed","tests":46,"passed":46,"assertions":114,"duration_ms":4672}
```

### 4.2 Menus Tests
```text
PS C:\dev\getnow-cms> php artisan config:clear
INFO Configuration cache cleared successfully.

PS C:\dev\getnow-cms> vendor/bin/pest tests/Feature/Domain/Content/Policies/MenuPolicyTest.php tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php tests/Feature/Support/Rules/SafeLinkTest.php tests/Feature/Domain/Content/Actions/UpdateMenuTest.php tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php tests/Feature/Admin/Menu/MenuFormTest.php
{"tool":"pest","result":"passed","tests":32,"passed":32,"assertions":140,"duration_ms":3717}
```

### 4.3 Site Settings Tests
```text
PS C:\dev\getnow-cms> php artisan config:clear
INFO Configuration cache cleared successfully.

PS C:\dev\getnow-cms> vendor/bin/pest tests/Feature/Content/StorefrontSettingsTest.php
{"tool":"pest","result":"passed","tests":12,"passed":12,"assertions":41,"duration_ms":2008}
```

### 4.4 Content API Tests
```text
PS C:\dev\getnow-cms> php artisan config:clear
INFO Configuration cache cleared successfully.

PS C:\dev\getnow-cms> vendor/bin/pest tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php tests/Arch/ContentApiArchTest.php
{"tool":"pest","result":"passed","tests":23,"passed":23,"assertions":329,"duration_ms":3512}
```

---

## 5. Full Quality Gate (`composer check`)

```text
PS C:\dev\getnow-cms> composer check
{"tool":"pint","result":"passed"}{"tool":"phpstan","result":"passed","errors":0}

 INFO Configuration cache cleared successfully. 

{"tool":"pest","result":"passed","tests":429,"passed":429,"assertions":2602,"duration_ms":49750}
```

---

## 6. Verification of Decisions and Rule IDs (V4, F2, F4)

### 6.1 Architecture Decisions Table (§12)
| Decision ID | Quoted Text from ARCHITECTURE.md §12 | Guide Citation & Consistency |
|---|---|---|
| **D1** | *"CMS is its own project and database. Storefronts consume the Content API. No shared database."* | Cited in `content-api` (intro, decisions). Matched verbatim. |
| **D2** | *"Placements, menu keys and block types are defined in code. Content lives in the database."* | Cited in `content-blocks`, `menus` (intro, invariants, decisions). Matched verbatim. |
| **D3** | *"Design tokens stay in the storefront. Blocks choose from enums, never raw colours."* | Cited in `content-blocks`, `content-api` (decisions). Matched verbatim. |
| **D4** | *"Category data stays with the catalog source. Menus link by URL until a Catalog context exists."* | Cited in `menus` (invariants, limits, decisions). Matched verbatim. |
| **D5** | *"Phase 1 has no images. Media library arrives with the first image block in phase 2."* | Cited in `site-settings`, `content-blocks` (limits, decisions). Matched verbatim. |
| **D6** | *"Phase 1 admin access is one platform-admin flag. Roles come with the retailer panel."* | Cited in `content-blocks`, `site-settings` (invariants, limits, decisions). Matched verbatim. |
| **D7** | *"Published content edits go live immediately in phase 1. Revisions and preview come in phase 2."* | Cited in `content-blocks` (how it works, decisions). Matched verbatim. |
| **D8** | *"MaxCount is enforced at API output limit, not publish lock. Tie-break: `sort_order ASC, starts_at DESC NULLS LAST, id DESC`. One block = one message."* | **Conflicts with ADR 0004 on write-time enforcement.** Read-side mechanism in `GetLayoutContent` follows ADR 0001 and D8 tie-breaking (`sort_order ASC, starts_at DESC NULLS LAST, id DESC`) and slices candidates to `Placement::maxCount()` in memory. Write-time mechanism in domain actions follows ADR 0004 (enforcing instantaneous concurrency under `pg_advisory_xact_lock`). See Section 8 item 6. |
| **D9** | *"Concurrency & Locks: Content blocks use last-write-wins for payloads, pessimistic locking for state transitions. Menus use whole-tree atomic replacement under a row lock, last-write-wins."* | Quoted in `menus` (invariants, history). Content blocks concurrency is formally documented in ADR 0003. |

### 6.2 Cited Rule IDs from Rule Register / AGENTS.md (F4 Note)
> [!NOTE]
> The rule IDs below resolve through `docs/blueprints/rule-register.md` which catalogs and standardizes rule statements from `AGENTS.md`. `AGENTS.md` in this repository contains the normative rule statements in prose without alphanumeric prefixes; P1-DOC2 will reconcile and formalize rule IDs across documentation. The guides retain the canonical rule ID references.

| Rule ID | Quoted Rule Register Text | Guide Where Cited |
|---|---|---|
| **K1** | *"Block type keys are permanent. Never rename one."* | `content-blocks` (triggers, changing it) |
| **K2** | *"Every block payload has a `schema_version`. Changing a Data class shape means bumping the version and adding an upgrader. Old stored payloads must still load."* | `content-blocks` (triggers, changing it) |
| **K3** | *"No raw HTML, CSS or JavaScript fields anywhere. Fields are short text with max lengths, enums, validated links and booleans."* | `content-blocks` (#6), `site-settings` (#1) |
| **K4** | *"Links must be a relative path starting with `/` or an `https://` URL. Reject every other scheme."* | `content-blocks` (#7), `menus` (#4) |
| **K7** | *"Visibility (status, enabled, schedule) is decided server-side. The API never returns drafts, archived, disabled or out-of-window content."* | `content-blocks` (#1) |
| **K8** | *"The Content API is a contract. Within v1, only additive changes. Update `docs/contracts/content-api.md` and the fixture in `tests/Fixtures/contracts/` in the same change."* | `content-api` (#5, triggers, changing it) |
| **SEC4** | *"Admin access is checked server-side in `canAccessPanel`. Never rely on UI visibility as authorization."* | `content-blocks` (#8), `site-settings` (#2) |
| **SEC5** | *"Write sensitive content changes (publish, disable, menu and settings edits) to the activity log."* | `site-settings` (#3) |
| **DB4** | *"Add foreign keys, indexes for query paths, and `CHECK` constraints for statuses and non-negative numbers."* | `content-blocks` (triggers, how it works) |
| **C5** | *"The 'call an Action' rule applies to every write to persisted state, including Spatie settings. Pages that save settings call an Action."* | `site-settings` (#4) |
| **C6** | *"Use Form Requests or Data validation for input and Policies for authorization on every model exposed through Filament or the API."* | `content-blocks` (#8) |
| **S2** | *"Controllers, Filament resources and pages are thin: validate, authorize, call an Action, return output."* | `content-api` (#7, triggers) |

---

## 7. Claim-Source Verification Matrix & Recipe Verification (AC-6, V5, M1, M2, m5)

| Area | Statement / Recipe Step / Limit | Source File & Class::Method | Specific Enforcer Assertion or `Review only` | Read This Session |
|---|---|---|---|---|
| content-blocks | #1 Lifecycle visibility: A block is live only when published, enabled, and inside starts_at/ends_at window | `app/Domain/Content/Models/ContentBlock.php::isLive` | `tests/Feature/Domain/Content/ServingWindowTest.php` (`it('evaluates isLive() strictly based on status, schedule window, and enable state')`) | Y |
| content-blocks | #2 Deterministic tie-breaking order: `sort_order ASC`, `starts_at DESC NULLS LAST`, `id DESC` | `app/Domain/Content/Models/ContentBlock.php::scopeServingOrder` | `tests/Feature/Domain/Content/Models/ServingOrderScopeTest.php` (`it('applies deterministic D8 tie-breaking order: sort_order ASC, starts_at DESC NULLS LAST, id DESC')`) | Y |
| content-blocks | #3 Write-time live capacity enforcement under advisory lock (`pg_advisory_xact_lock`) | `app/Domain/Content/Actions/PublishContentBlock.php::__invoke` | `Review only` (ADR 0004) | Y |
| content-blocks | #4 Fail-closed serving health per block skips unservable blocks | `app/Domain/Content/Actions/GetBlockServingStatus.php::__invoke` | `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php` (`it('returns PayloadError with the right outcome for each broken state')`) | Y |
| content-blocks | #5 Constrained input: no raw HTML and safe links | `app/Support/Rules/NoHtml.php::__invoke`, `app/Support/Rules/SafeLink.php::__invoke` | `tests/Feature/Domain/Content/Rules/NoHtmlTest.php` (`it('fails with html tags')`) and `tests/Feature/Support/Rules/SafeLinkTest.php` (`it('accepts valid relative and https urls')`, `it('rejects unsafe schemes')`) | Y |
| content-blocks | #6 Permanent block type keys and versioned payloads | `app/Domain/Content/Contracts/BlockDefinition.php`, `app/Domain/Content/Actions/GetUpgradedBlockPayload.php` | `Review only` (keys and schema bumping) and `tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php` (`it('upgrades sequentially from v1 to v2 to v3')` for upgrade-on-read) | Y |
| content-blocks | #7 Database CHECK constraints on content_blocks table | `database/migrations/2026_10_07_091040_create_content_blocks_table.php` | `tests/Feature/Domain/Content/Models/DBConstraintsTest.php` (`it('enforces non-negative sort_order')`, `it('enforces valid status check')`, `it('enforces ends_at after starts_at')`, `it('enforces positive schema_version')`) | Y |
| content-blocks | #8 Platform admin policy authorization restricts operations to admins | `app/Domain/Content/Policies/ContentBlockPolicy.php` | `tests/Feature/Domain/Content/Policies/ContentBlockPolicyTest.php` (`it('allows admin to view, create, update, reorder and replicate')`, `it('denies non-admin everywhere')`, `it('denies delete, restore, and force delete even for admins')`) | Y |
| content-blocks | How it works: Allowed block types and maxCount per Placement | `app/Domain/Content/Enums/Placement.php::allowedBlockTypes`, `maxCount` | `Review only` (D2) | Y |
| content-blocks | Recipe: Adding block type - Step 1: Create definition implementing BlockDefinition and payload extending BlockPayload | `app/Domain/Content/Contracts/BlockDefinition.php`, `app/Domain/Content/Data/BlockPayload.php` | `Review only` (contract and base DTO class) | Y |
| content-blocks | Recipe: Adding block type - Step 2: Register definition in ContentServiceProvider | `app/Providers/ContentServiceProvider.php::register` | `Review only` (explicit registration list in provider per K6; verified in `tests/Feature/Domain/Content/Actions/GetBlockServingStatusTest.php`) | Y |
| content-blocks | Recipe: Adding block type - Step 3: Create Filament form schema in app/Filament/Blocks/<Name>Form.php | `app/Filament/Blocks/` | `Review only` (schema factory returning Filament form components) | Y |
| content-blocks | Recipe: Adding block type - Step 4: Register form schema in BlockFormRegistry::FORMS | `app/Filament/Blocks/BlockFormRegistry.php` | `Review only` (`BlockFormRegistry::get()` throws `UnknownBlockTypeException` if unregistered) | Y |
| content-blocks | Recipe: Adding block type - Step 5: Add block type string to allowed block types on Placement case | `app/Domain/Content/Enums/Placement.php::allowedBlockTypes` | `Review only` (Placement enum method) | Y |
| content-blocks | Recipe: Adding block type - Step 6: Add test coverage for payload validation, API output, and update contract fixtures | `tests/Feature/Domain/Content/`, `tests/Fixtures/contracts/content-layout-v1.json` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('matches the canonical contract fixture schema')`) | Y |
| content-blocks | Recipe: Updating payload - Step 1: Increment schemaVersion() in block definition class | `app/Domain/Content/Contracts/BlockDefinition.php::schemaVersion` | `Review only` (version integer method) | Y |
| content-blocks | Recipe: Updating payload - Step 2: Implement upgrader implementing BlockPayloadUpgrader | `app/Domain/Content/Contracts/BlockPayloadUpgrader.php::upgrade` | `Review only` (upgrader contract method) | Y |
| content-blocks | Recipe: Updating payload - Step 3: Wire upgrader into upgradePayload in definition class | `app/Domain/Content/Contracts/BlockDefinition.php::upgradePayload` | `Review only` (verified against `AnnouncementBarBlock::upgradePayload`) | Y |
| content-blocks | Recipe: Updating payload - Step 4: Add unit test verifying step-by-step upgrade from previous version | `tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php` | `tests/Feature/Domain/Content/Actions/GetUpgradedBlockPayloadTest.php` (`it('upgrades sequentially from v1 to v2 to v3')`) | Y |
| content-blocks | Limit: Advisory lock SQL inline in actions with CRC32 keys | `app/Domain/Content/Actions/PublishContentBlock.php`, `EnableContentBlock.php`, `UpdateContentBlock.php` | `Review only` (ADR 0004 code inspection in actions) | Y |
| menus | #1 Menu keys are code-owned; admins cannot create or delete menus | `app/Domain/Content/Policies/MenuPolicy.php::create`, `delete` | `tests/Feature/Domain/Content/Policies/MenuPolicyTest.php` (`it('denies create action for platform admin')`, `it('denies delete action for platform admin')`) | Y |
| menus | #2 Maximum nesting depth is 2; grandchild items are rejected | `app/Domain/Content/Actions/UpdateMenu.php::__invoke` | `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` (`it('rejects grandchild items beyond depth 2')`) | Y |
| menus | #3 Maximum 10 top-level items and maximum 10 children per parent | `app/Domain/Content/Actions/UpdateMenu.php::__invoke` | `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` (`it('rejects exceeding 10 top level items')`, `it('rejects exceeding 10 children for a single parent')`) | Y |
| menus | #4 URLs must be relative `/` or `https://` | `app/Support/Rules/SafeLink.php::__invoke` and `app/Domain/Content/Actions/UpdateMenu.php` | `tests/Feature/Support/Rules/SafeLinkTest.php` and `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` (`it('rejects link violations with InvalidMenuTreeException')`) | Y |
| menus | #5 Existing item public_ids are stable across saves when updated in place | `app/Domain/Content/Actions/UpdateMenu.php::__invoke` | `tests/Feature/Domain/Content/Actions/UpdateMenuTest.php` (`it('preserves public_id values on item reordering and reparenting')`) | Y |
| menus | #6 Disabled parents suppress children from appearing in the tree output | `app/Domain/Content/Actions/GetMenuTree.php::__invoke` | `tests/Feature/Domain/Content/Actions/GetMenuTreeTest.php` (`it('excludes disabled children and children of disabled parents')`) | Y |
| menus | #7 Mutations execute whole-tree reconciliation under a row lock inside a single transaction | `app/Domain/Content/Actions/UpdateMenu.php::__invoke` | `Review only` (D9) | Y |
| menus | Trigger: Hidden public_id fields on MenuForm repeaters prevent duplication | `app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php::form` | `tests/Feature/Admin/Menu/MenuFormTest.php` (`it('saves form data through UpdateMenu and re-hydrates state without duplicate creation on second save')`) | Y |
| menus | Recipe: Adding menu - Step 1: Add new case to MenuKey enum | `app/Domain/Content/Enums/MenuKey.php` | `Review only` (backed enum cases) | Y |
| menus | Recipe: Adding menu - Step 2: Create idempotent migration in database/migrations/ | `database/migrations/2026_10_14_000000_create_menus_and_menu_items_tables.php` | `tests/Feature/Domain/Content/Models/MenuSchemaAndProvisioningTest.php` (`it('provisions all five required menus')`) | Y |
| menus | Recipe: Adding menu - Step 3: Update docs/contracts/content-api.md and fixture | `docs/contracts/content-api.md`, `tests/Fixtures/contracts/content-layout-v1.json` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('includes every defined placement and menu key even when empty or unprovisioned')`) | Y |
| menus | Recipe: Adding menu - Step 4: Verify tests in MenuSchemaAndProvisioningTest and GetLayoutContentTest | `tests/Feature/Domain/Content/Models/MenuSchemaAndProvisioningTest.php`, `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | `tests/Feature/Domain/Content/Models/MenuSchemaAndProvisioningTest.php` and `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | Y |
| menus | Recipe: Modifying validation - Step 1: Adjust constraints in UpdateMenu and MenuItemData | `app/Domain/Content/Actions/UpdateMenu.php`, `app/Domain/Content/Data/MenuItemData.php` | `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` (`it('rejects grandchild items beyond depth 2')`) | Y |
| menus | Recipe: Modifying validation - Step 2: Update form schema components in MenuForm | `app/Filament/Admin/Resources/MenuResource/Schemas/MenuForm.php` | `tests/Feature/Admin/Menu/MenuFormTest.php` (`it('saves form data through UpdateMenu and re-hydrates state without duplicate creation on second save')`) | Y |
| menus | Recipe: Modifying validation - Step 3: Add test cases in UpdateMenuRejectionsTest and MenuFormTest | `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php`, `tests/Feature/Admin/Menu/MenuFormTest.php` | `tests/Feature/Domain/Content/Actions/UpdateMenuRejectionsTest.php` and `tests/Feature/Admin/Menu/MenuFormTest.php` | Y |
| menus | Limit: Last-write-wins tree reconciliation under row lock | `app/Domain/Content/Actions/UpdateMenu.php` | `Review only` (D9) | Y |
| site-settings | #1 Strict length bounds and HTML `<` and `>` regex rejection | `app/Domain/Content/Data/StorefrontSettingsData.php` | `tests/Feature/Content/StorefrontSettingsTest.php` (`test_validates_rules_exactly_via_livewire()`) | Y |
| site-settings | #2 Non-admin visiting or mounting settings page returns HTTP 403 Forbidden | `app/Filament/Admin/Pages/ManageStorefrontSettings.php::canAccess` | `tests/Feature/Content/StorefrontSettingsTest.php` (`test_denies_non_admins_from_viewing_settings_page()`) | Y |
| site-settings | #3 Modifying settings records old and updated values to the activity log | `app/Domain/Content/Actions/UpdateStorefrontSettings.php::__invoke` | `tests/Feature/Content/StorefrontSettingsTest.php` (`test_updates_settings_and_writes_activity_log_on_exact_changes()`) | Y |
| site-settings | #4 Settings mutations execute via UpdateStorefrontSettings action | `app/Domain/Content/Actions/UpdateStorefrontSettings.php::__invoke` | `Review only` (C5) | Y |
| site-settings | Recipe: Adding setting - Step 1: Add typed property to StorefrontSettings | `app/Domain/Content/Settings/StorefrontSettings.php` | `Review only` (public typed property declaration on Spatie settings class) | Y |
| site-settings | Recipe: Adding setting - Step 2: Create Spatie settings migration in database/settings/ | `database/settings/2026_10_07_091043_create_storefront_settings.php` | `Review only` (`database/settings/` migration executed on test setup) | Y |
| site-settings | Recipe: Adding setting - Step 3: Add property with validation attributes to StorefrontSettingsData | `app/Domain/Content/Data/StorefrontSettingsData.php` | `tests/Feature/Content/StorefrontSettingsTest.php` (`test_validates_rules_exactly_via_livewire()`) | Y |
| site-settings | Recipe: Adding setting - Step 4: Add input component to StorefrontSettingsForm | `app/Filament/Admin/Schemas/StorefrontSettingsForm.php::form` | `Review only` (Filament form schema field definition) | Y |
| site-settings | Recipe: Adding setting - Step 5: Update UpdateStorefrontSettings property mapping | `app/Domain/Content/Actions/UpdateStorefrontSettings.php::__invoke` | `tests/Feature/Content/StorefrontSettingsTest.php` (`test_updates_settings_and_writes_activity_log_on_exact_changes()`) | Y |
| site-settings | Recipe: Adding setting - Step 6: If exposed in storefront layout, add to LayoutSettingsData and GetLayoutContent | `app/Domain/Content/Data/LayoutSettingsData.php`, `app/Domain/Content/Actions/GetLayoutContent.php` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('returns storefront settings matching configured values')`) | Y |
| site-settings | Recipe: Adding setting - Step 7: Update docs/contracts/content-api.md and fixture | `docs/contracts/content-api.md`, `tests/Fixtures/contracts/content-layout-v1.json` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('matches the canonical contract fixture schema')`) | Y |
| site-settings | Recipe: Adding setting - Step 8: Add test cases in StorefrontSettingsTest and GetLayoutContentTest | `tests/Feature/Content/StorefrontSettingsTest.php`, `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | `tests/Feature/Content/StorefrontSettingsTest.php` and `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | Y |
| content-api | #1 Public read-only endpoint without authentication credentials | `app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php` | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` (`it('returns HTTP 200 with layout data and meta without authentication')`) | Y |
| content-api | #2 Deterministic revision ETag caching with 304 Not Modified | `app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php` | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` (`it('handles conditional GET returning 304 on matching ETag and 200 on stale ETag')`) | Y |
| content-api | #3 Fail-closed serving health per block skips corrupted block without failing response | `app/Domain/Content/Actions/GetLayoutContent.php::__invoke` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('falls back to next valid candidate when top candidate is unservable')`) | Y |
| content-api | #4 All placements and menus present even when empty | `app/Domain/Content/Actions/GetLayoutContent.php::__invoke` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('includes every defined placement and menu key even when empty or unprovisioned')`) | Y |
| content-api | #5 Additive contract changes only within v1 | `docs/contracts/content-api.md` | `Review only` (monitored by `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` `it('matches the canonical contract fixture schema')`) | Y |
| content-api | #6 Per-minute IP rate limiting applies (HTTP 429 on excessive requests) | `config/content.php`, `app/Providers/AppServiceProvider.php` | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` (`it('throttles requests exceeding configured rate limit with HTTP 429')`) | Y |
| content-api | #7 Controller is thin and delegates to GetLayoutContent without queries or domain models | `app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php` | `tests/Arch/ContentApiArchTest.php` (`it('ensures content api controllers are thin and use actions')`) | Y |
| content-api | Limit: No application caching beyond HTTP ETags | `app/Http/Controllers/Api/V1/Content/GetLayoutContentController.php`, `app/Domain/Content/Actions/GetLayoutContent.php` | `Review only` (Search across `GetLayoutContentController.php` and `GetLayoutContent.php` confirms 0 calls to `Cache::` or `cache()`; layout responses rely entirely on HTTP conditional 304 via ETag header comparison) | Y |
| content-api | Recipe: Extending contract - Step 1: Add optional or defaulted property to relevant Layout*Data DTO | `app/Domain/Content/Data/LayoutContentData.php` | `Review only` (spatie/laravel-data property definition) | Y |
| content-api | Recipe: Extending contract - Step 2: Update GetLayoutContent assembly logic | `app/Domain/Content/Actions/GetLayoutContent.php::__invoke` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('returns full layout structure matching contract')`) | Y |
| content-api | Recipe: Extending contract - Step 3: Update docs/contracts/content-api.md specification | `docs/contracts/content-api.md` | `Review only` (documentation update per rule K8) | Y |
| content-api | Recipe: Extending contract - Step 4: Update contract test fixture tests/Fixtures/contracts/content-layout-v1.json | `tests/Fixtures/contracts/content-layout-v1.json` | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` (`it('matches the canonical contract fixture schema')`) | Y |
| content-api | Recipe: Extending contract - Step 5: Verify contract tests in GetLayoutContentTest | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` | `tests/Feature/Domain/Content/Actions/GetLayoutContentTest.php` (`it('matches the canonical contract fixture schema')`) | Y |
| content-api | Recipe: Adjusting rate limiting - Step 1: Modify rate limit config in config/content.php or CONTENT_API_RATE_LIMIT_PER_MINUTE | `config/content.php`, `app/Providers/AppServiceProvider.php::boot` | `Review only` (configuration parameter) | Y |
| content-api | Recipe: Adjusting rate limiting - Step 2: Verify throttle threshold in GetLayoutContentEndpointTest | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` | `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` (`it('throttles requests exceeding configured rate limit with HTTP 429')`) | Y |

---

## 8. Section 13 Conflicts and Resolutions (F1, F2)

1. **Menu Item Public ID Preservation in Code vs. Brief AC-8**:
   - *Conflict*: `brief.md` AC-8 stated *"menus: items link by plain URL (D4); UpdateMenu recreates all items with new public_ids on every save."*
   - *Active Code Reality*: [UpdateMenu.php](file:///c:/dev/getnow-cms/app/Domain/Content/Actions/UpdateMenu.php) (lines 116–172) and [UpdateMenuTest.php](file:///c:/dev/getnow-cms/tests/Feature/Domain/Content/Actions/UpdateMenuTest.php) (`it('preserves public_id values on item reordering and reparenting')`) explicitly update matching items in place, preserving existing ULID `public_id`s and database IDs.
   - *Resolution*: Condition C3 formally withdrew the claim. The guide documents in-place item updating and stable `public_id` values, enforced by `UpdateMenuTest.php`.
2. **ARCHITECTURE.md Decision D9 Wording vs. Active Code**:
   - *Conflict*: [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) Decision D9 states: *"Menus use whole-tree atomic replacement under a row lock, last-write-wins."*
   - *Active Code Reality*: `UpdateMenu` reconciles the submitted tree: items with matching `public_id` update in place, unkeyed items are inserted, and omitted items are deleted.
   - *Resolution*: Documented in this report per Condition C3; [ARCHITECTURE.md](file:///c:/dev/getnow-cms/ARCHITECTURE.md) is left unmodified.
3. **Audit Report Non-Existent Unit Test Globs**:
   - *Conflict*: Audit report Section 4 listed eight globs under `tests/Unit/Domain/Content/...` and `tests/Unit/Support/...`.
   - *Active Code Reality*: Verification showed these files reside under `tests/Feature/`.
   - *Resolution*: Non-existent globs were omitted from guide `paths:`.
4. **Composer Test Argument Forwarding**:
   - *Conflict*: Running `composer test -- --filter=...` fails because the composer script triggers `@php artisan config:clear --ansi` which does not accept `--filter`.
   - *Resolution*: Documented in [docs/README.md](file:///c:/dev/getnow-cms/docs/README.md) and all four area guides: execute `php artisan config:clear` first, followed by `vendor/bin/pest <path>`. Verified against `composer.json` lines 64–67:
     ```json
     "test": [
         "@php artisan config:clear --ansi",
         "@php artisan test"
     ],
     ```
     and `phpunit.xml` lines 27–28:
     ```xml
     <env name="DB_CONNECTION" value="pgsql"/>
     <env name="DB_DATABASE" value="phone_store_test"/>
     ```
5. **Pending UI Feature Branch Files Ownership (F1 Resolution)**:
   - *Conflict*: Files assigned by the audit (`ContentBlockPreviewController`, `CountLiveBlocksForPlacement`, `ContentBlockPreviewData`, `resources/views/filament/admin/resources/content-block-resource/**`, and ADR 0005) originate from unmerged feature branches (`feat/admin-block-previews`, `feat/reorganize-menu-form`) and are not present on `main`. Guides state only what is true on `main`.
   - *Resolution*: Per finding F1, all references to ephemeral admin preview approximation, `ContentBlockPreviewController`, and pending file ownership were removed from `content-blocks.md`. The branches carrying these files (`feat/admin-block-previews`, `feat/reorganize-menu-form`) will update `content-blocks.md` under the README update rule when merged into `main`. The three enums present on `main` (`ServingStatus`, `ServingHealthOutcome`, `ServiceIcon`) remain in `paths:`.
6. **Decision D8 Text vs. ADR 0004 Write-Time Capacity Enforcement (F2 Resolution)**:
   - *Conflict*: ARCHITECTURE.md §12 Decision D8 states: *"MaxCount is enforced at API output limit, not publish lock."* Conversely, ADR 0004 accepted write-time live capacity enforcement in domain actions under a PostgreSQL transaction advisory lock (`pg_advisory_xact_lock`).
   - *Resolution*: The guides distinguish between the two mechanisms exactly as implemented:
     - Read-side slicing: `GetLayoutContent` retrieves candidate blocks ordered by `servingOrder()`, pre-indexes them by placement in memory, and slices to `Placement::maxCount()` (ADR 0001 and D8 tie-breaking).
     - Write-time live capacity enforcement: Activating or scheduling a block enforces instantaneous schedule concurrency against `Placement::maxCount()` under an advisory lock, citing ADR 0004 rather than D8.
     - `ARCHITECTURE.md` is left unedited; P1-DOC2 will reconcile Decision D8.

---

## 9. Repaired Gaps (`Review only` Enforcers)

The following rows in the guide Invariants tables are enforced by architectural design and code review rather than automated tests:

1. **content-blocks Invariant 3**: *Write-time live capacity enforcement (`pg_advisory_xact_lock`)*.
   - *Reason*: Domain actions serialize capacity checks using PostgreSQL transaction-level advisory locks (ADR 0004). The advisory lock mechanism itself is verified by code review and static inspection.
2. **content-blocks Invariant 6**: *Permanent block type keys and versioned payloads (keys and schema bumping)*.
   - *Reason*: Permanence of block type keys and requirement of bumping `schemaVersion()` alongside an upgrader are code architecture policies (K1, K2). The upgrade loop itself on read is automated via `GetUpgradedBlockPayloadTest.php`.
3. **menus Invariant 7**: *Atomic whole-tree mutation under row lock*.
   - *Reason*: Transactional row lock (`Menu::lockForUpdate()`) and atomic multi-row reconciliation are reviewed statically (D9); no race condition integration test asserts process serialization.
4. **site-settings Invariant 4**: *Action encapsulation for mutations*.
   - *Reason*: Architectural rule C5 requires all writes to persisted state to go through an Action (`UpdateStorefrontSettings`). No architecture test currently asserts the negative absence of direct Spatie settings writes.
5. **content-api Invariant 5**: *Additive changes only within v1*.
   - *Reason*: Backward compatibility rule K8 is architectural policy. The contract fixture test `tests/Feature/Api/V1/Content/GetLayoutContentEndpointTest.php` guards against unintended schema drift, but verifying that schema changes are non-breaking is a review responsibility.
