<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class OrderPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'uuid'=>$this->uuid,'order_id'=>$this->order_id,
            'order_slug'=>$this->order?->slug,'transaction_id'=>$this->transaction_id,
            'amount'=>$this->amount,'currency'=>$this->currency,'payment_method'=>$this->payment_method,
            'payment_status'=>$this->payment_status,'message'=>$this->message,
            'payment_details'=>$this->payment_details ?? [],
            'created_at'=>$this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
