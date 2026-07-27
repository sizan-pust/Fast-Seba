<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id','seller_order_id','product_id','product_variant_id',
        'store_id','product_title','variant_title','sku','price',
        'special_price','quantity','subtotal','tax_amount','promo_discount',
        'status','is_returnable','returnable_until','cancellation_reason',
        'cancelled_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'promo_discount' => 'decimal:2',
            'is_returnable' => 'boolean',
            'returnable_until' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function sellerOrder(): BelongsTo { return $this->belongsTo(SellerOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function sellerItem(): HasOne { return $this->hasOne(SellerOrderItem::class); }
}
