<?php

namespace App\Console\Commands;

use App\Services\BulkUploadService;
use App\Services\SystemOperationsService;
use Illuminate\Console\Command;

class ProcessBulkUploads extends Command
{
    protected $signature = 'bulk-uploads:run {--limit=}';
    protected $description = 'Process pending FastSheba bulk import/export jobs';

    public function handle(
        BulkUploadService $bulk,
        SystemOperationsService $operations
    ): int {
        $limit = (int) (
            $this->option('limit')
            ?: config('fastsheba.bulk_upload.process_limit', 5)
        );

        $result = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $bulk->processPending($limit)
        );

        $this->info('Processed: '.$result['processed']);
        $this->info('Failed: '.$result['failed']);

        return self::SUCCESS;
    }
}
