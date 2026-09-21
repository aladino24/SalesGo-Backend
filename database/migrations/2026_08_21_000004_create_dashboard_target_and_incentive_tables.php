<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sales_id')->nullable()->constrained('users');
            $table->date('period');
            $table->decimal('revenue_target', 18, 2);
            $table->timestamps();
            $table->unique(['branch_id', 'sales_id', 'period']);
        });

        Schema::create('incentive_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('sales_id')->nullable()->constrained('users');
            $table->string('name');
            $table->decimal('minimum_achievement_percent', 8, 2);
            $table->decimal('incentive_rate_percent', 8, 2)->default(0);
            $table->decimal('fixed_amount', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'sales_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incentive_rules');
        Schema::dropIfExists('sales_targets');
    }
};
