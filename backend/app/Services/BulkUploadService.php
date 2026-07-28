<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BulkUploadService
{
    public function __construct(
        protected SellerCatalogueManagementService $catalogue,
        protected SubscriptionService $subscriptions
    ) {
    }

    public function createImportJob(
        User $user,
        ?Seller $seller,
        string $type,
        UploadedFile $file,
        bool $notify
    ): BulkUploadJob {
        if ($seller) {
            $eligibility = $this->subscriptions->eligibility(
                $seller,
                'bulk_uploads'
            );

            if (! $eligibility['eligible']) {
                throw new RuntimeException(
                    $eligibility['reason']
                );
            }
        }

        $path = $file->store(
            'bulk-uploads/imports',
            $this->diskName()
        );

        if (! $path) {
            throw new RuntimeException('Could not store bulk upload file.');
        }

        try {
            return DB::transaction(function () use (
                $user,
                $seller,
                $type,
                $file,
                $path,
                $notify
            ): BulkUploadJob {
                $job = BulkUploadJob::query()->create([
                    'user_id' => $user->id,
                    'seller_id' => $seller?->id,
                    'type' => $type,
                    'operation' => 'import',
                    'status' => 'pending',
                    'original_filename' =>
                        $file->getClientOriginalName(),
                    'stored_path' => $path,
                    'notify_on_finish' => $notify,
                ]);

                if ($seller) {
                    $this->subscriptions->consume(
                        $seller,
                        'bulk_uploads'
                    );
                }

                return $job;
            });
        } catch (Throwable $exception) {
            $this->disk()->delete($path);
            throw $exception;
        }
    }

    public function createExportJob(
        User $user,
        ?Seller $seller,
        string $type
    ): BulkUploadJob {
        if ($seller) {
            $eligibility = $this->subscriptions->eligibility(
                $seller,
                'bulk_uploads'
            );

            if (! $eligibility['eligible']) {
                throw new RuntimeException(
                    $eligibility['reason']
                );
            }
        }

        $path = 'bulk-uploads/exports/'
            .Str::uuid().'-'.$type.'.csv';

        return DB::transaction(function () use (
            $user,
            $seller,
            $type,
            $path
        ): BulkUploadJob {
            $job = BulkUploadJob::query()->create([
                'user_id' => $user->id,
                'seller_id' => $seller?->id,
                'type' => $type,
                'operation' => 'export',
                'status' => 'pending',
                'original_filename' => $type.'-export.csv',
                'stored_path' => $path,
                'notify_on_finish' => true,
            ]);

            if ($seller) {
                $this->subscriptions->consume(
                    $seller,
                    'bulk_uploads'
                );
            }

            return $job;
        });
    }

    public function processPending(int $limit = 5): array
    {
        $processed = 0;
        $failed = 0;

        BulkUploadJob::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (BulkUploadJob $job) use (
                &$processed,
                &$failed
            ): void {
                try {
                    $this->process($job);
                    $processed++;
                } catch (Throwable $exception) {
                    $job->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'metadata' => array_merge(
                            $job->metadata ?? [],
                            ['fatal_error' => $exception->getMessage()]
                        ),
                    ]);

                    $failed++;
                }
            });

        return compact('processed', 'failed');
    }

    public function process(BulkUploadJob $job): BulkUploadJob
    {
        $job = BulkUploadJob::query()
            ->lockForUpdate()
            ->findOrFail($job->id);

        if (! in_array($job->status, ['pending', 'failed'], true)) {
            return $job;
        }

        $job->update([
            'status' => 'processing',
            'started_at' => now(),
            'finished_at' => null,
        ]);

        if ($job->operation === 'export') {
            return $this->processExport($job);
        }

        return $this->processImport($job);
    }

    public function templates(string $type): array
    {
        return match ($type) {
            'categories' => [
                'title',
                'parent_id',
                'status',
                'requires_approval',
                'commission',
            ],
            'brands' => [
                'title',
                'status',
            ],
            'products' => [
                'seller_id',
                'title',
                'category_id',
                'brand_id',
                'store_id',
                'variant_title',
                'sku',
                'barcode',
                'price',
                'special_price',
                'cost',
                'stock',
                'low_stock_threshold',
                'is_returnable',
                'returnable_days',
            ],
            'inventory' => [
                'inventory_id',
                'change_type',
                'quantity',
                'reason',
            ],
            default => throw new RuntimeException(
                'Unsupported bulk upload type.'
            ),
        };
    }

    public function prune(int $days = 30): array
    {
        $jobs = BulkUploadJob::query()
            ->whereIn('status', ['completed', 'failed'])
            ->where('updated_at', '<', now()->subDays($days))
            ->get();

        $deletedFiles = 0;

        foreach ($jobs as $job) {
            foreach (
                [$job->stored_path, $job->failed_rows_path] as $path
            ) {
                if ($path && $this->disk()->exists($path)) {
                    $this->disk()->delete($path);
                    $deletedFiles++;
                }
            }
        }

        $deletedJobs = BulkUploadJob::query()
            ->whereKey($jobs->pluck('id'))
            ->delete();

        return compact('deletedJobs', 'deletedFiles');
    }

    private function processImport(
        BulkUploadJob $job
    ): BulkUploadJob {
        if (! $this->disk()->exists($job->stored_path)) {
            throw new RuntimeException('Bulk upload file is missing.');
        }

        $stream = $this->disk()->readStream($job->stored_path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Bulk upload file is unreadable.');
        }

        $headers = fgetcsv($stream);

        if (! is_array($headers)) {
            fclose($stream);
            throw new RuntimeException('CSV header row is missing.');
        }

        $headers = array_map(
            fn ($value) => Str::snake(trim((string) $value)),
            $headers
        );

        $required = $this->templates($job->type);

        if (array_diff($required, $headers) !== []) {
            fclose($stream);

            throw new RuntimeException(
                'CSV columns do not match the selected template.'
            );
        }

        $total = 0;
        $success = 0;
        $failedRows = [];

        while (($row = fgetcsv($stream)) !== false) {
            if ($row === [null] || $row === []) {
                continue;
            }

            $total++;
            $data = array_combine(
                $headers,
                array_pad($row, count($headers), null)
            );

            try {
                $this->processRow($job, $data);
                $success++;
            } catch (Throwable $exception) {
                $data['error'] = $exception->getMessage();
                $failedRows[] = $data;
            }

            $job->update([
                'total_rows' => $total,
                'processed_rows' => $total,
                'successful_rows' => $success,
                'failed_rows' => count($failedRows),
            ]);
        }

        fclose($stream);

        $failedPath = null;

        if ($failedRows !== []) {
            $failedPath = $this->writeFailedRows(
                $job,
                $failedRows
            );
        }

        $job->update([
            'status' => 'completed',
            'total_rows' => $total,
            'processed_rows' => $total,
            'successful_rows' => $success,
            'failed_rows' => count($failedRows),
            'failed_rows_path' => $failedPath,
            'finished_at' => now(),
        ]);

        return $job->fresh();
    }

    private function processExport(
        BulkUploadJob $job
    ): BulkUploadJob {
        $headers = $this->templates($job->type);
        $rows = $this->exportRows($job);

        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                array_map(
                    fn ($header) => $row[$header] ?? null,
                    $headers
                )
            );
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! $this->disk()->put($job->stored_path, $contents)) {
            throw new RuntimeException(
                'Could not write bulk export file.'
            );
        }

        $job->update([
            'status' => 'completed',
            'total_rows' => count($rows),
            'processed_rows' => count($rows),
            'successful_rows' => count($rows),
            'finished_at' => now(),
        ]);

        return $job->fresh();
    }

    private function processRow(
        BulkUploadJob $job,
        array $data
    ): void {
        match ($job->type) {
            'categories' => $this->importCategory($data),
            'brands' => $this->importBrand($data),
            'products' => $this->importProduct($job, $data),
            'inventory' => $this->importInventory($job, $data),
            default => throw new RuntimeException(
                'Unsupported bulk upload type.'
            ),
        };
    }

    private function importCategory(array $data): void
    {
        Category::query()->updateOrCreate(
            ['title' => trim((string) $data['title'])],
            [
                'parent_id' => $this->nullableInteger(
                    $data['parent_id']
                ),
                'status' => $data['status'] ?: 'active',
                'requires_approval' => $this->boolean(
                    $data['requires_approval']
                ),
                'commission' => (float) ($data['commission'] ?: 0),
            ]
        );
    }

    private function importBrand(array $data): void
    {
        Brand::query()->updateOrCreate(
            ['title' => trim((string) $data['title'])],
            [
                'status' => $data['status'] ?: 'active',
            ]
        );
    }

    private function importProduct(
        BulkUploadJob $job,
        array $data
    ): void {
        $sellerId = $job->seller_id
            ?? $this->nullableInteger($data['seller_id']);

        $seller = Seller::query()->findOrFail($sellerId);
        $actor = $job->user;

        $this->catalogue->createProduct(
            $seller,
            $actor,
            [
                'title' => trim((string) $data['title']),
                'category_id' => (int) $data['category_id'],
                'brand_id' => $this->nullableInteger(
                    $data['brand_id']
                ),
                'type' => 'simple',
                'status' => 'active',
                'is_returnable' => $this->boolean(
                    $data['is_returnable']
                ),
                'returnable_days' => $this->nullableInteger(
                    $data['returnable_days']
                ),
                'variants' => [
                    [
                        'title' =>
                            $data['variant_title'] ?: 'Default',
                        'barcode' => $data['barcode'] ?: null,
                        'is_default' => true,
                        'stores' => [
                            [
                                'store_id' => (int) $data['store_id'],
                                'sku' => trim((string) $data['sku']),
                                'price' => (float) $data['price'],
                                'special_price' =>
                                    $data['special_price'] === ''
                                        ? null
                                        : (float) $data['special_price'],
                                'cost' => (float) ($data['cost'] ?: 0),
                                'stock' => (int) ($data['stock'] ?: 0),
                                'low_stock_threshold' =>
                                    (int) (
                                        $data['low_stock_threshold']
                                        ?: 5
                                    ),
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    private function importInventory(
        BulkUploadJob $job,
        array $data
    ): void {
        $inventory = StoreProductVariant::query()
            ->with('store')
            ->findOrFail((int) $data['inventory_id']);

        if (
            $job->seller_id
            && $inventory->store->seller_id !== $job->seller_id
        ) {
            throw new RuntimeException(
                'Inventory row is outside the seller scope.'
            );
        }

        $seller = Seller::query()->findOrFail(
            $inventory->store->seller_id
        );

        $this->catalogue->adjustInventory(
            $seller,
            $job->user,
            $inventory->id,
            $data['change_type'],
            (int) $data['quantity'],
            $data['reason'] ?: 'Bulk inventory import'
        );
    }

    private function exportRows(BulkUploadJob $job): array
    {
        return match ($job->type) {
            'categories' => Category::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($item) => [
                    'title' => $item->title,
                    'parent_id' => $item->parent_id,
                    'status' => $item->status,
                    'requires_approval' =>
                        (int) $item->requires_approval,
                    'commission' => $item->commission,
                ])->all(),
            'brands' => Brand::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($item) => [
                    'title' => $item->title,
                    'status' => $item->status,
                ])->all(),
            'products' => $this->productExportRows($job),
            'inventory' => $this->inventoryExportRows($job),
            default => [],
        };
    }

    private function productExportRows(BulkUploadJob $job): array
    {
        return Product::query()
            ->when(
                $job->seller_id,
                fn ($query) =>
                    $query->where('seller_id', $job->seller_id)
            )
            ->with([
                'variants.storeProductVariants',
            ])
            ->get()
            ->flatMap(function (Product $product): array {
                $rows = [];

                foreach ($product->variants as $variant) {
                    foreach (
                        $variant->storeProductVariants as $inventory
                    ) {
                        $rows[] = [
                            'seller_id' => $product->seller_id,
                            'title' => $product->title,
                            'category_id' => $product->category_id,
                            'brand_id' => $product->brand_id,
                            'store_id' => $inventory->store_id,
                            'variant_title' => $variant->title,
                            'sku' => $inventory->sku,
                            'barcode' => $variant->barcode,
                            'price' => $inventory->price,
                            'special_price' =>
                                $inventory->special_price,
                            'cost' => $inventory->cost,
                            'stock' => $inventory->stock,
                            'low_stock_threshold' =>
                                $inventory->low_stock_threshold,
                            'is_returnable' =>
                                (int) $product->is_returnable,
                            'returnable_days' =>
                                $product->returnable_days,
                        ];
                    }
                }

                return $rows;
            })
            ->values()
            ->all();
    }

    private function inventoryExportRows(
        BulkUploadJob $job
    ): array {
        return StoreProductVariant::query()
            ->when(
                $job->seller_id,
                fn ($query) => $query->whereHas(
                    'store',
                    fn ($storeQuery) =>
                        $storeQuery->where(
                            'seller_id',
                            $job->seller_id
                        )
                )
            )
            ->get()
            ->map(fn ($inventory) => [
                'inventory_id' => $inventory->id,
                'change_type' => 'adjust',
                'quantity' => $inventory->stock,
                'reason' => 'Exported current stock',
            ])
            ->all();
    }

    private function writeFailedRows(
        BulkUploadJob $job,
        array $rows
    ): string {
        $headers = array_keys($rows[0]);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv(
                $stream,
                array_map(
                    fn ($header) => $row[$header] ?? null,
                    $headers
                )
            );
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        $path = 'bulk-uploads/errors/'.$job->uuid.'.csv';
        if (! $this->disk()->put($path, $contents)) {
            throw new RuntimeException(
                'Could not write failed-row export.'
            );
        }

        return $path;
    }

    private function diskName(): string
    {
        $disk = trim((string) config(
            'bulk_upload.file_disk',
            'local'
        ));

        return $disk !== '' ? $disk : 'local';
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function boolean(mixed $value): bool
    {
        return in_array(
            Str::lower(trim((string) $value)),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }
}
