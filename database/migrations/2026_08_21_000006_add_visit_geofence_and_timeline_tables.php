<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedInteger('default_geofence_radius_meters')->default(100)->after('is_active');
        });
        Schema::table('outlets', function (Blueprint $table) {
            $table->unsignedInteger('geofence_radius_meters')->nullable()->after('longitude');
        });
        Schema::create('visit_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->foreignId('visit_id')->constrained();
            $table->foreignId('sales_id')->constrained('users');
            $table->string('activity');
            $table->text('description');
            $table->json('location')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['outlet_id', 'occurred_at']);
            $table->index(['visit_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_activities');
        Schema::table('outlets', fn (Blueprint $table) => $table->dropColumn('geofence_radius_meters'));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('default_geofence_radius_meters'));
    }
};
