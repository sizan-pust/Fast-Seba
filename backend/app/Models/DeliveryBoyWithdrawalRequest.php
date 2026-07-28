<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryBoyWithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id','delivery_boy_id','amount','status','request_note',
        'admin_remark','processed_at','processed_by','wallet_transaction_id',
        'external_transaction_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2','processed_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function deliveryBoy(): BelongsTo { return $this->belongsTo(DeliveryBoy::class); }
    public function processedBy(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
    public function walletTransaction(): BelongsTo { return $this->belongsTo(WalletTransaction::class); }
}
