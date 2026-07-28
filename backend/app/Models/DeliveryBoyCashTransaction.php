<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryBoyCashTransaction extends Model
{
    protected $fillable = [
        'delivery_boy_id','order_id','delivery_boy_assignment_id',
        'type','amount','status','reference','note',
        'processed_by','processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function deliveryBoy(): BelongsTo
    {
        return $this->belongsTo(DeliveryBoy::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
