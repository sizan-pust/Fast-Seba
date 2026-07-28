<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanLimit extends Model
{
    protected $fillable = [
        'subscription_plan_id','feature_key','limit_value',
        'is_unlimited','metadata',
    ];

    protected function casts(): array
    {
        return [
            'limit_value' => 'integer',
            'is_unlimited' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'subscription_plan_id'
        );
    }
}
