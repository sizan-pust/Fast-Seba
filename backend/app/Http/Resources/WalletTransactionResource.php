<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,'uuid'=>$this->uuid,'type'=>$this->type,'amount'=>$this->amount,
            'opening_balance'=>$this->opening_balance,'closing_balance'=>$this->closing_balance,
            'reference_type'=>$this->reference_type,'reference_id'=>$this->reference_id,
            'status'=>$this->status,'description'=>$this->description,
            'created_at'=>$this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
