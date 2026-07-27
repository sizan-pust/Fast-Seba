<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserOtp extends Model
{
    protected $fillable = [
        'mobile',
        'otp',
        'expires_at',
        'verified_at',
        'attempts',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('expires_at', '>', now())
            ->whereNull('verified_at');
    }

    public function scopeForMobile(
        Builder $query,
        string $mobile
    ): Builder {
        return $query->where('mobile', $mobile);
    }

    public function matches(string $otp): bool
    {
        return Hash::check($otp, $this->otp);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function maxAttemptsReached(): bool
    {
        return $this->attempts >= config(
            'fastsheba.otp.max_attempts',
            3
        );
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        $this->refresh();
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }
}