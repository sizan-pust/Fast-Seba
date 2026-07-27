<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('customer');
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('blocked_balance', 15, 2)->default(0);
            $table->string('currency_code', 3)->default('BDT');
            $table->timestamps();

            $table->unique(['user_id', 'type'], 'wallets_user_id_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};