<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdempotencyService
{
    public function replayOrReserve(
        ?User $user,
        string $scope,
        string $key,
        array $payload
    ): ?JsonResponse {
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $user,
            $scope,
            $key,
            $hash
        ): ?JsonResponse {
            $record = DB::table('idempotency_keys')
                ->where('scope', $scope)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($record) {
                if ($record->request_hash !== $hash) {
                    throw ValidationException::withMessages([
                        'Idempotency-Key' =>
                            'The same key was reused with a different request.',
                    ]);
                }

                if ($record->response_code !== null) {
                    return response()->json(
                        json_decode($record->response_body, true) ?? [],
                        (int) $record->response_code
                    );
                }

                throw ValidationException::withMessages([
                    'Idempotency-Key' =>
                        'A request with this key is already being processed.',
                ]);
            }

            DB::table('idempotency_keys')->insert([
                'user_id' => $user?->id,
                'scope' => $scope,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return null;
        });
    }

    public function remember(
        string $scope,
        string $key,
        int $statusCode,
        array $body
    ): void {
        DB::table('idempotency_keys')
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->update([
                'response_code' => $statusCode,
                'response_body' => json_encode(
                    $body,
                    JSON_THROW_ON_ERROR
                ),
                'updated_at' => now(),
            ]);
    }
}
