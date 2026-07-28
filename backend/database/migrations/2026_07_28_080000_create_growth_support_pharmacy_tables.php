<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Referral columns already exist from the foundation migrations.
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40)->default('custom');
            $table->string('scope_type', 30)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('custom_url')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('position', 40)->default('home_top');
            $table->string('visibility_status', 20)->default('draft')->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id'], 'banner_scope_idx');
            $table->index(['position', 'display_order'], 'banner_position_order_idx');
        });

        Schema::create('banner_zone', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['banner_id', 'zone_id'], 'banner_zone_unique');
        });

        Schema::create('featured_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_type', 30)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->string('style', 40)->default('horizontal');
            $table->string('section_type', 40)->default('manual')->index();
            $table->string('background_type', 20)->nullable();
            $table->string('background_color', 20)->nullable();
            $table->string('text_color', 20)->default('#000000');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('product_limit')->default(12);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id'], 'featured_scope_idx');
            $table->index(['status', 'sort_order'], 'featured_status_sort_idx');
        });

        Schema::create('featured_section_zone', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('featured_section_id')->constrained('featured_sections')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['featured_section_id', 'zone_id'],
                'featured_zone_unique'
            );
        });

        Schema::create('featured_section_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('featured_section_id')->constrained('featured_sections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['featured_section_id', 'product_id'],
                'featured_product_unique'
            );
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment');
            $table->string('status', 20)->default('published')->index();
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_note')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'created_at'], 'review_product_status_idx');
            $table->index(['store_id', 'status', 'created_at'], 'review_store_status_idx');
        });

        Schema::create('faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 80)->default('general')->index();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('product_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('asked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('question');
            $table->text('answer')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status'], 'product_faq_status_idx');
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('role_type', 30)->default('customer')->index();
            $table->string('type', 50)->default('general')->index();
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read', 'created_at'], 'notification_user_read_idx');
        });

        Schema::create('app_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('audience_type', 30)->index();
            $table->string('title');
            $table->text('message');
            $table->string('target_type', 40)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('app_notification_user_map', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('user_type', 30);
            $table->timestamps();

            $table->unique(
                ['notification_id', 'user_id'],
                'app_notification_user_unique'
            );
        });

        Schema::create('app_notification_zone_map', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('app_notifications')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['notification_id', 'zone_id'],
                'app_notification_zone_unique'
            );
        });

        Schema::create('support_ticket_types', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_type_id')->constrained('support_ticket_types')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->string('email');
            $table->text('description');
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('open')->index();
            $table->timestamp('last_replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'support_user_status_idx');
            $table->index(['assigned_to', 'status'], 'support_assignee_status_idx');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 30);
            $table->text('message');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at'], 'support_message_ticket_idx');
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('patient_name');
            $table->unsignedTinyInteger('patient_age')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('doctor_registration_no')->nullable();
            $table->date('prescribed_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'prescription_user_status_idx');
            $table->index(['seller_id', 'status'], 'prescription_seller_status_idx');
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->string('medicine_name');
            $table->string('strength')->nullable();
            $table->string('dosage')->nullable();
            $table->string('duration')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('referral_code', 32);
            $table->string('status', 20)->default('active')->index();
            $table->json('settings')->nullable();
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status'], 'referral_referrer_status_idx');
        });

        Schema::create('referral_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained('users')->cascadeOnDelete();
            $table->string('beneficiary_type', 20);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->decimal('order_amount', 12, 2)->default(0);
            $table->string('bonus_method', 20)->default('fixed');
            $table->decimal('bonus_value', 12, 2);
            $table->decimal('max_cap', 12, 2)->nullable();
            $table->decimal('earned_amount', 12, 2);
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['referral_id', 'beneficiary_type'],
                'referral_beneficiary_unique'
            );
            $table->index(['beneficiary_id', 'status'], 'referral_earning_user_idx');
        });

        Schema::create('gift_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->char('currency_code', 3)->default('BDT');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('gift_card_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(
                ['gift_card_id', 'user_id'],
                'gift_card_user_unique'
            );
        });

        Schema::create('system_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_role', 30)->nullable();
            $table->string('action', 80)->index();
            $table->string('entity_type', 120);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['entity_type', 'entity_id', 'created_at'],
                'audit_entity_created_idx'
            );
            $table->index(['actor_id', 'created_at'], 'audit_actor_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
        Schema::dropIfExists('gift_card_redemptions');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('referral_earnings');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('support_ticket_types');
        Schema::dropIfExists('app_notification_zone_map');
        Schema::dropIfExists('app_notification_user_map');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('product_faqs');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('featured_section_product');
        Schema::dropIfExists('featured_section_zone');
        Schema::dropIfExists('featured_sections');
        Schema::dropIfExists('banner_zone');
        Schema::dropIfExists('banners');

    }
};
