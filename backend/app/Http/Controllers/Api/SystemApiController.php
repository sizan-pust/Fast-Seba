<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosCustomerDisplay;
use App\Services\SystemOperationsService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;

class SystemApiController extends Controller
{
    public function __construct(
        protected SystemOperationsService $operations
    ) {
    }

    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function ready(): JsonResponse
    {
        $readiness = $this->operations->readiness();

        return response()->json(
            $readiness,
            $readiness['ready'] ? 200 : 503
        );
    }

    public function openApi(): JsonResponse
    {
        return response()->json(
            $this->operations->openApiDocument()
        );
    }

    public function customerDisplay(
        string $token
    ): JsonResponse {
        $display = PosCustomerDisplay::query()
            ->where('token', $token)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        $display->update(['last_seen_at' => now()]);

        return ApiResponseType::sendJsonResponse(
            true,
            'POS customer display state fetched.',
            [
                'token' => $display->token,
                'state' => $display->state_payload ?? [],
                'updated_at' =>
                    $display->updated_at?->toIso8601String(),
            ]
        );
    }
}
