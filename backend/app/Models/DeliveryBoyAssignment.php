<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryBoyAssignment extends Model
{
    protected $fillable = [
        'order_id','delivery_boy_id','assigned_by','status',
        'assigned_at','accepted_at','picked_up_at','out_for_delivery_at',
        'delivered_at','dropped_at','base_fee','distance_fee',
        'total_earnings','cash_collected','payment_status','failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at'=>'datetime','accepted_at'=>'datetime',
            'picked_up_at'=>'datetime','out_for_delivery_at'=>'datetime',
            'delivered_at'=>'datetime','dropped_at'=>'datetime',
            'base_fee'=>'decimal:2','distance_fee'=>'decimal:2',
            'total_earnings'=>'decimal:2','cash_collected'=>'decimal:2',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function deliveryBoy(): BelongsTo { return $this->belongsTo(DeliveryBoy::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
