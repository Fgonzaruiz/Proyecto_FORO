# Compara plantillas fuente con Default-theme.xml. SOLO LECTURA - no modifica archivos.
# Uso: .\diff_theme_source.ps1 [-Verbose]
param([switch]$Verbose)

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$xmlFile = Join-Path $PSScriptRoot 'Default-theme.xml'
$manifestFile = Join-Path $PSScriptRoot 'theme_templates.json'

if (-not (Test-Path $xmlFile)) { Write-Error "No se encontró $xmlFile"; exit 2 }
if (-not (Test-Path $manifestFile)) { Write-Error "No se encontró $manifestFile"; exit 2 }

$templates = Get-Content $manifestFile -Raw | ConvertFrom-Json
$xmlContent = [System.IO.File]::ReadAllText($xmlFile)

$legacyInSource = @{
    index = @{
        '#game-calendar-bar' = 'Calendario inline legacy (#game-calendar-bar)'
        '#modal_calendar' = 'Modal calendario legacy (#modal_calendar)'
        'id="calendar-grid"' = 'Grid de 100 días legacy (calendar-grid)'
    }
}

$mustKeepIfInXml = @{
    index = @{
        'rpg-tablon-container' = 'Tablón premium (rpg-tablon-container)'
        'tablon-fecha-widget' = 'Widget fecha del tablón (tablon-fecha-widget)'
        'roleplay-hero' = 'Banner hero (roleplay-hero)'
    }
    header = @{
        'game_rol_header_html' = 'Fecha on-rol del plugin (game_rol_header_html)'
    }
}

function Normalize-TemplateContent([string]$content) {
    ($content -replace "`r`n", "`n").TrimEnd("`n", "`t", ' ')
}

function Get-TemplateFromXml([string]$xml, [string]$name) {
    $pattern = "(?s)<template\s+name=`"$([regex]::Escape($name))`"[^>]*><!\[CDATA\[(.*?)\]\]></template>"
    $m = [regex]::Match($xml, $pattern)
    if (-not $m.Success) { return $null }
    return Normalize-TemplateContent $m.Groups[1].Value
}

function Read-SourceTemplate([string]$relativePath) {
    $path = Join-Path $PSScriptRoot ($relativePath -replace '/', [IO.Path]::DirectorySeparatorChar)
    if (-not (Test-Path $path)) { return $null }
    return Normalize-TemplateContent ([IO.File]::ReadAllText($path))
}

function Get-DiffSummary([string]$source, [string]$xml) {
    $sourceLines = if ($source) { $source -split "`n" } else { @() }
    $xmlLines = if ($xml) { $xml -split "`n" } else { @() }
    $max = [Math]::Max($sourceLines.Count, $xmlLines.Count)
    $changed = 0
    $firstChanges = @()

    for ($i = 0; $i -lt $max; $i++) {
        $a = if ($i -lt $sourceLines.Count) { $sourceLines[$i] } else { $null }
        $b = if ($i -lt $xmlLines.Count) { $xmlLines[$i] } else { $null }
        if ($a -ne $b) {
            $changed++
            if ($firstChanges.Count -lt 8) {
                $firstChanges += [pscustomobject]@{ Line = $i + 1; Source = $a; Xml = $b }
            }
        }
    }

    return [pscustomobject]@{
        SourceLines = $sourceLines.Count
        XmlLines = $xmlLines.Count
        ChangedLines = $changed
        FirstChanges = $firstChanges
    }
}

function Truncate-Line([string]$line, [int]$max = 100) {
    if ($null -eq $line) { return '' }
    $line = $line -replace "`t", '  '
    if ($line.Length -le $max) { return $line }
    return $line.Substring(0, $max - 1) + '...'
}

$inSync = @()
$outOfSync = @()
$missingInXml = @()
$missingSource = @()
$warnings = @()

foreach ($prop in $templates.PSObject.Properties) {
    $name = $prop.Name
    $relativePath = $prop.Value
    $source = Read-SourceTemplate $relativePath
    $xml = Get-TemplateFromXml $xmlContent $name

    if ($null -eq $source) {
        $missingSource += [pscustomobject]@{ Name = $name; Path = $relativePath }
        continue
    }
    if ($null -eq $xml) {
        $missingInXml += [pscustomobject]@{ Name = $name; Path = $relativePath }
        continue
    }

    if ($source -eq $xml) {
        $inSync += $name
    } else {
        $outOfSync += [pscustomobject]@{
            Name = $name
            Path = $relativePath
            Diff = (Get-DiffSummary $source $xml)
        }
    }

    if ($legacyInSource.ContainsKey($name)) {
        foreach ($entry in $legacyInSource[$name].GetEnumerator()) {
            if ($source.Contains($entry.Key)) {
                $warnings += "[LEGACY en fuente] ${name}: $($entry.Value)"
            }
        }
    }

    if ($mustKeepIfInXml.ContainsKey($name)) {
        foreach ($entry in $mustKeepIfInXml[$name].GetEnumerator()) {
            if ($xml.Contains($entry.Key) -and -not $source.Contains($entry.Key)) {
                $warnings += "[PERDIDA al sync] ${name}: XML tiene '$($entry.Value)' pero la fuente no - update_theme.php lo borraria"
            }
        }
    }
}

Write-Host '=== diff_theme_source.ps1 (solo lectura) ==='
Write-Host 'XML: Default-theme.xml'
Write-Host ("Templates en manifiesto: {0}`n" -f ($templates.PSObject.Properties | Measure-Object).Count)

if ($inSync.Count -gt 0) {
    Write-Host ("OK - en sync ({0}): {1}" -f $inSync.Count, ($inSync -join ', '))
}

if ($missingSource.Count -gt 0) {
    Write-Host "`nAVISO - fuente ausente ($($missingSource.Count)):"
    foreach ($row in $missingSource) {
        Write-Host "  - $($row.Name) -> $($row.Path) (update_theme.php no puede actualizar este bloque)"
    }
}

if ($missingInXml.Count -gt 0) {
    Write-Host "`nAVISO - template no encontrado en XML ($($missingInXml.Count)):"
    foreach ($row in $missingInXml) {
        Write-Host "  - $($row.Name) -> $($row.Path)"
    }
}

if ($outOfSync.Count -gt 0) {
    Write-Host "`nDIFERENCIAS - update_theme.php SOBRESCRIBIRIA el XML ($($outOfSync.Count)):"
    foreach ($row in $outOfSync) {
        $d = $row.Diff
        Write-Host "  - $($row.Name) ($($row.Path))"
        Write-Host "      fuente: $($d.SourceLines) líneas | XML: $($d.XmlLines) líneas | ~$($d.ChangedLines) líneas distintas"

        if ($Verbose -and $d.FirstChanges.Count -gt 0) {
            foreach ($change in $d.FirstChanges) {
                if ($null -eq $change.Source) {
                    Write-Host ("      L{0}  (solo en XML): {1}" -f $change.Line, (Truncate-Line $change.Xml))
                } elseif ($null -eq $change.Xml) {
                    Write-Host ("      L{0}  (solo en fuente): {1}" -f $change.Line, (Truncate-Line $change.Source))
                } else {
                    Write-Host ("      L{0}  fuente: {1}" -f $change.Line, (Truncate-Line $change.Source))
                    Write-Host ("      L{0}  XML:    {1}" -f $change.Line, (Truncate-Line $change.Xml))
                }
            }
            if ($d.ChangedLines -gt $d.FirstChanges.Count) {
                Write-Host ("      ... y {0} lineas mas" -f ($d.ChangedLines - $d.FirstChanges.Count))
            }
        }
    }
    if (-not $Verbose) {
        Write-Host "`n  Usa -Verbose para ver las primeras líneas de cada diff."
    }
}

if ($warnings.Count -gt 0) {
    Write-Host "`nADVERTENCIAS:"
    foreach ($w in $warnings) { Write-Host "  ! $w" }
}

Write-Host ''
if ($outOfSync.Count -eq 0 -and $missingInXml.Count -eq 0 -and $warnings.Count -eq 0) {
    Write-Host 'Resultado: SEGURO sincronizar (fuente = XML en manifiesto).'
    Write-Host 'Siguiente paso: php update_theme.php && php validate_theme_security.php'
    exit 0
}

if ($outOfSync.Count -gt 0 -or $warnings.Count -gt 0) {
    Write-Host 'Resultado: NO sincronizar todavia - revisa fuente vs XML antes de update_theme.php.'
    exit 1
}

Write-Host 'Resultado: revisar avisos antes de sincronizar.'
exit 1
