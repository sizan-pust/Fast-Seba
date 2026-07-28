<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerSubscriptionUsage extends Model
{
    protected $fillable = [
        'seller_subscription_id','seller_id','feature_key','used_count',
        'period_starts_at','period_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'used_count' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            SellerSubscription::class,
            'seller_subscription_id'
        );
    }
}
