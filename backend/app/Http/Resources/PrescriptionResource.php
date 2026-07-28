<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PrescriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'status_label' => Str::headline($this->status),
            'patient_name' => $this->patient_name,
            'patient_age' => $this->patient_age,
            'doctor_name' => $this->doctor_name,
            'doctor_registration_no' => $this->doctor_registration_no,
            'prescribed_at' => $this->prescribed_at?->format('Y-m-d'),
            'notes' => $this->notes,
            'review_notes' => $this->review_notes,
            'rejection_reason' => $this->rejection_reason,
            'order_id' => $this->order_id,
            'seller' => $this->seller ? [
                'id' => $this->seller->id,
                'business_name' => $this->seller->business_name,
            ] : null,
            'store' => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
            ] : null,
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
            ] : null,
            'items' => $this->relationLoaded('items')
                ? $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'medicine_name' => $item->medicine_name,
                    'strength' => $item->strength,
                    'dosage' => $item->dosage,
                    'duration' => $item->duration,
                    'quantity' => $item->quantity,
                    'instructions' => $item->instructions,
                ])->values()->all()
                : [],
            'files' => $this->getMedia('prescription_files')
                ->map(fn ($media) => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'mime_type' => $media->mime_type,
                    'url' => $media->getUrl(),
                ])->values()->all(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'fulfilled_at' => $this->fulfilled_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
