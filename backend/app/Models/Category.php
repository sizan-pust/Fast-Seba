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

class Category extends Model implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'parent_id',
        'title',
        'slug',
        'description',
        'status',
        'requires_approval',
        'commission',
        'sort_order',
        'is_home_category',
        'background_type',
        'background_color',
        'font_color',
        'search_labels',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requires_approval' => 'boolean',
            'commission' => 'decimal:2',
            'sort_order' => 'integer',
            'is_home_category' => 'boolean',
            'search_labels' => 'array',
            'metadata' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function secondaryProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'category_product'
        )->withTimestamps();
    }

    public function imageUrl(): string
    {
        return $this->getFirstMediaUrl('image');
    }

    public function iconUrl(): string
    {
        return $this->getFirstMediaUrl('icon');
    }

    public function activeIconUrl(): string
    {
        return $this->getFirstMediaUrl('active_icon');
    }

    public function backgroundImageUrl(): string
    {
        return $this->getFirstMediaUrl('background_image');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('icon')->singleFile();
        $this->addMediaCollection('active_icon')->singleFile();
        $this->addMediaCollection('background_image')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $category): void {
            if (! $category->slug || $category->isDirty('title')) {
                $category->slug = self::uniqueSlug(
                    $category->title,
                    $category->id
                );
            }
        });
    }

    private static function uniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'category';
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