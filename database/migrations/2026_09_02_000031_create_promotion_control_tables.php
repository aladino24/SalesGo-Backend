<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_usages')) Schema::create('promotion_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('sales_id')->constrained('users');
            $table->foreignId('outlet_transaction_id')->nullable()->constrained('outlet_transactions');
            $table->string('reference', 100)->unique();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('benefit_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('Reserved');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['promotion_id', 'outlet_id', 'status']);
        });

        if (! Schema::hasTable('promotion_special_requests')) Schema::create('promotion_special_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('promotion_id')->nullable()->constrained();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->string('requested_type', 40);
            $table->decimal('requested_value', 18, 2)->nullable();
            $table->unsignedInteger('requested_quantity')->nullable();
            $table->decimal('potential_revenue', 18, 2)->nullable();
            $table->text('reason');
            $table->json('attachment_ids')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 30)->default('Submitted');
            $table->timestamps();
            $table->index(['branch_id', 'status', 'created_at']);
        });

        if (! Schema::hasTable('promotion_merchandising_proofs')) Schema::create('promotion_merchandising_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('submitted_by')->constrained('users');
            $table->json('attachment_ids');
            $table->text('notes')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('status', 30)->default('Submitted');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->timestamps();
            $table->index(['promotion_id', 'outlet_id', 'status'], 'promo_proof_lookup_idx');
        });
        else Schema::table('promotion_merchandising_proofs', fn (Blueprint $table) => $table->index(['promotion_id', 'outlet_id', 'status'], 'promo_proof_lookup_idx'));
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_merchandising_proofs');
        Schema::dropIfExists('promotion_special_requests');
        Schema::dropIfExists('promotion_usages');
    }
};
