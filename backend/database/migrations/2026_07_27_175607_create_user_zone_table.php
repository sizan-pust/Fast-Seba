<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('delivery_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->unique(['user_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_zone');
    }
};