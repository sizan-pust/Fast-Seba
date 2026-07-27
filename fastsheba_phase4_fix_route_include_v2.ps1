param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

$artisanPath = Join-Path $BackendPath "artisan"
$apiRoutesPath = Join-Path $BackendPath "routes\api.php"
$commerceRoutesPath = Join-Path $BackendPath "routes\commerce.php"
$commerceMigrationPath = Join-Path $BackendPath "database\migrations\2026_07_28_040000_create_commerce_core_tables.php"
$commerceSeederPath = Join-Path $BackendPath "database\seeders\CommerceSeeder.php"
$commerceServicePath = Join-Path $BackendPath "app\Services\CommerceService.php"
$commerceTestPath = Join-Path $BackendPath "tests\Feature\Api\CommerceCoreApiTest.php"

if (-not (Test-Path $artisanPath)) {
    throw "Laravel backend not found: $BackendPath"
}

$requiredFiles = @(
    $apiRoutesPath,
    $commerceRoutesPath,
    $commerceMigrationPath,
    $commerceSeederPath,
    $commerceServicePath,
    $commerceTestPath
)

foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path $requiredFile)) {
        throw "Required Phase 4 file not found: $requiredFile"
    }
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase4_routefix_backups\$timestamp"

New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
Copy-Item -Path $apiRoutesPath -Destination (Join-Path $backupRoot "api.php") -Force

$commerceInclude = "require __DIR__.'/commerce.php';"
$apiRoutesContent = [System.IO.File]::ReadAllText($apiRoutesPath)

if ($apiRoutesContent.Contains($commerceInclude)) {
    Write-Host ""
    Write-Host "Commerce route include already exists." -ForegroundColor Yellow
}
else {
    $newLine = [Environment]::NewLine
    $appendText = $newLine + $newLine + $commerceInclude + $newLine
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)

    [System.IO.File]::AppendAllText($apiRoutesPath, $appendText, $utf8NoBom)

    Write-Host ""
    Write-Host "Commerce route include added successfully." -ForegroundColor Green
}

$verifiedContent = [System.IO.File]::ReadAllText($apiRoutesPath)

if (-not $verifiedContent.Contains($commerceInclude)) {
    throw "Commerce route include verification failed."
}

Write-Host "All required Phase 4 files are present." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next commands:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan migrate"
Write-Host "  herd php artisan db:seed --class=CommerceSeeder"
Write-Host "  herd php artisan migrate --env=testing"
Write-Host "  herd php artisan route:list --path=api/user"
Write-Host "  herd php artisan test --filter=CommerceCoreApiTest"
Write-Host "  herd php artisan test"
