<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class FeaturedSection extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'scope_type','scope_id','title','slug','short_description',
        'style','section_type','background_type','background_color',
        'text_color','sort_order','product_limit','status',
        'starts_at','ends_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'product_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryZone::class,
            'featured_section_zone',
            'featured_section_id',
            'zone_id'
        )->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'featured_section_product'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('featured_section_product.sort_order');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_background')->singleFile();
    }

    public function backgroundImageUrl(): string
    {
        return $this->getFirstMediaUrl('featured_background');
    }

    protected static function booted(): void
    {
        static::saving(function (self $section): void {
            if (! $section->slug || $section->isDirty('title')) {
                $base = Str::slug($section->title) ?: 'featured-section';
                $slug = $base;
                $counter = 2;

                while (
                    self::query()
                        ->where('slug', $slug)
                        ->when(
                            $section->exists,
                            fn ($query) => $query->where('id', '!=', $section->id)
                        )
                        ->exists()
                ) {
                    $slug = $base.'-'.$counter;
                    $counter++;
                }

                $section->slug = $slug;
            }
        });
    }
}
