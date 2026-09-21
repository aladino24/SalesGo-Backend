<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('reserved_stock')->default(0)->after('stock'));
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->timestamp('stock_reserved_at')->nullable()->after('approval_status');
            $table->timestamp('stock_released_at')->nullable()->after('stock_reserved_at');
            $table->timestamp('stock_consumed_at')->nullable()->after('stock_released_at');
        });
        Schema::table('journeys', function (Blueprint $table) {
            $table->timestamp('actual_started_at')->nullable()->after('ends_at');
            $table->timestamp('actual_completed_at')->nullable()->after('actual_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('journeys', fn (Blueprint $table) => $table->dropColumn(['actual_started_at', 'actual_completed_at']));
        Schema::table('delivery_notes', fn (Blueprint $table) => $table->dropColumn(['stock_reserved_at', 'stock_released_at', 'stock_consumed_at']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('reserved_stock'));
    }
};
