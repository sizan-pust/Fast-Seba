<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'start_date',
        'end_date',
        'discount_type',
        'discount_amount',
        'promo_mode',
        'scope_id',
        'usage_count',
        'individual_use',
        'max_total_usage',
        'max_usage_per_user',
        'min_order_total',
        'max_discount_value',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'discount_amount' => 'decimal:2',
            'usage_count' => 'integer',
            'individual_use' => 'boolean',
            'max_total_usage' => 'integer',
            'max_usage_per_user' => 'integer',
            'min_order_total' => 'decimal:2',
            'max_discount_value' => 'decimal:2',
        ];
    }

    public function userUsages(): HasMany
    {
        return $this->hasMany(PromoUserUsage::class);
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return $this->max_total_usage === null
            || $this->usage_count < $this->max_total_usage;
    }
}