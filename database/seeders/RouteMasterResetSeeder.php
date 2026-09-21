<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Outlet;
use App\Models\OutletRouteAssignment;
use App\Models\OutletTarget;
use App\Models\Role;
use App\Models\SalesDivision;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RouteMasterResetSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('Reset data demo dilarang pada production.');
        }

        DB::transaction(function (): void {
            $branch = Branch::updateOrCreate(
                ['code' => '001'],
                ['name' => 'Cabang Surabaya', 'is_active' => true]
            );
            $salesRole = Role::updateOrCreate(
                ['code' => '001'],
                ['name' => 'Sales', 'slug' => 'sales']
            );
            $division = SalesDivision::updateOrCreate(
                ['code' => '01'],
                ['name' => 'General Trade']
            );

            // Urutan hapus mengikuti foreign key outlet. Data ini adalah data
            // demo cabang 001 yang disetujui untuk di-reset.
            DB::table('visit_activities')->where('branch_id', $branch->id)->delete();
            DB::table('approvals')
                ->where('branch_id', $branch->id)
                ->where('entity_type', Visit::class)
                ->delete();
            DB::table('visits')->where('branch_id', $branch->id)->delete();
            DB::table('receivable_payments')->where('branch_id', $branch->id)->delete();
            DB::table('receivable_invoices')->where('branch_id', $branch->id)->delete();
            DB::table('outlet_transactions')->where('branch_id', $branch->id)->delete();
            DB::table('outlet_targets')->where('branch_id', $branch->id)->delete();
            DB::table('outlet_route_assignments')->where('branch_id', $branch->id)->delete();
            DB::table('outlets')->where('branch_id', $branch->id)->delete();

            $salesUsers = collect();
            for ($number = 1; $number <= 10; $number++) {
                $isPrimarySales = $number === 1;
                $salesUsers->push(User::updateOrCreate(
                    ['username' => $isPrimarySales ? 'andi.pratama' : sprintf('sales.%02d', $number)],
                    [
                        'branch_id' => $branch->id,
                        'role_id' => $salesRole->id,
                        'sales_division_id' => $division->id,
                        'employee_code' => sprintf('%s%s%s%02d', $branch->code, $salesRole->code, $division->code, $number),
                        'increment_no' => $number,
                        'name' => $isPrimarySales ? 'Andi Pratama' : sprintf('Sales Demo %02d', $number),
                        'email' => $isPrimarySales ? 'andi@example.test' : sprintf('sales%02d@example.test', $number),
                        'password' => Hash::make('password'),
                        'is_active' => true,
                    ]
                ));
            }

            $now = now();
            for ($number = 1; $number <= 100; $number++) {
                $responsible = $salesUsers[($number - 1) % $salesUsers->count()];
                $outlet = Outlet::create([
                    'branch_id' => $branch->id,
                    'branch_code' => $branch->code,
                    'code' => sprintf('%s-OTL-%04d', $branch->code, $number),
                    'name' => sprintf('Mitra Surabaya %03d', $number),
                    'address' => sprintf('Jl. Distribusi No. %03d, Surabaya', $number),
                    'type' => $number % 3 === 0 ? 'Retail' : 'Grosir',
                    'owner_name' => sprintf('Pemilik %03d', $number),
                    'contact_name' => sprintf('Kontak %03d', $number),
                    'phone' => sprintf('+6281200%04d', $number),
                    'latitude' => -7.2575 + (($number % 10) * 0.0015),
                    'longitude' => 112.7521 + (intdiv($number - 1, 10) * 0.0015),
                    'geofence_radius_meters' => 100,
                    'sales_responsible_id' => $responsible->id,
                    'status' => 'Active',
                ]);

                $day = (($number - 1) % 7) + 1;
                $week = (intdiv($number - 1, 7) % 4) + 1;
                $salesCount = (($number - 1) % 10) + 1;
                foreach ($salesUsers->take($salesCount) as $salesUser) {
                    OutletRouteAssignment::create([
                        'branch_id' => $branch->id,
                        'outlet_id' => $outlet->id,
                        'sales_id' => $salesUser->id,
                        'day_of_week' => $day,
                        'week_of_month' => $week,
                        'is_active' => true,
                    ]);
                }
                OutletTarget::create([
                    'branch_id' => $branch->id,
                    'outlet_id' => $outlet->id,
                    'period' => $now->copy()->startOfMonth()->toDateString(),
                    'revenue_target' => 5000000 + ($number * 25000),
                ]);
            }
        });
    }
}
