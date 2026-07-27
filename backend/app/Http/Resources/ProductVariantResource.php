<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inventory = $this->storeProductVariants->first();

        $attributes = [];

        foreach ($this->attributes as $variantAttribute) {
            $attribute = $variantAttribute->attribute;
            $value = $variantAttribute->attributeValue;

            if ($attribute && $value) {
                $attributes[$attribute->slug] = $value->title;
            }
        }

        $cartItem = null;
        $user = $request->user('sanctum');

        if ($user && $inventory) {
            $cartItem = CartItem::query()
                ->whereHas(
                    'cart',
                    fn ($query) => $query->where(
                        'user_id',
                        $user->id
                    )
                )
                ->where('product_variant_id', $this->id)
                ->where('store_id', $inventory->store_id)
                ->where('save_for_later', false)
                ->first();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->imageUrl(),
            'weight' => (float) ($this->weight ?? 0),
            'height' => (float) ($this->height ?? 0),
            'breadth' => (float) ($this->breadth ?? 0),
            'length' => (float) ($this->length ?? 0),
            'availability' => (bool) $this->availability,
            'cart_item' => [
                'exists' => $cartItem !== null,
                'cart_item_id' => $cartItem?->id,
            ],
            'barcode' => $this->barcode,
            'is_default' => (bool) $this->is_default,
            'price' => $inventory?->price,
            'special_price' => $inventory?->special_price,
            'store_id' => $inventory?->store_id,
            'store_slug' => $inventory?->store?->slug,
            'store_name' => $inventory?->store?->name,
            'stock' => $inventory?->stock,
            'sku' => $inventory?->sku,
            'attributes' => $attributes,
            'addon_groups' => [],
        ];
    }
}