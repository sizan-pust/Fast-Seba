<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FinalizeBackend extends Command
{
    protected $signature = 'fastsheba:finalize-backend';
    protected $description =
        'Run final backend synchronization and health checks';

    public function handle(): int
    {
        $commands = [
            'finance:sync',
            'engagement:sync',
            'subscriptions:sync-usage',
            'subscriptions:expire',
            'delivery-cash:sync',
            'bulk-uploads:run',
            'ads:settle-expired',
            'ads:prune-dedup',
            'orders:check-stuck',
            'openapi:export',
            'system:health-check',
        ];

        foreach ($commands as $command) {
            $this->newLine();
            $this->info('Running '.$command);

            $exit = Artisan::call($command);
            $this->output->write(Artisan::output());

            if ($exit !== self::SUCCESS) {
                $this->error($command.' failed.');

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('FastSheba backend finalization completed.');

        return self::SUCCESS;
    }
}
