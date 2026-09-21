<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\DeliveryNote;
use App\Models\Journey;
use App\Services\AuditLogger;
use App\Services\DeepLinkService;
use App\Services\DeliveryStockService;
use App\Services\IdempotencyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class DeliveryNoteController extends ApiController
{
    public function index(Request $request)
    {
        return response()->json(DeliveryNote::where('branch_id', $this->branchId($request->user()))
            ->where('created_by', $request->user()->id)->latest()->get()->map(fn ($note) => $this->payload($note)));
    }

    public function store(Request $request, IdempotencyService $idempotency, DeliveryStockService $stock)
    {
        return $idempotency->handle($request, function () use ($request, $stock) {
            $data = $request->validate([
                'number' => ['required', 'string', 'max:80'],
                'destination' => ['required', 'string', 'max:200'],
                'journeyId' => ['nullable'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.productId' => ['required'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
            ]);
            $branchId = $this->branchId($request->user());
            $journeyId = null;
            if (! empty($data['journeyId'])) {
                $journey = Journey::where('branch_id', $branchId)->where(fn ($query) => $query->where('id', $data['journeyId'])->orWhere('client_journey_id', $data['journeyId']))->firstOrFail();
                $journeyId = $journey->id;
            }
            $note = DeliveryNote::firstOrCreate(
                ['number' => $data['number']],
                ['branch_id' => $branchId, 'journey_id' => $journeyId, 'created_by' => $request->user()->id, 'destination' => $data['destination'], 'items' => $stock->normalizeItems($branchId, $data['items']), 'status' => 'Draft', 'approval_status' => 'Not Submitted']
            );
            if ($note->branch_id !== $branchId) {
                abort(403);
            }

            return response()->json($this->payload($note), 201);
        });
    }

    public function submit(Request $request, DeliveryNote $deliveryNote, IdempotencyService $idempotency, DeliveryStockService $stock, NotificationService $notifications, DeepLinkService $links, AuditLogger $audit)
    {
        abort_unless($deliveryNote->created_by === $request->user()->id && $deliveryNote->branch_id === $this->branchId($request->user()), 403);

        return $idempotency->handle($request, function () use ($request, $deliveryNote, $stock, $notifications, $links, $audit) {
            if ($deliveryNote->status !== 'Draft') {
                return response()->json(['message' => 'Surat jalan tidak dapat diajukan dari status saat ini.', 'code' => 'DELIVERY_NOTE_STATUS_INVALID'], 422);
            }
            $stock->reserve($deliveryNote->branch_id, $deliveryNote->items);
            $deliveryNote->update(['status' => 'Submitted', 'approval_status' => 'Waiting Approval', 'stock_reserved_at' => now()]);
            $approval = Approval::firstOrCreate(
                ['branch_id' => $deliveryNote->branch_id, 'type' => 'delivery_note', 'entity_type' => DeliveryNote::class, 'entity_id' => $deliveryNote->id],
                ['requested_by' => $request->user()->id, 'reason' => 'Pengajuan surat jalan '.$deliveryNote->number]
            );
            if ($approval->wasRecentlyCreated) {
                $notifications->notifyBranchManagers($deliveryNote->branch_id, 'approval_request', 'Approval surat jalan', 'Surat jalan '.$deliveryNote->number.' menunggu keputusan.', DeliveryNote::class, $deliveryNote->id, $links->deliveryNote($deliveryNote->id));
            }
            $audit->record($request->user()->id, $deliveryNote->branch_id, 'delivery_note_stock_reserved', DeliveryNote::class, $deliveryNote->id, ['number' => $deliveryNote->number]);

            return response()->json($this->payload($deliveryNote));
        });
    }

    public function use(Request $request, DeliveryNote $deliveryNote, IdempotencyService $idempotency, DeliveryStockService $stock, AuditLogger $audit)
    {
        abort_unless($deliveryNote->created_by === $request->user()->id && $deliveryNote->branch_id === $this->branchId($request->user()), 403);

        return $idempotency->handle($request, function () use ($request, $deliveryNote, $stock, $audit) {
            if ($deliveryNote->approval_status !== 'Approved') {
                return response()->json(['message' => 'Surat jalan harus disetujui Branch Manager sebelum digunakan.', 'code' => 'DELIVERY_NOTE_APPROVAL_REQUIRED'], 422);
            }
            if ($deliveryNote->status !== 'Approved') {
                return response()->json(['message' => 'Surat jalan tidak dapat digunakan dari status saat ini.', 'code' => 'DELIVERY_NOTE_STATUS_INVALID'], 422);
            }
            $stock->consume($deliveryNote->branch_id, $deliveryNote->items);
            $deliveryNote->update(['status' => 'Completed', 'used_at' => now(), 'stock_consumed_at' => now()]);
            $audit->record($request->user()->id, $deliveryNote->branch_id, 'delivery_note_stock_consumed', DeliveryNote::class, $deliveryNote->id, ['number' => $deliveryNote->number]);

            return response()->json($this->payload($deliveryNote));
        });
    }

    public function cancel(Request $request, DeliveryNote $deliveryNote, IdempotencyService $idempotency, DeliveryStockService $stock, AuditLogger $audit)
    {
        abort_unless($deliveryNote->created_by === $request->user()->id && $deliveryNote->branch_id === $this->branchId($request->user()), 403);

        return $idempotency->handle($request, function () use ($request, $deliveryNote, $stock, $audit) {
            if (! in_array($deliveryNote->status, ['Draft', 'Submitted'], true)) {
                return response()->json(['message' => 'Surat jalan tidak dapat dibatalkan dari status saat ini.', 'code' => 'DELIVERY_NOTE_STATUS_INVALID'], 422);
            }
            if ($deliveryNote->stock_reserved_at && ! $deliveryNote->stock_released_at) {
                $stock->release($deliveryNote->branch_id, $deliveryNote->items);
                $deliveryNote->stock_released_at = now();
            }
            $deliveryNote->fill(['status' => 'Cancelled', 'approval_status' => 'Cancelled'])->save();
            Approval::where('entity_type', DeliveryNote::class)->where('entity_id', $deliveryNote->id)->where('status', 'Pending')->update(['status' => 'Cancelled', 'comment' => 'Dibatalkan oleh pengaju', 'decided_at' => now()]);
            $audit->record($request->user()->id, $deliveryNote->branch_id, 'delivery_note_cancelled', DeliveryNote::class, $deliveryNote->id, ['number' => $deliveryNote->number]);

            return response()->json($this->payload($deliveryNote));
        });
    }

    private function payload(DeliveryNote $note): array
    {
        return ['id' => (string) $note->id, 'number' => $note->number, 'journeyId' => $note->journey_id ? (string) $note->journey_id : null, 'destination' => $note->destination, 'items' => $note->items, 'status' => $note->status, 'approvalStatus' => $note->approval_status, 'stockReservedAt' => $note->stock_reserved_at?->toIso8601String(), 'stockReleasedAt' => $note->stock_released_at?->toIso8601String(), 'stockConsumedAt' => $note->stock_consumed_at?->toIso8601String(), 'createdAt' => $note->created_at->toIso8601String(), 'usedAt' => $note->used_at?->toIso8601String()];
    }
}
