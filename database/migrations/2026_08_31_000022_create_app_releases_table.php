<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20)->default('android');
            $table->string('version_name', 40);
            $table->unsignedBigInteger('version_code');
            $table->string('md5', 32);
            $table->string('sha256', 64);
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name', 180);
            $table->unsignedBigInteger('size_bytes');
            $table->text('release_notes')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_published')->default(false);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'version_code']);
            $table->index(['platform', 'is_published', 'version_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
