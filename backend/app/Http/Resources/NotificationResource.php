<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_type' => $this->role_type,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'is_read' => (bool) $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'store_id' => $this->store_id,
            'order_id' => $this->order_id,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
