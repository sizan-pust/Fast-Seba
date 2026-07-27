<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductCondition extends Model
{
    protected $fillable = [
        'uuid',
        'category_id',
        'title',
        'slug',
        'alignment',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $condition): void {
            $condition->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $condition): void {
            if (! $condition->slug || $condition->isDirty('title')) {
                $condition->slug = Str::slug($condition->title);
            }
        });
    }
}