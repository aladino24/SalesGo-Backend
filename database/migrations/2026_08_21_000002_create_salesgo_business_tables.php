<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('client_transaction_id')->nullable()->unique();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('outlet_id')->constrained();
            $t->foreignId('sales_id')->constrained('users');
            $t->string('type');
            $t->string('status')->default('Submitted');
            $t->string('approval_status')->nullable();
            $t->text('reason')->nullable();
            $t->decimal('amount', 18, 2)->default(0);
            $t->json('items')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();
            $t->index(['branch_id', 'outlet_id', 'type']);
        });
        Schema::create('receivable_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('outlet_id')->constrained();
            $t->string('invoice_number')->unique();
            $t->date('invoice_date');
            $t->date('due_date');
            $t->decimal('original_amount', 18, 2);
            $t->decimal('paid_amount', 18, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('journeys', function (Blueprint $t) {
            $t->id();
            $t->string('client_journey_id')->nullable()->unique();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('sales_id')->constrained('users');
            $t->string('type');
            $t->string('destination');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->nullable();
            $t->string('status')->default('Planned');
            $t->string('approval_status')->default('Not Required');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('delivery_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('journey_id')->nullable()->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->string('number')->unique();
            $t->string('destination');
            $t->json('items');
            $t->string('status')->default('Draft');
            $t->string('approval_status')->default('Not Submitted');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->string('disk');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime_type');
            $t->unsignedBigInteger('size_bytes');
            $t->string('status')->default('Finalized');
            $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('branch_id')->constrained();
            $t->string('type');
            $t->string('title');
            $t->text('message');
            $t->string('entity_type')->nullable();
            $t->string('entity_id')->nullable();
            $t->string('deep_link')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'attachments', 'delivery_notes', 'journeys', 'receivable_invoices', 'outlet_transactions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
