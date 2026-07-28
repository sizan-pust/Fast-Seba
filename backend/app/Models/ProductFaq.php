<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFaq extends Model
{
    protected $fillable = [
        'product_id','asked_by','answered_by','question','answer',
        'status','answered_at',
    ];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime'];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function asker(): BelongsTo { return $this->belongsTo(User::class, 'asked_by'); }
    public function answerer(): BelongsTo { return $this->belongsTo(User::class, 'answered_by'); }
}
