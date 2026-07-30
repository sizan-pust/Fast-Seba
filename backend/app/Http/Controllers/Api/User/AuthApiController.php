<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AppleCallbackRequest;
use App\Http\Requests\Auth\GoogleCallbackRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\DeviceTokenService;
use App\Services\FirebaseAuthService;
use App\Services\OtpService;
use App\Services\SettingService;
use App\Services\SocialAuthService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly DeviceTokenService $deviceTokenService,
        private readonly FirebaseAuthService $firebaseAuthService,
        private readonly SocialAuthService $socialAuthService,
        private readonly OtpService $otpService
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
            ? User::query()
                ->where('email', $validated['value'])
                ->first()
            : User::query()
                ->whereIn(
                    'mobile',
                    $this->buildMobileCandidates(
                        $validated['value'],
                        $validated['country_code'] ?? null
                    )
                )
                ->first();

        return ApiResponseType::sendJsonResponse(
            $user !== null,
            $user ? 'User found.' : 'User not found.',
            [
                'exists' => $user !== null,
                'type' => $validated['type'],
                'value' => $validated['value'],
                'country_code' => $validated['country_code'] ?? null,
            ]
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

        $query = User::query()
            ->where('access_panel', GuardNameEnum::WEB->value);

        if (! empty($validated['email'])) {
            $query->where('email', $validated['email']);
        } else {
            $query->whereIn(
                'mobile',
                $this->otpService->mobileCandidates(
                    $validated['mobile']
                )
            );
        }

        $user = $query->first();

        if (
            ! $user
            || ! $user->password
            || ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
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

        return $this->respondWithToken(
            $request,
            $user,
            false,
            'Login successful.'
        );
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
            ],
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

        $mobile = $this->otpService->sanitizeMobile(
            $validated['mobile']
        );

        if (User::query()->whereIn(
            'mobile',
            $this->otpService->mobileCandidates($mobile)
        )->exists()) {
            throw ValidationException::withMessages([
                'mobile' => [
                    'The mobile number has already been taken.',
                ],
            ]);
        }

        $user = DB::transaction(
            function () use ($validated, $mobile, $request): User {
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

                $user->syncRoles([
                    DefaultSystemRolesEnum::CUSTOMER->value,
                ]);

                $system = $this->settingService
                    ->getSettingValues('system');

                Wallet::query()->create([
                    'user_id' => $user->id,
                    'type' => WalletTypeEnum::CUSTOMER->value,
                    'balance' => max(
                        0,
                        (float) (
                            $system['welcomeWalletBalanceAmount'] ?? 0
                        )
                    ),
                    'blocked_balance' => 0,
                    'currency_code' => $system['currencyCode'] ?? 'BDT',
                ]);

                $this->storeFcmToken($request, $user);

                return $user;
            }
        );

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
        }

        return $this->respondWithToken(
            $request,
            $user,
            true,
            'Registration successful. Verification email sent.'
        );
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

    public function googleCallback(
        GoogleCallbackRequest $request
    ): JsonResponse {
        $auth = $this->settingService
            ->getSettingValues('authentication');

        if (empty($auth['googleLogin'])) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Google login is not enabled.',
                [],
                422
            );
        }

        try {
            $profile = $this->firebaseAuthService->verify(
                $request->validated('idToken')
            );

            $result = $this->socialAuthService
                ->loginOrRegisterFromGoogle(
                    $profile,
                    $request->validated('friends_code'),
                    [
                        'country' => $request->validated('country'),
                        'iso_2' => $request->validated('iso_2'),
                    ]
                );

            $this->storeFcmToken(
                $request,
                $result['user']
            );

            return $this->respondWithToken(
                $request,
                $result['user'],
                $result['is_new'],
                $result['is_new']
                    ? 'Registration successful.'
                    : 'Login successful.'
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid Firebase token.',
                ['error' => $e->getMessage()],
                401
            );
        }
    }

    public function appleCallback(
        AppleCallbackRequest $request
    ): JsonResponse {
        $auth = $this->settingService
            ->getSettingValues('authentication');

        if (empty($auth['appleLogin'])) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Apple login is not enabled.',
                [],
                422
            );
        }

        try {
            $profile = $this->firebaseAuthService->verify(
                $request->validated('idToken')
            );

            $result = $this->socialAuthService
                ->loginOrRegisterFromApple(
                    $profile,
                    $request->validated('friends_code'),
                    [
                        'country' => $request->validated('country'),
                        'iso_2' => $request->validated('iso_2'),
                    ]
                );

            $this->storeFcmToken(
                $request,
                $result['user']
            );

            return $this->respondWithToken(
                $request,
                $result['user'],
                $result['is_new'],
                $result['is_new']
                    ? 'Registration successful.'
                    : 'Login successful.'
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid Firebase token.',
                ['error' => $e->getMessage()],
                401
            );
        }
    }

    public function phoneCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idToken' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ]);

        try {
            $profile = $this->firebaseAuthService->verify(
                $validated['idToken']
            );

            if (empty($profile['phone_number'])) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'Phone number was not found in Firebase.',
                    [],
                    422
                );
            }

            $authedUser = auth('sanctum')->user();

            if ($authedUser) {
                $mobile = $this->otpService->sanitizeMobile(
                    $profile['phone_number']
                );

                $conflict = User::query()
                    ->where('id', '!=', $authedUser->id)
                    ->whereIn(
                        'mobile',
                        $this->otpService->mobileCandidates($mobile)
                    )
                    ->exists();

                if ($conflict) {
                    return ApiResponseType::sendJsonResponse(
                        false,
                        'This mobile number is already in use.',
                        [],
                        422
                    );
                }

                $authedUser->forceFill([
                    'mobile' => $mobile,
                    'country_code' => '+880',
                    'firebase_uid' => $profile['uid'],
                    'mobile_verified_at' => now(),
                    'name' => $validated['name']
                        ?? $authedUser->name,
                ])->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Mobile verified successfully.',
                    // Keep the current Sanctum token when an authenticated
                    // customer verifies or changes the phone number.
                    'access_token' => $request->bearerToken(),
                    // Backward-compatible alias for older clients.
                    'token' => $request->bearerToken(),
                    'token_type' => 'Bearer',
                    'data' => new UserResource(
                        $authedUser->fresh()
                    ),
                    'assigned_permissions' => [],
                ]);
            }

            $result = $this->socialAuthService->loginWithPhone(
                $profile,
                $validated['name'] ?? null
            );

            if (! $result['user']) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'User not found.',
                    [],
                    404
                );
            }

            $this->storeFcmToken(
                $request,
                $result['user']
            );

            return $this->respondWithToken(
                $request,
                $result['user'],
                false,
                'Login successful.'
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid Firebase token.',
                ['error' => $e->getMessage()],
                401
            );
        }
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

    private function respondWithToken(
        Request $request,
        User $user,
        bool $isRegister,
        string $message
    ): JsonResponse {
        $data = (new UserResource($user->fresh()))
            ->resolve($request);

        $data['is_register'] = $isRegister;

        return response()->json([
            'success' => true,
            'message' => $message,
            'access_token' => $user->createToken(
                $user->email
                ?? $user->mobile
                ?? $user->firebase_uid
                ?? 'api-token'
            )->plainTextToken,
            'token_type' => 'Bearer',
            'data' => $data,
            'assigned_permissions' => [],
        ]);
    }

    private function storeFcmToken(
        Request $request,
        User $user
    ): void {
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
        } while (User::query()->where(
            'referral_code',
            $code
        )->exists());

        return $code;
    }

    private function buildMobileCandidates(
        string $value,
        ?string $countryCode
    ): array {
        $candidates = $this->otpService
            ->mobileCandidates($value);

        if ($countryCode) {
            $code = preg_replace('/\D+/', '', $countryCode);
            $digits = preg_replace('/\D+/', '', $value);

            $candidates[] = $code.$digits;
            $candidates[] = '+'.$code.$digits;
            $candidates[] = '+'.$code.' '.$digits;
        }

        return array_values(array_unique($candidates));
    }
}