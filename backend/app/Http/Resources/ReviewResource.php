<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'status' => $this->status,
            'status_label' => Str::headline($this->status),
            'seller_reply' => $this->seller_reply,
            'seller_replied_at' => $this->seller_replied_at?->toIso8601String(),
            'moderation_note' => $this->moderation_note,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'title' => $this->product->title,
                'slug' => $this->product->slug,
                'image' => $this->product->mainImageUrl(),
            ] : null,
            'store' => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ] : null,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
