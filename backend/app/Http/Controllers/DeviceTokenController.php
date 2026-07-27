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