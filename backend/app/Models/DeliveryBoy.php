<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliveryBoy extends Model
{
    protected $fillable = [
        'user_id','delivery_zone_id','status','verification_status',
        'is_blocked','blocked_reason','vehicle_type','vehicle_number',
        'license_number','metadata',
    ];

    protected function casts(): array
    {
        return ['is_blocked'=>'boolean','metadata'=>'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deliveryZone(): BelongsTo { return $this->belongsTo(DeliveryZone::class); }
    public function location(): HasOne { return $this->hasOne(DeliveryBoyLocation::class); }
    public function assignments(): HasMany { return $this->hasMany(DeliveryBoyAssignment::class); }
    public function returns(): HasMany { return $this->hasMany(OrderItemReturn::class); }

    public function canWork(): bool
    {
        return ! $this->is_blocked
            && $this->verification_status === 'approved'
            && in_array($this->status, ['available','on_delivery'], true);
    }
}
