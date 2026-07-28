<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerFeedback extends Model
{
    protected $table = 'seller_feedback';

    protected $fillable = [
        'user_id','seller_id','order_id','rating','comment',
        'status','seller_reply','seller_replied_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'seller_replied_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
