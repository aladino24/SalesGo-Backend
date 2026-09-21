<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['code' => '006'],
            [
                'name' => 'Operasional',
                'slug' => 'operational',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('code', '006')
            ->where('slug', 'operational')
            ->delete();
    }
};
