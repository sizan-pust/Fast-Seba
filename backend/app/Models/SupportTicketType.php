<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportTicketType extends Model
{
    protected $fillable = ['title','slug','status','sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function tickets(): HasMany { return $this->hasMany(SupportTicket::class, 'ticket_type_id'); }

    protected static function booted(): void
    {
        static::saving(function (self $type): void {
            if (! $type->slug || $type->isDirty('title')) {
                $type->slug = Str::slug($type->title) ?: 'support';
            }
        });
    }
}
