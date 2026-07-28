<?php

namespace App\Console\Commands;

use App\Services\AdvertisingService;
use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class SettleExpiredAds extends Command
{
    protected $signature = 'ads:settle-expired';
    protected $description =
        'Complete expired ad campaigns and release unused wallet reserves';

    public function handle(
        AdvertisingService $ads,
        SystemOperationsService $operations
    ): int {
        $count = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $ads->settleExpired()
        );

        $this->info('Expired campaigns settled: '.$count);

        return self::SUCCESS;
    }
}
