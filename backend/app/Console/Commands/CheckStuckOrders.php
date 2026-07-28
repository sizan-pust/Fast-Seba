<?php

namespace App\Console\Commands;

use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class CheckStuckOrders extends Command
{
    protected $signature = 'orders:check-stuck {--minutes=}';
    protected $description = 'Flag orders that have remained in an active status too long';

    public function handle(
        SystemOperationsService $operations
    ): int {
        $minutes = (int) (
            $this->option('minutes')
            ?: config(
                'fastsheba.operations.stuck_order_minutes',
                60
            )
        );

        $count = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $operations->flagStuckOrders($minutes)
        );

        $this->info('Stuck orders flagged: '.$count);

        return self::SUCCESS;
    }
}
