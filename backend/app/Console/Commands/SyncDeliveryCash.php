<?php

namespace App\Console\Commands;

use App\Services\DeliveryCashFeedbackService;
use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class SyncDeliveryCash extends Command
{
    protected $signature = 'delivery-cash:sync';
    protected $description = 'Create COD cash collection ledger rows';

    public function handle(
        DeliveryCashFeedbackService $cash,
        SystemOperationsService $operations
    ): int {
        $result = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $cash->syncCashCollections()
        );

        $this->info(
            'Cash collections created: '
            .$result['collections_created']
        );

        return self::SUCCESS;
    }
}
