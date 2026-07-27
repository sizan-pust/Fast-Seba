<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrderPaymentTransaction extends Model
{
    protected $fillable = [
        'uuid','order_id','user_id','transaction_id','amount','currency',
        'payment_method','payment_status','message','payment_details',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2','payment_details' => 'array'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
