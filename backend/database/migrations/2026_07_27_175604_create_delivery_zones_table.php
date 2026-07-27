<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();

            $table->decimal('center_latitude', 10, 8);
            $table->decimal('center_longitude', 11, 8);
            $table->double('radius_km');
            $table->integer('rush_delivery_time_per_km')->nullable();
            $table->integer('rush_delivery_charges')->nullable();
            $table->integer('delivery_time_per_km');
            $table->integer('regular_delivery_charges');
            $table->integer('free_delivery_amount')->nullable();
            $table->integer('distance_based_delivery_charges')->nullable();
            $table->integer('per_store_drop_off_fee')->nullable();
            $table->integer('handling_charges')->nullable();
            $table->integer('buffer_time');

            $table->json('boundary_json')->nullable();
            $table->boolean('rush_delivery_enabled')->default(false);

            $table->decimal('delivery_boy_base_fee', 10, 2)->nullable();
            $table->decimal('delivery_boy_per_store_pickup_fee', 10, 2)->nullable();
            $table->decimal('delivery_boy_distance_based_fee', 10, 2)->nullable();
            $table->decimal('delivery_boy_per_order_incentive', 10, 2)->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};