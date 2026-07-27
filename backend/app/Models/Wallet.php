<?php

namespace App\Models;

use App\Enums\WalletTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'balance',
        'blocked_balance',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletTypeEnum::class,
            'balance' => 'decimal:2',
            'blocked_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availableBalance(): string
    {
        return number_format(
            (float) $this->balance - (float) $this->blocked_balance,
            2,
            '.',
            ''
        );
    }
}