<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function __construct(
        protected SettingService $settingService
    ) {
    }

    public function sendSms(
        string $mobile,
        string $message
    ): array {
        $config = $this->settingService
            ->getSettingValues('authentication');

        if (empty($config['customSms'])) {
            return [
                'success' => false,
                'message' => 'Custom SMS is not enabled.',
            ];
        }

        return $this->sendCustomSms($mobile, $message, $config);
    }

    private function sendCustomSms(
        string $mobile,
        string $message,
        array $config
    ): array {
        try {
            $url = (string) ($config['customSmsUrl'] ?? '');

            if ($url === '') {
                return [
                    'success' => false,
                    'message' => 'SMS gateway URL is missing.',
                ];
            }

            $method = strtoupper(
                (string) ($config['customSmsMethod'] ?? 'GET')
            );

            $bag = [
                '{mobile}' => $mobile,
                '{message}' => $message,
            ];

            $headers = $this->buildPairs(
                $config['customSmsHeaderKey'] ?? [],
                $config['customSmsHeaderValue'] ?? [],
                $bag
            );

            $query = $this->buildPairs(
                $config['customSmsParamsKey'] ?? [],
                $config['customSmsParamsValue'] ?? [],
                $bag
            );

            $body = $this->buildPairs(
                $config['customSmsBodyKey'] ?? [],
                $config['customSmsBodyValue'] ?? [],
                $bag
            );

            $url = strtr(
                $url,
                array_map('urlencode', $bag)
            );

            $request = Http::withHeaders($headers)
                ->timeout(8)
                ->connectTimeout(3);

            $encoded = $this->applyBodyEncoding(
                $request,
                $headers
            );

            $response = match ($method) {
                'POST' => $encoded->post(
                    $url.($query ? '?'.http_build_query($query) : ''),
                    $body
                ),
                'PUT' => $encoded->put($url, $body),
                'PATCH' => $encoded->patch($url, $body),
                default => $request->get(
                    $url,
                    array_merge($query, $body)
                ),
            };

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully.',
                ];
            }

            Log::error('Custom SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'SMS gateway returned an error.',
            ];
        } catch (\Throwable $e) {
            Log::error('Custom SMS exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function applyBodyEncoding(
        PendingRequest $request,
        array $headers
    ): PendingRequest {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, 'Content-Type') !== 0) {
                continue;
            }

            $contentType = strtolower((string) $value);

            if (str_contains($contentType, 'application/json')) {
                return $request->asJson();
            }

            if (str_contains(
                $contentType,
                'multipart/form-data'
            )) {
                return $request->asMultipart();
            }
        }

        return $request->asForm();
    }

    private function buildPairs(
        array $keys,
        array $values,
        array $bag
    ): array {
        $output = [];

        foreach ($keys as $index => $key) {
            if ($key === null || $key === '') {
                continue;
            }

            $value = $values[$index] ?? '';

            $output[(string) $key] = is_string($value)
                ? strtr($value, $bag)
                : $value;
        }

        return $output;
    }
}