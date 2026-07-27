<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();

            $table->string('firebase_uid')->nullable()->unique()->after('id');
            $table->string('mobile', 20)->nullable()->unique()->after('firebase_uid');
            $table->string('country_code', 10)->nullable()->after('mobile');

            $table->string('referral_code', 32)->nullable()->unique()->after('country_code');
            $table->string('friends_code', 32)->nullable()->after('referral_code');
            $table->timestamp('referral_prompt_dismissed_at')->nullable()->after('friends_code');
            $table->decimal('reward_points', 10, 2)->default(0);

            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->enum('access_panel', ['web', 'admin', 'seller'])
                ->default('web')
                ->index()
                ->comment('Defines the access panel for the user: web, admin, or seller');

            $table->string('country')->nullable();
            $table->string('iso_2', 2)->nullable();

            $table->timestamp('mobile_verified_at')->nullable();
            $table->enum('logged_in_type', ['google', 'apple', 'platform'])->nullable();

            $table->softDeletes();

            $table->index(['status', 'access_panel']);
            $table->index('friends_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status', 'access_panel']);
            $table->dropIndex(['friends_code']);

            $table->dropColumn([
                'firebase_uid',
                'mobile',
                'country_code',
                'referral_code',
                'friends_code',
                'referral_prompt_dismissed_at',
                'reward_points',
                'status',
                'access_panel',
                'country',
                'iso_2',
                'mobile_verified_at',
                'logged_in_type',
                'deleted_at',
            ]);

            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};