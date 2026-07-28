<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'title','slug','description','price','duration_days','trial_days',
        'is_featured','status','sort_order','metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'trial_days' => 'integer',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function limits(): HasMany
    {
        return $this->hasMany(SubscriptionPlanLimit::class);
    }
}
