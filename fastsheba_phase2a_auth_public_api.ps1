param(
    [string]$BackendPath = "D:\Workspace\fastsheba-platform\backend"
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $directory = Split-Path $Path -Parent
    if ($directory) {
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Content, $utf8NoBom)
}

function Backup-File([string]$Path, [string]$BackupRoot) {
    if (Test-Path $Path) {
        $relative = $Path.Substring($BackendPath.Length).TrimStart("\")
        $destination = Join-Path $BackupRoot $relative
        $directory = Split-Path $destination -Parent
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
        Copy-Item $Path $destination -Force
    }
}

if (-not (Test-Path "$BackendPath\artisan")) {
    throw "Laravel backend not found at: $BackendPath"
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$backupRoot = Join-Path $BackendPath "_phase2a_backups\$timestamp"
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null

$targets = @(
    "$BackendPath\routes\api.php",
    "$BackendPath\config\fastsheba.php",
    "$BackendPath\app\Enums\SettingTypeEnum.php",
    "$BackendPath\app\Types\Api\ApiResponseType.php",
    "$BackendPath\app\Http\Resources\UserResource.php",
    "$BackendPath\app\Http\Resources\DeliveryZoneResource.php",
    "$BackendPath\app\Http\Resources\SettingResource.php",
    "$BackendPath\app\Services\SettingService.php",
    "$BackendPath\app\Services\DeliveryZoneService.php",
    "$BackendPath\app\Services\DeviceTokenService.php",
    "$BackendPath\app\Http\Controllers\Api\User\AuthApiController.php",
    "$BackendPath\app\Http\Controllers\Api\User\UserApiController.php",
    "$BackendPath\app\Http\Controllers\Api\SettingApiController.php",
    "$BackendPath\app\Http\Controllers\Api\DeliveryZoneApiController.php",
    "$BackendPath\app\Http\Controllers\DeviceTokenController.php"
)

foreach ($path in $targets) {
    Backup-File $path $backupRoot
}

Write-Utf8NoBom -Path "$BackendPath\config\fastsheba.php" -Content @'
<?php

return [
    'apps' => [
        'customer' => [
            'latest_version' => env('CUSTOMER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('CUSTOMER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('CUSTOMER_ANDROID_URL', ''),
            'ios_url' => env('CUSTOMER_IOS_URL', ''),
        ],
        'seller' => [
            'latest_version' => env('SELLER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('SELLER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('SELLER_ANDROID_URL', ''),
            'ios_url' => env('SELLER_IOS_URL', ''),
        ],
        'rider' => [
            'latest_version' => env('RIDER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('RIDER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('RIDER_ANDROID_URL', ''),
            'ios_url' => env('RIDER_IOS_URL', ''),
        ],
        'web' => [
            'latest_version' => env('WEB_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('WEB_APP_MIN_VERSION', '1.0.0'),
            'android_url' => '',
            'ios_url' => '',
        ],
    ],
];
'@

Write-Utf8NoBom -Path "$BackendPath\app\Enums\SettingTypeEnum.php" -Content @'
<?php

namespace App\Enums;

enum SettingTypeEnum: string
{
    case SYSTEM = 'system';
    case AUTHENTICATION = 'authentication';
    case NOTIFICATION = 'notification';
    case APP = 'app';

    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Types\Api\ApiResponseType.php" -Content @'
<?php

namespace App\Types\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponseType
{
    public static function sendJsonResponse(
        bool $success,
        string $message,
        mixed $data = null,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => $success,
            'message' => __($message),
            'data' => $data,
        ], $status);
    }

    public static function responseFromPaginator(
        LengthAwarePaginator $paginator,
        mixed $items = null
    ): array {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $items ?? $paginator->items(),
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\UserResource.php" -Content @'
<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $accessPanel = $this->access_panel;
        if ($accessPanel instanceof BackedEnum) {
            $accessPanel = $accessPanel->value;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'country_code' => $this->country_code,
            'referral_code' => $this->referral_code,
            'friends_code' => $this->friends_code,
            'reward_points' => $this->reward_points,
            'profile_image' => $this->profile_image,
            'status' => $this->status,
            'country' => $this->country,
            'iso_2' => $this->iso_2,
            'access_panel' => $accessPanel,
            'email_verified_at' => $this->email_verified_at,
            'mobile_verified_at' => $this->mobile_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\DeliveryZoneResource.php" -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'center_latitude' => $this->center_latitude,
            'center_longitude' => $this->center_longitude,
            'radius_km' => $this->radius_km,
            'boundary_json' => $this->boundary_json,
            'rush_delivery_enabled' => $this->rush_delivery_enabled,
            'delivery_time_per_km' => $this->delivery_time_per_km,
            'rush_delivery_time_per_km' => $this->rush_delivery_time_per_km,
            'rush_delivery_charges' => $this->rush_delivery_charges,
            'regular_delivery_charges' => $this->regular_delivery_charges,
            'free_delivery_amount' => $this->free_delivery_amount,
            'distance_based_delivery_charges' => $this->distance_based_delivery_charges,
            'per_store_drop_off_fee' => $this->per_store_drop_off_fee,
            'handling_charges' => $this->handling_charges,
            'buffer_time' => $this->buffer_time,
            'status' => $this->status,
            'delivery_boy_base_fee' => $this->delivery_boy_base_fee,
            'delivery_boy_per_store_pickup_fee' => $this->delivery_boy_per_store_pickup_fee,
            'delivery_boy_distance_based_fee' => $this->delivery_boy_distance_based_fee,
            'delivery_boy_per_order_incentive' => $this->delivery_boy_per_order_incentive,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Resources\SettingResource.php" -Content @'
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'variable' => $this->variable,
            'value' => $this->value,
        ];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\SettingService.php" -Content @'
<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public function getAllSettings(): Collection
    {
        return collect(SettingTypeEnum::values())
            ->map(fn (string $variable) => $this->getSettingByVariable($variable))
            ->filter()
            ->values();
    }

    public function getSettingByVariable(string $variable): ?JsonResource
    {
        if (! in_array($variable, SettingTypeEnum::values(), true)) {
            return null;
        }

        $setting = Setting::query()->where('variable', $variable)->first();

        return $setting ? new SettingResource($setting) : null;
    }

    public function getSettingValues(string $variable): array
    {
        return Cache::remember(
            "settings:{$variable}",
            now()->addMinutes(30),
            fn (): array => Setting::query()
                ->where('variable', $variable)
                ->first()?->value ?? []
        );
    }

    public function clearSettingCache(string $variable): void
    {
        Cache::forget("settings:{$variable}");
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\DeviceTokenService.php" -Content @'
<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFcmToken;

class DeviceTokenService
{
    public function sync(
        User $user,
        string $token,
        ?string $deviceType,
        ?string $roleType,
        ?string $previousToken = null
    ): UserFcmToken {
        if ($previousToken && $previousToken !== $token) {
            UserFcmToken::query()
                ->where('user_id', $user->id)
                ->where('fcm_token', $previousToken)
                ->delete();
        }

        return UserFcmToken::query()->updateOrCreate(
            ['fcm_token' => $token],
            [
                'user_id' => $user->id,
                'device_type' => $deviceType,
                'role_type' => $roleType,
            ]
        );
    }

    public function forget(User $user, string $token): void
    {
        UserFcmToken::query()
            ->where('user_id', $user->id)
            ->where('fcm_token', $token)
            ->delete();
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Services\DeliveryZoneService.php" -Content @'
<?php

namespace App\Services;

use App\Models\DeliveryZone;
use Illuminate\Support\Collection;

class DeliveryZoneService
{
    public static function validateCoordinates(
        float $latitude,
        float $longitude
    ): bool {
        return $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
    }

    public static function getZonesAtPoint(
        float $latitude,
        float $longitude
    ): array {
        $zones = DeliveryZone::query()
            ->where('status', 'active')
            ->get()
            ->filter(
                fn (DeliveryZone $zone): bool => self::containsPoint(
                    $zone,
                    $latitude,
                    $longitude
                )
            )
            ->values();

        $first = $zones->first();

        return [
            'zone_count' => $zones->count(),
            'zone' => $first,
            'zone_id' => $first?->id,
        ];
    }

    public static function existsAtPoint(
        float $latitude,
        float $longitude
    ): bool {
        return self::getZonesAtPoint($latitude, $longitude)['zone_count'] > 0;
    }

    private static function containsPoint(
        DeliveryZone $zone,
        float $latitude,
        float $longitude
    ): bool {
        $boundary = $zone->boundary_json;

        if (is_array($boundary) && count($boundary) >= 3) {
            return self::pointInPolygon($latitude, $longitude, $boundary);
        }

        return self::distanceInKilometres(
            $latitude,
            $longitude,
            (float) $zone->center_latitude,
            (float) $zone->center_longitude
        ) <= (float) $zone->radius_km;
    }

    private static function distanceInKilometres(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $earthRadius = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function pointInPolygon(
        float $latitude,
        float $longitude,
        array $polygon
    ): bool {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $pointI = self::normalizeBoundaryPoint($polygon[$i]);
            $pointJ = self::normalizeBoundaryPoint($polygon[$j]);

            if (! $pointI || ! $pointJ) {
                continue;
            }

            [$latI, $lngI] = $pointI;
            [$latJ, $lngJ] = $pointJ;

            $intersects = (($latI > $latitude) !== ($latJ > $latitude))
                && (
                    $longitude
                    < ($lngJ - $lngI)
                    * ($latitude - $latI)
                    / (($latJ - $latI) ?: PHP_FLOAT_EPSILON)
                    + $lngI
                );

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function normalizeBoundaryPoint(mixed $point): ?array
    {
        if (! is_array($point)) {
            return null;
        }

        $lat = $point['lat'] ?? $point['latitude'] ?? $point[0] ?? null;
        $lng = $point['lng'] ?? $point['longitude'] ?? $point[1] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return [(float) $lat, (float) $lng];
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\AuthApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DeviceTokenService;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly DeviceTokenService $deviceTokenService
    ) {
    }

    public function verifyUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:email,mobile'],
            'value' => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $validated['type'] === 'email'
            ? User::query()->where('email', $validated['value'])->first()
            : User::query()
                ->whereIn(
                    'mobile',
                    $this->buildMobileCandidates(
                        $validated['value'],
                        $validated['country_code'] ?? null
                    )
                )
                ->first();

        $data = [
            'exists' => $user !== null,
            'type' => $validated['type'],
            'value' => $validated['value'],
            'country_code' => $validated['country_code'] ?? null,
        ];

        return ApiResponseType::sendJsonResponse(
            $user !== null,
            $user ? 'User found.' : 'User not found.',
            $data
        );
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:mobile', 'nullable', 'email'],
            'mobile' => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ]);

        $query = User::query()->where('access_panel', GuardNameEnum::WEB->value);

        if (! empty($validated['email'])) {
            $query->where('email', $validated['email']);
        } else {
            $query->whereIn(
                'mobile',
                $this->buildMobileCandidates($validated['mobile'], null)
            );
        }

        $user = $query->first();

        if (! $user || ! $user->password || ! Hash::check(
            $validated['password'],
            $user->password
        )) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid credentials.',
                [],
                401
            );
        }

        if ($user->status !== 'active') {
            return ApiResponseType::sendJsonResponse(
                false,
                'Your account is inactive.',
                [],
                403
            );
        }

        $this->storeFcmToken($request, $user);

        $tokenName = $user->email ?? $user->mobile ?? 'customer-api';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => (new UserResource($user))->resolve($request),
            'assigned_permissions' => [],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'country' => ['nullable', 'string', 'max:255'],
            'iso_2' => ['nullable', 'string', 'size:2'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ]);

        $mobile = preg_replace('/\D+/', '', $validated['mobile']);

        if (User::query()->where('mobile', $mobile)->exists()) {
            throw ValidationException::withMessages([
                'mobile' => ['The mobile number has already been taken.'],
            ]);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $mobile,
            'password' => $validated['password'],
            'country' => $validated['country'] ?? 'Bangladesh',
            'iso_2' => strtoupper($validated['iso_2'] ?? 'BD'),
            'country_code' => '+880',
            'referral_code' => $this->generateReferralCode(),
            'friends_code' => $validated['friends_code'] ?? null,
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
            'logged_in_type' => UserLoginTypeEnum::PLATFORM->value,
        ]);

        $user->syncRoles([DefaultSystemRolesEnum::CUSTOMER->value]);

        $system = $this->settingService->getSettingValues('system');
        $welcomeAmount = max(
            0,
            (float) ($system['welcomeWalletBalanceAmount'] ?? 0)
        );

        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => WalletTypeEnum::CUSTOMER->value,
            'balance' => $welcomeAmount,
            'blocked_balance' => 0,
            'currency_code' => $system['currencyCode'] ?? 'BDT',
        ]);

        $this->storeFcmToken($request, $user);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
            // Local mail configuration may intentionally use the log driver.
        }

        $token = $user->createToken($validated['email'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Verification email sent.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => [
                'user' => (new UserResource($user))->resolve($request),
                'is_register' => true,
            ],
        ], 201);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        return ApiResponseType::sendJsonResponse(
            $status === Password::RESET_LINK_SENT,
            __($status),
            []
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['fcm_token'])) {
            $this->deviceTokenService->forget(
                $request->user(),
                $validated['fcm_token']
            );
        }

        $request->user()->currentAccessToken()?->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Logout successful.',
            []
        );
    }

    private function storeFcmToken(Request $request, User $user): void
    {
        $token = $request->input('fcm_token');

        if (! is_string($token) || $token === '') {
            return;
        }

        $this->deviceTokenService->sync(
            $user,
            $token,
            $request->input('device_type'),
            'customer'
        );
    }

    private function generateReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::query()->where('referral_code', $code)->exists());

        return $code;
    }

    private function buildMobileCandidates(
        string $value,
        ?string $countryCode
    ): array {
        $digits = preg_replace('/\D+/', '', $value);
        $candidates = [$value, $digits];

        if ($countryCode) {
            $code = preg_replace('/\D+/', '', $countryCode);

            if ($code !== '' && $digits !== '') {
                $candidates[] = $code.$digits;
                $candidates[] = '+'.$code.$digits;
                $candidates[] = '+'.$code.' '.$digits;
            }
        }

        return array_values(
            array_unique(
                array_filter(
                    $candidates,
                    static fn ($candidate): bool => is_string($candidate)
                        && $candidate !== ''
                )
            )
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\User\UserApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserApiController extends Controller
{
    public function getProfile(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Profile fetched successfully.',
            new UserResource($request->user())
        );
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'mobile' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('users', 'mobile')->ignore($user->id),
            ],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'iso_2' => ['sometimes', 'nullable', 'string', 'size:2'],
            'profile_image' => [
                'sometimes',
                'nullable',
                'image',
                'max:5120',
            ],
        ]);

        if (isset($validated['mobile'])) {
            $validated['mobile'] = preg_replace(
                '/\D+/',
                '',
                $validated['mobile']
            );
        }

        if (isset($validated['iso_2'])) {
            $validated['iso_2'] = strtoupper($validated['iso_2']);
        }

        unset($validated['profile_image']);
        $user->fill($validated)->save();

        if ($request->hasFile('profile_image')) {
            $user
                ->addMediaFromRequest('profile_image')
                ->toMediaCollection('profile_image');
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Profile updated successfully.',
            new UserResource($user->fresh())
        );
    }

    public function updateEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->forceFill([
            'email' => $validated['email'],
            'email_verified_at' => null,
        ])->save();

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
            // The local mail driver can intentionally be set to log.
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Email updated. Verification email sent.',
            new UserResource($user->fresh())
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $request->user();

        if (! $user->password || ! Hash::check(
            $validated['current_password'],
            $user->password
        )) {
            return ApiResponseType::sendJsonResponse(
                false,
                'The current password is incorrect.',
                [],
                422
            );
        }

        $user->password = $validated['password'];
        $user->save();

        return ApiResponseType::sendJsonResponse(
            true,
            'Password updated successfully.',
            []
        );
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->fcmTokens()->delete();
        $user->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Account deleted successfully.',
            []
        );
    }

    public function resendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->email) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Email is not set.',
                [],
                422
            );
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Email is already verified.',
                [],
                422
            );
        }

        $user->sendEmailVerificationNotification();

        return ApiResponseType::sendJsonResponse(
            true,
            'Verification email sent.',
            []
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\SettingApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api;

use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingApiController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService
    ) {
    }

    public function index(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Settings fetched successfully.',
            $this->settingService->getAllSettings()
        );
    }

    public function show(string $variable): JsonResponse
    {
        $setting = $this->settingService->getSettingByVariable($variable);

        if (! $setting) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Setting not found.',
                [],
                404
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Setting fetched successfully.',
            $setting
        );
    }

    public function settingVariables(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Setting variables fetched successfully.',
            SettingTypeEnum::values()
        );
    }

    public function firebaseConfig(): JsonResponse
    {
        $auth = $this->settingService->getSettingValues('authentication');
        $notification = $this->settingService
            ->getSettingValues('notification');

        return ApiResponseType::sendJsonResponse(
            true,
            'Firebase configuration fetched successfully.',
            [
                'apiKey' => $auth['fireBaseApiKey'] ?? '',
                'authDomain' => $auth['fireBaseAuthDomain'] ?? '',
                'projectId' => $auth['fireBaseProjectId'] ?? '',
                'storageBucket' => $auth['fireBaseStorageBucket'] ?? '',
                'messagingSenderId' => $auth[
                    'fireBaseMessagingSenderId'
                ] ?? '',
                'appId' => $auth['fireBaseAppId'] ?? '',
                'vapidKey' => $notification['vapIdKey'] ?? '',
            ]
        );
    }

    public function checkVersion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_version' => ['required', 'string'],
            'platform' => ['required', 'in:android,ios'],
            'app' => ['required', 'in:customer,rider,seller,web'],
        ]);

        $config = config(
            "fastsheba.apps.{$validated['app']}",
            []
        );

        $latest = (string) ($config['latest_version'] ?? '1.0.0');
        $minimum = (string) (
            $config['min_supported_version'] ?? $latest
        );

        $updateType = null;

        if (version_compare(
            $validated['current_version'],
            $minimum,
            '<'
        )) {
            $updateType = 'force_update';
        } elseif (version_compare(
            $validated['current_version'],
            $latest,
            '<'
        )) {
            $updateType = 'soft_update';
        }

        $urlKey = $validated['platform'].'_url';

        return ApiResponseType::sendJsonResponse(
            true,
            'Version check successful.',
            [
                'update_available' => $updateType !== null,
                'update_type' => $updateType ?? '',
                'min_supported_version' => $minimum,
                'latest_version' => $latest,
                'message' => $updateType === 'force_update'
                    ? 'A new version is required. Please update to continue.'
                    : (
                        $updateType === 'soft_update'
                            ? 'A newer version is available.'
                            : ''
                    ),
                'update_url' => $config[$urlKey] ?? '',
            ]
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\Api\DeliveryZoneApiController.php" -Content @'
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\DeliveryZone;
use App\Services\DeliveryZoneService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryZoneApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = DeliveryZone::query()->where('status', 'active');

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $zones = $query
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery zones found.',
            ApiResponseType::responseFromPaginator(
                $zones,
                DeliveryZoneResource::collection($zones->items())->resolve()
            )
        );
    }

    public function show(int $id): JsonResponse
    {
        $zone = DeliveryZone::query()
            ->where('status', 'active')
            ->find($id);

        if (! $zone) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Delivery zone not found.',
                [],
                404
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery zone found.',
            new DeliveryZoneResource($zone)
        );
    }

    public function checkDelivery(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];

        $zoneInfo = DeliveryZoneService::getZonesAtPoint(
            $latitude,
            $longitude
        );

        $isDeliverable = $zoneInfo['zone_count'] > 0;

        if ($isDeliverable && $zoneInfo['zone_id']) {
            $user = Auth::guard('sanctum')->user();

            if ($user) {
                $user->deliveryZones()->sync([$zoneInfo['zone_id']]);
            }
        }

        return ApiResponseType::sendJsonResponse(
            true,
            $isDeliverable
                ? 'Delivery is available.'
                : 'Delivery is not available.',
            [
                'is_deliverable' => $isDeliverable,
                'zone_count' => $zoneInfo['zone_count'],
                'zone' => $zoneInfo['zone']
                    ? (new DeliveryZoneResource(
                        $zoneInfo['zone']
                    ))->resolve($request)
                    : null,
                'zone_id' => $zoneInfo['zone_id'],
                'coordinates' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            ]
        );
    }

    public function search(Request $request): JsonResponse
    {
        $search = (string) $request->input('search', '');

        $results = DeliveryZone::query()
            ->where('status', 'active')
            ->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (DeliveryZone $zone): array => [
                'id' => $zone->id,
                'value' => $zone->id,
                'text' => $zone->name,
            ]);

        return response()->json($results);
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\app\Http\Controllers\DeviceTokenController.php" -Content @'
<?php

namespace App\Http\Controllers;

use App\Services\DeviceTokenService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly DeviceTokenService $deviceTokenService
    ) {
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'new_token' => ['nullable', 'string', 'max:255'],
            'previous_token' => ['nullable', 'string', 'max:255'],
            'old_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['required', 'in:android,ios,web'],
            'role_type' => ['required', 'in:admin,customer,seller,rider'],
        ]);

        $token = $validated['fcm_token']
            ?? $validated['new_token']
            ?? null;

        if (! $token) {
            return ApiResponseType::sendJsonResponse(
                false,
                'The FCM token field is required.',
                [],
                422
            );
        }

        $this->deviceTokenService->sync(
            $request->user(),
            $token,
            $validated['device_type'],
            $validated['role_type'],
            $validated['previous_token']
                ?? $validated['old_token']
                ?? null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Device synced successfully.',
            []
        );
    }

    public function forget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:255'],
        ]);

        $this->deviceTokenService->forget(
            $request->user(),
            $validated['fcm_token']
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Device forgotten successfully.',
            []
        );
    }
}
'@

Write-Utf8NoBom -Path "$BackendPath\routes\api.php" -Content @'
<?php

use App\Http\Controllers\Api\DeliveryZoneApiController;
use App\Http\Controllers\Api\SettingApiController;
use App\Http\Controllers\Api\User\AuthApiController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthApiController::class, 'register'])
    ->name('register');

Route::post('login', [AuthApiController::class, 'login'])
    ->name('login');

Route::post(
    'forget-password',
    [AuthApiController::class, 'forgotPassword']
)->name('password');

Route::post(
    'verify-user',
    [AuthApiController::class, 'verifyUser']
);

Route::prefix('settings')->name('api.')->group(function (): void {
    Route::get('/', [SettingApiController::class, 'index'])
        ->name('settings.index');

    Route::get(
        'firebase-config',
        [SettingApiController::class, 'firebaseConfig']
    )->name('settings.firebase-config');

    Route::get(
        'check-version',
        [SettingApiController::class, 'checkVersion']
    )->name('settings.check-version');

    Route::get(
        'variables',
        [SettingApiController::class, 'settingVariables']
    )->name('settings.variables');

    Route::get(
        '{setting}',
        [SettingApiController::class, 'show']
    )->name('settings.show');
});

Route::prefix('delivery-zone')
    ->name('delivery_zone.')
    ->group(function (): void {
        Route::get('/', [DeliveryZoneApiController::class, 'index']);
        Route::get(
            'check',
            [DeliveryZoneApiController::class, 'checkDelivery']
        );
        Route::get(
            'search',
            [DeliveryZoneApiController::class, 'search']
        )->name('search');
        Route::get(
            '{id}',
            [DeliveryZoneApiController::class, 'show']
        );
    });

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthApiController::class, 'logout']);

    Route::post(
        'devices/sync',
        [DeviceTokenController::class, 'sync']
    );

    Route::delete(
        'devices',
        [DeviceTokenController::class, 'forget']
    );

    Route::prefix('user')->name('user.')->group(function (): void {
        Route::get(
            'profile',
            [UserApiController::class, 'getProfile']
        );

        Route::post(
            'profile',
            [UserApiController::class, 'updateProfile']
        );

        Route::post(
            'change-password',
            [UserApiController::class, 'changePassword']
        )->name('change-password');

        Route::post(
            'update-email',
            [UserApiController::class, 'updateEmail']
        )->name('update-email');

        Route::post(
            'email/verification-notification',
            [UserApiController::class, 'resendEmailVerification']
        )->middleware('throttle:6,1')
            ->name('verification.send');

        Route::delete(
            'delete-account',
            [UserApiController::class, 'deleteAccount']
        )->name('delete-account');
    });
});
'@

Write-Host ""
Write-Host "Phase 2A Authentication and Public API files written." -ForegroundColor Green
Write-Host "Backup created at: $backupRoot" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next commands:"
Write-Host "  Set-Location `"$BackendPath`""
Write-Host "  herd php artisan optimize:clear"
Write-Host "  herd php artisan route:list --path=api"
Write-Host "  herd php artisan test"
