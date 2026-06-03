$ErrorActionPreference = 'Stop'
$xmlPath = 'Default-theme.xml'
$manifest = Get-Content 'theme_templates.json' -Raw | ConvertFrom-Json
$xmlContent = [IO.File]::ReadAllText($xmlPath)

# Debug: count properties
$count = 0
foreach ($prop in $manifest.PSObject.Properties) {
    $count++
}
Write-Host "Found $count properties in manifest"

# Debug: check specific template
foreach ($prop in $manifest.PSObject.Properties) {
    $name = $prop.Name
    $pattern = "(?s)(<template\s+name=""$([regex]::Escape($name))""[^>]*><!\[CDATA\[)(.*?)(\]\]></template>)"
    $m = [regex]::Match($xmlContent, $pattern)
    if ($m.Success) {
        Write-Host "OK $name (regex matches)"
    } else {
        Write-Host "FAIL $name (regex fails)"
    }
}
