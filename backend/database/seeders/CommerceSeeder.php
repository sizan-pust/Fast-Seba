<?php

namespace Database\Seeders;

use App\Models\Promo;
use Illuminate\Database\Seeder;

class CommerceSeeder extends Seeder
{
    public function run(): void
    {
        Promo::query()->updateOrCreate(
            ['code' => 'FAST10'],
            [
                'description' => '10% development promo.',
                'start_date' => now()->subDay(),
                'end_date' => now()->addYear(),
                'discount_type' => 'percentage',
                'discount_amount' => 10,
                'promo_mode' => 'global',
                'usage_count' => 0,
                'individual_use' => false,
                'max_total_usage' => 10000,
                'max_usage_per_user' => 10,
                'min_order_total' => 10,
                'max_discount_value' => 100,
                'status' => 'active',
            ]
        );
    }
}