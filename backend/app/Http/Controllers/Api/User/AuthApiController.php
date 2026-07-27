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

        $user->refresh();

        $data = (new UserResource($user))->resolve($request);
        $data['is_register'] = true;

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Verification email sent.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => $data,
        ]);
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