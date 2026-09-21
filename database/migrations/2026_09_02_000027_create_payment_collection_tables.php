<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->decimal('credit_limit', 18, 2)->default(0)->after('status');
            $table->string('credit_status', 40)->default('Aktif')->after('credit_limit');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_payment_id')->unique();
            $table->string('payment_number')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('received_by')->constrained('users');
            $table->decimal('amount', 18, 2);
            $table->decimal('allocated_amount', 18, 2)->default(0);
            $table->decimal('unallocated_amount', 18, 2)->default(0);
            $table->string('status', 40)->default('PENDING_SYNC');
            $table->string('source', 40)->default('mobile');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy_meters', 10, 2)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'outlet_id', 'status']);
            $table->index(['received_by', 'status']);
        });

        Schema::create('payment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('method', 40);
            $table->decimal('amount', 18, 2);
            $table->string('status', 40)->default('SUBMITTED');
            $table->string('reference_number')->nullable();
            $table->json('details')->nullable();
            $table->json('attachment_ids')->nullable();
            $table->timestamps();
            $table->index(['method', 'status']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('receivable_invoices');
            $table->decimal('amount', 18, 2);
            $table->string('status', 40)->default('PENDING');
            $table->timestamps();
            $table->unique(['payment_transaction_id', 'invoice_id']);
            $table->index(['invoice_id', 'status']);
        });

        Schema::create('collection_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_activity_id')->unique();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('type', 40);
            $table->string('status', 40)->default('SUBMITTED');
            $table->string('reason')->nullable();
            $table->date('promise_date')->nullable();
            $table->decimal('promised_amount', 18, 2)->nullable();
            $table->json('invoice_ids')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'outlet_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_activities');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_components');
        Schema::dropIfExists('payment_transactions');
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'credit_status']);
        });
    }
};
