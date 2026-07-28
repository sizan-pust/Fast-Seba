<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('seller_order_id')->nullable()->constrained('seller_orders')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('order_item_returns')->nullOnDelete();
            $table->string('entry_type', 40)->index();
            $table->string('direction', 10)->default('credit')->index();
            $table->decimal('amount', 14, 2);
            $table->char('currency_code', 3)->default('BDT');
            $table->string('reference_type', 60);
            $table->unsignedBigInteger('reference_id');
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('posted_at');
            $table->string('settlement_status', 20)->default('unsettled')->index();
            $table->timestamp('settled_at')->nullable();
            $table->string('settlement_reference')->nullable()->index();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['seller_id', 'reference_type', 'reference_id', 'entry_type'],
                'seller_statement_reference_unique'
            );
            $table->index(['seller_id', 'settlement_status', 'posted_at']);
        });

        Schema::create('seller_withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->text('request_note')->nullable();
            $table->text('admin_remark')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('external_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'status', 'created_at'], 'seller_withdrawal_status_created_idx');
        });

        Schema::create('delivery_boy_withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delivery_boy_id')->constrained('delivery_boys')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->text('request_note')->nullable();
            $table->text('admin_remark')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('external_transaction_id')->nullable();
            $table->timestamps();

            $table->index(['delivery_boy_id', 'status', 'created_at'], 'rider_withdrawal_status_created_idx');
        });

        Schema::create('payment_gateway_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('display_name');
            $table->boolean('enabled')->default(false)->index();
            $table->boolean('test_mode')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('public_config')->nullable();
            $table->text('secret_config')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_configs');
        Schema::dropIfExists('delivery_boy_withdrawal_requests');
        Schema::dropIfExists('seller_withdrawal_requests');
        Schema::dropIfExists('seller_statements');
    }
};
