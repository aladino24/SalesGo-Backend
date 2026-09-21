<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_division_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_division_id')->constrained('sales_divisions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'sales_division_id']);
        });
        Schema::create('outlet_sales_division', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_division_id')->constrained('sales_divisions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['outlet_id', 'sales_division_id']);
        });

        $now = now();
        DB::table('users')->select(['id', 'sales_division_id'])->orderBy('id')->eachById(function ($user) use ($now) {
            DB::table('sales_division_user')->insertOrIgnore([
                'user_id' => $user->id,
                'sales_division_id' => $user->sales_division_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_sales_division');
        Schema::dropIfExists('sales_division_user');
    }
};
