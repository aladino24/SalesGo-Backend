<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['Percentage', 'Fixed']);
            $table->decimal('value', 18, 2);
            $table->decimal('maximum_discount', 18, 2)->nullable();
            $table->decimal('minimum_order_amount', 18, 2)->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
