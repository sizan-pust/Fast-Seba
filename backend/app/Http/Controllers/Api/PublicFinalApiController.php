<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\DeliveryBoy;
use App\Models\ProductCollection;
use App\Models\Seller;
use App\Services\AdvertisingService;
use App\Services\DeliveryCashFeedbackService;
use App\Services\TaxCollectionAddonService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFinalApiController extends Controller
{
    public function __construct(
        protected TaxCollectionAddonService $catalogue,
        protected AdvertisingService $advertising,
        protected DeliveryCashFeedbackService $feedback
    ) {
    }

    public function taxClasses(): JsonResponse
    {
        $items = $this->catalogue->taxClasses()->map(
            fn ($class) => [
                'id' => $class->id,
                'name' => $class->name,
                'slug' => $class->slug,
                'is_default' => (bool) $class->is_default,
                'rates' => $class->rates->map(fn ($rate) => [
                    'id' => $rate->id,
                    'name' => $rate->name,
                    'rate' => (float) $rate->rate,
                    'compound' => (bool) $rate->compound,
                ])->values(),
            ]
        )->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Tax classes fetched.',
            $items
        );
    }

    public function collections(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Collections fetched.',
            $this->catalogue->collections()->map(
                fn (ProductCollection $collection) => [
                    'id' => $collection->id,
                    'title' => $collection->title,
                    'slug' => $collection->slug,
                    'description' => $collection->description,
                    'metadata' => $collection->metadata ?? [],
                    'products' => $collection->products->map(
                        fn ($product) => [
                            'id' => $product->id,
                            'title' => $product->title,
                            'slug' => $product->slug,
                            'main_image' => $product->mainImageUrl(),
                            'brand' => $product->brand?->title,
                            'category' => $product->category?->title,
                        ]
                    )->values(),
                ]
            )->values()
        );
    }

    public function collection(string $slug): JsonResponse
    {
        $collection = ProductCollection::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->with([
                'products' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where('verification_status', 'approved')
                    ->with([
                        'brand',
                        'category',
                        'variants.storeProductVariants.store',
                    ]),
            ])
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(
            true,
            'Collection fetched.',
            [
                'id' => $collection->id,
                'title' => $collection->title,
                'slug' => $collection->slug,
                'description' => $collection->description,
                'metadata' => $collection->metadata ?? [],
                'products' => $collection->products,
            ]
        );
    }

    public function variantAddons(
        int $storeId,
        int $variantId
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            true,
            'Product addons fetched.',
            $this->catalogue->variantAddonMatrix(
                $storeId,
                $variantId
            )
        );
    }

    public function advertisements(Request $request): JsonResponse
    {
        $placement = $request->input(
            'placement',
            'home_feed'
        );

        $items = $this->advertising
            ->publicCampaigns($placement)
            ->map(fn (AdCampaign $campaign) => [
                'id' => $campaign->id,
                'uuid' => $campaign->uuid,
                'title' => $campaign->title,
                'ad_type' => $campaign->ad_type,
                'placement' => $campaign->placement,
                'seller' => [
                    'id' => $campaign->seller_id,
                    'business_name' =>
                        $campaign->seller?->business_name,
                ],
                'store' => $campaign->store ? [
                    'id' => $campaign->store->id,
                    'name' => $campaign->store->name,
                    'slug' => $campaign->store->slug,
                ] : null,
                'product' => $campaign->product ? [
                    'id' => $campaign->product->id,
                    'title' => $campaign->product->title,
                    'slug' => $campaign->product->slug,
                    'image' => $campaign->product->mainImageUrl(),
                ] : null,
                'metadata' => $campaign->metadata ?? [],
            ])->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertisements fetched.',
            $items
        );
    }

    public function recordAdEvent(
        Request $request,
        string $uuid
    ): JsonResponse {
        $data = $request->validate([
            'event_type' => [
                'required',
                'in:impression,click,conversion',
            ],
            'event_uuid' => ['required', 'uuid'],
            'session_hash' => ['nullable', 'string', 'max:100'],
        ]);

        $campaign = AdCampaign::query()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return ApiResponseType::sendJsonResponse(
            true,
            'Advertisement event processed.',
            $this->advertising->recordEvent(
                $campaign,
                $data['event_type'],
                $data['event_uuid'],
                $request->user(),
                $data['session_hash'] ?? null
            )
        );
    }

    public function sellerRating(int $sellerId): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Seller rating fetched.',
            $this->feedback->sellerRating(
                Seller::query()->findOrFail($sellerId)
            )
        );
    }

    public function riderRating(int $riderId): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery rating fetched.',
            $this->feedback->riderRating(
                DeliveryBoy::query()->findOrFail($riderId)
            )
        );
    }
}
