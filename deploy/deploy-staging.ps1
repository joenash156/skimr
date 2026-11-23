<#
.SYNOPSIS
    Builds Skimr and uploads it to the InfinityFree staging site over FTP (spec 17.2).

.DESCRIPTION
    1. Builds the React dashboard into backend/public/app (skipped until frontend/ exists).
    2. Copies backend/ into deploy/build/htdocs without dev files, tests or any .env.
    3. Runs composer install --no-dev --optimize-autoloader in the copy.
    4. Mirrors deploy/build/htdocs to the FTP folder (default /htdocs) with WinSCP.

    The staging .env is NOT uploaded by this script. Upload it once by hand to
    htdocs/.env (with APP_ENV=staging, APP_DEBUG=false and real secrets).

    Credentials come from environment variables, never from the repo:
        $env:SKIMR_FTP_HOST = "ftpupload.net"
        $env:SKIMR_FTP_USER = "if0_12345678"
        $env:SKIMR_FTP_PASS = "<from the InfinityFree panel>"

    Requires WinSCP (https://winscp.net), which provides winscp.com.

.EXAMPLE
    ./deploy/deploy-staging.ps1
    ./deploy/deploy-staging.ps1 -SkipFrontend -DryRun
#>
[CmdletBinding()]
param(
    [string]$RemoteDir = "/htdocs",
    [switch]$SkipFrontend,
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$build = Join-Path $PSScriptRoot "build"
$target = Join-Path $build "htdocs"

function Invoke-Step([string]$Name, [scriptblock]$Action) {
    Write-Host "==> $Name" -ForegroundColor Cyan
    & $Action
    if ($LASTEXITCODE -and $LASTEXITCODE -ne 0) { throw "$Name failed (exit $LASTEXITCODE)" }
}

foreach ($name in "SKIMR_FTP_HOST", "SKIMR_FTP_USER", "SKIMR_FTP_PASS") {
    if (-not $DryRun -and -not [Environment]::GetEnvironmentVariable($name)) {
        throw "Set `$env:$name first (see the help at the top of this script)."
    }
}

$winscp = (Get-Command winscp.com -ErrorAction SilentlyContinue).Source
if (-not $winscp) {
    $candidate = "${env:ProgramFiles(x86)}\WinSCP\WinSCP.com"
    if (Test-Path $candidate) { $winscp = $candidate }
}
if (-not $DryRun -and -not $winscp) { throw "WinSCP not found. Install it from https://winscp.net, then run again." }

if (-not $SkipFrontend -and (Test-Path (Join-Path $root "frontend/package.json"))) {
    Invoke-Step "Build the dashboard" {
        Push-Location (Join-Path $root "frontend")
        try { pnpm install --frozen-lockfile; if ($LASTEXITCODE -eq 0) { pnpm build } } finally { Pop-Location }
    }
}

if (Test-Path (Join-Path $root "backend/package.json")) {
    Invoke-Step "Build the public site CSS" {
        Push-Location (Join-Path $root "backend")
        try { pnpm install --frozen-lockfile; if ($LASTEXITCODE -eq 0) { pnpm build:public } } finally { Pop-Location }
        if ($LASTEXITCODE -ne 0) { throw "Public CSS build failed." }
    }
}

Invoke-Step "Copy backend/ to deploy/build/htdocs" {
    if (Test-Path $build) { Remove-Item -Recurse -Force $build }
    New-Item -ItemType Directory -Force $target | Out-Null
    robocopy (Join-Path $root "backend") $target /E /NFL /NDL /NJH /NJS /NP `
        /XD vendor var tests .phpunit.cache node_modules `
        /XF .env ".env.*" phpunit.xml.dist phpstan.neon.dist .php-cs-fixer.dist.php "*.cache" | Out-Null
    # robocopy: exit codes below 8 mean success.
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed (exit $LASTEXITCODE)" }
    $global:LASTEXITCODE = 0
    Copy-Item (Join-Path $PSScriptRoot "htdocs.htaccess") (Join-Path $target ".htaccess")
}

Invoke-Step "Install production PHP dependencies" {
    composer install --working-dir="$target" --no-dev --optimize-autoloader --no-interaction --no-progress
}

if ($DryRun) {
    Write-Host "Dry run: build is ready in $target (nothing uploaded)." -ForegroundColor Yellow
    exit 0
}

Invoke-Step "Upload to $env:SKIMR_FTP_HOST$RemoteDir" {
    $user = [Uri]::EscapeDataString($env:SKIMR_FTP_USER)
    $pass = [Uri]::EscapeDataString($env:SKIMR_FTP_PASS)
    $log = Join-Path $build "winscp.log"
    # synchronize remote -delete mirrors the build; files that only exist on the server
    # are removed, EXCEPT the excluded ones, which are always kept: .env (the server's
    # secrets), var/ (logs) and the GitHub Actions deploy state file.
    # -explicit = FTP over TLS, so the password and files are encrypted in transit.
    & $winscp /ini=nul /log="$log" /command `
        "open ftp://${user}:${pass}@$($env:SKIMR_FTP_HOST)/ -explicit" `
        "option batch abort" `
        "option confirm off" `
        "synchronize remote -delete -filemask=""|.env; var/; .ftp-deploy-sync-state.json"" ""$target"" ""$RemoteDir""" `
        "exit"
}

Write-Host "Deployed. Check https://skimr.foliony.app/api/v1/health in a browser." -ForegroundColor Green
