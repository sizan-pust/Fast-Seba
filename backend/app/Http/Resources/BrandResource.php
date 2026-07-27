<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'logo' => $this->logoUrl(),
            'status' => $this->status,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'scope_category_slug' => $this->scopeCategory?->slug ?? '',
            'scope_category_title' => $this->scopeCategory?->title ?? '',
            'description' => $this->description,
            'metadata' => $this->metadata ?? [],
            'total_products' => (int) (
                $this->products_count ?? 0
            ),
            'enabled' => (bool) ($this->enabled ?? true),
        ];
    }
}