<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreInventoryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'store_id',
        'store_product_variant_id',
        'product_variant_id',
        'change_type',
        'quantity',
        'previous_stock',
        'new_stock',
        'reason',
        'reference_type',
        'reference_id',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_stock' => 'integer',
            'new_stock' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(
            StoreProductVariant::class,
            'store_product_variant_id'
        );
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}