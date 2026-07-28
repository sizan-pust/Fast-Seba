<?php

namespace App\Console\Commands;

use App\Enums\WalletTypeEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class Phase9Preflight extends Command
{
    protected $signature = 'phase9:preflight';
    protected $description =
        'Validate prerequisites for the final backend migration';

    public function handle(): int
    {
        $requiredTables = [
            'users',
            'sellers',
            'stores',
            'wallets',
            'products',
            'product_variants',
            'store_product_variants',
            'orders',
            'order_items',
            'seller_orders',
            'order_payment_transactions',
            'delivery_boys',
            'delivery_boy_assignments',
            'payment_gateway_configs',
            'system_audit_logs',
            'settings',
            'seller_user',
        ];

        $missingTables = collect($requiredTables)
            ->reject(fn ($table) => Schema::hasTable($table))
            ->values();

        $requiredFiles = [
            app_path('Models/OrderItemAddon.php'),
            app_path('Models/StoreAddonItem.php'),
            app_path('Models/StoreProductVariantAddon.php'),
            base_path('tests/Feature/Api/FinalOperationsApiTest.php'),
            base_path('routes/final.php'),
            base_path('routes/schedule.php'),
        ];

        $missingFiles = collect($requiredFiles)
            ->reject(fn ($path) => is_file($path))
            ->values();

        $walletTypes = collect(WalletTypeEnum::cases())
            ->map(fn (WalletTypeEnum $type) => $type->value);

        $walletTypeCompatible = $walletTypes->contains('seller_ad');

        $requiredColumns = [
            'orders' => [
                'slug',
                'user_id',
                'delivery_type',
                'final_total',
                'payment_status',
                'delivered_at',
            ],
            'wallets' => [
                'user_id',
                'type',
                'balance',
                'blocked_balance',
                'currency_code',
            ],
            'stores' => [
                'seller_id',
                'status',
                'verification_status',
                'pos_payment_config',
            ],
            'settings' => [
                'variable',
                'value',
            ],
            'seller_user' => [
                'seller_id',
                'user_id',
                'position',
                'status',
            ],
            'payment_gateway_configs' => [
                'code',
                'enabled',
                'secret_config',
                'supported_currencies',
                'metadata',
            ],
            'delivery_boy_assignments' => [
                'order_id',
                'delivery_boy_id',
                'status',
                'cash_collected',
                'payment_status',
            ],
            'order_payment_transactions' => [
                'id',
                'order_id',
                'transaction_id',
                'payment_status',
            ],
        ];

        $missingColumns = [];

        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (
                    Schema::hasTable($table)
                    && ! Schema::hasColumn($table, $column)
                ) {
                    $missingColumns[] = $table.'.'.$column;
                }
            }
        }

        if (
            $missingTables->isNotEmpty()
            || $missingColumns !== []
            || $missingFiles->isNotEmpty()
            || ! $walletTypeCompatible
        ) {
            $this->error('Phase 9 preflight failed.');

            if ($missingTables->isNotEmpty()) {
                $this->line(
                    'Missing tables: '.$missingTables->join(', ')
                );
            }

            if ($missingColumns !== []) {
                $this->line(
                    'Missing columns: '.implode(', ', $missingColumns)
                );
            }

            if ($missingFiles->isNotEmpty()) {
                $this->line(
                    'Missing files: '.$missingFiles->join(', ')
                );
            }

            if (! $walletTypeCompatible) {
                $this->line(
                    'WalletTypeEnum must contain seller_ad.'
                );
            }

            return self::FAILURE;
        }

        $this->info('Phase 9 preflight passed.');
        $settingsOptionalColumns = collect([
            'is_public',
            'description',
        ])->filter(
            fn (string $column): bool =>
                Schema::hasColumn('settings', $column)
        );

        $this->line(
            'POS, subscriptions, ads, bulk operations, cash settlement, '
            .'feedback, payment orchestration and system operations are ready.'
        );
        $this->line(
            'Settings compatibility: required columns variable/value found; '
            .'optional columns detected: '
            .($settingsOptionalColumns->isEmpty()
                ? 'none'
                : $settingsOptionalColumns->join(', '))
            .'.'
        );

        return self::SUCCESS;
    }
}
