<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\BulkUploadJob;
use App\Models\Seller;
use App\Services\BulkUploadService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminBulkUploadApiController extends Controller
{
    public function __construct(
        protected BulkUploadService $bulk
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = BulkUploadJob::query()
            ->when(
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'status',
                        $request->string('status')->toString()
                    )
            )
            ->when(
                $request->filled('type'),
                fn ($query) =>
                    $query->where(
                        'type',
                        $request->string('type')->toString()
                    )
            )
            ->with(['user', 'seller'])
            ->latest()
            ->paginate(
                min(
                    100,
                    max(1, (int) $request->input('per_page', 15))
                )
            );

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin bulk jobs fetched.',
            $items
        );
    }

    public function template(
        Request $request,
        string $type
    ): StreamedResponse {
        $this->ensureAdmin($request);
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
        $this->ensureAdmin($request);

        $data = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'categories',
                    'brands',
                    'products',
                    'inventory',
                ]),
            ],
            'seller_id' => [
                'nullable',
                'integer',
                'exists:sellers,id',
            ],
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:20480',
            ],
            'notify_on_finish' => ['nullable', 'boolean'],
        ]);

        $seller = ! empty($data['seller_id'])
            ? Seller::query()->findOrFail($data['seller_id'])
            : null;

        if (
            in_array($data['type'], ['products', 'inventory'], true)
            && ! $seller
        ) {
            return ApiResponseType::sendJsonResponse(
                false,
                'seller_id is required for seller-owned imports.',
                [],
                422
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin bulk import queued.',
            $this->bulk->createImportJob(
                $request->user(),
                $seller,
                $data['type'],
                $data['file'],
                $data['notify_on_finish'] ?? true
            ),
            201
        );
    }

    public function export(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'type' => [
                'required',
                Rule::in([
                    'categories',
                    'brands',
                    'products',
                    'inventory',
                ]),
            ],
            'seller_id' => [
                'nullable',
                'integer',
                'exists:sellers,id',
            ],
        ]);

        $seller = ! empty($data['seller_id'])
            ? Seller::query()->findOrFail($data['seller_id'])
            : null;

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin export queued.',
            $this->bulk->createExportJob(
                $request->user(),
                $seller,
                $data['type']
            ),
            201
        );
    }

    public function process(
        Request $request,
        string $uuid
    ): JsonResponse {
        $this->ensureAdmin($request);

        $job = BulkUploadJob::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(
            true,
            'Bulk job processed.',
            $this->bulk->process($job)
        );
    }

    public function show(
        Request $request,
        string $uuid
    ): JsonResponse {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin bulk job fetched.',
            BulkUploadJob::query()
                ->where('uuid', $uuid)
                ->with(['user', 'seller'])
                ->firstOrFail()
        );
    }

    public function download(
        Request $request,
        string $uuid
    ): StreamedResponse {
        $this->ensureAdmin($request);

        $job = BulkUploadJob::query()
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
        $this->ensureAdmin($request);

        $job = BulkUploadJob::query()
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

    private function ensureAdmin(Request $request): void
    {
        $panel = $request->user()?->access_panel;

        if ($panel instanceof BackedEnum) {
            $panel = $panel->value;
        }

        abort_unless(
            $panel === GuardNameEnum::ADMIN->value,
            403
        );
    }
}
