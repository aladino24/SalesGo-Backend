<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->date('period');
            $table->decimal('revenue_target', 18, 2);
            $table->timestamps();
            $table->unique(['branch_id', 'outlet_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_targets');
    }
};
