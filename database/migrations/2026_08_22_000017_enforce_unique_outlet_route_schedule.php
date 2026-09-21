<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data legacy dapat memiliki jadwal ganda. Jangan menghapus atau
        // memindahkan assignment lama secara otomatis. Aturan unik diterapkan
        // oleh RouteMasterController untuk seluruh create/update baru; indeks
        // ini mempercepat pemeriksaan konflik tersebut.
        Schema::table('outlet_route_assignments', function (Blueprint $table) {
            $table->index(
                ['branch_id', 'outlet_id', 'day_of_week', 'week_of_month'],
                'route_outlet_schedule_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::table('outlet_route_assignments', function (Blueprint $table) {
            $table->dropIndex('route_outlet_schedule_lookup');
        });
    }
};
