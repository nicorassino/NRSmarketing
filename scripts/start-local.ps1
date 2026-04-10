Param(
    [string]$ProjectRoot = "C:\wamp64\www\NRSmarketing-1"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Write-Host "== NRSMarketing local start =="

Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$ProjectRoot'; php artisan serve --host=127.0.0.1 --port=8000"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$ProjectRoot'; npm run dev"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "Set-Location '$ProjectRoot\whatsapp-bridge'; `$env:WHATSAPP_BRIDGE_TOKEN='change_me'; npm start"

Write-Host "Started:"
Write-Host "- Laravel: http://127.0.0.1:8000"
Write-Host "- Vite dev server"
Write-Host "- WhatsApp bridge: http://127.0.0.1:3000"
Write-Host ""
Write-Host "Open app and go to menu 'WhatsApp' to start/reconnect and scan QR."
