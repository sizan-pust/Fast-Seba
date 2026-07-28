<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemAddon extends Model
{
    protected $fillable = [
        'order_item_id','addon_group_id','addon_item_id','group_title',
        'item_title','unit_price','quantity','subtotal','metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'subtotal' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AddonGroup::class, 'addon_group_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AddonItem::class, 'addon_item_id');
    }
}
