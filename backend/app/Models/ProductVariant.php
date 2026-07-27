<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariant extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'product_id',
        'title',
        'slug',
        'weight',
        'height',
        'breadth',
        'length',
        'availability',
        'provider',
        'provider_product_id',
        'provider_json',
        'barcode',
        'visibility',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'height' => 'decimal:3',
            'breadth' => 'decimal:3',
            'length' => 'decimal:3',
            'availability' => 'boolean',
            'provider_json' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttribute::class,
            'product_variant_id'
        );
    }

    public function storeProductVariants(): HasMany
    {
        return $this->hasMany(
            StoreProductVariant::class,
            'product_variant_id'
        )->orderByDesc('stock');
    }

    public function imageUrl(): string
    {
        return $this->getFirstMediaUrl('variant_image');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('variant_image')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $variant): void {
            $variant->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $variant): void {
            if (! $variant->slug || $variant->isDirty('title')) {
                $base = Str::slug(
                    ($variant->product?->title ?? 'product')
                    .'-'.$variant->title
                ) ?: 'variant';

                $slug = $base;
                $counter = 2;

                while (
                    self::withTrashed()
                        ->where('slug', $slug)
                        ->when(
                            $variant->id,
                            fn ($query) => $query->where(
                                'id',
                                '!=',
                                $variant->id
                            )
                        )
                        ->exists()
                ) {
                    $slug = $base.'-'.$counter;
                    $counter++;
                }

                $variant->slug = $slug;
            }
        });
    }
}