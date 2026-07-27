<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Seller extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'business_name',
        'legal_name',
        'trade_license_number',
        'tax_number',
        'address',
        'city',
        'landmark',
        'state',
        'zipcode',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'commission_rate',
        'verification_status',
        'visibility_status',
        'status',
        'metadata',
        'post_accept_cancel_count',
        'verified_at',
        'verified_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'commission_rate' => 'decimal:2',
            'metadata' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'seller_user')
            ->withPivot(['position', 'status'])
            ->withTimestamps();
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'user_id', 'user_id')
            ->where('type', Wallet::TYPE_SELLER);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('business_license')->singleFile();
        $this->addMediaCollection('articles_of_incorporation')->singleFile();
        $this->addMediaCollection('national_identity_card')->singleFile();
        $this->addMediaCollection('authorized_signature')->singleFile();
    }
}