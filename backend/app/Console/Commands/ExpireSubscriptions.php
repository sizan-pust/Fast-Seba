<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire seller subscriptions past their end time';

    public function handle(
        SubscriptionService $subscriptions,
        SystemOperationsService $operations
    ): int {
        $count = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $subscriptions->expireDue()
        );

        $this->info('Expired subscriptions: '.$count);

        return self::SUCCESS;
    }
}
