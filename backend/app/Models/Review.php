<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Review extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $fillable = [
        'user_id','product_id','order_id','order_item_id','store_id',
        'rating','title','comment','status','seller_reply',
        'seller_replied_at','moderated_by','moderation_note',
        'moderated_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'seller_replied_at' => 'datetime',
            'moderated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('review_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Keep the original upload. The customer website uses fixed aspect-ratio
        // containers and image_fit rules to avoid distortion.
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function moderator(): BelongsTo { return $this->belongsTo(User::class, 'moderated_by'); }
}
