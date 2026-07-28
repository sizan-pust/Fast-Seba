<?php

namespace App\Http\Controllers\Api\DeliveryBoy;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryBoyResource;
use App\Models\DeliveryBoy;
use App\Models\User;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeliveryBoyAuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->first();

        $rider = $user
            ? DeliveryBoy::query()
                ->with([
                    'user',
                    'deliveryZone',
                    'location',
                ])
                ->where('user_id', $user->id)
                ->first()
            : null;

        if (
            ! $user
            || ! $rider
            || ! $user->password
            || ! Hash::check(
                $data['password'],
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

        if ($rider->is_blocked) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Delivery partner account is blocked.',
                [
                    'blocked_reason' =>
                        $rider->blocked_reason,
                ],
                403
            );
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Delivery partner login successful.',
            'access_token' => $user
                ->createToken('delivery-boy-api')
                ->plainTextToken,
            'token_type' => 'Bearer',
            'data' => new DeliveryBoyResource($rider),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $rider = DeliveryBoy::query()
            ->with([
                'user',
                'deliveryZone',
                'location',
            ])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery partner profile fetched.',
            new DeliveryBoyResource($rider)
        );
    }

    public function updateStatus(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'status' => [
                'required',
                'in:available,offline',
            ],
        ]);

        $rider = DeliveryBoy::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($rider->status === 'on_delivery') {
            return ApiResponseType::sendJsonResponse(
                false,
                'Status cannot be changed during an active delivery.',
                [],
                422
            );
        }

        $rider->update([
            'status' => $data['status'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery partner status updated.',
            new DeliveryBoyResource(
                $rider->fresh([
                    'user',
                    'deliveryZone',
                    'location',
                ])
            )
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery partner logged out.',
            []
        );
    }
}
