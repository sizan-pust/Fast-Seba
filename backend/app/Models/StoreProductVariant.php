<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StoreProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_variant_id',
        'store_id',
        'sku',
        'price',
        'special_price',
        'cost',
        'stock',
        'low_stock_threshold',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(
            StoreInventoryLog::class,
            'store_product_variant_id'
        );
    }

    public function effectivePrice(): float
    {
        $specialPrice = $this->special_price;

        if (
            $specialPrice !== null
            && (float) $specialPrice > 0
            && (float) $specialPrice < (float) $this->price
        ) {
            return (float) $specialPrice;
        }

        return (float) $this->price;
    }
}