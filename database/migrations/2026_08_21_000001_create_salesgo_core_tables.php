<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->char('code', 3)->unique(), $t->string('name'), $t->boolean('is_active')->default(true), $t->timestamps()]));
        Schema::create('roles', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->char('code', 3)->unique(), $t->string('name'), $t->string('slug')->unique(), $t->timestamps()]));
        Schema::create('sales_divisions', fn (Blueprint $t) => tap($t, fn ($t) => [$t->id(), $t->char('code', 2)->unique(), $t->string('name'), $t->timestamps()]));
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('role_id')->constrained();
            $t->foreignId('sales_division_id')->constrained();
            $t->char('employee_code', 10)->unique();
            $t->unsignedTinyInteger('increment_no');
            $t->string('name');
            $t->string('username')->unique();
            $t->string('email')->nullable()->unique();
            $t->string('password');
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
            $t->unique(['branch_id', 'role_id', 'sales_division_id', 'increment_no'], 'users_scope_increment_unique');
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        Schema::create('refresh_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->char('branch_code', 3);
            $t->string('sku');
            $t->string('name');
            $t->string('category')->nullable();
            $t->decimal('price', 18, 2)->default(0);
            $t->integer('stock')->default(0);
            $t->string('image_url')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['branch_id', 'sku']);
        });
        Schema::create('outlets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->char('branch_code', 3);
            $t->string('code')->unique();
            $t->string('name');
            $t->text('address');
            $t->string('type');
            $t->string('owner_name')->nullable();
            $t->string('contact_name')->nullable();
            $t->string('phone')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->foreignId('sales_responsible_id')->nullable()->constrained('users');
            $t->string('status')->default('Active');
            $t->timestamps();
        });
        Schema::create('visits', function (Blueprint $t) {
            $t->id();
            $t->string('client_visit_id')->nullable()->unique();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('outlet_id')->constrained();
            $t->foreignId('sales_id')->constrained('users');
            $t->string('status')->default('Planned');
            $t->decimal('distance_km', 10, 3)->default(0);
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->timestamp('checked_in_at')->nullable();
            $t->timestamp('checked_out_at')->nullable();
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->string('type');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id');
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('approver_id')->nullable()->constrained('users');
            $t->text('reason');
            $t->text('comment')->nullable();
            $t->string('status')->default('Pending');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
        Schema::create('meetings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->string('meeting_code')->unique();
            $t->string('title');
            $t->text('description')->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->string('status')->default('Upcoming');
            $t->string('provider')->nullable();
            $t->text('join_url')->nullable();
            $t->json('agenda')->nullable();
            $t->timestamps();
        });
        Schema::create('idempotency_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->uuid('idempotency_key');
            $t->string('endpoint');
            $t->char('request_hash', 64);
            $t->unsignedSmallInteger('status_code');
            $t->json('response_body');
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['user_id', 'idempotency_key', 'endpoint']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->nullable()->constrained();
            $t->foreignId('user_id')->nullable()->constrained();
            $t->string('event');
            $t->string('entity_type')->nullable();
            $t->string('entity_id')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'idempotency_records', 'meetings', 'approvals', 'visits', 'outlets', 'products', 'refresh_tokens', 'personal_access_tokens', 'users', 'sales_divisions', 'roles', 'branches'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
