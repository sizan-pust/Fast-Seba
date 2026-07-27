param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

if (-not (Test-Path "$BackendPath\artisan")) {
    throw "Laravel backend not found at: $BackendPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase1c_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$authPath = Join-Path $BackendPath "config\auth.php"
$seederPath = Join-Path $BackendPath "database\seeders\FoundationSeeder.php"

Copy-Item $authPath (Join-Path $backupRoot "auth.php") -Force
Copy-Item $seederPath (Join-Path $backupRoot "FoundationSeeder.php") -Force

$authContent = @'
<?php

use App\Models\User;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'seller' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env(
                'AUTH_PASSWORD_RESET_TOKEN_TABLE',
                'password_reset_tokens'
            ),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
'@

Write-Utf8NoBom -Path $authPath -Content $authContent

$seeder = [System.IO.File]::ReadAllText($seederPath)

$seeder = [regex]::Replace(
    $seeder,
    "'currency_symbol'\s*=>\s*'[^']*',",
    "'currency_symbol' => `"`\u{09F3}`","
)

$seeder = [regex]::Replace(
    $seeder,
    "'native'\s*=>\s*'[^']*',",
    "'native' => `"`\u{09AC}`\u{09BE}`\u{0982}`\u{09B2}`\u{09BE}`\u{09A6}`\u{09C7}`\u{09B6}`","
)

$seeder = [regex]::Replace(
    $seeder,
    "'bn'\s*=>\s*'[^']*',",
    "'bn' => `"`\u{09AC}`\u{09BE}`\u{0982}`\u{09B2}`\u{09BE}`\u{09A6}`\u{09C7}`\u{09B6}`","
)

$seeder = [regex]::Replace(
    $seeder,
    "'currencySymbol'\s*=>\s*'[^']*',",
    "'currencySymbol' => `"`\u{09F3}`","
)

Write-Utf8NoBom -Path $seederPath -Content $seeder

Write-Host ""
Write-Host "Guard configuration and Unicode seed values fixed." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Run next:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan migrate:fresh --seed"
Write-Host "  herd php artisan test"
