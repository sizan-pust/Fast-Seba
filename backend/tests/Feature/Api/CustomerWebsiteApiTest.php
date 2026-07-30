<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWebsiteApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_website_setting_groups_are_public(): void
    {
        $response = $this->getJson('/api/settings')->assertOk();

        $variables = collect($response->json('data'))
            ->pluck('variable')
            ->all();

        $this->assertContains('web', $variables);
        $this->assertContains('payment', $variables);
        $this->assertContains('home_general_settings', $variables);
        $this->assertContains('advertisement', $variables);

        $webSetting = Setting::query()
            ->where('variable', 'web')
            ->firstOrFail();

        $this->assertSame(
            'FastSheba',
            data_get($webSetting->value, 'siteName')
        );
    }

    public function test_public_banners_have_customer_web_compatibility_fields(): void
    {
        $this->getJson(
            '/api/banners?latitude=23.8103&longitude=90.4125'
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'position',
                        'image',
                        'banner_image',
                        'custom_url',
                        'product_slug',
                        'category_slug',
                        'brand_slug',
                    ],
                ],
            ]);

        $this->assertGreaterThan(
            0,
            Banner::query()
                ->where('visibility_status', 'published')
                ->count()
        );
    }

    public function test_featured_sections_return_full_product_cards(): void
    {
        $response = $this->getJson(
            '/api/featured-sections?latitude=23.8103&longitude=90.4125'
        )->assertOk();

        $products = collect($response->json('data'))
            ->flatMap(fn (array $section): array => $section['products'] ?? [])
            ->values();

        if ($products->isNotEmpty()) {
            $product = $products->first();

            $this->assertArrayHasKey('main_image', $product);
            $this->assertArrayHasKey('variants', $product);
            $this->assertArrayHasKey('store_status', $product);
        }
    }

    public function test_delivery_zone_can_be_opened_by_slug(): void
    {
        $this->getJson('/api/delivery-zone/dhaka-test-zone')
            ->assertOk()
            ->assertJsonPath('data.slug', 'dhaka-test-zone');
    }
}
