<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreProductVariantAddon extends Model
{
    protected $fillable = [
        'store_id','product_variant_id','addon_group_id',
        'addon_item_id','is_default','sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
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
