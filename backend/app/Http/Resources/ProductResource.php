<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProductResource extends ProductListResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'returnable_days' => $this->returnable_days,
            'is_cancelable' => (float) $this->is_cancelable,
            'cancelable_till' => $this->cancelable_till,
            'custom_fields' => $this->custom_fields ?? [],
            'seller_ratings' => [
                'average_rating' => 0,
                'total_reviews' => 0,
            ],
            'custom_product_sections' => [],
        ]);
    }
}