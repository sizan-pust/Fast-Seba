<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerOrderItem extends Model
{
    protected $fillable = [
        'seller_order_id','order_item_id','product_id','product_variant_id',
        'store_id','price','quantity','subtotal',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2','quantity' => 'integer','subtotal' => 'decimal:2'];
    }

    public function sellerOrder(): BelongsTo { return $this->belongsTo(SellerOrder::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
}
