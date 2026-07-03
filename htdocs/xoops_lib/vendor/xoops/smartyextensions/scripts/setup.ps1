#Requires -Version 5.1
<#
.SYNOPSIS
    Install Composer dependencies for xoops/smartyextensions.

.DESCRIPTION
    Equivalent of `make install`. Run this once after cloning the repository.
    Requires PHP and Composer to be available on PATH.

.EXAMPLE
    .\scripts\setup.ps1
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$RepoRoot = Split-Path -Parent $PSScriptRoot

Write-Host "==> Checking PHP..." -ForegroundColor Cyan
php --version

Write-Host "==> Checking Composer..." -ForegroundColor Cyan
composer --version

Write-Host "==> Installing dependencies..." -ForegroundColor Cyan
Set-Location $RepoRoot
composer install

Write-Host ""
Write-Host "Setup complete. Run .\scripts\ci.ps1 to verify the full pipeline." -ForegroundColor Green
