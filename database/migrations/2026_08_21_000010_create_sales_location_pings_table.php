<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sales_id')->constrained('users');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 10, 2)->nullable();
            $table->string('source')->default('foreground');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['branch_id', 'sales_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_location_pings');
    }
};
