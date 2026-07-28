<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class WithdrawalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'status_label' => Str::headline($this->status),
            'request_note' => $this->request_note,
            'admin_remark' => $this->admin_remark,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'external_transaction_id' => $this->external_transaction_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
