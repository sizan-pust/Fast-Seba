<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Banner extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'type','scope_type','scope_id','title','slug','custom_url',
        'product_id','category_id','brand_id','position',
        'visibility_status','display_order','starts_at','ends_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function brand(): BelongsTo { return $this->belongsTo(Brand::class); }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryZone::class,
            'banner_zone',
            'banner_id',
            'zone_id'
        )->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner_image')->singleFile();
    }

    public function imageUrl(): string
    {
        return $this->getFirstMediaUrl('banner_image');
    }

    protected static function booted(): void
    {
        static::saving(function (self $banner): void {
            if (! $banner->slug || $banner->isDirty('title')) {
                $base = Str::slug($banner->title) ?: 'banner';
                $slug = $base;
                $counter = 2;

                while (
                    self::query()
                        ->where('slug', $slug)
                        ->when(
                            $banner->exists,
                            fn ($query) => $query->where('id', '!=', $banner->id)
                        )
                        ->exists()
                ) {
                    $slug = $base.'-'.$counter;
                    $counter++;
                }

                $banner->slug = $slug;
            }
        });
    }
}
