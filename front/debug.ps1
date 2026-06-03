$text = [IO.File]::ReadAllText('Default-theme.xml')
$name = 'headerinclude'
$escaped = [regex]::Escape($name)
$pattern = "(?s)(<template\s+name=""$escaped""[^>]*><!\[CDATA\[)(.*?)(\]\]></template>)"
Write-Host "Pattern: $pattern"
$m = [regex]::Match($text, $pattern)
if ($m.Success) {
    Write-Host "MATCH"
} else {
    Write-Host "NO MATCH"
    $simple = '<template\s+name="headerinclude"'
    $m2 = [regex]::Match($text, $simple)
    if ($m2.Success) { Write-Host "Simple match: $($m2.Value)" }
    else { Write-Host "Simple also fails" }
}
