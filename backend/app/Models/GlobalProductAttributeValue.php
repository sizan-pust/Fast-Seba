<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class GlobalProductAttributeValue extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'global_attribute_id',
        'title',
        'swatche_value',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(
            GlobalProductAttribute::class,
            'global_attribute_id'
        );
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttribute::class,
            'global_attribute_value_id'
        );
    }

    public function swatchValue(): ?string
    {
        if ($this->attribute?->swatche_type === 'image') {
            return $this->getFirstMediaUrl('swatche_image') ?: null;
        }

        return $this->swatche_value;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('swatche_image')->singleFile();
    }
}