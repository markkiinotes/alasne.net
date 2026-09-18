param(
    [string]$StageRoot = 'C:\\xampp\\htdocs\\alasne-stage-build',
    [switch]$SkipComposer
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$SourceRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$StageRoot = [System.IO.Path]::GetFullPath($StageRoot)

$ProtectedDirectories = @('.git', 'vendor', 'storage')
$ProtectedFiles = @('.env')

function Get-RelativeFilePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Root,

        [Parameter(Mandatory = $true)]
        [string]$FullName
    )

    $relative = $FullName.Substring($Root.Length)
    return $relative.TrimStart([char[]]'\\/')
}

function Test-ProtectedRelativePath {
    param(
        [Parameter(Mandatory = $true)]
        [string]$RelativePath
    )

    $normalized = $RelativePath.Replace('/', '\\')

    foreach ($directory in $ProtectedDirectories) {
        if ($normalized -ieq $directory) {
            return $true
        }

        $prefix = $directory + '\\'

        if ($normalized.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
    }

    $leaf = Split-Path -Path $normalized -Leaf

    foreach ($fileName in $ProtectedFiles) {
        if ($leaf -ieq $fileName) {
            return $true
        }
    }

    return $false
}

function Get-ComparableFiles {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Root
    )

    $files = @{}

    Get-ChildItem -LiteralPath $Root -Recurse -File | ForEach-Object {
        $relativePath = Get-RelativeFilePath -Root $Root -FullName $_.FullName

        if (-not (Test-ProtectedRelativePath -RelativePath $relativePath)) {
            $files[$relativePath] = $_.FullName
        }
    }

    return $files
}

Write-Host ''
Write-Host 'Alasne staging build'
Write-Host '--------------------'
Write-Host ('Source:      ' + $SourceRoot)
Write-Host ('Destination: ' + $StageRoot)
Write-Host ''

if (-not (Test-Path -LiteralPath $SourceRoot -PathType Container)) {
    throw 'Canonical Alasne source directory was not found.'
}

if (-not (Test-Path -LiteralPath $StageRoot -PathType Container)) {
    New-Item -ItemType Directory -Path $StageRoot -Force | Out-Null
}

$StageEnv = Join-Path $StageRoot '.env'

if (-not (Test-Path -LiteralPath $StageEnv -PathType Leaf)) {
    throw ('Staging .env is missing. Create the staging environment file before building so development secrets are never copied into staging.')
}

Write-Host 'Synchronizing application source...'

$RoboCopyArguments = @(
    $SourceRoot,
    $StageRoot,
    '/E',
    '/COPY:DAT',
    '/DCOPY:T',
    '/R:1',
    '/W:1',
    '/XD',
    '.git',
    'vendor',
    'storage',
    '/XF',
    '.env'
)

& robocopy @RoboCopyArguments
$RoboCopyExitCode = $LASTEXITCODE

if ($RoboCopyExitCode -ge 8) {
    throw ('Robocopy failed with exit code ' + $RoboCopyExitCode + '.')
}

if (-not $SkipComposer) {
    $ComposerCommand = Get-Command composer -ErrorAction SilentlyContinue

    if ($null -eq $ComposerCommand) {
        throw 'Composer is not available on PATH. Install Composer or rerun this script with -SkipComposer.'
    }

    Write-Host ''
    Write-Host 'Installing staging Composer dependencies...'

    Push-Location $StageRoot

    try {
        & composer install --no-dev --optimize-autoloader --no-interaction

        if ($LASTEXITCODE -ne 0) {
            throw ('Composer install failed with exit code ' + $LASTEXITCODE + '.')
        }

        & composer check-platform-reqs

        if ($LASTEXITCODE -ne 0) {
            throw ('Composer platform requirement check failed with exit code ' + $LASTEXITCODE + '.')
        }
    }
    finally {
        Pop-Location
    }
}

Write-Host ''
Write-Host 'Verifying source-tree parity...'

$SourceFiles = Get-ComparableFiles -Root $SourceRoot
$StageFiles = Get-ComparableFiles -Root $StageRoot
$Problems = New-Object System.Collections.ArrayList

foreach ($RelativePath in $SourceFiles.Keys) {
    if (-not $StageFiles.ContainsKey($RelativePath)) {
        [void]$Problems.Add([PSCustomObject]@{ Status = 'MISSING IN STAGE'; File = $RelativePath })
        continue
    }

    $SourceHash = (Get-FileHash -LiteralPath $SourceFiles[$RelativePath]).Hash
    $StageHash = (Get-FileHash -LiteralPath $StageFiles[$RelativePath]).Hash

    if ($SourceHash -ne $StageHash) {
        [void]$Problems.Add([PSCustomObject]@{ Status = 'DIFFERENT'; File = $RelativePath })
    }
}

foreach ($RelativePath in $StageFiles.Keys) {
    if (-not $SourceFiles.ContainsKey($RelativePath)) {
        [void]$Problems.Add([PSCustomObject]@{ Status = 'ONLY IN STAGE'; File = $RelativePath })
    }
}

if ($Problems.Count -gt 0) {
    Write-Host ''
    Write-Host 'Stage build verification FAILED.'
    Write-Host 'The staging tree contains source differences that require review.'
    $Problems | Sort-Object Status, File | Format-Table -AutoSize
    exit 2
}

Write-Host ''
Write-Host 'Stage build verification PASSED.'
Write-Host ''
Write-Host 'Preserved staging-only runtime data:'
Write-Host '  .env'
Write-Host '  vendor (rebuilt by Composer unless -SkipComposer is used)'
Write-Host '  storage'
Write-Host ''
Write-Host 'Canonical source remains:'
Write-Host ('  ' + $SourceRoot)
Write-Host ''
