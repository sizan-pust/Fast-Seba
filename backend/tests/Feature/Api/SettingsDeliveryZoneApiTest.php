<?php

namespace Tests\Feature\Api;

use App\Enums\GuardNameEnum;
use App\Models\DeliveryZone;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsDeliveryZoneApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->create([
            'variable' => 'system',
            'value' => [
                'appName' => 'FastSheba',
                'currencyCode' => 'BDT',
                'currencySymbol' => 'à§³',
            ],
        ]);

        Setting::query()->create([
            'variable' => 'authentication',
            'value' => [
                'firebase' => false,
                'googleLogin' => false,
                'appleLogin' => false,
            ],
        ]);

        Setting::query()->create([
            'variable' => 'notification',
            'value' => [
                'vapIdKey' => '',
                'firebaseProjectId' => '',
            ],
        ]);

        Setting::query()->create([
            'variable' => 'app',
            'value' => [
                'customerPlaystoreLink' => '',
                'customerAppstoreLink' => '',
            ],
        ]);

        DeliveryZone::query()->create([
            'name' => 'Dhaka Test Zone',
            'slug' => 'dhaka-test-zone',
            'center_latitude' => 23.8103,
            'center_longitude' => 90.4125,
            'radius_km' => 25,
            'delivery_time_per_km' => 3,
            'regular_delivery_charges' => 60,
            'free_delivery_amount' => 1000,
            'distance_based_delivery_charges' => 10,
            'per_store_drop_off_fee' => 0,
            'handling_charges' => 0,
            'buffer_time' => 10,
            'rush_delivery_enabled' => false,
            'delivery_boy_base_fee' => 0,
            'delivery_boy_per_store_pickup_fee' => 0,
            'delivery_boy_distance_based_fee' => 0,
            'delivery_boy_per_order_incentive' => 0,
            'status' => 'active',
        ]);
    }

    public function test_settings_index_and_firebase_config_are_available(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.variable', 'system');

        $this->getJson('/api/settings/firebase-config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'apiKey',
                    'authDomain',
                    'projectId',
                    'storageBucket',
                    'messagingSenderId',
                    'appId',
                    'vapidKey',
                ],
            ]);
    }

    public function test_version_check_supports_no_update_and_force_update(): void
    {
        config([
            'fastsheba.apps.customer.latest_version' => '2.1.0',
            'fastsheba.apps.customer.min_supported_version' => '2.0.0',
        ]);

        $this->getJson(
            '/api/settings/check-version'
            .'?current_version=2.1.0'
            .'&platform=android'
            .'&app=customer'
        )
            ->assertOk()
            ->assertJsonPath('data.update_available', false);

        $this->getJson(
            '/api/settings/check-version'
            .'?current_version=1.9.0'
            .'&platform=android'
            .'&app=customer'
        )
            ->assertOk()
            ->assertJsonPath('data.update_available', true)
            ->assertJsonPath('data.update_type', 'force_update');
    }

    public function test_delivery_zone_list_and_location_check_work(): void
    {
        $zone = DeliveryZone::query()
            ->where('slug', 'dhaka-test-zone')
            ->firstOrFail();

        $this->getJson('/api/delivery-zone')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'dhaka-test-zone');

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true)
            ->assertJsonPath('data.zone_id', $zone->id);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=24.9000'
            .'&longitude=91.9000'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', false)
            ->assertJsonPath('data.zone_id', null);
    }

    public function test_authenticated_zone_check_saves_selected_zone(): void
    {
        $zone = DeliveryZone::query()
            ->where('slug', 'dhaka-test-zone')
            ->firstOrFail();

        $user = User::query()->create([
            'name' => 'Zone Customer',
            'email' => 'zone@example.test',
            'mobile' => '01717777777',
            'password' => 'Test@123456',
            'status' => 'active',
            'access_panel' => GuardNameEnum::WEB->value,
        ]);

        Sanctum::actingAs($user);

        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=23.8103'
            .'&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('data.is_deliverable', true)
            ->assertJsonPath('data.zone_id', $zone->id);

        $this->assertDatabaseHas('user_zone', [
            'user_id' => $user->id,
            'zone_id' => $zone->id,
        ]);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->getJson(
            '/api/delivery-zone/check'
            .'?latitude=100'
            .'&longitude=200'
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'latitude',
                'longitude',
            ]);
    }
}