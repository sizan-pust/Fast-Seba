<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $user = $request->user('sanctum');

        $favorite = null;
        $itemCountInCart = 0;
        $isSaveForLater = false;

        if ($user) {
            $favoriteItems = WishlistItem::query()
                ->whereHas(
                    'wishlist',
                    fn ($query) => $query->where(
                        'user_id',
                        $user->id
                    )
                )
                ->where('product_id', $product->id)
                ->with(['wishlist', 'variant', 'store'])
                ->get();

            if ($favoriteItems->isNotEmpty()) {
                $favorite = $favoriteItems
                    ->map(fn ($item) => [
                        'id' => $item->id,
                        'wishlist_id' => $item->wishlist_id,
                        'wishlist_title' => $item->wishlist?->title,
                        'variant_id' => $item->variant?->id,
                        'variant_name' => $item->variant?->title,
                        'store_id' => $item->store?->id,
                        'store_name' => $item->store?->name,
                    ])
                    ->values()
                    ->all();
            }

            $cartItems = CartItem::query()
                ->whereHas(
                    'cart',
                    fn ($query) => $query->where(
                        'user_id',
                        $user->id
                    )
                )
                ->where('product_id', $product->id);

            $itemCountInCart = (clone $cartItems)
                ->where('save_for_later', false)
                ->sum('quantity');

            $isSaveForLater = (clone $cartItems)
                ->where('save_for_later', true)
                ->exists();
        }

        return [
            'id' => $product->id,
            'uuid' => $product->uuid,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'seller_id' => $product->seller_id,
            'title' => $product->title,
            'slug' => $product->slug,
            'type' => $product->type,
            'short_description' => $product->short_description,
            'category' => $product->category?->slug,
            'brand' => $product->brand?->slug,
            'category_name' => $product->category?->title,
            'brand_name' => $product->brand?->title,
            'seller' => $product->seller?->owner?->name
                ?? $product->seller?->business_name
                ?? 'N/A',
            'indicator' => $product->indicator,
            'favorite' => $favorite,
            'estimated_delivery_time' => null,
            'base_prep_time' => (int) $product->base_prep_time,
            'ratings' => 0.0,
            'rating_count' => 0,
            'main_image' => $product->mainImageUrl(),
            'image_fit' => $product->image_fit,
            'item_count_in_cart' => (int) $itemCountInCart,
            'is_save_for_later' => $isSaveForLater,
            'additional_images' => $product->additionalImageUrls(),
            'minimum_order_quantity' => (int) (
                $product->minimum_order_quantity
            ),
            'quantity_step_size' => (int) $product->quantity_step_size,
            'total_allowed_quantity' => (int) (
                $product->total_allowed_quantity
            ),
            'is_returnable' => (float) $product->is_returnable,
            'is_attachment_required' => (float) (
                $product->is_attachment_required
            ),
            'attachment_mode' => $product->attachment_mode,
            'requires_otp' => (float) $product->requires_otp,
            'tags' => $product->tags ?? [],
            'warranty_period' => $product->warranty_period,
            'guarantee_period' => $product->guarantee_period,
            'made_in' => $product->made_in,
            'is_inclusive_tax' => (bool) $product->is_inclusive_tax,
            'video_type' => $product->video_type,
            'video_link' => $product->video_link,
            'status' => $product->status,
            'featured' => $product->featured ? '1' : '0',
            'badge' => $product->badge ? [
                'id' => $product->badge->id,
                'label' => $product->badge->label,
                'bg_color' => $product->badge->bg_color,
                'text_color' => $product->badge->text_color,
                'border_color' => $product->badge->border_color,
            ] : null,
            'metadata' => $product->metadata ?? [],
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'store_status' => $this->storeStatus($product),
            'variants' => ProductVariantResource::collection(
                $product->variants
            ),
            'attributes' => $this->formattedAttributes($product),
            'is_sponsored' => false,
            'campaign_id' => null,
            'visitor_key' => null,
        ];
    }

    protected function formattedAttributes(Product $product): array
    {
        $groups = [];

        foreach ($product->variantAttributes as $variantAttribute) {
            $attribute = $variantAttribute->attribute;
            $value = $variantAttribute->attributeValue;

            if (! $attribute || ! $value) {
                continue;
            }

            $slug = $attribute->slug;

            $groups[$slug] ??= [
                'name' => $attribute->title,
                'slug' => $slug,
                'swatche_type' => $attribute->swatche_type,
                'values' => [],
                'swatch_values' => [],
            ];

            if (! in_array(
                $value->title,
                $groups[$slug]['values'],
                true
            )) {
                $groups[$slug]['values'][] = $value->title;
                $groups[$slug]['swatch_values'][] = [
                    'value' => $value->title,
                    'swatch' => $value->swatchValue(),
                ];
            }
        }

        return array_values($groups);
    }

    protected function storeStatus(Product $product): array
    {
        $store = $product->variants
            ->first()
            ?->storeProductVariants
            ->first()
            ?->store;

        if (! $store) {
            return [];
        }

        return [
            'is_open' => $store->status === 'online',
            'current_slot' => null,
            'next_opening_time' => '',
        ];
    }
}