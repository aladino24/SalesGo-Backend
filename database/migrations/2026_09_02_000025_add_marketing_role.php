<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['code' => '005'],
            [
                'name' => 'Marketing',
                'slug' => 'marketing',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('code', '005')->where('slug', 'marketing')->delete();
    }
};
