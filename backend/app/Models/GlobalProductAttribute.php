<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GlobalProductAttribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'title',
        'slug',
        'label',
        'swatche_type',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(
            GlobalProductAttributeValue::class,
            'global_attribute_id'
        );
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttribute::class,
            'global_attribute_id'
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $attribute): void {
            if (! $attribute->slug || $attribute->isDirty('title')) {
                $attribute->slug = Str::slug($attribute->title);
            }
        });
    }
}