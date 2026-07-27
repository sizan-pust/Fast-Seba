<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('requires_approval')->default(false);
            $table->decimal('commission', 5, 2)->default(0);
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_home_category')->default(false)->index();
            $table->string('background_type', 20)->nullable();
            $table->string('background_color', 20)->nullable();
            $table->string('font_color', 20)->nullable();
            $table->json('search_labels')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['parent_id', 'status']);
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope_type', 20)->default('global')->index();
            $table->foreignId('scope_id')
                ->nullable()
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
        });

        Schema::create('product_conditions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->string('alignment', 30)->default('strip');
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('label');
            $table->string('slug', 500)->unique();
            $table->string('bg_color', 20)->default('#198754');
            $table->string('text_color', 20)->default('#ffffff');
            $table->string('border_color', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('global_product_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('sellers')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->string('label');
            $table->string('swatche_type', 20)->default('text');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('global_product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('global_attribute_id')
                ->constrained('global_product_attributes')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('swatche_value')->nullable();
            $table->timestamps();

            $table->unique(
                ['global_attribute_id', 'title'],
                'global_attribute_value_title_unique'
            );
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')
                ->constrained('sellers')
                ->cascadeOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();
            $table->foreignId('product_condition_id')
                ->nullable()
                ->constrained('product_conditions')
                ->nullOnDelete();
            $table->foreignId('badge_id')
                ->nullable()
                ->constrained('badges')
                ->nullOnDelete();
            $table->foreignId('cloned_from_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->string('provider')->nullable();
            $table->unsignedBigInteger('provider_product_id')->nullable();
            $table->string('slug', 500)->unique();
            $table->string('title');
            $table->unsignedBigInteger('product_identity')
                ->nullable()
                ->unique();
            $table->string('type', 20)->default('simple')->index();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('indicator', 20)->nullable();

            $table->boolean('download_allowed')->default(false);
            $table->text('download_link')->nullable();

            $table->unsignedInteger('minimum_order_quantity')->default(1);
            $table->unsignedInteger('quantity_step_size')->default(1);
            $table->unsignedInteger('total_allowed_quantity')->default(100);

            $table->boolean('is_inclusive_tax')->default(false);
            $table->string('hsn_code')->nullable();
            $table->boolean('is_returnable')->default(false);
            $table->unsignedInteger('returnable_days')->nullable();
            $table->boolean('is_cancelable')->default(true);
            $table->string('cancelable_till', 50)->nullable();

            $table->boolean('is_attachment_required')->default(false);
            $table->string('attachment_mode', 20)->default('required');
            $table->boolean('requires_otp')->default(false);
            $table->unsignedInteger('base_prep_time')->default(0);

            $table->string('status', 20)->default('active')->index();
            $table->string('verification_status', 30)
                ->default('approved')
                ->index();
            $table->text('rejection_reason')->nullable();
            $table->boolean('featured')->default(false)->index();

            $table->string('video_type', 30)->nullable();
            $table->text('video_link')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            $table->string('warranty_period')->nullable();
            $table->string('guarantee_period')->nullable();
            $table->string('made_in')->nullable();
            $table->json('metadata')->nullable();
            $table->string('image_fit', 20)->default('contain');

            $table->softDeletes();
            $table->timestamps();

            $table->index([
                'category_id',
                'brand_id',
                'status',
                'verification_status',
            ], 'products_public_filter_index');
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('title');
            $table->string('slug', 500)->unique();
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('height', 10, 3)->nullable();
            $table->decimal('breadth', 10, 3)->nullable();
            $table->decimal('length', 10, 3)->nullable();
            $table->boolean('availability')->default(true)->index();
            $table->string('provider')->default('self');
            $table->string('provider_product_id')->nullable();
            $table->json('provider_json')->nullable();
            $table->string('barcode', 100)->nullable()->index();
            $table->string('visibility', 20)->default('published')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index([
                'product_id',
                'availability',
                'visibility',
            ], 'product_variants_public_index');
        });

        Schema::create('product_variant_attributes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('global_attribute_id')
                ->constrained('global_product_attributes')
                ->cascadeOnDelete();
            $table->foreignId('global_attribute_value_id')
                ->constrained('global_product_attribute_values')
                ->cascadeOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['product_variant_id', 'global_attribute_id'],
                'variant_attribute_unique'
            );
        });

        Schema::create('store_product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->string('sku', 100);
            $table->decimal('price', 12, 2);
            $table->decimal('special_price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->string('status', 20)->default('active')->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['store_id', 'product_variant_id'],
                'store_product_variant_unique'
            );
            $table->unique(['store_id', 'sku']);
            $table->index(['store_id', 'status', 'stock']);
        });

        Schema::create('store_inventory_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->foreignId('store_product_variant_id')
                ->constrained('store_product_variants')
                ->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->string('change_type', 20);
            $table->integer('quantity');
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index([
                'store_product_variant_id',
                'created_at',
            ], 'inventory_variant_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_inventory_logs');
        Schema::dropIfExists('store_product_variants');
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('global_product_attribute_values');
        Schema::dropIfExists('global_product_attributes');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('product_conditions');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};