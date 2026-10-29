$raw = git show "main:docs/GetNow CMS Step Workflow (Claude, Achintha, Gemini).md"
$sec4 = $false
$oldIds = @()
foreach ($line in ($raw -split "\r?\n")) {
    if ($line -match '^## 4\.') {
        $sec4 = $true
        continue
    }
    if ($sec4 -and $line -match '^## ') {
        break
    }
    if ($sec4 -and $line -match '^\|\s*([A-Z0-9]+)\s*\|') {
        if ($Matches[1] -ne 'ID') {
            $oldIds += $Matches[1].Trim()
        }
    }
}

$expected = ($oldIds + @('DOC1', 'DOC2', 'DOC3', 'DOC4')) | Sort-Object

$agentsLines = Get-Content AGENTS.md
$agentsIds = @()
foreach ($line in $agentsLines) {
    if ($line -match '^\s*-\s*(?:\[\s*\]\s*)?\*\*([A-Z0-9]+)\*\*') {
        $agentsIds += $Matches[1].Trim()
    }
}

$agentsUnique = $agentsIds | Select-Object -Unique | Sort-Object
$duplicates = $agentsIds | Group-Object | Where-Object { $_.Count -gt 1 }

$diffOldToAgents = Compare-Object -ReferenceObject $expected -DifferenceObject $agentsUnique

Write-Host "Old register IDs count on main: $($oldIds.Count)"
Write-Host "Expected IDs count (main + DOC1..DOC4): $($expected.Count)"
Write-Host "AGENTS.md extracted IDs count: $($agentsIds.Count)"
Write-Host "AGENTS.md unique IDs count: $($agentsUnique.Count)"
Write-Host "Duplicates count in AGENTS.md: $($duplicates.Count)"
if ($duplicates.Count -gt 0) {
    Write-Host "Duplicates found: $($duplicates.Name -join ', ')"
}

Write-Host "Differences between Expected and AGENTS.md:"
if ($null -eq $diffOldToAgents -or $diffOldToAgents.Count -eq 0) {
    Write-Host "NONE. Exactly equal."
} else {
    $diffOldToAgents | Format-Table -AutoSize
}

Write-Host "All AGENTS.md IDs list:"
Write-Host ($agentsIds -join ", ")
