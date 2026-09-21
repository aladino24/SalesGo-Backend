<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('sales_division_id')->nullable()->after('branch_id')->constrained('sales_divisions');
            $table->string('brand')->nullable()->after('name');
            $table->string('variant')->nullable()->after('brand');
            $table->string('size')->nullable()->after('variant');
            $table->string('uom')->nullable()->after('size');
            $table->unsignedInteger('units_per_case')->default(1)->after('uom');
            $table->string('barcode')->nullable()->after('units_per_case');
            $table->string('image_path')->nullable()->after('image_url');
            $table->index(['branch_id', 'sales_division_id', 'is_active'], 'products_branch_division_active_index');
            $table->unique(['branch_id', 'barcode'], 'products_branch_barcode_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_branch_barcode_unique');
            $table->dropIndex('products_branch_division_active_index');
            $table->dropConstrainedForeignId('sales_division_id');
            $table->dropColumn(['brand', 'variant', 'size', 'uom', 'units_per_case', 'barcode', 'image_path']);
        });
    }
};
