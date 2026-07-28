<?php

namespace App\Console\Commands;

use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check';
    protected $description = 'Run FastSheba database, cache and storage health checks';

    public function handle(
        SystemOperationsService $operations
    ): int {
        $health = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $operations->health()
        );

        $this->line(
            json_encode($health, JSON_PRETTY_PRINT)
        );

        return $health['status'] === 'healthy'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
