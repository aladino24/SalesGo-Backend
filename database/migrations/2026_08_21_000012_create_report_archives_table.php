<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('payload');
            $table->timestamp('archived_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'period_start', 'period_end']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_archives');
    }
};
