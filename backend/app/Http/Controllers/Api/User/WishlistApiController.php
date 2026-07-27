<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\WishlistItemResource;
use App\Http\Resources\WishlistResource;
use App\Models\ProductVariant;
use App\Models\StoreProductVariant;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WishlistApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->with([
                'items.product',
                'items.variant',
                'items.store',
            ])
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 15));

        $data = $paginator->toArray();
        $data['data'] = collect($paginator->items())
            ->map(
                fn (Wishlist $wishlist) =>
                    (new WishlistResource($wishlist))->resolve($request)
            )
            ->all();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlists fetched successfully.',
            $data
        );
    }

    public function getTitles(Request $request): JsonResponse
    {
        $data = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Wishlist $wishlist) => [
                'id' => $wishlist->id,
                'title' => $wishlist->title,
                'slug' => $wishlist->slug,
                'items_count' => $wishlist->items_count,
                'created_at' => $wishlist->created_at?->format(
                    'Y-m-d H:i:s'
                ),
            ])
            ->values();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist titles fetched successfully.',
            $data
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wishlist_title' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'product_variant_id' => [
                'required_with:product_id',
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
            'store_id' => [
                'required_with:product_id',
                'nullable',
                'integer',
                'exists:stores,id',
            ],
        ]);

        $result = DB::transaction(function () use (
            $request,
            $validated
        ): array {
            $title = $validated['wishlist_title'] ?? 'Favorite';

            $wishlist = Wishlist::query()->firstOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'title' => $title,
                ],
                ['is_default' => $title === 'Favorite']
            );

            if (empty($validated['product_id'])) {
                return [
                    'message' => 'Wishlist created successfully.',
                    'data' => new WishlistResource(
                        $wishlist->loadCount('items')
                    ),
                ];
            }

            $variant = ProductVariant::query()
                ->where('product_id', $validated['product_id'])
                ->findOrFail($validated['product_variant_id']);

            StoreProductVariant::query()
                ->where('product_variant_id', $variant->id)
                ->where('store_id', $validated['store_id'])
                ->where('status', 'active')
                ->firstOrFail();

            $item = WishlistItem::query()->firstOrCreate([
                'wishlist_id' => $wishlist->id,
                'product_id' => $validated['product_id'],
                'product_variant_id' => $variant->id,
                'store_id' => $validated['store_id'],
            ]);

            return [
                'message' => $item->wasRecentlyCreated
                    ? 'Item added to wishlist.'
                    : 'Item already exists in wishlist.',
                'data' => new WishlistItemResource(
                    $item->load(['product', 'variant', 'store'])
                ),
            ];
        });

        return ApiResponseType::sendJsonResponse(
            true,
            $result['message'],
            $result['data']
        );
    }

    public function createWishlist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $exists = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->where('title', $validated['title'])
            ->exists();

        if ($exists) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Wishlist already exists.',
                [],
                422
            );
        }

        $wishlist = Wishlist::query()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist created successfully.',
            new WishlistResource($wishlist),
            201
        );
    }

    public function show(
        Request $request,
        string $id
    ): JsonResponse {
        $wishlist = $this->ownedWishlist($request, $id)
            ->loadCount('items')
            ->load([
                'items.product',
                'items.variant',
                'items.store',
            ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist fetched successfully.',
            new WishlistResource($wishlist)
        );
    }

    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $wishlist = $this->ownedWishlist($request, $id);
        $wishlist->update(['title' => $validated['title']]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist updated successfully.',
            new WishlistResource(
                $wishlist->fresh()->loadCount('items')
            )
        );
    }

    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        $this->ownedWishlist($request, $id)->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist deleted successfully.',
            []
        );
    }

    public function removeItem(
        Request $request,
        string $itemId
    ): JsonResponse {
        $item = WishlistItem::query()
            ->whereHas(
                'wishlist',
                fn ($query) => $query->where(
                    'user_id',
                    $request->user()->id
                )
            )
            ->find($itemId);

        abort_if(! $item, 404, 'Wishlist item not found.');
        $item->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist item removed successfully.',
            []
        );
    }

    public function moveItem(
        Request $request,
        string $itemId
    ): JsonResponse {
        $validated = $request->validate([
            'target_wishlist_id' => [
                'required',
                'integer',
                'exists:wishlists,id',
            ],
        ]);

        $item = WishlistItem::query()
            ->whereHas(
                'wishlist',
                fn ($query) => $query->where(
                    'user_id',
                    $request->user()->id
                )
            )
            ->find($itemId);

        abort_if(! $item, 404, 'Wishlist item not found.');

        $target = $this->ownedWishlist(
            $request,
            (string) $validated['target_wishlist_id']
        );

        $duplicate = WishlistItem::query()
            ->where('wishlist_id', $target->id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('store_id', $item->store_id)
            ->first();

        if ($duplicate) {
            $item->delete();
            $item = $duplicate;
        } else {
            $item->update(['wishlist_id' => $target->id]);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Wishlist item moved successfully.',
            new WishlistItemResource(
                $item->fresh(['product', 'variant', 'store'])
            )
        );
    }

    private function ownedWishlist(
        Request $request,
        string $id
    ): Wishlist {
        $wishlist = Wishlist::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        abort_if(! $wishlist, 404, 'Wishlist not found.');

        return $wishlist;
    }
}