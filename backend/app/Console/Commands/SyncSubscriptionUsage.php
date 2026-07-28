<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class SyncSubscriptionUsage extends Command
{
    protected $signature = 'subscriptions:sync-usage';
    protected $description = 'Synchronize seller subscription feature usage';

    public function handle(
        SubscriptionService $subscriptions,
        SystemOperationsService $operations
    ): int {
        $result = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $subscriptions->syncUsage()
        );

        $this->info(
            'Usage rows synchronized: '
            .$result['usage_rows_synced']
        );

        return self::SUCCESS;
    }
}
