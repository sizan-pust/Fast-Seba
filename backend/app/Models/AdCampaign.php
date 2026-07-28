<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdCampaign extends Model
{
    protected $fillable = [
        'uuid','seller_id','store_id','product_id','title','ad_type',
        'placement','status','budget','spent_amount','bid_amount',
        'starts_at','ends_at','approved_by','approved_at',
        'rejection_reason','metadata',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'bid_amount' => 'decimal:4',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(
            AdCampaignStat::class,
            'ad_campaign_id'
        );
    }
}
