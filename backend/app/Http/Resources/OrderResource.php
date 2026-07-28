<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $assignment = $this->relationLoaded('deliveryAssignments')
            ? $this->deliveryAssignments->sortByDesc('id')->first()
            : null;

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'email' => $this->email,
            'status' => $this->status,
            'status_label' => Str::headline($this->status),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'delivery_type' => $this->delivery_type,
            'promo_code' => $this->promo_code,
            'promo_discount' => $this->promo_discount,
            'wallet_balance' => $this->wallet_balance,
            'subtotal' => $this->subtotal,
            'delivery_charge' => $this->delivery_charge,
            'handling_charges' => $this->handling_charges,
            'per_store_drop_off_fee' => $this->per_store_drop_off_fee,
            'total_payable' => $this->total_payable,
            'final_total' => $this->final_total,
            'billing_name' => $this->billing_name,
            'billing_phone' => $this->billing_phone,
            'shipping_name' => $this->shipping_name,
            'shipping_address_1' => $this->shipping_address_1,
            'shipping_address_2' => $this->shipping_address_2,
            'shipping_landmark' => $this->shipping_landmark,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_zip' => $this->shipping_zip,
            'shipping_country' => $this->shipping_country,
            'shipping_phone' => $this->shipping_phone,
            'order_note' => $this->order_note,
            'is_rush_order' => (bool) $this->is_rush_order,
            'estimated_delivery_time' => $this->estimated_delivery_time,
            'delivery_started_at' => $this->delivery_started_at
                ?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'delivery_zone' => $this->deliveryZone ? [
                'id' => $this->deliveryZone->id,
                'name' => $this->deliveryZone->name,
                'slug' => $this->deliveryZone->slug,
            ] : null,
            'delivery_boy' => $this->deliveryBoy
                ? new DeliveryBoyResource($this->deliveryBoy)
                : null,
            'delivery_boy_id' => $this->delivery_boy_id,
            'delivery_assignment' => $assignment
                ? new DeliveryAssignmentResource($assignment)
                : null,
            'delivery_route' => null,
            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),
            'transactions' => OrderPaymentResource::collection(
                $this->whenLoaded('paymentTransactions')
            ),
            'status_timeline' => $this->relationLoaded('statusLogs')
                ? $this->statusLogs
                    ->sortBy('created_at')
                    ->map(fn ($log) => [
                        'id' => $log->id,
                        'from_status' => $log->from_status,
                        'to_status' => $log->to_status,
                        'label' => Str::headline($log->to_status),
                        'actor_type' => $log->actor_type,
                        'note' => $log->note,
                        'created_at' => $log->created_at
                            ?->toIso8601String(),
                    ])
                    ->values()
                    ->all()
                : [],
            'invoice_url' => null,
            'created_at' => $this->created_at
                ?->format('M d, Y h:i A'),
            'updated_at' => $this->updated_at
                ?->format('M d, Y h:i A'),
        ];
    }
}
