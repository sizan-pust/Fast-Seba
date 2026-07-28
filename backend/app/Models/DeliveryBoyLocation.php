<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryBoyLocation extends Model
{
    protected $fillable = [
        'delivery_boy_id','latitude','longitude','heading',
        'speed','accuracy','recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at'=>'datetime'];
    }

    public function deliveryBoy(): BelongsTo
    {
        return $this->belongsTo(DeliveryBoy::class);
    }
}
