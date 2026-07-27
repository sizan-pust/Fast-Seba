<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    protected $fillable = [
        'uuid','wallet_id','user_id','type','amount','opening_balance',
        'closing_balance','reference_type','reference_id','status',
        'description','metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2','opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2','metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }
}
