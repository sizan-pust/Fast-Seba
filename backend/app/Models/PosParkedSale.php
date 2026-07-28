<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosParkedSale extends Model
{
    protected $fillable = [
        'uuid','seller_id','store_id','customer_id','parked_by',
        'reference','cart_payload','totals_payload','note','expires_at',
    ];

    protected function casts(): array
    {
        return [
            'cart_payload' => 'array',
            'totals_payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
