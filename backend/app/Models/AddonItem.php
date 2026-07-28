<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AddonItem extends Model
{
    protected $fillable = [
        'addon_group_id','title','slug','default_price','default_cost',
        'status','sort_order','metadata',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'default_cost' => 'decimal:2',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AddonGroup::class, 'addon_group_id');
    }
}
