<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FoundationSeeder::class,
            AuthProviderSeeder::class,
            CatalogueInventorySeeder::class,
            CommerceSeeder::class,
            OrderPaymentSeeder::class,
            DeliveryReturnSeeder::class,
            SellerManagementFinanceSeeder::class,
            GrowthSupportPharmacySeeder::class,
            FinalOperationsSeeder::class,
            SellerPanelSeeder::class,
            CustomerWebsiteSeeder::class,
        ]);
    }
}
