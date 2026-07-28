<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid','slug','user_id','address_id','delivery_zone_id','delivery_boy_id',
        'status','payment_method','payment_status','delivery_type','is_rush_order',
        'promo_id','promo_code','promo_discount','wallet_balance','subtotal',
        'delivery_charge','handling_charges','per_store_drop_off_fee',
        'total_payable','final_total','email','billing_name','billing_phone',
        'shipping_name','shipping_address_1','shipping_address_2',
        'shipping_landmark','shipping_city','shipping_state','shipping_zip',
        'shipping_country','shipping_phone','order_note',
        'estimated_delivery_time','delivery_started_at','delivered_at',
        'paid_at','cancelled_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_rush_order'=>'boolean','promo_discount'=>'decimal:2',
            'wallet_balance'=>'decimal:2','subtotal'=>'decimal:2',
            'delivery_charge'=>'decimal:2','handling_charges'=>'decimal:2',
            'per_store_drop_off_fee'=>'decimal:2','total_payable'=>'decimal:2',
            'final_total'=>'decimal:2','estimated_delivery_time'=>'integer',
            'delivery_started_at'=>'datetime','delivered_at'=>'datetime',
            'paid_at'=>'datetime','cancelled_at'=>'datetime','metadata'=>'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function address(): BelongsTo { return $this->belongsTo(Address::class); }
    public function deliveryZone(): BelongsTo { return $this->belongsTo(DeliveryZone::class); }
    public function deliveryBoy(): BelongsTo { return $this->belongsTo(DeliveryBoy::class); }
    public function promo(): BelongsTo { return $this->belongsTo(Promo::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function sellerOrders(): HasMany { return $this->hasMany(SellerOrder::class); }
    public function paymentTransactions(): HasMany { return $this->hasMany(OrderPaymentTransaction::class); }
    public function statusLogs(): HasMany { return $this->hasMany(OrderStatusLog::class); }
    public function deliveryAssignments(): HasMany { return $this->hasMany(DeliveryBoyAssignment::class); }
    public function returns(): HasMany { return $this->hasMany(OrderItemReturn::class); }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->uuid ??= (string) Str::uuid();
            $order->slug ??= self::generateSlug();
        });
    }

    private static function generateSlug(): string
    {
        do {
            $slug='FS-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (self::withTrashed()->where('slug',$slug)->exists());

        return $slug;
    }
}
