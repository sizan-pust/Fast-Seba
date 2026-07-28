<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    protected $fillable = [
        'code','title','amount','currency_code','max_redemptions',
        'redemption_count','starts_at','ends_at','status','created_by','metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'max_redemptions' => 'integer',
            'redemption_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function redemptions(): HasMany { return $this->hasMany(GiftCardRedemption::class); }
}
