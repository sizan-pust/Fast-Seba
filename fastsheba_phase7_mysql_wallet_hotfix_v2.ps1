param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

$artisan = Join-Path $BackendPath "artisan"

if (-not (Test-Path $artisan)) {
    throw "Laravel backend not found: $BackendPath"
}

$migrationPath = Join-Path $BackendPath "database\migrations\2026_07_28_070000_create_seller_management_finance_tables.php"
$walletServicePath = Join-Path $BackendPath "app\Services\FinanceWalletService.php"
$seederPath = Join-Path $BackendPath "database\seeders\SellerManagementFinanceSeeder.php"
$sellerFinanceControllerPath = Join-Path $BackendPath "app\Http\Controllers\Api\Seller\SellerFinanceApiController.php"

$requiredFiles = @(
    $migrationPath,
    $walletServicePath,
    $seederPath,
    $sellerFinanceControllerPath
)

foreach ($file in $requiredFiles) {
    if (-not (Test-Path $file)) {
        throw "Required Phase 7 file not found: $file"
    }
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase7_hotfix_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Backup-Phase7File {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path
    )

    $relative = $Path.Substring($BackendPath.Length).TrimStart('\')
    $destination = Join-Path $backupRoot $relative
    $destinationDirectory = Split-Path -Parent $destination

    New-Item -ItemType Directory -Force -Path $destinationDirectory | Out-Null
    Copy-Item -Path $Path -Destination $destination -Force
}

function Write-Utf8NoBom {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Path,

        [Parameter(Mandatory = $true)]
        [string]$Content
    )

    [System.IO.File]::WriteAllText(
        $Path,
        $Content,
        $utf8NoBom
    )
}

function Invoke-HerdCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string[]]$Arguments,

        [Parameter(Mandatory = $true)]
        [string]$FailureMessage
    )

    & herd @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw $FailureMessage
    }
}

foreach ($file in $requiredFiles) {
    Backup-Phase7File -Path $file
}

# -------------------------------------------------------------------------
# 1. Fix MySQL's 64-character identifier limit.
# -------------------------------------------------------------------------
$migration = [System.IO.File]::ReadAllText($migrationPath)

$oldSellerIndex = '$table->index([''seller_id'', ''status'', ''created_at'']);'
$newSellerIndex = '$table->index([''seller_id'', ''status'', ''created_at''], ''seller_withdrawal_status_created_idx'');'

if ($migration.Contains($oldSellerIndex)) {
    $migration = $migration.Replace(
        $oldSellerIndex,
        $newSellerIndex
    )
}

$oldRiderIndex = '$table->index([''delivery_boy_id'', ''status'', ''created_at'']);'
$newRiderIndex = '$table->index([''delivery_boy_id'', ''status'', ''created_at''], ''rider_withdrawal_status_created_idx'');'

if ($migration.Contains($oldRiderIndex)) {
    $migration = $migration.Replace(
        $oldRiderIndex,
        $newRiderIndex
    )
}

if (-not $migration.Contains('seller_withdrawal_status_created_idx')) {
    throw "Seller withdrawal short index name was not applied."
}

if (-not $migration.Contains('rider_withdrawal_status_created_idx')) {
    throw "Delivery withdrawal short index name was not applied."
}

Write-Utf8NoBom -Path $migrationPath -Content $migration

# -------------------------------------------------------------------------
# 2. Remove Phase 7 assumptions that wallets has a `status` column.
# -------------------------------------------------------------------------
$walletService = [System.IO.File]::ReadAllText($walletServicePath)

$walletServicePatched = [regex]::Replace(
    $walletService,
    "(?m)^[ \t]*'status'[ \t]*=>[ \t]*'active',[ \t]*\r?\n",
    ""
)

if (($walletServicePatched -eq $walletService) -and $walletService.Contains("'status' => 'active'")) {
    throw "Could not remove wallet status writes from FinanceWalletService."
}

if ($walletServicePatched.Contains("'status' => 'active'")) {
    throw "FinanceWalletService still contains an invalid wallet status write."
}

Write-Utf8NoBom `
    -Path $walletServicePath `
    -Content $walletServicePatched

$seeder = [System.IO.File]::ReadAllText($seederPath)

$seederPatched = [regex]::Replace(
    $seeder,
    "(?m)^[ \t]*'status'[ \t]*=>[ \t]*'active',[ \t]*\r?\n",
    ""
)

if (($seederPatched -eq $seeder) -and $seeder.Contains("'status' => 'active'")) {
    throw "Could not remove wallet status writes from SellerManagementFinanceSeeder."
}

if ($seederPatched.Contains("'status' => 'active'")) {
    throw "SellerManagementFinanceSeeder still contains an invalid wallet status write."
}

Write-Utf8NoBom `
    -Path $seederPath `
    -Content $seederPatched

$controller = [System.IO.File]::ReadAllText($sellerFinanceControllerPath)

$controllerPatched = [regex]::Replace(
    $controller,
    '(?m)^[ \t]*''status''[ \t]*=>[ \t]*\$wallet->status,[ \t]*\r?\n',
    ""
)

if ($controllerPatched.Contains('$wallet->status')) {
    throw "SellerFinanceApiController still references the missing wallet status column."
}

Write-Utf8NoBom `
    -Path $sellerFinanceControllerPath `
    -Content $controllerPatched

Set-Location $BackendPath

# -------------------------------------------------------------------------
# 3. Syntax-check every patched PHP file before touching the database.
# -------------------------------------------------------------------------
foreach ($file in $requiredFiles) {
    Invoke-HerdCommand `
        -Arguments @("php", "-l", $file) `
        -FailureMessage "PHP syntax check failed: $file"
}

# -------------------------------------------------------------------------
# 4. Remove only EMPTY partial Phase 7 tables left by failed migrations.
# -------------------------------------------------------------------------
$tempPhp = Join-Path $BackendPath "_phase7_database_hotfix.php"

$cleanupPhp = @'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$migration = '2026_07_28_070000_create_seller_management_finance_tables';

$tables = [
    'seller_statements',
    'seller_withdrawal_requests',
    'delivery_boy_withdrawal_requests',
    'payment_gateway_configs',
];

$counts = [];

foreach ($tables as $table) {
    $counts[$table] = Schema::hasTable($table)
        ? (int) DB::table($table)->count()
        : null;
}

echo "Current Phase 7 table state:\n";

foreach ($counts as $table => $count) {
    echo '  '.$table.': '.($count === null ? 'missing' : $count)."\n";
}

$nonEmpty = array_filter(
    $counts,
    static fn ($count) => $count !== null && $count > 0
);

if ($nonEmpty !== []) {
    fwrite(
        STDERR,
        "\nABORTED: A Phase 7 table contains data. Nothing was dropped.\n"
    );

    exit(2);
}

Schema::disableForeignKeyConstraints();

try {
    foreach (array_reverse($tables) as $table) {
        Schema::dropIfExists($table);
    }

    DB::table('migrations')
        ->where('migration', $migration)
        ->delete();
} finally {
    Schema::enableForeignKeyConstraints();
}

echo "\nEmpty partial Phase 7 tables and any stale migration row were removed.\n";
'@

Write-Utf8NoBom -Path $tempPhp -Content $cleanupPhp

try {
    Invoke-HerdCommand `
        -Arguments @("php", $tempPhp) `
        -FailureMessage "Database cleanup stopped. Review the Phase 7 table counts above."

    Invoke-HerdCommand `
        -Arguments @("php", "artisan", "optimize:clear") `
        -FailureMessage "artisan optimize:clear failed."

    Invoke-HerdCommand `
        -Arguments @("php", "artisan", "migrate", "--force") `
        -FailureMessage "Phase 7 migration failed. Seeder was NOT executed."

    Invoke-HerdCommand `
        -Arguments @("php", "artisan", "db:seed", "--class=SellerManagementFinanceSeeder", "--force") `
        -FailureMessage "SellerManagementFinanceSeeder failed."

    Invoke-HerdCommand `
        -Arguments @("php", "artisan", "permission:cache-reset") `
        -FailureMessage "Permission cache reset failed."

    Invoke-HerdCommand `
        -Arguments @("php", "artisan", "optimize:clear") `
        -FailureMessage "Final optimize:clear failed."

    Invoke-HerdCommand `
        -Arguments @("php", "artisan", "migrate:status") `
        -FailureMessage "migrate:status failed."

    Write-Host ""
    Write-Host "Phase 7 database and wallet compatibility hotfix completed." -ForegroundColor Green
    Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
}
finally {
    if (Test-Path $tempPhp) {
        Remove-Item $tempPhp -Force
    }
}
