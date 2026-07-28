<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TaxClass extends Model
{
    protected $fillable = [
        'name','slug','description','is_default','status',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function rates(): BelongsToMany
    {
        return $this->belongsToMany(
            TaxRate::class,
            'tax_class_tax_rate'
        )->withTimestamps();
    }
}
