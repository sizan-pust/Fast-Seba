<?php

namespace App\Console\Commands;

use App\Services\SystemOperationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportOpenApi extends Command
{
    protected $signature = 'openapi:export {--path=docs/openapi.json}';
    protected $description = 'Export the FastSheba OpenAPI route document';

    public function handle(
        SystemOperationsService $operations
    ): int {
        $path = (string) $this->option('path');
        $document = $operations->runLogged(
            $this->getName(),
            'manual',
            fn () => $operations->openApiDocument()
        );

        Storage::disk('local')->put(
            $path,
            json_encode(
                $document,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            )
        );

        $this->info(
            'OpenAPI document exported to storage/app/private/'
            .$path
        );

        return self::SUCCESS;
    }
}
