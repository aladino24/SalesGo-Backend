<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\SalesDivision;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class ItAdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('IT_ADMIN_PASSWORD', '');
        if (mb_strlen($password) < 12) {
            throw new RuntimeException('Isi IT_ADMIN_PASSWORD (minimal 12 karakter) di .env sebelum membuat user IT.');
        }

        $branch = Branch::query()->orderBy('id')->firstOrFail();
        $division = SalesDivision::query()->orderBy('id')->firstOrFail();
        $role = Role::query()->where('slug', 'it')->firstOrFail();
        $username = (string) env('IT_ADMIN_USERNAME', 'it.admin');
        $email = (string) env('IT_ADMIN_EMAIL', 'it@example.test');
        $increment = (int) env('IT_ADMIN_INCREMENT', 1);

        User::updateOrCreate(
            ['username' => $username],
            [
                'branch_id' => $branch->id,
                'role_id' => $role->id,
                'sales_division_id' => $division->id,
                'employee_code' => sprintf('%s%s%s%02d', $branch->code, $role->code, $division->code, $increment),
                'increment_no' => $increment,
                'name' => 'IT Administrator',
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
            ],
        );
    }
}
