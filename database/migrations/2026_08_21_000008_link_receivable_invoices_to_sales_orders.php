<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receivable_invoices', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->unique()->after('outlet_id')->constrained('outlet_transactions');
        });
    }

    public function down(): void
    {
        Schema::table('receivable_invoices', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropUnique(['sales_order_id']);
            $table->dropColumn('sales_order_id');
        });
    }
};
