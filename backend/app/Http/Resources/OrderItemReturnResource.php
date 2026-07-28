<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class OrderItemReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'order_id' => $this->order_id,
            'quantity' => $this->quantity,
            'reason' => $this->reason,
            'details' => $this->details,
            'refund_amount' => $this->refund_amount,
            'refund_method' => $this->refund_method,
            'return_status' => $this->return_status,
            'return_status_label' => Str::headline(
                $this->return_status
            ),
            'pickup_status' => $this->pickup_status,
            'pickup_status_label' => Str::headline(
                $this->pickup_status
            ),
            'seller_comment' => $this->seller_comment,
            'admin_comment' => $this->admin_comment,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'seller_decided_at' => $this->seller_decided_at
                ?->toIso8601String(),
            'pickup_assigned_at' => $this->pickup_assigned_at
                ?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'received_at' => $this->received_at?->toIso8601String(),
            'refund_processed_at' => $this->refund_processed_at
                ?->toIso8601String(),
            'delivery_boy' => $this->deliveryBoy
                ? new DeliveryBoyResource($this->deliveryBoy)
                : null,
            'refund_transaction' => $this->refundTransaction ? [
                'id' => $this->refundTransaction->id,
                'transaction_id' =>
                    $this->refundTransaction->transaction_id,
                'amount' => $this->refundTransaction->amount,
                'method' => $this->refundTransaction->method,
                'status' => $this->refundTransaction->status,
            ] : null,
        ];
    }
}
