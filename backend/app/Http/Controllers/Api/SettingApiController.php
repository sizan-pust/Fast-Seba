<?php

namespace App\Http\Controllers\Api;

use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingApiController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService
    ) {
    }

    public function index(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Settings fetched successfully.',
            $this->settingService->getAllSettings()
        );
    }

    public function show(string $variable): JsonResponse
    {
        $setting = $this->settingService->getSettingByVariable($variable);

        if (! $setting) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Setting not found.',
                [],
                404
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Setting fetched successfully.',
            $setting
        );
    }

    public function settingVariables(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Setting variables fetched successfully.',
            SettingTypeEnum::values()
        );
    }

    public function firebaseConfig(): JsonResponse
    {
        $auth = $this->settingService->getSettingValues('authentication');
        $notification = $this->settingService
            ->getSettingValues('notification');

        return ApiResponseType::sendJsonResponse(
            true,
            'Firebase configuration fetched successfully.',
            [
                'apiKey' => $auth['fireBaseApiKey'] ?? '',
                'authDomain' => $auth['fireBaseAuthDomain'] ?? '',
                'projectId' => $auth['fireBaseProjectId'] ?? '',
                'storageBucket' => $auth['fireBaseStorageBucket'] ?? '',
                'messagingSenderId' => $auth[
                    'fireBaseMessagingSenderId'
                ] ?? '',
                'appId' => $auth['fireBaseAppId'] ?? '',
                'vapidKey' => $notification['vapIdKey'] ?? '',
            ]
        );
    }

    public function checkVersion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_version' => ['required', 'string'],
            'platform' => ['required', 'in:android,ios'],
            'app' => ['required', 'in:customer,rider,seller,web'],
        ]);

        $config = config(
            "fastsheba.apps.{$validated['app']}",
            []
        );

        $latest = (string) ($config['latest_version'] ?? '1.0.0');
        $minimum = (string) (
            $config['min_supported_version'] ?? $latest
        );

        $updateType = null;

        if (version_compare(
            $validated['current_version'],
            $minimum,
            '<'
        )) {
            $updateType = 'force_update';
        } elseif (version_compare(
            $validated['current_version'],
            $latest,
            '<'
        )) {
            $updateType = 'soft_update';
        }

        $urlKey = $validated['platform'].'_url';

        return ApiResponseType::sendJsonResponse(
            true,
            'Version check successful.',
            [
                'update_available' => $updateType !== null,
                'update_type' => $updateType ?? '',
                'min_supported_version' => $minimum,
                'latest_version' => $latest,
                'message' => $updateType === 'force_update'
                    ? 'A new version is required. Please update to continue.'
                    : (
                        $updateType === 'soft_update'
                            ? 'A newer version is available.'
                            : ''
                    ),
                'update_url' => $config[$urlKey] ?? '',
            ]
        );
    }
}