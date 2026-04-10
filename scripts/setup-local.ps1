Param(
    [string]$ProjectRoot = "C:\wamp64\www\NRSmarketing-1"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Write-Host "== NRSMarketing local setup =="
Set-Location $ProjectRoot

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "Created .env from .env.example"
}

if (-not (Test-Path "vendor")) {
    Write-Host "Running composer install..."
    composer install
}

if (-not (Test-Path "node_modules")) {
    Write-Host "Running npm install..."
    npm install
}

Write-Host "Generating APP_KEY..."
php artisan key:generate

Write-Host "Running migrations..."
php artisan migrate

if (-not (Test-Path "whatsapp-bridge\node_modules")) {
    Write-Host "Installing bridge dependencies..."
    Set-Location "$ProjectRoot\whatsapp-bridge"
    npm install
    Set-Location $ProjectRoot
}

Write-Host "Setup complete."
Write-Host "Next: run scripts\start-local.ps1"
