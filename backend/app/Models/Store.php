<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Store extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'address',
        'city',
        'landmark',
        'state',
        'zipcode',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'contact_email',
        'contact_number',
        'description',
        'timing',
        'tax_name',
        'tax_number',
        'bank_name',
        'bank_branch_code',
        'account_holder_name',
        'account_number',
        'routing_number',
        'bank_account_type',
        'currency_code',
        'status',
        'max_delivery_distance',
        'order_preparation_time',
        'promotional_text',
        'about_us',
        'return_replacement_policy',
        'refund_policy',
        'terms_and_conditions',
        'delivery_policy',
        'domestic_shipping_charges',
        'international_shipping_charges',
        'metadata',
        'verification_status',
        'visibility_status',
        'is_recommended',
        'fulfillment_type',
        'pos_upi_vpa',
        'pos_upi_payee_name',
        'pos_payment_config',
        'receipt_template',
        'allows_pickup',
        'pickup_instructions',
    ];

    protected $hidden = [
        'bank_name',
        'bank_branch_code',
        'account_holder_name',
        'account_number',
        'routing_number',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'timing' => 'array',
            'bank_name' => 'encrypted',
            'bank_branch_code' => 'encrypted',
            'account_holder_name' => 'encrypted',
            'account_number' => 'encrypted',
            'routing_number' => 'encrypted',
            'max_delivery_distance' => 'decimal:2',
            'domestic_shipping_charges' => 'decimal:2',
            'international_shipping_charges' => 'decimal:2',
            'metadata' => 'array',
            'is_recommended' => 'boolean',
            'pos_payment_config' => 'array',
            'receipt_template' => 'array',
            'allows_pickup' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(DeliveryZone::class, 'store_zone', 'store_id', 'zone_id')
            ->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('store_logo')->singleFile();
        $this->addMediaCollection('store_banner')->singleFile();
        $this->addMediaCollection('address_proof')->singleFile();
        $this->addMediaCollection('voided_check')->singleFile();
    }

    protected static function booted(): void
    {
        static::saving(function (self $store): void {
            if (! $store->slug || $store->isDirty('name')) {
                $base = Str::slug($store->name) ?: 'store';
                $slug = $base;
                $counter = 2;

                while (
                    self::query()
                        ->where('slug', $slug)
                        ->when($store->exists, fn ($query) => $query->where('id', '!=', $store->id))
                        ->exists()
                ) {
                    $slug = "{$base}-{$counter}";
                    $counter++;
                }

                $store->slug = $slug;
            }
        });
    }
}