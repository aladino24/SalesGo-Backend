<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\Attachment;
use App\Models\Outlet;
use App\Models\Promotion;
use App\Models\PromotionMerchandisingProof;
use App\Models\PromotionSpecialRequest;
use App\Models\PromotionUsage;
use App\Services\AuditLogger;
use App\Services\DeepLinkService;
use App\Services\IdempotencyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PromotionControlController extends ApiController
{
    public function dashboard(Request $request)
    {
        abort_unless(in_array($request->user()->role->slug, ['supervisor', 'branchManager', 'it', 'marketing'], true), 403);
        $branchId = $this->branchId($request->user());
        $usage = PromotionUsage::where('branch_id', $branchId);

        return response()->json([
            'activePrograms' => Promotion::where('branch_id', $branchId)->where('is_active', true)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->count(),
            'reservedBenefits' => (float) (clone $usage)->where('status', 'Reserved')->sum('benefit_amount'),
            'consumedBenefits' => (float) (clone $usage)->where('status', 'Consumed')->sum('benefit_amount'),
            'participatingOutlets' => (clone $usage)->whereIn('status', ['Reserved', 'Consumed'])->distinct('outlet_id')->count('outlet_id'),
            'pendingSpecialRequests' => PromotionSpecialRequest::where('branch_id', $branchId)->whereIn('status', ['Submitted', 'Waiting Approval'])->count(),
            'pendingProofs' => PromotionMerchandisingProof::where('branch_id', $branchId)->where('status', 'Submitted')->count(),
            'programs' => Promotion::where('branch_id', $branchId)->orderByDesc('updated_at')->limit(20)->get()->map(fn (Promotion $item) => [
                'id' => (string) $item->id, 'code' => $item->code, 'name' => $item->name,
                'quotaTotal' => $item->quota_total, 'usedQuota' => $item->used_quota,
                'budgetAmount' => $item->budget_amount === null ? null : (float) $item->budget_amount,
                'usedBudget' => (float) $item->used_budget, 'status' => $item->status,
            ]),
        ]);
    }

    public function specialRequest(Request $request, IdempotencyService $idempotency, NotificationService $notifications, DeepLinkService $links, AuditLogger $audit)
    {
        return $idempotency->handle($request, function () use ($request, $notifications, $links, $audit) {
            $data = $request->validate([
                'outletId' => ['required', 'integer'], 'promotionId' => ['nullable', 'integer'], 'productId' => ['nullable', 'integer'],
                'requestedType' => ['required', 'in:Fixed,Percentage,Bonus,Bundle,Gift'], 'requestedValue' => ['nullable', 'numeric', 'min:0'],
                'requestedQuantity' => ['nullable', 'integer', 'min:1'], 'potentialRevenue' => ['nullable', 'numeric', 'min:0'],
                'reason' => ['required', 'string', 'min:10', 'max:1000'], 'attachmentIds' => ['nullable', 'array', 'max:5'], 'attachmentIds.*' => ['integer'],
                'startsAt' => ['required', 'date'], 'endsAt' => ['required', 'date', 'after_or_equal:startsAt'],
            ]);
            $branchId = $this->branchId($request->user());
            $outlet = Outlet::whereKey($data['outletId'])->where('branch_id', $branchId)->firstOrFail();
            if (! empty($data['promotionId'])) Promotion::whereKey($data['promotionId'])->where('branch_id', $branchId)->firstOrFail();
            $attachments = $data['attachmentIds'] ?? [];
            if ($attachments !== [] && Attachment::whereIn('id', $attachments)->where('branch_id', $branchId)->where('uploaded_by', $request->user()->id)->where('status', 'Finalized')->count() !== count(array_unique($attachments))) {
                return response()->json(['message' => 'Lampiran promosi belum valid atau belum selesai diunggah.', 'code' => 'PROMOTION_ATTACHMENT_INVALID'], 422);
            }
            $special = PromotionSpecialRequest::create([
                'branch_id' => $branchId, 'outlet_id' => $outlet->id, 'requested_by' => $request->user()->id,
                'promotion_id' => $data['promotionId'] ?? null, 'product_id' => $data['productId'] ?? null,
                'requested_type' => $data['requestedType'], 'requested_value' => $data['requestedValue'] ?? null,
                'requested_quantity' => $data['requestedQuantity'] ?? null, 'potential_revenue' => $data['potentialRevenue'] ?? null,
                'reason' => $data['reason'], 'attachment_ids' => $attachments, 'starts_at' => $data['startsAt'], 'ends_at' => $data['endsAt'], 'status' => 'Waiting Approval',
            ]);
            $approval = Approval::create(['branch_id' => $branchId, 'type' => 'promotion_special_request', 'entity_type' => PromotionSpecialRequest::class, 'entity_id' => $special->id, 'requested_by' => $request->user()->id, 'reason' => 'Pengajuan promosi khusus untuk '.$outlet->name, 'status' => 'Pending']);
            $notifications->notifyBranchManagers($branchId, 'approval_request', 'Pengajuan promosi khusus', $request->user()->name.' mengajukan promosi khusus untuk '.$outlet->name.'.', Approval::class, $approval->id, $links->approval($approval->id));
            $audit->record($request->user()->id, $branchId, 'promotion_special_requested', PromotionSpecialRequest::class, $special->id, ['outletId' => $outlet->id, 'requestedType' => $special->requested_type]);

            return response()->json($this->specialPayload($special), 201);
        });
    }

    public function proofs(Request $request, Promotion $promotion, IdempotencyService $idempotency, AuditLogger $audit)
    {
        return $idempotency->handle($request, function () use ($request, $promotion, $audit) {
            $branchId = $this->branchId($request->user());
            abort_unless($promotion->branch_id === $branchId, 403);
            $data = $request->validate(['outletId' => ['required', 'integer'], 'attachmentIds' => ['required', 'array', 'min:1', 'max:8'], 'attachmentIds.*' => ['integer'], 'notes' => ['nullable', 'string', 'max:1000'], 'latitude' => ['nullable', 'numeric'], 'longitude' => ['nullable', 'numeric']]);
            Outlet::whereKey($data['outletId'])->where('branch_id', $branchId)->firstOrFail();
            $valid = Attachment::whereIn('id', $data['attachmentIds'])->where('branch_id', $branchId)->where('uploaded_by', $request->user()->id)->where('status', 'Finalized')->count();
            if ($valid !== count(array_unique($data['attachmentIds']))) return response()->json(['message' => 'Foto bukti belum valid atau belum selesai diunggah.', 'code' => 'PROMOTION_PROOF_INVALID'], 422);
            $proof = PromotionMerchandisingProof::create(['promotion_id' => $promotion->id, 'branch_id' => $branchId, 'outlet_id' => $data['outletId'], 'submitted_by' => $request->user()->id, 'attachment_ids' => $data['attachmentIds'], 'notes' => $data['notes'] ?? null, 'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null]);
            $audit->record($request->user()->id, $branchId, 'promotion_merchandising_proof_submitted', PromotionMerchandisingProof::class, $proof->id, ['promotionId' => $promotion->id]);
            return response()->json(['id' => (string) $proof->id, 'status' => $proof->status], 201);
        });
    }

    private function specialPayload(PromotionSpecialRequest $request): array
    {
        return ['id' => (string) $request->id, 'outletId' => (string) $request->outlet_id, 'status' => $request->status, 'requestedType' => $request->requested_type, 'requestedValue' => $request->requested_value === null ? null : (float) $request->requested_value, 'reason' => $request->reason, 'startsAt' => $request->starts_at->toIso8601String(), 'endsAt' => $request->ends_at->toIso8601String()];
    }
}
