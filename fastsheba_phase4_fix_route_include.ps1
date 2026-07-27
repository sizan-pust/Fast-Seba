param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom(
    [string]$Path,
    [string]$Content
) {
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)

    [System.IO.File]::WriteAllText(
        $Path,
        $Content,
        $utf8NoBom
    )
}

if (-not (Test-Path "$BackendPath\artisan")) {
    throw "Laravel backend not found at: $BackendPath"
}

$requiredFiles = @(
    "$BackendPath\routes\commerce.php",
    "$BackendPath\database\migrations\2026_07_28_040000_create_commerce_core_tables.php",
    "$BackendPath\database\seeders\CommerceSeeder.php",
    "$BackendPath\app\Services\CommerceService.php",
    "$BackendPath\app\Services\PromoService.php",
    "$BackendPath\app\Http\Controllers\Api\User\AddressApiController.php",
    "$BackendPath\app\Http\Controllers\Api\User\WishlistApiController.php",
    "$BackendPath\app\Http\Controllers\Api\User\CartApiController.php",
    "$BackendPath\app\Http\Controllers\Api\User\PromoApiController.php",
    "$BackendPath\tests\Feature\Api\CommerceCoreApiTest.php"
)

$missingFiles = @(
    $requiredFiles |
        Where-Object { -not (Test-Path $_) }
)

if ($missingFiles.Count -gt 0) {
    $missingList = $missingFiles -join [Environment]::NewLine

    throw @"
Phase 4 stopped before all expected files were created.
Missing files:
$missingList
"@
}

$apiRoutesPath = "$BackendPath\routes\api.php"

if (-not (Test-Path $apiRoutesPath)) {
    throw "API routes file not found: $apiRoutesPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupDirectory = Join-Path `
    $BackendPath `
    "_phase4_routefix_backups\$timestamp"

New-Item `
    -ItemType Directory `
    -Force `
    -Path $backupDirectory |
    Out-Null

Copy-Item `
    $apiRoutesPath `
    (Join-Path $backupDirectory "api.php") `
    -Force

$apiRoutes = [System.IO.File]::ReadAllText(
    $apiRoutesPath
)

$commerceInclude = "require __DIR__.'/commerce.php';"

if ($apiRoutes.Contains($commerceInclude)) {
    Write-Host ""
    Write-Host "Commerce route include already exists." `
        -ForegroundColor Yellow
} else {
    $newLine = [Environment]::NewLine

    $apiRoutes = (
        $apiRoutes.TrimEnd()
        + $newLine
        + $newLine
        + $commerceInclude
        + $newLine
    )

    Write-Utf8NoBom `
        -Path $apiRoutesPath `
        -Content $apiRoutes

    Write-Host ""
    Write-Host "Commerce route include added successfully." `
        -ForegroundColor Green
}

$verifiedRoutes = [System.IO.File]::ReadAllText(
    $apiRoutesPath
)

if (-not $verifiedRoutes.Contains($commerceInclude)) {
    throw "Commerce route include verification failed."
}

Write-Host "Phase 4 generated files are present." `
    -ForegroundColor Green
Write-Host "Backup created at: $backupDirectory" `
    -ForegroundColor Cyan
Write-Host ""
Write-Host "Run next:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan migrate"
Write-Host "  herd php artisan db:seed --class=CommerceSeeder"
Write-Host "  herd php artisan migrate --env=testing"
Write-Host "  herd php artisan route:list --path=api/user"
Write-Host "  herd php artisan test --filter=CommerceCoreApiTest"
Write-Host "  herd php artisan test"
