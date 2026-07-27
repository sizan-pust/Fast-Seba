<?php

return [
    'apps' => [
        'customer' => [
            'latest_version' => env('CUSTOMER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('CUSTOMER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('CUSTOMER_ANDROID_URL', ''),
            'ios_url' => env('CUSTOMER_IOS_URL', ''),
        ],
        'seller' => [
            'latest_version' => env('SELLER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('SELLER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('SELLER_ANDROID_URL', ''),
            'ios_url' => env('SELLER_IOS_URL', ''),
        ],
        'rider' => [
            'latest_version' => env('RIDER_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('RIDER_APP_MIN_VERSION', '1.0.0'),
            'android_url' => env('RIDER_ANDROID_URL', ''),
            'ios_url' => env('RIDER_IOS_URL', ''),
        ],
        'web' => [
            'latest_version' => env('WEB_APP_LATEST_VERSION', '1.0.0'),
            'min_supported_version' => env('WEB_APP_MIN_VERSION', '1.0.0'),
            'android_url' => '',
            'ios_url' => '',
        ],
    ],

    'otp' => [
        'expires_in_seconds' => (int) env('OTP_EXPIRES_IN', 600),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 3),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 60),
        'test_mode' => (bool) env('OTP_TEST_MODE', false),
        'test_code' => (string) env('OTP_TEST_CODE', '123456'),
    ],

    'firebase' => [
        'service_account_path' => env(
            'FIREBASE_CREDENTIALS',
            storage_path('app/private/settings/service-account-file.json')
        ),
    ],
];