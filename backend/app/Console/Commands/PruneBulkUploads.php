<?php

namespace App\Console\Commands;

use App\Services\BulkUploadService;
use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class PruneBulkUploads extends Command
{
    protected $signature = 'bulk-uploads:prune {--days=}';
    protected $description = 'Prune terminal bulk jobs and stored CSV files';

    public function handle(
        BulkUploadService $bulk,
        SystemOperationsService $operations
    ): int {
        $days = (int) (
            $this->option('days')
            ?: config('fastsheba.bulk_upload.retention_days', 30)
        );

        $result = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $bulk->prune($days)
        );

        $this->info('Deleted jobs: '.$result['deletedJobs']);
        $this->info('Deleted files: '.$result['deletedFiles']);

        return self::SUCCESS;
    }
}
