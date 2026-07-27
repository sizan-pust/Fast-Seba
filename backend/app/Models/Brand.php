<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Brand extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'uuid',
        'scope_type',
        'scope_id',
        'title',
        'slug',
        'description',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function scopeCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'scope_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function logoUrl(): string
    {
        return $this->getFirstMediaUrl('brand_logo');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('brand_logo')->singleFile();
    }

    protected static function booted(): void
    {
        static::creating(function (self $brand): void {
            $brand->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $brand): void {
            if (! $brand->slug || $brand->isDirty('title')) {
                $brand->slug = self::uniqueSlug(
                    $brand->title,
                    $brand->id
                );
            }
        });
    }

    private static function uniqueSlug(
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'brand';
        $slug = $base;
        $counter = 2;

        while (
            self::query()
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