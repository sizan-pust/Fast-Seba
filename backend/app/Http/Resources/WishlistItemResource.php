<?php

namespace App\Http\Resources;

use App\Models\StoreProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $inventory = StoreProductVariant::query()
            ->where('product_variant_id', $this->product_variant_id)
            ->where('store_id', $this->store_id)
            ->first();

        return [
            'id' => $this->id,
            'wishlist_id' => $this->wishlist_id,
            'product' => [
                'id' => $this->product?->id,
                'title' => $this->product?->title,
                'name' => $this->product?->title,
                'slug' => $this->product?->slug,
                'image' => $this->product?->mainImageUrl() ?? '',
                'short_description' =>
                    $this->product?->short_description,
            ],
            'variant' => [
                'id' => $this->variant?->id,
                'title' => $this->variant?->title,
                'slug' => $this->variant?->slug,
                'sku' => $inventory?->sku,
                'image' => $this->variant?->imageUrl() ?? '',
                'price' => $inventory?->price,
                'special_price' => $inventory?->special_price,
                'store_id' => $this->store_id,
                'store_slug' => $this->store?->slug,
                'store_name' => $this->store?->name,
                'stock' => $inventory?->stock ?? 0,
            ],
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
                'slug' => $this->store?->slug,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}