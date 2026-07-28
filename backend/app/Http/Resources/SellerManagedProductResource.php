<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SellerManagedProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'seller_id' => $this->seller_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'rejection_reason' => $this->rejection_reason,
            'featured' => (bool) $this->featured,
            'minimum_order_quantity' => $this->minimum_order_quantity,
            'quantity_step_size' => $this->quantity_step_size,
            'total_allowed_quantity' => $this->total_allowed_quantity,
            'is_returnable' => (bool) $this->is_returnable,
            'returnable_days' => $this->returnable_days,
            'is_cancelable' => (bool) $this->is_cancelable,
            'cancelable_till' => $this->cancelable_till,
            'requires_otp' => (bool) $this->requires_otp,
            'base_prep_time' => $this->base_prep_time,
            'tags' => $this->tags ?? [],
            'custom_fields' => $this->custom_fields ?? [],
            'category' => $this->category ? [
                'id' => $this->category->id,
                'title' => $this->category->title,
                'slug' => $this->category->slug,
                'requires_approval' => (bool) $this->category->requires_approval,
                'commission' => $this->category->commission,
            ] : null,
            'categories' => $this->relationLoaded('categories')
                ? $this->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                ])->values()->all()
                : [],
            'brand' => $this->brand ? [
                'id' => $this->brand->id,
                'title' => $this->brand->title,
                'slug' => $this->brand->slug,
            ] : null,
            'variants' => $this->relationLoaded('variants')
                ? $this->variants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'uuid' => $variant->uuid,
                        'title' => $variant->title,
                        'slug' => $variant->slug,
                        'barcode' => $variant->barcode,
                        'availability' => (bool) $variant->availability,
                        'visibility' => $variant->visibility,
                        'is_default' => (bool) $variant->is_default,
                        'attributes' => $variant->relationLoaded('attributes')
                            ? $variant->attributes->map(fn ($item) => [
                                'id' => $item->id,
                                'attribute_id' => $item->global_attribute_id,
                                'attribute_value_id' => $item->global_attribute_value_id,
                                'attribute' => $item->attribute?->title,
                                'value' => $item->attributeValue?->title,
                            ])->values()->all()
                            : [],
                        'stores' => $variant->relationLoaded('storeProductVariants')
                            ? $variant->storeProductVariants->map(fn ($inventory) => [
                                'id' => $inventory->id,
                                'store_id' => $inventory->store_id,
                                'store_name' => $inventory->store?->name,
                                'sku' => $inventory->sku,
                                'price' => $inventory->price,
                                'special_price' => $inventory->special_price,
                                'cost' => $inventory->cost,
                                'stock' => $inventory->stock,
                                'low_stock_threshold' => $inventory->low_stock_threshold,
                                'status' => $inventory->status,
                            ])->values()->all()
                            : [],
                    ];
                })->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
