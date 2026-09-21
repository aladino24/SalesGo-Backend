<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\SalesDivision;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('IT_ADMIN_PASSWORD', '');
        if (mb_strlen($password) < 12) {
            throw new RuntimeException('IT_ADMIN_PASSWORD minimal harus 12 karakter.');
        }

        $branch = Branch::updateOrCreate(
            ['code' => (string) env('INITIAL_BRANCH_CODE', '001')],
            ['name' => (string) env('INITIAL_BRANCH_NAME', 'Cabang Utama'), 'is_active' => true],
        );
        $division = SalesDivision::updateOrCreate(
            ['code' => '01'],
            ['name' => 'General Trade'],
        );
        $roles = [
            ['code' => '001', 'name' => 'Sales', 'slug' => 'sales'],
            ['code' => '002', 'name' => 'Supervisor', 'slug' => 'supervisor'],
            ['code' => '003', 'name' => 'Branch Manager', 'slug' => 'branchManager'],
            ['code' => '004', 'name' => 'Marketing', 'slug' => 'marketing'],
            ['code' => '005', 'name' => 'IT', 'slug' => 'it'],
            ['code' => '006', 'name' => 'Operasional', 'slug' => 'operational'],
        ];
        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }

        $itRole = Role::where('slug', 'it')->firstOrFail();
        $increment = max(1, (int) env('IT_ADMIN_INCREMENT', 1));
        $username = (string) env('IT_ADMIN_USERNAME', 'it.admin');
        $admin = User::updateOrCreate(
            ['username' => $username],
            [
                'branch_id' => $branch->id,
                'role_id' => $itRole->id,
                'sales_division_id' => $division->id,
                'employee_code' => sprintf('%s%s%s%02d', $branch->code, $itRole->code, $division->code, $increment),
                'increment_no' => $increment,
                'name' => 'IT Administrator',
                'email' => (string) env('IT_ADMIN_EMAIL', 'it@example.test'),
                'password' => Hash::make($password),
                'max_devices' => 1,
                'is_active' => true,
            ],
        );
        $admin->divisions()->syncWithoutDetaching([$division->id]);
    }
}
