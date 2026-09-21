<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branch_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->timestamps();
            $table->unique(['user_id', 'branch_id']);
        });
        Schema::create('daily_branch_sales_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->date('date');
            $table->decimal('committed_revenue', 18, 2)->default(0);
            $table->unsignedInteger('committed_order_count')->default(0);
            $table->unsignedInteger('active_outlet_count')->default(0);
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_branch_sales_aggregates');
        Schema::dropIfExists('user_branch_access');
    }
};
