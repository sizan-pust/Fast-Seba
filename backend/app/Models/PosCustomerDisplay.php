<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PosCustomerDisplay extends Model
{
    protected $fillable = [
        'token','seller_id','store_id','state_payload',
        'expires_at','last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'state_payload' => 'array',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->token ??= (string) Str::uuid();
        });
    }
}
