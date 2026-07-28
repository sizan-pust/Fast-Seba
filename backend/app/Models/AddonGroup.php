<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AddonGroup extends Model
{
    protected $fillable = [
        'seller_id','title','slug','selection_type','minimum_selection',
        'maximum_selection','is_required','status','sort_order','metadata',
    ];

    protected function casts(): array
    {
        return [
            'minimum_selection' => 'integer',
            'maximum_selection' => 'integer',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AddonItem::class)->orderBy('sort_order');
    }
}
