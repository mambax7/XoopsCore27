#Requires -Version 5.1
<#
.SYNOPSIS
    Run the full CI pipeline locally on Windows.

.DESCRIPTION
    Equivalent of `make ci`. Runs composer install, PHPCS, PHPStan, and PHPUnit
    in the same order as the GitHub Actions workflow. Requires PHP and Composer
    on PATH.

    First-run note: if PHPStan reports errors on a clean checkout, those may be
    pre-existing issues not yet captured in phpstan-baseline.neon. Run
    `.\scripts\baseline.ps1` to generate the baseline before treating
    `composer analyse` as a hard gate.

.PARAMETER SkipInstall
    Skip `composer install`. Use when dependencies are already installed.

.EXAMPLE
    .\scripts\ci.ps1

.EXAMPLE
    .\scripts\ci.ps1 -SkipInstall
#>

param(
    [switch]$SkipInstall
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot

function Step {
    param([string]$Label, [scriptblock]$Block)
    Write-Host ""
    Write-Host "==> $Label" -ForegroundColor Cyan
    & $Block
    if ($LASTEXITCODE -ne 0) {
        Write-Host "FAILED: $Label (exit $LASTEXITCODE)" -ForegroundColor Red
        exit $LASTEXITCODE
    }
}

if (-not $SkipInstall) {
    Step "Install dependencies" { composer install }
}

# PHPCS: style failures are advisory, matching ci.yml continue-on-error: true
Write-Host ""
Write-Host "==> Lint (PHPCS — advisory, non-blocking)" -ForegroundColor Cyan
composer lint
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Style issues found. Fix with: composer fix" -ForegroundColor Yellow
}

Step "Static analysis (PHPStan level max)" { composer analyse }
Step "Tests (PHPUnit)"                     { composer test }

Write-Host ""
Write-Host "CI pipeline passed." -ForegroundColor Green
