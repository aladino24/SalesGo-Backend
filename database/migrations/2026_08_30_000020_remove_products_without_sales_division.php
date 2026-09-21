<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master produk tanpa divisi tidak dapat dijual oleh sales mana pun.
        // Putuskan relasi promosi terlebih dahulu agar foreign key tidak
        // menghalangi pembersihan katalog lama.
        $orphanProductIds = DB::table('products')
            ->whereNull('sales_division_id')
            ->pluck('id');

        if ($orphanProductIds->isNotEmpty()) {
            DB::table('promotions')
                ->whereIn('product_id', $orphanProductIds)
                ->update(['product_id' => null]);

            DB::table('products')
                ->whereIn('id', $orphanProductIds)
                ->delete();
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('sales_division_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('sales_division_id')->nullable()->change();
        });
    }
};
