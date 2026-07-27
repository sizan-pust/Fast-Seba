<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug', 300)->unique();

            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('landmark', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('country', 100)->default('Bangladesh');
            $table->string('country_code', 10)->default('+880');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->text('description')->nullable();
            $table->json('timing')->nullable();

            $table->string('tax_name')->nullable();
            $table->string('tax_number')->nullable();

            $table->text('bank_name')->nullable();
            $table->text('bank_branch_code')->nullable();
            $table->text('account_holder_name')->nullable();
            $table->text('account_number')->nullable();
            $table->text('routing_number')->nullable();
            $table->string('bank_account_type', 20)->nullable();

            $table->char('currency_code', 3)->default('BDT');
            $table->string('status', 20)->default('online')->index();
            $table->decimal('max_delivery_distance', 8, 2)->default(10);
            $table->unsignedInteger('order_preparation_time')->default(15);

            $table->string('promotional_text', 1024)->nullable();
            $table->text('about_us')->nullable();
            $table->text('return_replacement_policy')->nullable();
            $table->text('refund_policy')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('delivery_policy')->nullable();

            $table->decimal('domestic_shipping_charges', 12, 2)->nullable();
            $table->decimal('international_shipping_charges', 12, 2)->nullable();

            $table->json('metadata')->nullable();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->string('visibility_status', 20)->default('draft')->index();
            $table->boolean('is_recommended')->default(false)->index();
            $table->string('fulfillment_type', 20)->default('hyperlocal');

            $table->string('pos_upi_vpa')->nullable();
            $table->string('pos_upi_payee_name')->nullable();
            $table->json('pos_payment_config')->nullable();
            $table->json('receipt_template')->nullable();

            $table->boolean('allows_pickup')->default(false);
            $table->string('pickup_instructions', 500)->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['seller_id', 'status']);
            $table->index(['verification_status', 'visibility_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};