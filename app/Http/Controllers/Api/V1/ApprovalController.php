<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\DeliveryNote;
use App\Models\Journey;
use App\Models\OutletTransaction;
use App\Models\Outlet;
use App\Models\OutletShipToLocation;
use App\Models\PromotionSpecialRequest;
use App\Models\User;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\DeepLinkService;
use App\Services\DeliveryStockService;
use App\Services\IdempotencyService;
use App\Services\NotificationService;
use App\Services\SalesOrderCalculator;
use App\Services\VisitActivityLogger;
use Illuminate\Http\Request;

class ApprovalController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensureApprover($request);

        $query = Approval::with('requester.role')
            ->where('branch_id', $this->branchId($request->user()))
            ->whereIn('status', ['Pending', 'Waiting Approval']);
        if (! in_array($request->user()->role->slug, ['branchManager', 'it'], true)) {
            $query->whereNotIn('type', ['new_outlet', 'new_ship_to_location']);
        }

        return response()->json($query->latest()->get()->map(fn ($a) => $this->payload($a)));
    }

    public function decide(Request $request, Approval $approval, IdempotencyService $idempotency, VisitActivityLogger $timeline, SalesOrderCalculator $calculator, DeliveryStockService $deliveryStock, NotificationService $notifications, DeepLinkService $links, AuditLogger $audit)
    {
        $this->ensureApprover($request);
        abort_unless($approval->branch_id === $this->branchId($request->user()), 403);

        return $idempotency->handle($request, function () use ($request, $approval, $timeline, $calculator, $deliveryStock, $notifications, $links, $audit) {
            $data = $request->validate(['status' => ['required', 'in:Approved,Rejected'], 'comment' => ['nullable', 'string', 'max:500']]);
            if ($approval->status !== 'Pending') {
                return response()->json(['message' => 'Approval sudah diputuskan.', 'code' => 'APPROVAL_ALREADY_DECIDED'], 422);
            }
            if ($data['status'] === 'Rejected' && blank($data['comment'] ?? null)) {
                return response()->json(['message' => 'Alasan reject wajib diisi.', 'code' => 'REJECT_REASON_REQUIRED'], 422);
            }
            if ($approval->entity_type === DeliveryNote::class && $request->user()->role->slug !== 'branchManager' && ! $this->isSuperUser($request->user())) {
                return response()->json(['message' => 'Surat jalan hanya dapat diputuskan oleh Branch Manager.', 'code' => 'DELIVERY_NOTE_BRANCH_MANAGER_REQUIRED'], 403);
            }
            if ($approval->entity_type === Outlet::class && $request->user()->role->slug !== 'branchManager' && ! $this->isSuperUser($request->user())) {
                return response()->json(['message' => 'Pengajuan outlet baru hanya dapat diputuskan oleh Branch Manager.', 'code' => 'OUTLET_BRANCH_MANAGER_REQUIRED'], 403);
            }
            if ($approval->entity_type === OutletShipToLocation::class && $request->user()->role->slug !== 'branchManager' && ! $this->isSuperUser($request->user())) {
                return response()->json(['message' => 'Lokasi pengiriman hanya dapat diputuskan oleh Branch Manager.', 'code' => 'SHIP_TO_BRANCH_MANAGER_REQUIRED'], 403);
            }
            if ($approval->entity_type === PromotionSpecialRequest::class && ! in_array($request->user()->role->slug, ['branchManager', 'it'], true)) {
                return response()->json(['message' => 'Pengajuan promosi khusus hanya dapat diputuskan oleh Branch Manager.', 'code' => 'PROMOTION_BRANCH_MANAGER_REQUIRED'], 403);
            }
            $approval->update(['status' => $data['status'], 'comment' => $data['comment'] ?? null, 'approver_id' => $request->user()->id, 'decided_at' => now()]);
            if ($approval->entity_type === Visit::class) {
                $visit = Visit::findOrFail($approval->entity_id);
                if ($data['status'] === 'Approved') {
                    $visit->update(['status' => 'In Progress']);
                } else {
                    // Penolakan harus mengembalikan visit ke kondisi yang dapat
                    // diajukan ulang; status Pending sebelumnya tidak boleh
                    // mengunci sales pada pengajuan lama.
                    $visit->update(['status' => 'Planned']);
                }
                $timeline->record($visit, 'approval_'.strtolower($data['status']), 'Approval override check-in '.$data['status'], [], ['approvalId' => $approval->id, 'approverId' => $request->user()->id, 'comment' => $data['comment'] ?? null]);
            }
            if ($approval->entity_type === DeliveryNote::class) {
                $deliveryNote = DeliveryNote::lockForUpdate()->findOrFail($approval->entity_id);
                if ($data['status'] === 'Rejected' && $deliveryNote->stock_reserved_at && ! $deliveryNote->stock_released_at) {
                    $deliveryStock->release($deliveryNote->branch_id, $deliveryNote->items);
                    $deliveryNote->stock_released_at = now();
                }
                $deliveryNote->approval_status = $data['status'];
                $deliveryNote->status = $data['status'] === 'Approved' ? 'Approved' : 'Rejected';
                $deliveryNote->save();
            }
            if ($approval->entity_type === Journey::class) {
                $journey = Journey::lockForUpdate()->findOrFail($approval->entity_id);
                $journey->approval_status = $data['status'];
                if ($data['status'] === 'Rejected') {
                    $journey->status = 'Cancelled';
                }
                $journey->save();
            }
            if ($approval->entity_type === Outlet::class) {
                $outlet = Outlet::lockForUpdate()->findOrFail($approval->entity_id);
                $outlet->update(['status' => $data['status'] === 'Approved' ? 'Active' : 'Rejected']);
            }
            if ($approval->entity_type === OutletShipToLocation::class) {
                $location = OutletShipToLocation::lockForUpdate()->findOrFail($approval->entity_id);
                $location->update(['is_active' => $data['status'] === 'Approved']);
            }
            if ($approval->entity_type === PromotionSpecialRequest::class) {
                $special = PromotionSpecialRequest::lockForUpdate()->findOrFail($approval->entity_id);
                $special->update(['status' => $data['status'] === 'Approved' ? 'Approved' : 'Rejected']);
            }
            if ($approval->entity_type === OutletTransaction::class) {
                $transaction = OutletTransaction::lockForUpdate()->findOrFail($approval->entity_id);
                if ($data['status'] === 'Approved' && $transaction->status !== 'Committed') {
                    $direction = $transaction->type === 'return' ? 1 : -1;
                    $calculator->adjustStock($transaction->branch_id, $transaction->items ?? [], $direction);
                    $transaction->update(['status' => 'Committed', 'approval_status' => 'Approved', 'committed_at' => now()]);
                } else {
                    $transaction->update(['status' => 'Rejected', 'approval_status' => 'Rejected']);
                }
            }
            $requester = User::find($approval->requested_by);
            if ($requester) {
                $deepLink = match ($approval->entity_type) {
                    Journey::class => $links->journey($approval->entity_id),
                    DeliveryNote::class => $links->deliveryNote($approval->entity_id),
                    Visit::class => $links->visit($approval->entity_id, Visit::find($approval->entity_id)?->outlet_id),
                    Outlet::class => $links->notification($approval->id),
                    OutletShipToLocation::class => $links->outlet(OutletShipToLocation::find($approval->entity_id)?->outlet_id),
                    default => $links->approval($approval->id),
                };
                $decision = $data['status'] === 'Approved' ? 'disetujui' : 'ditolak';
                $suffix = $data['comment'] ? ' Catatan: '.$data['comment'] : '';
                [$title, $message] = match ($approval->type) {
                    'new_outlet' => ['Pengajuan outlet '.$decision, 'Pengajuan outlet baru Anda telah '.$decision.'.'.$suffix],
                    'new_ship_to_location' => ['Lokasi pengiriman '.$decision, 'Pengajuan lokasi pengiriman Anda telah '.$decision.'.'.$suffix],
                    'visit_out_of_radius' => ['Override check-in '.$decision, 'Pengajuan check-in di luar radius Anda telah '.$decision.'.'.$suffix],
                    'promotion_special_request' => ['Promosi khusus '.$decision, 'Pengajuan promosi khusus Anda telah '.$decision.'.'.$suffix],
                    default => ['Approval '.$data['status'], 'Pengajuan '.$approval->type.' Anda telah '.$data['status'].'.'],
                };
                $notifications->notify($requester, 'approval_decision', $title, $message, Approval::class, $approval->id, $deepLink);
            }
            $audit->record($request->user()->id, $approval->branch_id, 'approval_decided', Approval::class, $approval->id, ['decision' => $data['status'], 'entityType' => $approval->entity_type, 'entityId' => $approval->entity_id]);

            return response()->json($this->payload($approval));
        });
    }

    private function ensureApprover(Request $request): void
    {
        abort_unless(in_array($request->user()->role->slug, ['supervisor', 'branchManager', 'it'], true), 403);
    }

    private function payload(Approval $a): array
    {
        $requester = $a->relationLoaded('requester') ? $a->requester : User::with('role')->find($a->requested_by);
        $payload = [
            'id' => (string) $a->id,
            'type' => $a->type,
            'entityId' => (string) $a->entity_id,
            'requestedBy' => [
                'id' => (string) $a->requested_by,
                'name' => $requester?->name ?? 'User #'.$a->requested_by,
                'role' => $requester?->role?->name ?? $requester?->role?->slug ?? '-',
            ],
            'reason' => $a->reason,
            'status' => $a->status,
            'comment' => $a->comment,
            'createdAt' => $a->created_at->toIso8601String(),
        ];
        if ($a->entity_type === Outlet::class) {
            $outlet = Outlet::with('photoAttachment')->find($a->entity_id);
            $payload['outlet'] = $outlet ? [
                'code' => $outlet->code,
                'name' => $outlet->name,
                'type' => $outlet->type,
                'ownerName' => $outlet->owner_name,
                'contactName' => $outlet->contact_name,
                'phone' => $outlet->phone,
                'address' => $outlet->address,
                'latitude' => $outlet->latitude,
                'longitude' => $outlet->longitude,
                'photoAttachmentId' => $outlet->photo_attachment_id ? (string) $outlet->photo_attachment_id : null,
                'photoUrl' => $outlet->photo_attachment_id ? url('/api/v1/attachments/'.$outlet->photo_attachment_id.'/content') : null,
            ] : null;
        }
        if ($a->entity_type === OutletShipToLocation::class) {
            $location = OutletShipToLocation::with('outlet')->find($a->entity_id);
            $payload['shipToLocation'] = $location ? [
                'id' => (string) $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'address' => $location->address,
                'contactName' => $location->contact_name,
                'phone' => $location->phone,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'outletName' => $location->outlet?->name,
                'outletCode' => $location->outlet?->code,
            ] : null;
        }
        if ($a->entity_type === Visit::class) {
            $visit = Visit::with('outlet')->find($a->entity_id);
            $photoId = $visit?->metadata['photoId'] ?? null;
            $payload['visit'] = $visit ? [
                'id' => (string) $visit->id,
                'outletId' => (string) $visit->outlet_id,
                'outletName' => $visit->outlet?->name,
                'outletCode' => $visit->outlet?->code,
                'outletAddress' => $visit->outlet?->address,
                'latitude' => $visit->outlet?->latitude,
                'longitude' => $visit->outlet?->longitude,
                'distanceMeters' => (int) round(((float) $visit->distance_km) * 1000),
                'status' => $visit->status,
                'photoAttachmentId' => $photoId ? (string) $photoId : null,
                // Relative URL memastikan Flutter tetap memakai host ngrok/API
                // yang sedang aktif, bukan APP_URL server lokal.
                'photoUrl' => $photoId ? '/api/v1/attachments/'.$photoId.'/content' : null,
            ] : null;
        }
        if ($a->entity_type === PromotionSpecialRequest::class) {
            $special = PromotionSpecialRequest::with('outlet')->find($a->entity_id);
            $payload['promotionSpecialRequest'] = $special ? [
                'id' => (string) $special->id,
                'outletName' => $special->outlet?->name,
                'outletCode' => $special->outlet?->code,
                'requestedType' => $special->requested_type,
                'requestedValue' => $special->requested_value === null ? null : (float) $special->requested_value,
                'requestedQuantity' => $special->requested_quantity,
                'potentialRevenue' => $special->potential_revenue === null ? null : (float) $special->potential_revenue,
                'startsAt' => $special->starts_at?->toIso8601String(),
                'endsAt' => $special->ends_at?->toIso8601String(),
            ] : null;
        }

        return $payload;
    }
}
