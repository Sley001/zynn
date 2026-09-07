$ErrorActionPreference = "Stop"

Write-Host "Preparing ZYN Store Laravel backend..." -ForegroundColor Cyan

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw "PHP is not installed or is not available in PATH."
}

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw "Composer is not installed or is not available in PATH."
}

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host "Created .env from .env.example." -ForegroundColor Green
}

composer install
php artisan key:generate

Write-Host ""
Write-Host "Base project is ready." -ForegroundColor Green
Write-Host "Next: edit DB_PASSWORD, ADMIN_EMAIL, and ADMIN_PASSWORD in .env."
Write-Host "Then run: php artisan migrate --seed"
Write-Host "Then run: php artisan storage:link"
Write-Host "Then run: php artisan serve"
