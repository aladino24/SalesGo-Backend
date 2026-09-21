<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\IncentiveRule;
use App\Models\Outlet;
use App\Models\OutletRouteAssignment;
use App\Models\OutletTarget;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SalesDivision;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Seeder development dilarang pada production.');
        }
        $branch = Branch::updateOrCreate(['code' => '001'], ['name' => 'Cabang Surabaya', 'is_active' => true]);
        $sales = Role::updateOrCreate(['code' => '001'], ['name' => 'Sales', 'slug' => 'sales']);
        $supervisor = Role::updateOrCreate(['code' => '002'], ['name' => 'Supervisor', 'slug' => 'supervisor']);
        $branchManager = Role::updateOrCreate(['code' => '003'], ['name' => 'Branch Manager', 'slug' => 'branchManager']);
        Role::updateOrCreate(['code' => '006'], ['name' => 'Operasional', 'slug' => 'operational']);
        $division = SalesDivision::updateOrCreate(['code' => '01'], ['name' => 'General Trade']);
        $andi = User::updateOrCreate(['username' => 'andi.pratama'], ['branch_id' => $branch->id, 'role_id' => $sales->id, 'sales_division_id' => $division->id, 'employee_code' => '0010010101', 'increment_no' => 1, 'name' => 'Andi Pratama', 'email' => 'andi@example.test', 'password' => Hash::make('password'), 'is_active' => true]);
        User::updateOrCreate(['username' => 'supervisor.andi'], ['branch_id' => $branch->id, 'role_id' => $supervisor->id, 'sales_division_id' => $division->id, 'employee_code' => '0010020101', 'increment_no' => 1, 'name' => 'Supervisor Andi', 'email' => 'supervisor@example.test', 'password' => Hash::make('password'), 'is_active' => true]);
        User::updateOrCreate(['username' => 'manager.andi'], ['branch_id' => $branch->id, 'role_id' => $branchManager->id, 'sales_division_id' => $division->id, 'employee_code' => '0010030101', 'increment_no' => 1, 'name' => 'Branch Manager Andi', 'email' => 'manager@example.test', 'password' => Hash::make('password'), 'is_active' => true]);
        Product::updateOrCreate(['branch_id' => $branch->id, 'sku' => '001-PRD-0001'], ['branch_code' => $branch->code, 'name' => 'Susu Ultra', 'category' => 'Minuman', 'price' => 18000, 'stock' => 120, 'is_active' => true]);
        Product::updateOrCreate(['branch_id' => $branch->id, 'sku' => '001-PRD-0002'], ['branch_code' => $branch->code, 'name' => 'Mie Instan', 'category' => 'Makanan', 'price' => 4500, 'stock' => 260, 'is_active' => true]);
        Promotion::updateOrCreate(['branch_id' => $branch->id, 'code' => 'AUGUST10'], ['name' => 'Diskon Agustus 10%', 'type' => 'Percentage', 'value' => 10, 'maximum_discount' => 50000, 'minimum_order_amount' => 50000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(), 'is_active' => true]);
        $outlet = Outlet::updateOrCreate(['code' => '001-OTL-0001'], ['branch_id' => $branch->id, 'branch_code' => $branch->code, 'name' => 'Toko Sumber Rejeki', 'address' => 'Jl. Melati No. 12, Surabaya', 'type' => 'Grosir', 'owner_name' => 'Bapak Joko', 'contact_name' => 'Bapak Joko', 'phone' => '+628123456789', 'latitude' => -7.2575, 'longitude' => 112.7521, 'sales_responsible_id' => $andi->id, 'status' => 'Active']);
        $routeOutlets = [$outlet];
        foreach ([['0002', 'Toko Maju Jaya', -7.2610, 112.7480], ['0003', 'Toko Berkah', -7.2532, 112.7591], ['0004', 'Toko Abadi', -7.2674, 112.7552]] as [$suffix, $name, $latitude, $longitude]) {
            $routeOutlets[] = Outlet::updateOrCreate(['code' => '001-OTL-'.$suffix], ['branch_id' => $branch->id, 'branch_code' => $branch->code, 'name' => $name, 'address' => 'Surabaya', 'type' => 'Grosir', 'latitude' => $latitude, 'longitude' => $longitude, 'sales_responsible_id' => $andi->id, 'status' => 'Active']);
        }
        for ($number = 5; $number <= 50; $number++) {
            $routeOutlets[] = Outlet::updateOrCreate(
                ['code' => sprintf('001-OTL-%04d', $number)],
                ['branch_id' => $branch->id, 'branch_code' => $branch->code, 'name' => sprintf('Toko Rute %02d', $number), 'address' => 'Surabaya Area '.(($number % 5) + 1), 'type' => 'Grosir', 'latitude' => -7.2575 + (($number % 10) * 0.002), 'longitude' => 112.7521 + (intdiv($number, 10) * 0.002), 'sales_responsible_id' => $andi->id, 'status' => 'Active'],
            );
        }
        $routeUsers = User::where('branch_id', $branch->id)->where('is_active', true)->get();
        foreach ($routeUsers->values() as $userIndex => $routeUser) {
            foreach ($routeOutlets as $index => $routeOutlet) {
                // Outlet yang sama dapat dimiliki beberapa sales, tetapi
                // jadwal hari/minggunya harus berbeda untuk mencegah bentrok.
                $day = (($index + $userIndex) % 7) + 1;
                $week = (intdiv($index, 7) % 4) + 1;
                OutletRouteAssignment::updateOrCreate(['outlet_id' => $routeOutlet->id, 'sales_id' => $routeUser->id, 'day_of_week' => $day, 'week_of_month' => $week], ['branch_id' => $branch->id, 'is_active' => true]);
            }
        }
        OutletTarget::updateOrCreate(['branch_id' => $branch->id, 'outlet_id' => $outlet->id, 'period' => now()->startOfMonth()->toDateString()], ['revenue_target' => 10000000]);
        SalesTarget::updateOrCreate(['branch_id' => $branch->id, 'sales_id' => $andi->id, 'period' => now()->startOfMonth()->toDateString()], ['revenue_target' => 50000000]);
        IncentiveRule::updateOrCreate(['branch_id' => $branch->id, 'sales_id' => null, 'name' => 'Incentive 100% Target'], ['minimum_achievement_percent' => 100, 'incentive_rate_percent' => 2, 'fixed_amount' => 0, 'is_active' => true]);
    }
}
