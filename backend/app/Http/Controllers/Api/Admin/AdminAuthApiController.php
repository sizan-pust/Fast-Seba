<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where(
                'access_panel',
                GuardNameEnum::ADMIN->value
            )
            ->where('email', $data['email'])
            ->first();

        if (
            ! $user
            || ! $user->password
            || ! Hash::check(
                $data['password'],
                $user->password
            )
        ) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Invalid admin credentials.',
                [],
                401
            );
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Admin login successful.',
            'access_token' => $user
                ->createToken('admin-api')
                ->plainTextToken,
            'token_type' => 'Bearer',
            'data' => new UserResource($user),
        ]);
    }

    public function logout(
        Request $request
    ): JsonResponse {
        $request->user()
            ->currentAccessToken()
            ?->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin logged out.',
            []
        );
    }
}
