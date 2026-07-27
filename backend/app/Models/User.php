<?php

namespace App\Models;

use App\Enums\GuardNameEnum;
use App\Enums\UserLoginTypeEnum;
use App\Enums\WalletTypeEnum;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use InteractsWithMedia;
    use MustVerifyEmailTrait;
    use Notifiable;
    use SoftDeletes;

    protected $appends = ['profile_image'];

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'country_code',
        'referral_code',
        'friends_code',
        'referral_prompt_dismissed_at',
        'reward_points',
        'remember_token',
        'status',
        'password',
        'access_panel',
        'iso_2',
        'country',
        'firebase_uid',
        'email_verified_at',
        'mobile_verified_at',
        'logged_in_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'reward_points' => 'decimal:2',
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'referral_prompt_dismissed_at' => 'datetime',
            'access_panel' => GuardNameEnum::class,
            'logged_in_type' => UserLoginTypeEnum::class,
        ];
    }

    public function getDefaultGuardName(): string
    {
        return GuardNameEnum::fromString(
            $this->access_panel instanceof GuardNameEnum
                ? $this->access_panel->value
                : (string) $this->access_panel
        )->value;
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class)
            ->where('type', WalletTypeEnum::CUSTOMER->value);
    }

    public function sellerWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)
            ->where('type', WalletTypeEnum::SELLER->value);
    }

    public function deliveryBoyWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)
            ->where('type', WalletTypeEnum::DELIVERY_BOY->value);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class);
    }

    public function routeNotificationForFirebase(): array
    {
        return $this->fcmTokens()
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->all();
    }

    public function ownedSeller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    public function sellers(): BelongsToMany
    {
        return $this->belongsToMany(Seller::class, 'seller_user')
            ->withPivot(['position', 'status'])
            ->withTimestamps();
    }

    public function deliveryZones(): BelongsToMany
    {
        return $this->belongsToMany(
            DeliveryZone::class,
            'user_zone',
            'user_id',
            'zone_id'
        )->withTimestamps();
    }

    public function getProfileImageAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('profile_image');

        return $url !== '' ? $url : null;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image')->singleFile();
    }

    public function isFullyVerified(): bool
    {
        return $this->email_verified_at !== null
            && $this->mobile_verified_at !== null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    protected static function booted(): void
    {
        static::deleting(function (self $user): void {
            $user->clearMediaCollection('profile_image');
        });
    }
}