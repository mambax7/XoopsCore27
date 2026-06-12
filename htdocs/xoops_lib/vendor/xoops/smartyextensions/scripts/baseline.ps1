#Requires -Version 5.1
<#
.SYNOPSIS
    Regenerate the PHPStan baseline from the current source state.

.DESCRIPTION
    Equivalent of `make baseline`. Captures all current PHPStan findings into
    phpstan-baseline.neon so subsequent runs only fail on *new* errors.

    When to use this:
      - First run on a clean checkout before treating `composer analyse` as a gate.
      - After intentionally accepting a known PHPStan finding that cannot be fixed
        immediately (add a comment explaining why).

    When NOT to use this:
      - To silence a new error you just introduced. Fix the error instead.

.EXAMPLE
    .\scripts\baseline.ps1
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $RepoRoot

Write-Host "==> Generating PHPStan baseline..." -ForegroundColor Cyan
.\vendor\bin\phpstan analyse --generate-baseline phpstan-baseline.neon

Write-Host ""
Write-Host "Baseline written to phpstan-baseline.neon." -ForegroundColor Green
Write-Host "Review the file, commit it, and subsequent 'composer analyse' runs" -ForegroundColor Green
Write-Host "will only fail on errors introduced after this baseline." -ForegroundColor Green
