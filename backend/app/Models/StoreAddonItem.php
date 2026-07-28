<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAddonItem extends Model
{
    protected $fillable = [
        'store_id','addon_item_id','price','cost','stock',
        'low_stock_threshold','is_available',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function addonItem(): BelongsTo
    {
        return $this->belongsTo(AddonItem::class);
    }
}
