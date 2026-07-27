<?php

namespace App\Http\Resources;

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
                'exists' => false,
                'cart_item_id' => null,
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