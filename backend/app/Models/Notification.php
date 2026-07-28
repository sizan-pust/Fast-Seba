<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id','store_id','order_id','role_type','type','title',
        'message','is_read','read_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
