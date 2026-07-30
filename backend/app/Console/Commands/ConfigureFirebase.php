<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use JsonException;

class ConfigureFirebase extends Command
{
    protected $signature = 'fastsheba:configure-firebase
        {--web-config= : Path to Firebase Web SDK config JSON}
        {--service-account= : Path to Firebase Admin service-account JSON}
        {--google=1 : Enable Google sign-in (1 or 0)}
        {--phone=0 : Enable Firebase phone OTP (1 or 0)}
        {--apple=0 : Enable Apple sign-in (1 or 0)}
        {--vapid-key= : Optional FCM Web Push VAPID key}';

    protected $description = 'Configure Firebase Web Auth and Laravel Admin SDK for FastSheba.';

    public function handle(): int
    {
        $webConfigPath = $this->absolutePath((string) $this->option('web-config'));
        $serviceAccountPath = $this->absolutePath(
            (string) $this->option('service-account')
        );

        if (! is_file($webConfigPath)) {
            $this->error("Firebase web config was not found: {$webConfigPath}");

            return self::FAILURE;
        }

        if (! is_file($serviceAccountPath)) {
            $this->error("Firebase service-account file was not found: {$serviceAccountPath}");

            return self::FAILURE;
        }

        try {
            $webPayload = $this->decodeJsonFile($webConfigPath);
            $serviceAccount = $this->decodeJsonFile($serviceAccountPath);
        } catch (JsonException $exception) {
            $this->error('One of the Firebase files is not valid JSON: '.$exception->getMessage());

            return self::FAILURE;
        }

        $webConfig = $this->findWebConfig($webPayload);

        if ($webConfig === null) {
            $this->error(
                'Could not find apiKey, authDomain, projectId and appId in the web config JSON.'
            );

            return self::FAILURE;
        }

        $adminProjectId = (string) Arr::get($serviceAccount, 'project_id', '');
        $webProjectId = (string) Arr::get($webConfig, 'projectId', '');

        if ($adminProjectId === '' || $webProjectId === '') {
            $this->error('Firebase project_id/projectId is missing.');

            return self::FAILURE;
        }

        if ($adminProjectId !== $webProjectId) {
            $this->error(
                "Firebase project mismatch: web={$webProjectId}, service-account={$adminProjectId}"
            );

            return self::FAILURE;
        }

        $targetDirectory = storage_path('app/private/firebase');
        $targetServiceAccount = $targetDirectory.'/service-account.json';

        File::ensureDirectoryExists($targetDirectory);
        File::copy($serviceAccountPath, $targetServiceAccount);

        $googleEnabled = $this->booleanOption('google');
        $phoneEnabled = $this->booleanOption('phone');
        $appleEnabled = $this->booleanOption('apple');

        $authSetting = Setting::query()->firstOrCreate(
            ['variable' => 'authentication'],
            ['value' => []]
        );
        $auth = is_array($authSetting->value) ? $authSetting->value : [];

        $authSetting->value = array_merge($auth, [
            'firebase' => true,
            'googleLogin' => $googleEnabled,
            'appleLogin' => $appleEnabled,
            'smsGateway' => $phoneEnabled
                ? 'firebase'
                : (($auth['smsGateway'] ?? '') === 'firebase'
                    ? ''
                    : ($auth['smsGateway'] ?? '')),
            'fireBaseApiKey' => (string) Arr::get($webConfig, 'apiKey', ''),
            'fireBaseAuthDomain' => (string) Arr::get($webConfig, 'authDomain', ''),
            'fireBaseDatabaseURL' => (string) Arr::get($webConfig, 'databaseURL', ''),
            'fireBaseProjectId' => $webProjectId,
            'fireBaseStorageBucket' => (string) Arr::get($webConfig, 'storageBucket', ''),
            'fireBaseMessagingSenderId' => (string) Arr::get(
                $webConfig,
                'messagingSenderId',
                ''
            ),
            'fireBaseAppId' => (string) Arr::get($webConfig, 'appId', ''),
            'fireBaseMeasurementId' => (string) Arr::get(
                $webConfig,
                'measurementId',
                ''
            ),
        ]);
        $authSetting->save();

        $vapidKey = trim((string) $this->option('vapid-key'));
        $notificationSetting = Setting::query()->firstOrCreate(
            ['variable' => 'notification'],
            ['value' => []]
        );
        $notification = is_array($notificationSetting->value)
            ? $notificationSetting->value
            : [];

        $notificationSetting->value = array_merge($notification, [
            'firebaseProjectId' => $webProjectId,
            'vapIdKey' => $vapidKey !== ''
                ? $vapidKey
                : ($notification['vapIdKey'] ?? ''),
        ]);
        $notificationSetting->save();

        $this->writeEnvironmentValue(
            'FIREBASE_CREDENTIALS',
            str_replace('\\', '/', $targetServiceAccount)
        );

        $this->components->info('Firebase configuration saved successfully.');
        $this->table(
            ['Item', 'Value'],
            [
                ['Project', $webProjectId],
                ['Google sign-in', $googleEnabled ? 'enabled' : 'disabled'],
                ['Phone OTP', $phoneEnabled ? 'enabled' : 'disabled'],
                ['Apple sign-in', $appleEnabled ? 'enabled' : 'disabled'],
                ['Service account', $targetServiceAccount],
            ]
        );

        if ($phoneEnabled) {
            $this->warn(
                'Real Firebase Phone Auth SMS requires a billing-linked Blaze project. Use Firebase test phone numbers during local development.'
            );
        }

        $this->newLine();
        $this->line('Next: php artisan optimize:clear');

        return self::SUCCESS;
    }

    private function booleanOption(string $name): bool
    {
        return filter_var(
            $this->option($name),
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? false;
    }

    private function absolutePath(string $path): string
    {
        $path = trim($path, " \t\n\r\0\x0B\"");

        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function findWebConfig(mixed $payload): ?array
    {
        if (is_string($payload)) {
            try {
                return $this->findWebConfig(json_decode(
                    $payload,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                ));
            } catch (JsonException) {
                return null;
            }
        }

        if (! is_array($payload)) {
            return null;
        }

        if (
            isset(
                $payload['apiKey'],
                $payload['authDomain'],
                $payload['projectId'],
                $payload['appId']
            )
        ) {
            return $payload;
        }

        foreach ($payload as $value) {
            $config = $this->findWebConfig($value);

            if ($config !== null) {
                return $config;
            }
        }

        return null;
    }

    private function decodeJsonFile(string $path): array
    {
        $content = File::get($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $decoded = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (! is_array($decoded)) {
            throw new JsonException('The JSON root must be an object or array.');
        }

        return $decoded;
    }

    private function writeEnvironmentValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            return;
        }

        $content = File::get($envPath);
        $line = $key.'="'.str_replace('"', '\\"', $value).'"';
        $pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';

        if (preg_match($pattern, $content) === 1) {
            $content = preg_replace($pattern, $line, $content) ?? $content;
        } else {
            $content = rtrim($content).PHP_EOL.$line.PHP_EOL;
        }

        File::put($envPath, $content);
    }
}
