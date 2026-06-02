# Simula check_template() de MyBB 1.8 sobre plantillas HTML fuente.
# Uso: powershell -File validate_theme_security.ps1

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$files = Get-ChildItem -Path (Join-Path $root 'templates') -Filter '*.html' -Recurse

function Test-MyBBTemplateSecurity {
    param([string]$Name, [string]$Content)
    if ($Content -match '(?i)\$config\s*\[') {
        return 'referencia a $config'
    }
    if ($Content -match '\$\s*\{') {
        return 'patron $\s*{ (p. ej. template literals JS)'
    }
    $allowed = [regex]::Replace(
        $Content,
        '\{\$+[a-zA-Z_][a-zA-Z_0-9]*((?:->|::)\$*[a-zA-Z_][a-zA-Z_0-9]*|\[\s*\$*(''|"")?[a-zA-Z_ 0-9]+(''|"")?\]\s*)*\}',
        ''
    )
    if ($allowed -match '\{\$.+?\}') {
        return 'variable {$...} no reconocida por MyBB (revisa comillas escapadas)'
    }
    return $null
}

$failed = @()
foreach ($f in $files) {
    $rel = $f.FullName.Substring($root.Length + 1)
    $content = [IO.File]::ReadAllText($f.FullName)
    $issue = Test-MyBBTemplateSecurity -Name $rel -Content $content
    if ($issue) {
        $failed += [pscustomobject]@{ File = $rel; Issue = $issue }
    }
}

if ($failed.Count -eq 0) {
    Write-Host 'OK: ninguna plantilla fuente dispara el escaner de MyBB.'
    exit 0
}

Write-Host 'FALLO: plantillas que bloquearian la importacion:'
$failed | Format-Table -AutoSize
exit 1
