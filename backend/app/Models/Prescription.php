<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Prescription extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'uuid','user_id','order_id','seller_id','store_id','patient_name',
        'patient_age','doctor_name','doctor_registration_no','prescribed_at',
        'notes','status','review_notes','rejection_reason','reviewed_by',
        'reviewed_at','approved_at','fulfilled_at','cancelled_at',
        'expires_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'patient_age' => 'integer',
            'prescribed_at' => 'date',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function seller(): BelongsTo { return $this->belongsTo(Seller::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function items(): HasMany { return $this->hasMany(PrescriptionItem::class); }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('prescription_files');
    }

    protected static function booted(): void
    {
        static::creating(function (self $prescription): void {
            $prescription->uuid ??= (string) Str::uuid();
        });
    }
}
