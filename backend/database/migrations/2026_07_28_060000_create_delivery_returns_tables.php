<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_boys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->string('status', 30)->default('available')->index();
            $table->string('verification_status', 30)->default('pending')->index();
            $table->boolean('is_blocked')->default(false)->index();
            $table->string('blocked_reason')->nullable();
            $table->string('vehicle_type', 40)->nullable();
            $table->string('vehicle_number', 80)->nullable();
            $table->string('license_number', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_boy_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_boy_id')->unique()->constrained('delivery_boys')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('heading', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('delivery_boy_id')
                ->nullable()
                ->after('delivery_zone_id')
                ->constrained('delivery_boys')
                ->nullOnDelete();
            $table->timestamp('delivery_started_at')->nullable()->after('estimated_delivery_time');
            $table->timestamp('delivered_at')->nullable()->after('delivery_started_at');
        });

        Schema::create('delivery_boy_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('delivery_boy_id')->constrained('delivery_boys')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('accepted')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->decimal('base_fee', 12, 2)->default(0);
            $table->decimal('distance_fee', 12, 2)->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('cash_collected', 12, 2)->default(0);
            $table->string('payment_status', 30)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['delivery_boy_id', 'status']);
        });

        Schema::create('order_item_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('delivery_boy_id')->nullable()->constrained('delivery_boys')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('reason');
            $table->text('details')->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->string('refund_method', 30)->default('wallet');
            $table->text('seller_comment')->nullable();
            $table->text('admin_comment')->nullable();
            $table->string('pickup_status', 30)->default('pending')->index();
            $table->string('return_status', 30)->default('requested')->index();
            $table->timestamp('requested_at');
            $table->timestamp('seller_decided_at')->nullable();
            $table->timestamp('pickup_assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refund_processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'return_status']);
            $table->index(['delivery_boy_id', 'pickup_status']);
        });

        Schema::create('refund_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_item_return_id')->constrained('order_item_returns')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('transaction_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('BDT');
            $table->string('method', 30)->default('wallet');
            $table->string('status', 30)->default('completed')->index();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_transactions');
        Schema::dropIfExists('order_item_returns');
        Schema::dropIfExists('delivery_boy_assignments');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivery_boy_id');
            $table->dropColumn(['delivery_started_at', 'delivered_at']);
        });

        Schema::dropIfExists('delivery_boy_locations');
        Schema::dropIfExists('delivery_boys');
    }
};
