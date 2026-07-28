<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class Phase8Preflight extends Command
{
    protected $signature = 'phase8:preflight';
    protected $description = 'Verify the existing FastSheba schema before Phase 8 migration';

    public function handle(): int
    {
        $requiredTables = [
            'users',
            'media',
            'settings',
            'delivery_zones',
            'sellers',
            'stores',
            'store_zone',
            'products',
            'product_variants',
            'store_product_variants',
            'orders',
            'seller_orders',
            'order_items',
            'wallets',
            'wallet_transactions',
        ];

        $requiredColumns = [
            'users' => [
                'id',
                'status',
                'access_panel',
                'referral_code',
                'friends_code',
                'referral_prompt_dismissed_at',
            ],
            'products' => [
                'id',
                'seller_id',
                'slug',
                'status',
                'verification_status',
                'featured',
            ],
            'orders' => [
                'id',
                'user_id',
                'status',
                'final_total',
                'delivered_at',
            ],
            'order_items' => [
                'id',
                'order_id',
                'product_id',
                'store_id',
                'status',
            ],
            'wallets' => [
                'id',
                'user_id',
                'type',
                'balance',
                'blocked_balance',
                'currency_code',
            ],
        ];

        $errors = [];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                $errors[] = 'Missing table: '.$table;
            }
        }

        foreach ($requiredColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $errors[] = 'Missing column: '.$table.'.'.$column;
                }
            }
        }

        if ($errors !== []) {
            $this->error('Phase 8 preflight failed.');

            foreach ($errors as $error) {
                $this->line('  - '.$error);
            }

            return self::FAILURE;
        }

        $this->info('Phase 8 preflight passed.');
        $this->line('Required previous-phase tables and columns are available.');
        $this->line('The wallets table is treated without a status column.');

        return self::SUCCESS;
    }
}
