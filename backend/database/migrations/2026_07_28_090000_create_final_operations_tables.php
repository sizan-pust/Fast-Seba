<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 8, 4)->default(0);
            $table->string('country_code', 10)->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('compound')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('tax_class_tax_rate', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tax_class_id')->constrained('tax_classes')->cascadeOnDelete();
            $table->foreignId('tax_rate_id')->constrained('tax_rates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['tax_class_id', 'tax_rate_id'],
                'tax_class_rate_unique'
            );
        });

        Schema::create('collections', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('collection_product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['collection_id', 'product_id'],
                'collection_product_unique'
            );
        });

        Schema::create('following_sellers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_id', 'seller_id'],
                'following_seller_unique'
            );
        });

        Schema::create('addon_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('selection_type', 20)->default('multiple');
            $table->unsignedInteger('minimum_selection')->default(0);
            $table->unsignedInteger('maximum_selection')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'slug'], 'addon_group_seller_slug_unique');
        });

        Schema::create('addon_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('addon_group_id')->constrained('addon_groups')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->decimal('default_price', 12, 2)->default(0);
            $table->decimal('default_cost', 12, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['addon_group_id', 'slug'],
                'addon_item_group_slug_unique'
            );
        });

        Schema::create('store_product_variant_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('addon_group_id')->constrained('addon_groups')->cascadeOnDelete();
            $table->foreignId('addon_item_id')->constrained('addon_items')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['store_id', 'product_variant_id', 'addon_item_id'],
                'store_variant_addon_item_unique'
            );
        });

        Schema::create('store_addon_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('addon_item_id')->constrained('addon_items')->cascadeOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('is_available')->default(true)->index();
            $table->timestamps();

            $table->unique(
                ['store_id', 'addon_item_id'],
                'store_addon_item_unique'
            );
        });

        Schema::create('order_item_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('addon_group_id')->nullable()->constrained('addon_groups')->nullOnDelete();
            $table->foreignId('addon_item_id')->nullable()->constrained('addon_items')->nullOnDelete();
            $table->string('group_title')->nullable();
            $table->string('item_title');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_item_id', 'addon_item_id'], 'order_addon_lookup_idx');
        });

        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('duration_days')->default(30);
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plan_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('feature_key', 80);
            $table->unsignedBigInteger('limit_value')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['subscription_plan_id', 'feature_key'],
                'subscription_plan_feature_unique'
            );
        });

        Schema::create('seller_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('payment_method', 40)->nullable();
            $table->json('snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['seller_id', 'status', 'ends_at'],
                'seller_subscription_status_end_idx'
            );
        });

        Schema::create('seller_subscription_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seller_subscription_id')->constrained('seller_subscriptions')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->string('feature_key', 80);
            $table->unsignedBigInteger('used_count')->default(0);
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['seller_subscription_id', 'feature_key'],
                'seller_subscription_usage_unique'
            );
        });

        Schema::create('subscription_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('seller_subscription_id')->nullable()->constrained('seller_subscriptions')->nullOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('transaction_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('BDT');
            $table->string('payment_method', 40);
            $table->string('status', 20)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('title');
            $table->string('ad_type', 20)->default('cpc');
            $table->string('placement', 40)->default('home_feed')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('budget', 12, 2);
            $table->decimal('spent_amount', 12, 2)->default(0);
            $table->decimal('bid_amount', 12, 4)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['seller_id', 'status', 'created_at'],
                'ad_campaign_seller_status_idx'
            );
        });

        Schema::create('ad_campaign_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('spent_amount', 12, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['ad_campaign_id', 'stat_date'],
                'ad_campaign_stat_date_unique'
            );
        });

        Schema::create('ad_event_dedup', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->foreignId('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 20);
            $table->string('session_hash', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['ad_campaign_id', 'event_type', 'created_at'],
                'ad_event_campaign_type_idx'
            );
        });

        Schema::create('bulk_upload_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('type', 40)->index();
            $table->string('operation', 20)->default('import');
            $table->string('status', 20)->default('pending')->index();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->unsignedBigInteger('total_rows')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('successful_rows')->default(0);
            $table->unsignedBigInteger('failed_rows')->default(0);
            $table->string('failed_rows_path')->nullable();
            $table->boolean('notify_on_finish')->default(true);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'created_at'],
                'bulk_upload_status_created_idx'
            );
        });

        Schema::create('pos_parked_sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parked_by')->constrained('users')->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->json('cart_payload');
            $table->json('totals_payload')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(
                ['seller_id', 'store_id', 'created_at'],
                'parked_sale_seller_store_idx'
            );
        });

        Schema::create('pos_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30)->default('cash');
            $table->string('status', 20)->default('completed')->index();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_refund_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pos_refund_id')->constrained('pos_refunds')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_customer_displays', function (Blueprint $table): void {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->json('state_payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_boy_cash_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_boy_id')->constrained('delivery_boys')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('delivery_boy_assignment_id')->nullable();
            $table->foreign(
                'delivery_boy_assignment_id',
                'rider_cash_assignment_fk'
            )
                ->references('id')
                ->on('delivery_boy_assignments')
                ->nullOnDelete();
            $table->string('type', 30)->index();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index();
            $table->string('reference')->nullable()->index();
            $table->text('note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['delivery_boy_id', 'status', 'created_at'],
                'rider_cash_status_created_idx'
            );
        });

        Schema::create('seller_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->text('seller_reply')->nullable();
            $table->timestamp('seller_replied_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'order_id', 'seller_id'],
                'seller_feedback_order_unique'
            );
        });

        Schema::create('delivery_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delivery_boy_id')->constrained('delivery_boys')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('published')->index();
            $table->timestamps();

            $table->unique(
                ['user_id', 'order_id', 'delivery_boy_id'],
                'delivery_feedback_order_unique'
            );
        });

        Schema::create('command_run_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('command', 120)->index();
            $table->string('status', 20)->default('running')->index();
            $table->string('triggered_by', 20)->default('manual');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->longText('output')->nullable();
            $table->longText('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['command', 'started_at'],
                'command_run_command_started_idx'
            );
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 80);
            $table->string('idempotency_key', 120);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['scope', 'idempotency_key'],
                'idempotency_scope_key_unique'
            );
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40)->index();
            $table->string('event_id', 160);
            $table->string('event_type', 100)->nullable();
            $table->string('status', 20)->default('received')->index();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'event_id'],
                'webhook_provider_event_unique'
            );
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('purpose', 40);
            $table->string('provider', 40)->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('BDT');
            $table->string('status', 20)->default('pending')->index();
            $table->string('external_id')->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('system_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('version')->unique();
            $table->string('status', 20)->default('planned')->index();
            $table->string('checksum')->nullable();
            $table->text('release_notes')->nullable();
            $table->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deployed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasColumn('orders', 'source')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('source', 20)
                    ->default('app')
                    ->after('delivery_type')
                    ->index();
            });
        }

        if (! Schema::hasColumn('orders', 'pos_operator_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('pos_operator_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'pos_reference')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('pos_reference')
                    ->nullable()
                    ->after('source')
                    ->index();
            });
        }

        if (! Schema::hasColumn('orders', 'cash_received')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('cash_received', 12, 2)
                    ->default(0)
                    ->after('final_total');
            });
        }

        if (! Schema::hasColumn('orders', 'change_returned')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->decimal('change_returned', 12, 2)
                    ->default(0)
                    ->after('cash_received');
            });
        }

        if (! Schema::hasColumn('orders', 'invoice_number')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('invoice_number')
                    ->nullable()
                    ->after('slug')
                    ->unique();
            });
        }

        if (! Schema::hasColumn('stores', 'pos_enabled')) {
            Schema::table('stores', function (Blueprint $table): void {
                $table->boolean('pos_enabled')
                    ->default(true)
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('stores', 'pos_enabled')) {
            Schema::table('stores', function (Blueprint $table): void {
                $table->dropColumn('pos_enabled');
            });
        }

        if (Schema::hasColumn('orders', 'pos_operator_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pos_operator_id');
            });
        }

        foreach ([
            'source',
            'pos_reference',
            'cash_received',
            'change_returned',
            'invoice_number',
        ] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('system_releases');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('command_run_logs');
        Schema::dropIfExists('delivery_feedback');
        Schema::dropIfExists('seller_feedback');
        Schema::dropIfExists('delivery_boy_cash_transactions');
        Schema::dropIfExists('pos_customer_displays');
        Schema::dropIfExists('pos_refund_lines');
        Schema::dropIfExists('pos_refunds');
        Schema::dropIfExists('pos_parked_sales');
        Schema::dropIfExists('bulk_upload_jobs');
        Schema::dropIfExists('ad_event_dedup');
        Schema::dropIfExists('ad_campaign_stats');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('subscription_transactions');
        Schema::dropIfExists('seller_subscription_usages');
        Schema::dropIfExists('seller_subscriptions');
        Schema::dropIfExists('subscription_plan_limits');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('order_item_addons');
        Schema::dropIfExists('store_addon_items');
        Schema::dropIfExists('store_product_variant_addons');
        Schema::dropIfExists('addon_items');
        Schema::dropIfExists('addon_groups');
        Schema::dropIfExists('following_sellers');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('tax_class_tax_rate');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_classes');
    }
};
