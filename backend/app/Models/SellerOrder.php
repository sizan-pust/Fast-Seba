<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerOrder extends Model
{
    protected $fillable = [
        'order_id','seller_id','store_id','status','delivery_type',
        'subtotal','commission_amount','seller_earnings','accepted_at',
        'ready_for_pickup_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'seller_earnings' => 'decimal:2',
            'accepted_at' => 'datetime',
            'ready_for_pickup_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function sellerItems(): HasMany { return $this->hasMany(SellerOrderItem::class); }
}
