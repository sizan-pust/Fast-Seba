<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $panel = $request->user()?->access_panel;

        if ($panel instanceof \BackedEnum) {
            $panel = $panel->value;
        }

        $isAdmin = $panel === 'admin';

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'subject' => $this->subject,
            'email' => $this->email,
            'description' => $this->description,
            'priority' => $this->priority,
            'priority_label' => Str::headline($this->priority),
            'status' => $this->status,
            'status_label' => Str::headline($this->status),
            'type' => $this->type ? [
                'id' => $this->type->id,
                'title' => $this->type->title,
                'slug' => $this->type->slug,
            ] : null,
            'order_id' => $this->order_id,
            'assigned_to' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null,
            'messages' => $this->relationLoaded('messages')
                ? $this->messages
                    ->filter(fn ($message) => ! $message->is_internal || $isAdmin)
                    ->map(fn ($message) => [
                        'id' => $message->id,
                        'sender_role' => $message->sender_role,
                        'sender_name' => $message->sender?->name,
                        'message' => $message->message,
                        'is_internal' => (bool) $message->is_internal,
                        'attachments' => $message
                            ->getMedia('support_attachments')
                            ->map(fn ($media) => [
                                'id' => $media->id,
                                'name' => $media->name,
                                'url' => $media->getUrl(),
                            ])->values()->all(),
                        'created_at' => $message->created_at?->toIso8601String(),
                    ])->values()->all()
                : [],
            'last_replied_at' => $this->last_replied_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
