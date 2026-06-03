# Sincroniza plantillas fuente + rpg_custom.css → Default-theme.xml
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot)

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
    $pattern = "(?s)(<template\s+name=`"$([regex]::Escape($name))`"[^>]*><!\[CDATA\[)(.*?)(\]\]></template>)"
    $evaluator = [System.Text.RegularExpressions.MatchEvaluator] {
        param($match)
        return $match.Groups[1].Value + $html + "`n`t`t" + $match.Groups[3].Value
    }
    $newContent = [regex]::Replace($xmlContent, $pattern, $evaluator)
    if ($newContent -eq $xmlContent) {
        Write-Host "WARN $name not found in XML"
    } else {
        $xmlContent = $newContent
        Write-Host "OK $name"
    }
}

$rpgCssPath = '..\back\forum\rpg_custom.css'
if (Test-Path $rpgCssPath) {
    $rpgCss = ([IO.File]::ReadAllText($rpgCssPath)) -replace "`r`n", "`n"
    $cssPattern = "(?s)(<stylesheet\s+name=`"global\.css`"[^>]*><!\[CDATA\[)(.*?)(</stylesheet>)"
    $cssEval = [System.Text.RegularExpressions.MatchEvaluator] {
        param($match)
        $prefix = $match.Groups[1].Value
        $content = $match.Groups[2].Value
        $suffix = $match.Groups[3].Value
        $marker = '/* RPG Premium Modern Theme */'
        $markerPos = $content.IndexOf($marker)
        if ($markerPos -ge 0) { $baseCss = $content.Substring(0, $markerPos) }
        else { $baseCss = $content + "`n" }
        $baseCss = $baseCss -replace '\]\]>\s*$', ''
        $newCss = $baseCss + $marker + "`n" + $rpgCss
        return $prefix + $newCss + "]]>`n`t`t" + $suffix
    }
    $xmlContent = [regex]::Replace($xmlContent, $cssPattern, $cssEval)
    Write-Host 'OK global.css from rpg_custom.css'
}

[IO.File]::WriteAllText($xmlPath, $xmlContent)
Write-Host 'Default-theme.xml updated.'
