<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RefundTransaction extends Model
{
    protected $fillable = [
        'uuid','order_item_return_id','order_id','user_id',
        'wallet_transaction_id','transaction_id','amount',
        'currency','method','status','reason','metadata',
    ];

    protected function casts(): array
    {
        return ['amount'=>'decimal:2','metadata'=>'array'];
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderItemReturn::class, 'order_item_return_id');
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function walletTransaction(): BelongsTo { return $this->belongsTo(WalletTransaction::class); }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->uuid ??= (string) Str::uuid();
            $transaction->transaction_id ??=
                'RF-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5));
        });
    }
}
