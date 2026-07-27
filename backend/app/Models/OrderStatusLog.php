<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends Model
{
    protected $fillable = [
        'order_id','order_item_id','from_status','to_status','changed_by',
        'actor_type','note',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function item(): BelongsTo { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
