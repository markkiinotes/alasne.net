param(
    [string] $StageRoot = 'C:\xampp\htdocs\alasne-stage-build',
    [switch] $SkipComposer
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$SourceRoot = [System.IO.Path]::GetFullPath(
    (Join-Path $PSScriptRoot '..')
)

$StageRoot = [System.IO.Path]::GetFullPath(
    $StageRoot
)

$ProtectedDirectoryNames = @(
    '.git',
    'vendor',
    'storage'
)

$ProtectedFileNames = @(
    '.env'
)

function Test-ProtectedRelativePath {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RelativePath
    )

    $normalized = $RelativePath.Replace('/', '\')

    foreach ($directoryName in $ProtectedDirectoryNames) {
        if (
            $normalized -eq $directoryName
            -or $normalized.StartsWith(
                $directoryName + '\',
                [System.StringComparison]::OrdinalIgnoreCase
            )
        ) {
            return $true
        }
    }

    $leafName = Split-Path $normalized -Leaf

    foreach ($fileName in $ProtectedFileNames) {
        if (
            $leafName.Equals(
                $fileName,
                [System.StringComparison]::OrdinalIgnoreCase
            )
        ) {
            return $true
        }
    }

    return $false
}

function Get-ComparableFiles {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Root
    )

    $files = @{}

    Get-ChildItem -LiteralPath $Root -Recurse -File |
        ForEach-Object {
            $relativePath = $_.FullName
                .Substring($Root.Length)
                .TrimStart('\', '/')

            if (
                -not (
                    Test-ProtectedRelativePath
                        -RelativePath $relativePath
                )
            ) {
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
    New-Item -ItemType Directory -Path $StageRoot -Force |
        Out-Null
}

$stageEnv = Join-Path $StageRoot '.env'

if (-not (Test-Path -LiteralPath $stageEnv -PathType Leaf)) {
    throw (
        'Staging .env is missing. Create the staging environment file ' +
        'before building so environment-specific secrets are never copied ' +
        'from development.'
    )
}

Write-Host 'Synchronizing application source...'

$robocopyArguments = @(
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

& robocopy @robocopyArguments
$robocopyExitCode = $LASTEXITCODE

if ($robocopyExitCode -ge 8) {
    throw (
        'Robocopy failed with exit code ' +
        $robocopyExitCode +
        '.'
    )
}

if (-not $SkipComposer) {
    $composerCommand = Get-Command composer -ErrorAction SilentlyContinue

    if ($null -eq $composerCommand) {
        throw (
            'Composer is not available on PATH. Install Composer or rerun ' +
            'this script with -SkipComposer.'
        )
    }

    Write-Host ''
    Write-Host 'Installing staging Composer dependencies...'

    Push-Location $StageRoot

    try {
        & composer install --no-dev --optimize-autoloader --no-interaction

        if ($LASTEXITCODE -ne 0) {
            throw (
                'Composer install failed with exit code ' +
                $LASTEXITCODE +
                '.'
            )
        }

        & composer check-platform-reqs

        if ($LASTEXITCODE -ne 0) {
            throw (
                'Composer platform requirement check failed with exit code ' +
                $LASTEXITCODE +
                '.'
            )
        }
    }
    finally {
        Pop-Location
    }
}

Write-Host ''
Write-Host 'Verifying source-tree parity...'

$sourceFiles = Get-ComparableFiles -Root $SourceRoot
$stageFiles = Get-ComparableFiles -Root $StageRoot

$problems = New-Object System.Collections.Generic.List[object]

foreach ($relativePath in $sourceFiles.Keys) {
    if (-not $stageFiles.ContainsKey($relativePath)) {
        $problems.Add(
            [PSCustomObject]@{
                Status = 'MISSING IN STAGE'
                File = $relativePath
            }
        )

        continue
    }

    $sourceHash = (
        Get-FileHash -LiteralPath $sourceFiles[$relativePath]
    ).Hash

    $stageHash = (
        Get-FileHash -LiteralPath $stageFiles[$relativePath]
    ).Hash

    if ($sourceHash -ne $stageHash) {
        $problems.Add(
            [PSCustomObject]@{
                Status = 'DIFFERENT'
                File = $relativePath
            }
        )
    }
}

foreach ($relativePath in $stageFiles.Keys) {
    if (-not $sourceFiles.ContainsKey($relativePath)) {
        $problems.Add(
            [PSCustomObject]@{
                Status = 'ONLY IN STAGE'
                File = $relativePath
            }
        )
    }
}

if ($problems.Count -gt 0) {
    Write-Host ''
    Write-Host 'Stage build verification FAILED.'
    Write-Host 'The staging tree contains source differences that require review.'

    $problems |
        Sort-Object Status, File |
        Format-Table -AutoSize

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
