param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

$testPath = Join-Path $BackendPath "tests\Feature\Api\SettingsDeliveryZoneApiTest.php"

if (-not (Test-Path $testPath)) {
    throw "Test file not found: $testPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase2a_testfix_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
Copy-Item $testPath (Join-Path $backupRoot "SettingsDeliveryZoneApiTest.php") -Force

$content = [System.IO.File]::ReadAllText($testPath)

$oldBlock1 = @'
    public function test_delivery_zone_list_and_location_check_work(): void
    {
        $this->getJson('/api/delivery-zone')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'dhaka-test-zone');

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true)
            ->assertJsonPath('data.zone_id', 1);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=24.9000'
            .'&longitude=91.9000'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', false)
            ->assertJsonPath('data.zone_id', null);
    }
'@

$newBlock1 = @'
    public function test_delivery_zone_list_and_location_check_work(): void
    {
        $zone = DeliveryZone::query()
            ->where('slug', 'dhaka-test-zone')
            ->firstOrFail();

        $this->getJson('/api/delivery-zone')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'dhaka-test-zone');

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true)
            ->assertJsonPath('data.zone_id', $zone->id);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=24.9000'
            .'&longitude=91.9000'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', false)
            ->assertJsonPath('data.zone_id', null);
    }
'@

$oldBlock2 = @'
    public function test_authenticated_zone_check_saves_selected_zone(): void
    {
        $user = User::query()->create([
            'name' => 'Zone Customer',
            'email' => 'zone@example.test',
            'mobile' => '01717777777',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true);

        $this->assertDatabaseHas('user_zone', [
            'user_id' => $user->id,
            'zone_id' => 1,
        ]);
    }
'@

$newBlock2 = @'
    public function test_authenticated_zone_check_saves_selected_zone(): void
    {
        $zone = DeliveryZone::query()
            ->where('slug', 'dhaka-test-zone')
            ->firstOrFail();

        $user = User::query()->create([
            'name' => 'Zone Customer',
            'email' => 'zone@example.test',
            'mobile' => '01717777777',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true)
            ->assertJsonPath('data.zone_id', $zone->id);

        $this->assertDatabaseHas('user_zone', [
            'user_id' => $user->id,
            'zone_id' => $zone->id,
        ]);
    }
'@

if (-not $content.Contains($oldBlock1)) {
    throw "First expected test block was not found. The test file may have been edited."
}

if (-not $content.Contains($oldBlock2)) {
    throw "Second expected test block was not found. The test file may have been edited."
}

$content = $content.Replace($oldBlock1, $newBlock1)
$content = $content.Replace($oldBlock2, $newBlock2)

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($testPath, $content, $utf8NoBom)

Write-Host ""
Write-Host "Dynamic delivery-zone ID test fix applied." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Run:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan test --testsuite=Feature"
