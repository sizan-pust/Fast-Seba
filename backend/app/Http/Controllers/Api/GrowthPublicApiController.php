<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Faq;
use App\Models\GiftCard;
use App\Models\Product;
use App\Models\ProductFaq;
use App\Services\GiftCardService;
use App\Services\GrowthContentService;
use App\Services\ReviewService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthPublicApiController extends Controller
{
    public function __construct(
        protected GrowthContentService $growth,
        protected ReviewService $reviews,
        protected GiftCardService $giftCards
    ) {
    }

    public function banners(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'position' => ['nullable', 'string', 'max:40'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $zoneId = $this->growth->zoneId(
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Banners fetched.',
            $this->growth->banners(
                $zoneId,
                $data['position'] ?? null,
                $data['category_id'] ?? null
            )
        );
    }

    public function featuredSections(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $zoneId = $this->growth->zoneId(
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Featured sections fetched.',
            $this->growth->sections($zoneId)
        );
    }

    public function featuredSection(
        Request $request,
        string $slug
    ): JsonResponse {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $zoneId = $this->growth->zoneId(
            isset($data['latitude']) ? (float) $data['latitude'] : null,
            isset($data['longitude']) ? (float) $data['longitude'] : null
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Featured section fetched.',
            $this->growth->section($slug, $zoneId)
        );
    }

    public function faqs(Request $request): JsonResponse
    {
        $items = Faq::query()
            ->where('status', 'active')
            ->when(
                $request->filled('category'),
                fn ($query) => $query->where(
                    'category',
                    $request->string('category')->toString()
                )
            )
            ->orderBy('sort_order')
            ->get();

        return ApiResponseType::sendJsonResponse(
            true,
            'FAQs fetched.',
            $items
        );
    }

    public function productFaqs(string $slug): JsonResponse
    {
        $product = Product::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $items = ProductFaq::query()
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->whereNotNull('answer')
            ->with(['asker:id,name', 'answerer:id,name'])
            ->latest('answered_at')
            ->get();

        return ApiResponseType::sendJsonResponse(
            true,
            'Product FAQs fetched.',
            $items
        );
    }

    public function productReviews(
        Request $request,
        string $slug
    ): JsonResponse {
        $product = Product::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $items = $this->reviews->publicReviews(
            $product->id,
            min(100, max(1, (int) $request->input('per_page', 15)))
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Product reviews fetched.',
            [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'rating_summary' => [
                    'average' => round(
                        (float) \App\Models\Review::query()
                            ->where('product_id', $product->id)
                            ->where('status', 'published')
                            ->avg('rating'),
                        2
                    ),
                    'count' => \App\Models\Review::query()
                        ->where('product_id', $product->id)
                        ->where('status', 'published')
                        ->count(),
                    'breakdown' => collect(range(1, 5))
                        ->mapWithKeys(
                            fn (int $rating): array => [
                                (string) $rating => \App\Models\Review::query()
                                    ->where('product_id', $product->id)
                                    ->where('status', 'published')
                                    ->where('rating', $rating)
                                    ->count(),
                            ]
                        )
                        ->all(),
                ],
                'data' => ReviewResource::collection($items->items())
                    ->resolve($request),
            ]
        );
    }

    public function validateGiftCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $card = $this->giftCards->validateCode(
            $data['code'],
            $request->user()
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Gift card is valid.',
            [
                'code' => $card->code,
                'title' => $card->title,
                'amount' => $card->amount,
                'currency_code' => $card->currency_code,
                'ends_at' => $card->ends_at?->toIso8601String(),
            ]
        );
    }
}
