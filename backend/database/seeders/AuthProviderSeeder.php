<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class AuthProviderSeeder extends Seeder
{
    public function run(): void
    {
        $setting = Setting::query()->firstOrCreate(
            ['variable' => 'authentication'],
            ['value' => []]
        );

        $setting->value = array_merge([
            'customSms' => false,
            'customSmsUrl' => '',
            'customSmsMethod' => 'GET',
            'customSmsHeaderKey' => [],
            'customSmsHeaderValue' => [],
            'customSmsParamsKey' => [],
            'customSmsParamsValue' => [],
            'customSmsBodyKey' => [],
            'customSmsBodyValue' => [],
            'customSmsTextFormatData' =>
                'Your OTP is: {otp}. Valid for {minutes} minutes.',
            'firebase' => false,
            'googleLogin' => false,
            'appleLogin' => false,
            'smsGateway' => '',
            'fireBaseApiKey' => '',
            'fireBaseAuthDomain' => '',
            'fireBaseDatabaseURL' => '',
            'fireBaseProjectId' => '',
            'fireBaseStorageBucket' => '',
            'fireBaseMessagingSenderId' => '',
            'fireBaseAppId' => '',
            'fireBaseMeasurementId' => '',
        ], $setting->value ?? []);

        $setting->save();
    }
}