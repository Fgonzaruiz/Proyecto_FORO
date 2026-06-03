$xmlPath = "Default-theme.xml"
$xmlContent = [System.IO.File]::ReadAllText($xmlPath)

$templates = @{
    'headerinclude' = 'templates\mybb\global\headerinclude.html'
    'header' = 'templates\mybb\global\header.html'
    'postbit' = 'templates\mybb\showthread\postbit.html'
    'newreply' = 'templates\mybb\newreply\newreply.html'
    'newthread' = 'templates\mybb\newthread\newthread.html'
    'showthread_quickreply' = 'templates\mybb\showthread\showthread_quickreply.html'
    'index_boardstats' = 'templates\mybb\index\index_boardstats.html'
    'index' = 'templates\mybb\index\index.html'
    'footer' = 'templates\mybb\global\footer.html'
}

foreach ($key in $templates.Keys) {
    $path = $templates[$key]
    if (Test-Path $path) {
        $html = [System.IO.File]::ReadAllText($path)
        $pattern = "(?s)(<template\s+name=`"$key`"[^>]*><\!\[CDATA\[)(.*?)(\]\]></template>)"
        
        $evaluator = [System.Text.RegularExpressions.MatchEvaluator] {
            param($match)
            return $match.Groups[1].Value + $html + "`n`t`t" + $match.Groups[3].Value
        }
        
        $xmlContent = [System.Text.RegularExpressions.Regex]::Replace($xmlContent, $pattern, $evaluator)
        Write-Host "Updated $key"
    } else {
        Write-Host "File not found: $path"
    }
}

[System.IO.File]::WriteAllText($xmlPath, $xmlContent)
Write-Host "Updated templates in XML."

# Update global.css with contents of rpg_custom.css
$rpgCssPath = "..\back\forum\rpg_custom.css"
if (Test-Path $rpgCssPath) {
    $xmlContent = [System.IO.File]::ReadAllText($xmlPath)
    $rpgCssContent = [System.IO.File]::ReadAllText($rpgCssPath)
    $rpgCssContent = $rpgCssContent -replace "`r`n", "`n"
    
    $cssPattern = "(?s)(<stylesheet\s+name=`"global\.css`"[^>]*><\!\[CDATA\[)(.*?)(\<\/stylesheet\>)"
    
    $cssEvaluator = [System.Text.RegularExpressions.MatchEvaluator] {
        param($match)
        $prefix = $match.Groups[1].Value
        $content = $match.Groups[2].Value
        $suffix = $match.Groups[3].Value
        
        $marker = "/* RPG Premium Modern Theme */"
        $markerIndex = $content.IndexOf($marker)
        
        if ($markerIndex -ge 0) {
            $baseCss = $content.Substring(0, $markerIndex)
        } else {
            $baseCss = $content + "`n"
        }
        
        # Clean trailing CDATA end if any
        $baseCss = $baseCss -replace "\]\]>\s*$", ""
        
        $newCss = $baseCss + $marker + "`n" + $rpgCssContent
        
        return $prefix + $newCss + "]]>`n`t`t" + $suffix
    }
    
    $xmlContent = [System.Text.RegularExpressions.Regex]::Replace($xmlContent, $cssPattern, $cssEvaluator)
    [System.IO.File]::WriteAllText($xmlPath, $xmlContent)
    Write-Host "Updated global.css stylesheet in XML from rpg_custom.css"
} else {
    Write-Host "rpg_custom.css not found, skipping stylesheet update."
}

Write-Host "XML updated successfully."
