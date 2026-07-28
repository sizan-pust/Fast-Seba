<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxRate extends Model
{
    protected $fillable = [
        'name','rate','country_code','state','postcode','priority',
        'compound','status',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'priority' => 'integer',
            'compound' => 'boolean',
        ];
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(
            TaxClass::class,
            'tax_class_tax_rate'
        )->withTimestamps();
    }
}
