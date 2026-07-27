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