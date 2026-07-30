<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AttachMedicineImages extends Command
{
    protected $signature =
        'fastsheba:attach-medicine-images
        {directory : Folder containing product images}
        {--replace : Clear existing additional images first}
        {--dry-run : Show matches without changing media}';

    protected $description =
        'Attach product images by medicine product slug.';

    public function handle(): int
    {
        $directory = realpath((string) $this->argument('directory'));

        if (! $directory || ! is_dir($directory)) {
            $this->error('Image directory was not found.');

            return self::FAILURE;
        }

        $extensions = ['webp', 'png', 'jpg', 'jpeg'];
        $attachedMain = 0;
        $attachedAdditional = 0;
        $missing = 0;

        $products = Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->where('metadata->catalogue', 'fastsheba-100-medicines')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            $main = $this->findFile(
                $directory,
                $product->slug,
                $extensions
            );

            if (! $main) {
                $missing++;
                $this->line('Missing: '.$product->slug);

                continue;
            }

            $this->info(
                ($this->option('dry-run') ? '[DRY] ' : '')
                .'Main: '.$product->slug.' <- '.basename($main)
            );

            if (! $this->option('dry-run')) {
                $product
                    ->addMedia($main)
                    ->preservingOriginal()
                    ->toMediaCollection('product_main_image');

                $attachedMain++;
            }

            $additionalFiles = [];

            for ($index = 2; $index <= 8; $index++) {
                $file = $this->findFile(
                    $directory,
                    $product->slug.'-'.$index,
                    $extensions
                );

                if ($file) {
                    $additionalFiles[] = $file;
                }
            }

            if (
                $additionalFiles !== []
                && $this->option('replace')
                && ! $this->option('dry-run')
            ) {
                $product->clearMediaCollection(
                    'product_additional_image'
                );
            }

            foreach ($additionalFiles as $file) {
                $this->line(
                    ($this->option('dry-run') ? '[DRY] ' : '')
                    .'Gallery: '.$product->slug
                    .' <- '.basename($file)
                );

                if (! $this->option('dry-run')) {
                    $product
                        ->addMedia($file)
                        ->preservingOriginal()
                        ->toMediaCollection(
                            'product_additional_image'
                        );

                    $attachedAdditional++;
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Products', 'Main attached', 'Gallery attached', 'Missing'],
            [[
                $products->count(),
                $attachedMain,
                $attachedAdditional,
                $missing,
            ]]
        );

        return self::SUCCESS;
    }

    private function findFile(
        string $directory,
        string $baseName,
        array $extensions
    ): ?string {
        foreach ($extensions as $extension) {
            $path = $directory
                .DIRECTORY_SEPARATOR
                .$baseName
                .'.'
                .$extension;

            if (File::isFile($path)) {
                return $path;
            }
        }

        return null;
    }
}
