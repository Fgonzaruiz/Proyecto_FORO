$ErrorActionPreference = 'Stop'
$xmlPath = 'Default-theme.xml'
$manifest = Get-Content 'theme_templates.json' -Raw | ConvertFrom-Json
$xmlContent = [IO.File]::ReadAllText($xmlPath)

foreach ($prop in $manifest.PSObject.Properties) {
    $name = $prop.Name
    $relPath = $prop.Value -replace '/', [IO.Path]::DirectorySeparatorChar
    if (-not (Test-Path $relPath)) {
        Write-Host "SKIP $name (missing $relPath)"
        continue
    }
    $html = ([IO.File]::ReadAllText($relPath)) -replace "`r`n", "`n"
    $escaped = [regex]::Escape($name)
    $pattern = "(?s)(<template\s+name=`"$escaped`"[^>]*><!\[CDATA\[)(.*?)(\]\]></template>)"
    Write-Host "--- $name ---"
    $m = [regex]::Match($xmlContent, $pattern)
    if ($m.Success) {
        Write-Host "MATCH: $($m.Groups[1].Value) ..."
        $evaluator = {
            param($match)
            return $match.Groups[1].Value + $html + "`n`t`t" + $match.Groups[3].Value
        }
        $newContent = [regex]::Replace($xmlContent, $pattern, $evaluator)
        if ($newContent -eq $xmlContent) {
            Write-Host "  but Replace didn't change content!"
        } else {
            $xmlContent = $newContent
            Write-Host "OK $name"
        }
    } else {
        Write-Host "NO MATCH"
    }
}
