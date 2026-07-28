<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id','referred_id','referral_code','status','settings',
        'rewarded_at','completed_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'rewarded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo { return $this->belongsTo(User::class, 'referrer_id'); }
    public function referred(): BelongsTo { return $this->belongsTo(User::class, 'referred_id'); }
    public function earnings(): HasMany { return $this->hasMany(ReferralEarning::class); }
}
