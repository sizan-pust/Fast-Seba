<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Wishlist extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'slug',
        'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $wishlist): void {
            $wishlist->uuid ??= (string) Str::uuid();
            $wishlist->slug = self::uniqueSlug(
                $wishlist->user_id,
                $wishlist->title
            );
        });

        static::updating(function (self $wishlist): void {
            if ($wishlist->isDirty('title')) {
                $wishlist->slug = self::uniqueSlug(
                    $wishlist->user_id,
                    $wishlist->title,
                    $wishlist->id
                );
            }
        });
    }

    private static function uniqueSlug(
        int $userId,
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'wishlist';
        $slug = $base;
        $counter = 2;

        while (
            self::query()
                ->where('user_id', $userId)
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