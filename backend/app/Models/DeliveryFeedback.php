<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryFeedback extends Model
{
    protected $table = 'delivery_feedback';

    protected $fillable = [
        'user_id','delivery_boy_id','order_id','rating',
        'comment','status',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function deliveryBoy(): BelongsTo
    {
        return $this->belongsTo(DeliveryBoy::class);
    }
}
