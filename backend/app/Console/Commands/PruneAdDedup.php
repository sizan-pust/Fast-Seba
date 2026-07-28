<?php

namespace App\Console\Commands;

use App\Services\AdvertisingService;
use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class PruneAdDedup extends Command
{
    protected $signature = 'ads:prune-dedup {--days=}';
    protected $description = 'Prune old advertising event deduplication rows';

    public function handle(
        AdvertisingService $ads,
        SystemOperationsService $operations
    ): int {
        $days = (int) (
            $this->option('days')
            ?: config('fastsheba.operations.ad_dedup_days', 7)
        );

        $count = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $ads->pruneDedup($days)
        );

        $this->info('Deleted ad event rows: '.$count);

        return self::SUCCESS;
    }
}
