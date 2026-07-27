<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'seller_id',
        'category_id',
        'brand_id',
        'product_condition_id',
        'badge_id',
        'cloned_from_id',
        'provider',
        'provider_product_id',
        'slug',
        'title',
        'product_identity',
        'type',
        'short_description',
        'description',
        'indicator',
        'download_allowed',
        'download_link',
        'minimum_order_quantity',
        'quantity_step_size',
        'total_allowed_quantity',
        'is_inclusive_tax',
        'hsn_code',
        'is_returnable',
        'returnable_days',
        'is_cancelable',
        'cancelable_till',
        'is_attachment_required',
        'attachment_mode',
        'requires_otp',
        'base_prep_time',
        'status',
        'verification_status',
        'rejection_reason',
        'featured',
        'video_type',
        'video_link',
        'tags',
        'custom_fields',
        'warranty_period',
        'guarantee_period',
        'made_in',
        'metadata',
        'image_fit',
    ];

    protected function casts(): array
    {
        return [
            'download_allowed' => 'boolean',
            'minimum_order_quantity' => 'integer',
            'quantity_step_size' => 'integer',
            'total_allowed_quantity' => 'integer',
            'is_inclusive_tax' => 'boolean',
            'is_returnable' => 'boolean',
            'returnable_days' => 'integer',
            'is_cancelable' => 'boolean',
            'is_attachment_required' => 'boolean',
            'requires_otp' => 'boolean',
            'base_prep_time' => 'integer',
            'featured' => 'boolean',
            'tags' => 'array',
            'custom_fields' => 'array',
            'metadata' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_product'
        )->withTimestamps();
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function productCondition(): BelongsTo
    {
        return $this->belongsTo(ProductCondition::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function clonedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cloned_from_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderByDesc('is_default');
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class);
    }

    public function mainImageUrl(): string
    {
        return $this->getFirstMediaUrl('product_main_image');
    }

    public function additionalImageUrls(): array
    {
        return $this->getMedia('product_additional_image')
            ->map(fn ($media) => $media->getUrl())
            ->values()
            ->all();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_main_image')->singleFile();
        $this->addMediaCollection('product_additional_image');
        $this->addMediaCollection('product_video')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            $product->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $product): void {
            if (! $product->slug || $product->isDirty('title')) {
                $product->slug = self::uniqueSlug(
                    $product->title,
                    $product->id
                );
            }
        });
    }

    private static function uniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'product';
        $slug = $base;
        $counter = 2;

        while (
            self::withTrashed()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}