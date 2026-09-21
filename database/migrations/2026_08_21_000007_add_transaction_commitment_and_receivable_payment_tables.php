<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlet_transactions', function (Blueprint $table) {
            $table->string('document_number')->nullable()->unique()->after('client_transaction_id');
            $table->timestamp('committed_at')->nullable()->after('occurred_at');
        });
        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('receivable_invoices');
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('received_by')->constrained('users');
            $table->decimal('amount', 18, 2);
            $table->timestamp('paid_at');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
        Schema::table('outlet_transactions', function (Blueprint $table) {
            $table->dropUnique(['document_number']);
            $table->dropColumn(['document_number', 'committed_at']);
        });
    }
};
