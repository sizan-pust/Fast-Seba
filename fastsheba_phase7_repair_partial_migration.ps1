param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend",
    [switch]$Testing
)

$ErrorActionPreference = "Stop"

$artisan = Join-Path $BackendPath "artisan"

if (-not (Test-Path $artisan)) {
    throw "Laravel backend not found: $BackendPath"
}

Set-Location $BackendPath

$environmentName = if ($Testing) { "testing" } else { "local" }
$tempPhp = Join-Path $BackendPath "_repair_phase7_partial_migration.php"

$php = @'
<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

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

echo "Phase 7 table state:\n";

foreach ($counts as $table => $count) {
    $value = $count === null ? 'missing' : (string) $count;
    echo "  {$table}: {$value}\n";
}

$nonEmpty = array_filter(
    $counts,
    static fn ($count) => $count !== null && $count > 0
);

if ($nonEmpty !== []) {
    fwrite(
        STDERR,
        "\nABORTED: At least one Phase 7 table contains data. "
        ."Nothing was dropped.\n"
    );

    exit(2);
}

Schema::disableForeignKeyConstraints();

try {
    foreach (array_reverse($tables) as $table) {
        Schema::dropIfExists($table);
    }
} finally {
    Schema::enableForeignKeyConstraints();
}

echo "\nEmpty partial Phase 7 tables were removed safely.\n";
'@

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($tempPhp, $php, $utf8NoBom)

$previousAppEnv = $env:APP_ENV

try {
    if ($Testing) {
        $env:APP_ENV = "testing"
    } else {
        Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue
    }

    Write-Host ""
    Write-Host "Repairing Phase 7 partial migration in environment: $environmentName" -ForegroundColor Cyan

    herd php $tempPhp

    if ($LASTEXITCODE -ne 0) {
        throw "Repair stopped. Review the table counts shown above."
    }

    herd php artisan optimize:clear

    if ($Testing) {
        herd php artisan migrate --env=testing
    } else {
        herd php artisan migrate
        herd php artisan db:seed --class=SellerManagementFinanceSeeder
        herd php artisan permission:cache-reset
        herd php artisan optimize:clear
    }

    Write-Host ""
    Write-Host "Phase 7 migration repair completed successfully." -ForegroundColor Green
}
finally {
    if ($null -eq $previousAppEnv) {
        Remove-Item Env:APP_ENV -ErrorAction SilentlyContinue
    } else {
        $env:APP_ENV = $previousAppEnv
    }

    if (Test-Path $tempPhp) {
        Remove-Item $tempPhp -Force
    }
}
