<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id','actor_role','action','entity_type','entity_id',
        'before_data','after_data','ip_address','user_agent',
        'request_id','metadata','created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
