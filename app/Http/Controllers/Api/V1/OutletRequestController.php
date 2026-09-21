<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\Attachment;
use App\Models\Outlet;
use App\Services\DeepLinkService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class OutletRequestController extends ApiController
{
    public function store(Request $request, NotificationService $notifications, DeepLinkService $links)
    {
        abort_unless(in_array($request->user()->role->slug, ['sales', 'supervisor', 'branchManager', 'it'], true), 403, 'Role ini tidak dapat menambahkan outlet.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'type' => ['required', 'string', 'max:80'],
            'ownerName' => ['nullable', 'string', 'max:150'],
            'contactName' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'photoId' => ['required', 'integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $branchId = $this->branchId($request->user());
        $photo = Attachment::whereKey($data['photoId'])
            ->where('branch_id', $branchId)
            ->where('uploaded_by', $request->user()->id)
            ->whereIn('mime_type', ['image/jpeg', 'image/png'])
            ->firstOrFail();
        $branchCode = $request->user()->branch->code;
        $next = Outlet::where('branch_id', $branchId)->count() + 1;
        $isAutoApproved = $request->user()->role->slug === 'branchManager' || $this->isSuperUser($request->user());
        $outlet = Outlet::create(['branch_id' => $branchId, 'branch_code' => $branchCode, 'code' => $branchCode.'-OTL-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT), 'name' => $data['name'], 'address' => $data['address'], 'type' => $data['type'], 'owner_name' => $data['ownerName'] ?? null, 'contact_name' => $data['contactName'] ?? null, 'phone' => $data['phone'] ?? null, 'photo_attachment_id' => $photo->id, 'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'sales_responsible_id' => $request->user()->id, 'status' => $isAutoApproved ? 'Active' : 'Pending Approval']);
        $approval = null;
        if (! $isAutoApproved) {
            $approval = Approval::create(['branch_id' => $branchId, 'type' => 'new_outlet', 'entity_type' => Outlet::class, 'entity_id' => $outlet->id, 'requested_by' => $request->user()->id, 'reason' => 'Pengajuan outlet baru: '.$outlet->name, 'status' => 'Pending']);
            $requesterRole = $request->user()->role?->name ?? $request->user()->role?->slug ?? 'User';
            $notifications->notifyBranchManagers($branchId, 'approval_request', 'Approval outlet baru', $request->user()->name.' ('.$requesterRole.') mengajukan outlet '.$outlet->name.'.', Approval::class, $approval->id, $links->approval($approval->id));
        }

        return response()->json(['id' => (string) $outlet->id, 'code' => $outlet->code, 'status' => $outlet->status, 'approvalRequired' => ! $isAutoApproved, 'approvalId' => $approval ? (string) $approval->id : null], 201);
    }
}
