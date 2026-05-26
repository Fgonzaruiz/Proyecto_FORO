$xmlPath = "Default-theme.xml"
$xmlContent = [System.IO.File]::ReadAllText($xmlPath)

$templates = @{
    'headerinclude' = 'templates\mybb\global\headerinclude.html'
    'postbit' = 'templates\mybb\showthread\postbit.html'
    'newreply' = 'templates\mybb\newreply\newreply.html'
    'newthread' = 'templates\mybb\newthread\newthread.html'
    'showthread_quickreply' = 'templates\mybb\showthread\showthread_quickreply.html'
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
Write-Host "XML updated successfully."
