<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Badge extends Model
{
    protected $fillable = [
        'uuid',
        'label',
        'slug',
        'bg_color',
        'text_color',
        'border_color',
        'status',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $badge): void {
            $badge->uuid ??= (string) Str::uuid();
        });

        static::saving(function (self $badge): void {
            if (! $badge->slug || $badge->isDirty('label')) {
                $badge->slug = Str::slug($badge->label);
            }
        });
    }
}