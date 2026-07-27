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

$servicePath = Join-Path $BackendPath "app\Services\OtpService.php"
$controllerPath = Join-Path $BackendPath "app\Http\Controllers\Api\User\OtpApiController.php"
$testPath = Join-Path $BackendPath "tests\Feature\Api\OtpSocialAuthApiTest.php"

foreach ($path in @($servicePath, $controllerPath, $testPath)) {
    if (-not (Test-Path $path)) {
        throw "Required file not found: $path"
    }
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase2b_otpfix_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

Copy-Item $servicePath (Join-Path $backupRoot "OtpService.php") -Force
Copy-Item $controllerPath (Join-Path $backupRoot "OtpApiController.php") -Force
Copy-Item $testPath (Join-Path $backupRoot "OtpSocialAuthApiTest.php") -Force

$service = [System.IO.File]::ReadAllText($servicePath)

$oldSignature = @'
    public function verifyOtp(
        string $mobile,
        string $otp
    ): array {
'@

$newSignature = @'
    public function verifyOtp(
        string $mobile,
        string $otp,
        bool $consume = true
    ): array {
'@

if (-not $service.Contains($oldSignature)) {
    throw "OtpService verifyOtp signature was not found."
}

$service = $service.Replace($oldSignature, $newSignature)

$oldConsume = @'
        $record->markAsVerified();

        return [
'@

$newConsume = @'
        if ($consume) {
            $record->markAsVerified();
        }

        return [
'@

if (-not $service.Contains($oldConsume)) {
    throw "OtpService OTP-consume block was not found."
}

$service = $service.Replace($oldConsume, $newConsume)
Write-Utf8NoBom -Path $servicePath -Content $service

$controller = [System.IO.File]::ReadAllText($controllerPath)

$oldVerification = @'
        $verification = $this->otpService->verifyOtp(
            $mobile,
            $validated['otp']
        );

        if (! $verification['success']) {
'@

$newVerification = @'
        $authenticatedUser = auth('sanctum')->user();

        $existingUser = User::query()
            ->whereIn(
                'mobile',
                $this->otpService->mobileCandidates($mobile)
            )
            ->exists();

        $hasRegistrationDetails = ! empty($validated['name'])
            && ! empty($validated['password']);

        $consumeOtp = $authenticatedUser !== null
            || $existingUser
            || $hasRegistrationDetails;

        $verification = $this->otpService->verifyOtp(
            $mobile,
            $validated['otp'],
            $consumeOtp
        );

        if (! $verification['success']) {
'@

if (-not $controller.Contains($oldVerification)) {
    throw "OtpApiController verification block was not found."
}

$controller = $controller.Replace($oldVerification, $newVerification)

$oldAuthLookup = @'
        $authedUser = auth('sanctum')->user();

        if ($authedUser) {
'@

$newAuthLookup = @'
        $authedUser = $authenticatedUser;

        if ($authedUser) {
'@

if (-not $controller.Contains($oldAuthLookup)) {
    throw "OtpApiController authenticated-user block was not found."
}

$controller = $controller.Replace($oldAuthLookup, $newAuthLookup)
Write-Utf8NoBom -Path $controllerPath -Content $controller

$test = [System.IO.File]::ReadAllText($testPath)

$insertBefore = @'
    public function test_otp_can_register_new_customer(): void
'@

$newTest = @'
    public function test_unknown_mobile_can_verify_then_register_with_same_otp(): void
    {
        $this->postJson('/api/auth/send-otp', [
            'mobile' => '01710000006',
        ])->assertOk();

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000006',
            'otp' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('data.new_user', true)
            ->assertJsonPath('data.is_register', true);

        $this->postJson('/api/auth/verify-otp', [
            'mobile' => '01710000006',
            'otp' => '123456',
            'name' => 'Two Step OTP Customer',
            'email' => 'two-step-otp@example.test',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
            'country' => 'Bangladesh',
            'iso_2' => 'BD',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_register', true)
            ->assertJsonPath('data.mobile', '01710000006');

        $this->assertDatabaseHas('users', [
            'email' => 'two-step-otp@example.test',
            'mobile' => '01710000006',
        ]);
    }

    public function test_otp_can_register_new_customer(): void
'@

if (-not $test.Contains($insertBefore)) {
    throw "Test insertion point was not found."
}

$test = $test.Replace($insertBefore, $newTest)
Write-Utf8NoBom -Path $testPath -Content $test

Write-Host ""
Write-Host "Two-step OTP verification fix applied." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Run next:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan db:seed --class=FoundationSeeder"
Write-Host "  herd php artisan db:seed --class=AuthProviderSeeder"
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan test"
