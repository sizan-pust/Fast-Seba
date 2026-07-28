<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Seller;
use App\Models\Store;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SellerMediaApiController extends Controller
{
    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function uploadProductMedia(
        Request $request,
        int $productId
    ): JsonResponse {
        $seller = $this->seller($request);

        $product = Product::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($productId);

        $request->validate([
            'main_image' => ['nullable', 'image', 'max:5120'],
            'additional_images' => ['nullable', 'array', 'max:8'],
            'additional_images.*' => ['image', 'max:5120'],
            'video' => ['nullable', 'file', 'max:20480'],
        ]);

        if ($request->hasFile('main_image')) {
            $product->addMediaFromRequest('main_image')
                ->toMediaCollection('product_main_image');
        }

        foreach ($request->file('additional_images', []) as $image) {
            $product->addMedia($image)
                ->toMediaCollection('product_additional_image');
        }

        if ($request->hasFile('video')) {
            $product->addMediaFromRequest('video')
                ->toMediaCollection('product_video');
        }

        return ApiResponseType::sendJsonResponse(true, 'Product media uploaded.', [
            'main_image' => $product->mainImageUrl(),
            'additional_images' => $product->additionalImageUrls(),
            'video' => $product->getFirstMediaUrl('product_video'),
        ]);
    }

    public function uploadVariantImage(
        Request $request,
        int $variantId
    ): JsonResponse {
        $seller = $this->seller($request);

        $variant = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where(
                'seller_id',
                $seller->id
            ))
            ->findOrFail($variantId);

        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        $variant->addMediaFromRequest('image')
            ->toMediaCollection('variant_image');

        return ApiResponseType::sendJsonResponse(true, 'Variant image uploaded.', [
            'image' => $variant->imageUrl(),
        ]);
    }

    public function uploadStoreMedia(
        Request $request,
        int $storeId
    ): JsonResponse {
        $seller = $this->seller($request);

        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($storeId);

        $request->validate([
            'logo' => ['nullable', 'image', 'max:5120'],
            'banner' => ['nullable', 'image', 'max:8192'],
        ]);

        if ($request->hasFile('logo')) {
            $store->addMediaFromRequest('logo')
                ->toMediaCollection('store_logo');
        }

        if ($request->hasFile('banner')) {
            $store->addMediaFromRequest('banner')
                ->toMediaCollection('store_banner');
        }

        return ApiResponseType::sendJsonResponse(true, 'Store media uploaded.', [
            'logo' => $store->getFirstMediaUrl('store_logo'),
            'banner' => $store->getFirstMediaUrl('store_banner'),
        ]);
    }

    public function deleteMedia(
        Request $request,
        int $mediaId
    ): JsonResponse {
        $seller = $this->seller($request);
        $media = Media::query()->findOrFail($mediaId);

        $allowed = match ($media->model_type) {
            Product::class => Product::query()
                ->where('seller_id', $seller->id)
                ->whereKey($media->model_id)
                ->exists(),
            ProductVariant::class => ProductVariant::query()
                ->whereKey($media->model_id)
                ->whereHas('product', fn ($query) => $query->where(
                    'seller_id',
                    $seller->id
                ))
                ->exists(),
            Store::class => Store::query()
                ->where('seller_id', $seller->id)
                ->whereKey($media->model_id)
                ->exists(),
            default => false,
        };

        abort_unless($allowed, 403);

        $media->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Media deleted successfully.',
            []
        );
    }
}
