<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'image' => $this->imageUrl(),
            'banner' => '',
            'icon' => $this->iconUrl(),
            'active_icon' => $this->activeIconUrl(),
            'background_type' => $this->background_type,
            'background_color' => $this->background_color ?? '',
            'background_image' => $this->backgroundImageUrl(),
            'font_color' => $this->font_color ?? '',
            'search_labels' => $this->search_labels ?? [],
            'parent_id' => $this->parent_id,
            'commission' => $this->commission ?? '0.00',
            'parent_slug' => $this->parent?->slug,
            'description' => $this->description,
            'status' => $this->status,
            'requires_approval' => (bool) $this->requires_approval,
            'metadata' => $this->metadata ?? [],
            'subcategory_count' => (int) (
                $this->children_count ?? 0
            ),
            'product_count' => (int) (
                $this->products_count ?? 0
            ),
            'enabled' => (bool) ($this->enabled ?? true),
        ];
    }
}