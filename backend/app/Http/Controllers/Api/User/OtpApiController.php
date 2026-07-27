<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\DeviceTokenService;
use App\Services\OtpService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;

class OtpApiController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected DeviceTokenService $deviceTokenService
    ) {
    }

    public function sendOtp(
        SendOtpRequest $request
    ): JsonResponse {
        $result = $this->otpService->sendOtp(
            $request->validated('mobile')
        );

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? [
                'retry_after' => $result['retry_after'] ?? null,
            ],
            $result['success'] ? 200 : 422
        );
    }

    public function verifyOtp(
        VerifyOtpRequest $request
    ): JsonResponse {
        $validated = $request->validated();
        $mobile = $this->otpService->sanitizeMobile(
            $validated['mobile']
        );

        $authenticatedUser = auth('sanctum')->user();

        $existingUser = User::query()
            ->whereIn(
                'mobile',
                $this->otpService->mobileCandidates($mobile)
            )
            ->exists();

        $verification = $this->otpService->verifyOtp(
            $mobile,
            $validated['otp'],
            false
        );

        if (! $verification['success']) {
            return ApiResponseType::sendJsonResponse(
                false,
                $verification['message'],
                $verification,
                422
            );
        }

        $authedUser = $authenticatedUser;

        if ($authedUser) {
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
                'mobile_verified_at' => now(),
                'name' => $validated['name']
                    ?? $authedUser->name,
                'friends_code' => $validated['friends_code']
                    ?? $authedUser->friends_code,
            ])->save();

            $this->otpService->consumeOtp($mobile);

            return ApiResponseType::sendJsonResponse(
                true,
                'Mobile verified successfully.',
                new UserResource($authedUser->fresh())
            );
        }

        $resolved = $this->otpService->resolveMobileUser(
            $mobile,
            $validated
        );

        if ($resolved['status'] === 'pending') {
            return ApiResponseType::sendJsonResponse(
                true,
                'OTP verified. Registration details are required.',
                [
                    'new_user' => true,
                    'mobile' => $mobile,
                    'is_register' => true,
                ]
            );
        }

        $user = $resolved['user'];

        $this->otpService->consumeOtp($mobile);

        if (! empty($validated['fcm_token'])) {
            $this->deviceTokenService->sync(
                $user,
                $validated['fcm_token'],
                $validated['device_type'] ?? null,
                'customer'
            );
        }

        return $this->tokenResponse(
            $user,
            $resolved['status'] === 'created'
        );
    }

    private function tokenResponse(
        User $user,
        bool $isRegister
    ): JsonResponse {
        $data = (new UserResource($user->fresh()))
            ->resolve(request());

        $data['is_register'] = $isRegister;

        return response()->json([
            'success' => true,
            'message' => $isRegister
                ? 'Registration successful.'
                : 'Verified successfully.',
            'access_token' => $user
                ->createToken($user->mobile ?? 'mobile-api')
                ->plainTextToken,
            'token_type' => 'Bearer',
            'data' => $data,
        ]);
    }
}