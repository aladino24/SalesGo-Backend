<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\OutletShipToLocation;
use Illuminate\Database\Seeder;

class OutletShipToLocationSeeder extends Seeder
{
    /**
     * Membuat alamat Ship-to utama dari data outlet yang sudah ada. Tidak ada
     * alamat atau koordinat fiktif: seluruh nilai berasal dari master outlet.
     */
    public function run(): void
    {
        Outlet::query()
            ->where('status', 'Active')
            ->orderBy('id')
            ->each(function (Outlet $outlet): void {
                OutletShipToLocation::updateOrCreate(
                    [
                        'branch_id' => $outlet->branch_id,
                        'code' => $outlet->code.'-SHIP-01',
                    ],
                    [
                        'outlet_id' => $outlet->id,
                        'name' => 'Alamat Utama '.$outlet->name,
                        'address' => $outlet->address,
                        'contact_name' => $outlet->contact_name ?? $outlet->owner_name,
                        'phone' => $outlet->phone,
                        'latitude' => $outlet->latitude,
                        'longitude' => $outlet->longitude,
                        'is_default' => true,
                        'is_active' => true,
                    ],
                );
            });
    }
}
