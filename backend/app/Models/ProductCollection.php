<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductCollection extends Model
{
    protected $table = 'collections';

    protected $fillable = [
        'title','slug','description','status','sort_order','metadata',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'collection_product',
            'collection_id',
            'product_id'
        )->withPivot('sort_order')->withTimestamps();
    }
}
