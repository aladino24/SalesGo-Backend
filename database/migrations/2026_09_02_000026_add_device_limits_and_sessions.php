<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_devices')->default(1)->after('is_active');
        });

        Schema::create('user_device_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // UUID instalasi aplikasi, bukan IMEI/identifier perangkat fisik.
            $table->uuid('device_id');
            $table->string('platform', 20);
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->unsignedBigInteger('refresh_token_id')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id']);
            $table->index(['user_id', 'revoked_at', 'expires_at']);
            $table->index('access_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_sessions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('max_devices');
        });
    }
};
