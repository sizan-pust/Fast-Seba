<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 100);
            $table->string('landmark')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('mobile', 32);
            $table->string('address_type', 20)->default('home');
            $table->string('country', 100)->default('Bangladesh');
            $table->string('country_code', 10)->default('+880');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'address_type']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wishlist_id')
                ->constrained('wishlists')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['wishlist_id', 'product_variant_id', 'store_id'],
                'wishlist_variant_store_unique'
            );
        });

        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->boolean('save_for_later')->default(false);
            $table->timestamps();

            $table->unique(
                ['cart_id', 'product_variant_id', 'store_id'],
                'cart_variant_store_unique'
            );
            $table->index(['cart_id', 'save_for_later']);
        });

        Schema::create('promos', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->text('description')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->string('discount_type', 20)->default('fixed');
            $table->decimal('discount_amount', 12, 2);
            $table->string('promo_mode', 20)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('individual_use')->default(false);
            $table->unsignedInteger('max_total_usage')->nullable();
            $table->unsignedInteger('max_usage_per_user')->nullable();
            $table->decimal('min_order_total', 12, 2)->default(0);
            $table->decimal('max_discount_value', 12, 2)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['promo_mode', 'scope_id']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('promo_user_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_id')
                ->constrained('promos')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->unique(['promo_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_user_usages');
        Schema::dropIfExists('promos');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('addresses');
    }
};