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