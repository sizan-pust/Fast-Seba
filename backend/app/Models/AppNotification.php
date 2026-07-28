<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AppNotification extends Model
{
    protected $fillable = [
        'audience_type','title','message','target_type','target_id',
        'scheduled_at','sent_at','status','metadata','created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'app_notification_user_map',
            'notification_id',
            'user_id'
        )->withPivot('user_type')->withTimestamps();
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryZone::class,
            'app_notification_zone_map',
            'notification_id',
            'zone_id'
        )->withTimestamps();
    }
}
