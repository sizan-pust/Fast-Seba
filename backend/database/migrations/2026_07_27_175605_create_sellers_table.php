<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('business_name');
            $table->string('legal_name')->nullable();
            $table->string('trade_license_number')->nullable()->index();
            $table->string('tax_number')->nullable();

            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('landmark', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zipcode', 20)->nullable();
            $table->string('country', 100)->default('Bangladesh');
            $table->string('country_code', 10)->default('+880');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->string('verification_status', 20)->default('pending')->index();
            $table->string('visibility_status', 20)->default('draft')->index();
            $table->string('status', 20)->default('active')->index();

            $table->json('metadata')->nullable();
            $table->unsignedInteger('post_accept_cancel_count')->default(0);

            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['verification_status', 'visibility_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};