<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    protected $fillable = [
        'uuid','ticket_type_id','user_id','order_id','assigned_to',
        'subject','email','description','priority','status',
        'last_replied_at','resolved_at','closed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_replied_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo { return $this->belongsTo(SupportTicketType::class, 'ticket_type_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function messages(): HasMany { return $this->hasMany(SupportTicketMessage::class, 'ticket_id'); }

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            $ticket->uuid ??= (string) Str::uuid();
        });
    }
}
