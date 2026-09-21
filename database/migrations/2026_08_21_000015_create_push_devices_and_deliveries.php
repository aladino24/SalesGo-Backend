<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->string('platform', 20);
            // FCM registration tokens can exceed the utf8mb4 index limit.
            // Keep the raw token unindexed and use its fixed SHA-256 hash for uniqueness.
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at');
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'disabled_at']);
        });
        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('push_devices')->cascadeOnDelete();
            $table->string('status')->default('Pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->json('provider_response')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->unique(['notification_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_devices');
    }
};
