<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class DeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'center_latitude',
        'center_longitude',
        'radius_km',
        'rush_delivery_time_per_km',
        'rush_delivery_charges',
        'delivery_time_per_km',
        'regular_delivery_charges',
        'free_delivery_amount',
        'distance_based_delivery_charges',
        'per_store_drop_off_fee',
        'handling_charges',
        'buffer_time',
        'boundary_json',
        'rush_delivery_enabled',
        'delivery_boy_base_fee',
        'delivery_boy_per_store_pickup_fee',
        'delivery_boy_distance_based_fee',
        'delivery_boy_per_order_incentive',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'boundary_json' => 'array',
            'rush_delivery_enabled' => 'boolean',
            'center_latitude' => 'decimal:8',
            'center_longitude' => 'decimal:8',
            'delivery_boy_base_fee' => 'decimal:2',
            'delivery_boy_per_store_pickup_fee' => 'decimal:2',
            'delivery_boy_distance_based_fee' => 'decimal:2',
            'delivery_boy_per_order_incentive' => 'decimal:2',
        ];
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(
            Store::class,
            'store_zone',
            'zone_id',
            'store_id'
        )->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_zone',
            'zone_id',
            'user_id'
        )->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (self $zone): void {
            if (! $zone->slug || $zone->isDirty('name')) {
                $base = Str::slug($zone->name) ?: 'zone';
                $slug = $base;
                $counter = 2;

                while (
                    self::query()
                        ->where('slug', $slug)
                        ->when(
                            $zone->exists,
                            fn ($query) => $query->where('id', '!=', $zone->id)
                        )
                        ->exists()
                ) {
                    $slug = "{$base}-{$counter}";
                    $counter++;
                }

                $zone->slug = $slug;
            }
        });
    }
}