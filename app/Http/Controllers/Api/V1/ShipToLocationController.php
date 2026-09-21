<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\Outlet;
use App\Models\OutletShipToLocation;
use App\Services\DeepLinkService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ShipToLocationController extends ApiController
{
    /** Master alamat pengiriman yang dapat dicache perangkat untuk order offline. */
    public function index(Request $request)
    {
        $items = OutletShipToLocation::query()
            ->where('branch_id', $this->branchId($request->user()))
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json($items->map(fn (OutletShipToLocation $item) => $this->payload($item))->values());
    }

    /** Mengajukan alamat pengiriman tambahan dari halaman detail outlet. */
    public function store(Request $request, Outlet $outlet, NotificationService $notifications, DeepLinkService $links)
    {
        abort_unless($outlet->branch_id === $this->branchId($request->user()), 403);
        abort_unless(in_array($request->user()->role->slug, ['sales', 'supervisor', 'branchManager', 'it'], true), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'contactName' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $isAutoApproved = $request->user()->role->slug === 'branchManager' || $this->isSuperUser($request->user());
        $sequence = OutletShipToLocation::where('outlet_id', $outlet->id)->count() + 1;
        $location = OutletShipToLocation::create([
            'branch_id' => $outlet->branch_id,
            'outlet_id' => $outlet->id,
            'code' => $outlet->code.'-SHIP-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'name' => $data['name'],
            'address' => $data['address'],
            'contact_name' => $data['contactName'] ?? null,
            'phone' => $data['phone'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'is_default' => false,
            'is_active' => $isAutoApproved,
        ]);
        $approval = null;
        if (! $isAutoApproved) {
            $approval = Approval::create([
                'branch_id' => $outlet->branch_id,
                'type' => 'new_ship_to_location',
                'entity_type' => OutletShipToLocation::class,
                'entity_id' => $location->id,
                'requested_by' => $request->user()->id,
                'reason' => $data['notes'] ?? 'Pengajuan lokasi pengiriman baru untuk '.$outlet->name,
                'status' => 'Pending',
            ]);
            $role = $request->user()->role?->name ?? $request->user()->role?->slug ?? 'User';
            $notifications->notifyBranchManagers(
                $outlet->branch_id,
                'approval_request',
                'Approval lokasi pengiriman',
                $request->user()->name.' ('.$role.') mengajukan lokasi pengiriman untuk '.$outlet->name.'.',
                Approval::class,
                $approval->id,
                $links->approval($approval->id),
            );
        }

        return response()->json([
            'shipTo' => $this->payload($location),
            'status' => $isAutoApproved ? 'Active' : 'Pending Approval',
            'approvalRequired' => ! $isAutoApproved,
            'approvalId' => $approval ? (string) $approval->id : null,
        ], 201);
    }

    private function payload(OutletShipToLocation $item): array
    {
        return [
            'id' => (string) $item->id,
            'outletId' => (string) $item->outlet_id,
            'code' => $item->code,
            'name' => $item->name,
            'address' => $item->address,
            'contactName' => $item->contact_name,
            'phone' => $item->phone,
            'latitude' => $item->latitude,
            'longitude' => $item->longitude,
            'isDefault' => $item->is_default,
        ];
    }
}
