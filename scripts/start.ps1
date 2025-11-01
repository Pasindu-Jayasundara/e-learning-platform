param(
    [string]$BindHost = "localhost",
    [int]$Port = 8000,
    [string]$DocRoot = "public"
)

$ErrorActionPreference = 'Stop'

Write-Host "Starting E-Learning Platform server..." -ForegroundColor Cyan

# Load local environment variables if available
$envLocal = Join-Path $PSScriptRoot 'env.local.ps1'
$envExample = Join-Path $PSScriptRoot 'env.example.ps1'
if (Test-Path $envLocal) {
    . $envLocal
    Write-Host "Loaded environment from scripts/env.local.ps1" -ForegroundColor Green
} elseif (Test-Path $envExample) {
    . $envExample
    Write-Warning "Using scripts/env.example.ps1. Create scripts/env.local.ps1 with your real keys."
} else {
    Write-Warning "No environment file found. Create scripts/env.local.ps1 (copy from env.example.ps1)."
}

# Verify PHP exists
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Error "PHP not found in PATH. Please install PHP and ensure 'php' is available."
    exit 1
}

# Show Stripe configuration status
if ([string]::IsNullOrEmpty($env:STRIPE_SECRET_KEY) -or [string]::IsNullOrEmpty($env:STRIPE_PUBLISHABLE_KEY)) {
    Write-Warning "Stripe keys are not set. Paid checkout will be disabled."
} else {
    Write-Host "Stripe configured (publishable key present)." -ForegroundColor Green
}

# Start PHP built-in server
Push-Location (Split-Path $PSScriptRoot -Parent)
try {
    $url = "http://$($BindHost):$Port"
    Write-Host "Serving $DocRoot at $url" -ForegroundColor Cyan
    & php -S "$BindHost`:$Port" -t $DocRoot
} finally {
    Pop-Location
}
