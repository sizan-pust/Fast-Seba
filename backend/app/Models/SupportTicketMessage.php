<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SupportTicketMessage extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'ticket_id','sender_id','sender_role','message','is_internal',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class, 'ticket_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('support_attachments');
    }
}
