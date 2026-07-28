<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SubscriptionTransaction extends Model
{
    protected $fillable = [
        'uuid','seller_id','seller_subscription_id','subscription_plan_id',
        'transaction_id','amount','currency','payment_method','status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            SellerSubscription::class,
            'seller_subscription_id'
        );
    }
}
