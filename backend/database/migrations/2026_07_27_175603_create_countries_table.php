<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('iso3', 3)->unique();
            $table->char('iso2', 2)->unique();
            $table->char('numeric_code', 3)->nullable()->unique();
            $table->string('phonecode', 10);
            $table->string('capital')->nullable();
            $table->char('currency', 3);
            $table->string('currency_name')->nullable();
            $table->string('currency_symbol', 10)->nullable();
            $table->string('tld', 20)->nullable();
            $table->string('native')->nullable();
            $table->string('region')->nullable();
            $table->string('subregion')->nullable();
            $table->json('timezones')->nullable();
            $table->json('translations')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('emoji', 32)->nullable();
            $table->string('emojiU')->nullable();
            $table->boolean('flag')->default(true);
            $table->string('wikiDataId', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};