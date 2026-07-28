<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosRefundLine extends Model
{
    protected $fillable = [
        'pos_refund_id','order_item_id','quantity','amount','metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(PosRefund::class, 'pos_refund_id');
    }
}
