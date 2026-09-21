<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $t) {
            $t->uuid('client_uuid')->nullable()->unique()->after('id');
            $t->char('sha256', 64)->nullable();
            $t->text('error_message')->nullable();
            $t->timestamp('finalized_at')->nullable();
            $t->index(['branch_id', 'uploaded_by', 'status']);
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::table('attachments', function (Blueprint $t) {
            $t->dropIndex(['branch_id', 'uploaded_by', 'status']);
            $t->dropColumn(['client_uuid', 'sha256', 'error_message', 'finalized_at']);
        });
    }
};
