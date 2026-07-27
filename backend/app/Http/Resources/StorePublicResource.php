<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorePublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_count' => (int) (
                $this->product_count ?? 0
            ),
            'description' => $this->description,
            'contact_number' => $this->contact_number,
            'contact_email' => $this->contact_email,
            'seller_id' => $this->seller_id,
            'tax_name' => $this->tax_name,
            'tax_number' => $this->tax_number,
            'currency_code' => $this->currency_code,
            'max_delivery_distance' => $this->max_delivery_distance,
            'order_preparation_time' => $this->order_preparation_time,
            'promotional_text' => $this->promotional_text,
            'about_us' => $this->about_us,
            'return_replacement_policy' =>
                $this->return_replacement_policy,
            'refund_policy' => $this->refund_policy,
            'terms_and_conditions' => $this->terms_and_conditions,
            'delivery_policy' => $this->delivery_policy,
            'domestic_shipping_charges' =>
                $this->domestic_shipping_charges,
            'international_shipping_charges' =>
                $this->international_shipping_charges,
            'zones' => DeliveryZoneResource::collection(
                $this->whenLoaded('zones')
            ),
            'metadata' => $this->metadata ?? [],
            'fulfillment_type' => $this->fulfillment_type,
            'address' => $this->address,
            'city' => $this->city,
            'landmark' => $this->landmark,
            'state' => $this->state,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'zipcode' => $this->zipcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance' => 0,
            'timing' => $this->timing,
            'logo' => $this->getFirstMediaUrl('store_logo'),
            'banner' => $this->getFirstMediaUrl('store_banner'),
            'same_location' => true,
            'avg_products_rating' => '0.00',
            'avg_store_rating' => '0.00',
            'total_store_feedback' => '0',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'verification_status' => $this->verification_status,
            'visibility_status' => $this->visibility_status,
            'is_recommended' => (bool) $this->is_recommended,
            'status' => [
                'is_open' => $this->status === 'online',
                'current_slot' => null,
                'next_opening_time' => '',
            ],
            'allows_pickup' => (bool) $this->allows_pickup,
            'pickup_instructions' => $this->pickup_instructions ?? '',
        ];
    }
}