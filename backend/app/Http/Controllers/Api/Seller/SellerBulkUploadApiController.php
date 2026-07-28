<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\BulkUploadJob;
use App\Models\Seller;
use App\Services\BulkUploadService;
use App\Types\Api\ApiResponseType;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SellerBulkUploadApiController extends Controller
{
    public function __construct(
        protected BulkUploadService $bulk
    ) {
    }

    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $items = BulkUploadJob::query()
            ->where('seller_id', $this->seller($request)->id)
            ->latest()
            ->paginate(
                min(
                    100,
                    max(1, (int) $request->input('per_page', 15))
                )
            );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller bulk jobs fetched.',
            $items
        );
    }

    public function template(
        Request $request,
        string $type
    ): StreamedResponse {
        $this->seller($request);
        $headers = $this->bulk->templates($type);

        return response()->streamDownload(function () use ($headers): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $headers);
            fclose($stream);
        }, $type.'-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => [
                'required',
                Rule::in(['products', 'inventory']),
            ],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'notify_on_finish' => ['nullable', 'boolean'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller bulk import queued.',
            $this->bulk->createImportJob(
                $request->user(),
                $this->seller($request),
                $data['type'],
                $data['file'],
                $data['notify_on_finish'] ?? true
            ),
            201
        );
    }

    public function export(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => [
                'required',
                Rule::in(['products', 'inventory']),
            ],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller export queued.',
            $this->bulk->createExportJob(
                $request->user(),
                $this->seller($request),
                $data['type']
            ),
            201
        );
    }

    public function show(
        Request $request,
        string $uuid
    ): JsonResponse {
        $job = BulkUploadJob::query()
            ->where('seller_id', $this->seller($request)->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller bulk job fetched.',
            $job
        );
    }

    public function download(
        Request $request,
        string $uuid
    ): StreamedResponse {
        $job = BulkUploadJob::query()
            ->where('seller_id', $this->seller($request)->id)
            ->where('uuid', $uuid)
            ->where('status', 'completed')
            ->firstOrFail();

        $storage = $this->storage();

        abort_unless($storage->exists($job->stored_path), 404);

        return $storage->download(
            $job->stored_path,
            $job->original_filename
        );
    }

    public function downloadErrors(
        Request $request,
        string $uuid
    ): StreamedResponse {
        $job = BulkUploadJob::query()
            ->where('seller_id', $this->seller($request)->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        abort_unless(
            $job->failed_rows_path
                && $this->storage()->exists(
                    $job->failed_rows_path
                ),
            404
        );

        return $this->storage()->download(
            $job->failed_rows_path,
            'errors-'.$job->original_filename
        );
    }
    private function storage(): FilesystemAdapter
    {
        $disk = trim((string) config(
            'bulk_upload.file_disk',
            'local'
        ));

        return Storage::disk($disk !== '' ? $disk : 'local');
    }

}
