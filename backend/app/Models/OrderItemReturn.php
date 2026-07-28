<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItemReturn extends Model
{
    protected $fillable = [
        'order_item_id','order_id','user_id','seller_id','store_id',
        'delivery_boy_id','quantity','reason','details','refund_amount',
        'refund_method','seller_comment','admin_comment','pickup_status',
        'return_status','requested_at','seller_decided_at',
        'pickup_assigned_at','picked_up_at','received_at',
        'refund_processed_at','cancelled_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity'=>'integer','refund_amount'=>'decimal:2',
            'requested_at'=>'datetime','seller_decided_at'=>'datetime',
            'pickup_assigned_at'=>'datetime','picked_up_at'=>'datetime',
            'received_at'=>'datetime','refund_processed_at'=>'datetime',
            'cancelled_at'=>'datetime','metadata'=>'array',
        ];
    }

    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function deliveryBoy(): BelongsTo { return $this->belongsTo(DeliveryBoy::class); }
    public function refundTransaction(): HasOne { return $this->hasOne(RefundTransaction::class); }
}
