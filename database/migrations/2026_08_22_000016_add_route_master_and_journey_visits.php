<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outlet_route_assignments')) {
            Schema::create('outlet_route_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained();
                $table->foreignId('outlet_id')->constrained();
                $table->foreignId('sales_id')->constrained('users');
                $table->unsignedTinyInteger('day_of_week'); // ISO: 1 Senin ... 7 Minggu
                $table->unsignedTinyInteger('week_of_month'); // 1 ... 4
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['outlet_id', 'sales_id', 'day_of_week', 'week_of_month'], 'route_assignment_unique');
            });
        }
        Schema::table('outlet_route_assignments', function (Blueprint $table) {
            $table->index(['branch_id', 'sales_id', 'day_of_week', 'week_of_month'], 'route_assignment_lookup_idx');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('journey_id')->nullable()->after('sales_id')->constrained();
            $table->date('planned_for')->nullable()->after('journey_id');
            $table->boolean('is_required')->default(true)->after('planned_for');
            $table->index(['sales_id', 'journey_id', 'planned_for', 'is_required'], 'visit_journey_plan_index');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visit_journey_plan_index');
            $table->dropConstrainedForeignId('journey_id');
            $table->dropColumn(['planned_for', 'is_required']);
        });
        Schema::dropIfExists('outlet_route_assignments');
    }
};
