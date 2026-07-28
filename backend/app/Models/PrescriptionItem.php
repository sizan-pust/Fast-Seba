<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id','medicine_name','strength','dosage',
        'duration','quantity','instructions',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
