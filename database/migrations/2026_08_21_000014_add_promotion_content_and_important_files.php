<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->text('terms')->nullable()->after('description');
            $table->string('image_url')->nullable()->after('terms');
        });
        Schema::create('important_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('name');
            $table->string('type', 30);
            $table->string('disk');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('version', 50);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('important_files');
        Schema::table('promotions', fn (Blueprint $table) => $table->dropColumn(['description', 'terms', 'image_url']));
    }
};
