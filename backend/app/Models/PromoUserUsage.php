<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoUserUsage extends Model
{
    protected $fillable = [
        'promo_id',
        'user_id',
        'usage_count',
    ];

    protected function casts(): array
    {
        return ['usage_count' => 'integer'];
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}