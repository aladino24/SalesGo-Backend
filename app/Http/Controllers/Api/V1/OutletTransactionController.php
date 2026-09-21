<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\Attachment;
use App\Models\Outlet;
use App\Models\OutletShipToLocation;
use App\Models\OutletTransaction;
use App\Models\ReceivableInvoice;
use App\Models\User;
use App\Models\Visit;
use App\Services\DeepLinkService;
use App\Services\IdempotencyService;
use App\Services\NotificationService;
use App\Services\PromotionService;
use App\Services\ReceiptPdfService;
use App\Services\SalesOrderCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OutletTransactionController extends ApiController
{
    public function store(Request $request, IdempotencyService $idempotency, SalesOrderCalculator $calculator, PromotionService $promotions, string $type)
    {
        return $idempotency->handle($request, function () use ($request, $type, $calculator, $promotions) {
            $data = $request->validate([
                'id' => ['nullable', 'string', 'max:100'],
                'outletId' => ['required'],
                'items' => ['nullable', 'array'],
                'amount' => ['nullable', 'numeric', 'min:0'],
                'total' => ['nullable', 'numeric', 'min:0'],
                'reason' => [in_array($type, ['return', 'gift', 'supplier_return'], true) ? 'required' : 'nullable', 'string', 'max:500'],
                'condition' => [$type === 'return' ? 'required' : 'nullable', 'in:Good,Damaged,Expired,Other'],
                'attachmentIds' => [$type === 'return' ? 'required' : 'nullable', 'array', 'min:1'],
                'attachmentIds.*' => ['integer'],
                'supplierName' => [$type === 'supplier_return' ? 'required' : 'nullable', 'string', 'max:150'],
                'metadata' => ['nullable', 'array'],
                'metadata.paymentType' => ['nullable', 'in:Cash,Credit'],
                'metadata.paymentTermDays' => ['nullable', 'integer', 'min:1', 'max:365'],
                'shipTo' => ['nullable', 'array'],
                'shipTo.id' => ['nullable', 'integer'],
                'promotionCode' => ['nullable', 'string', 'max:50'],
            ]);
            $outlet = Outlet::whereKey($data['outletId'])->where('branch_id', $this->branchId($request->user()))->firstOrFail();
            $hasActiveVisit = Visit::query()
                ->where('branch_id', $outlet->branch_id)
                ->where('outlet_id', $outlet->id)
                ->where('sales_id', $request->user()->id)
                ->where('status', 'In Progress')
                ->exists();
            if (! $hasActiveVisit) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'outletId' => 'Check-in outlet diperlukan sebelum membuat transaksi.',
                ]);
            }
            $clientId = $data['id'] ?? null;
            if ($clientId && ($existing = OutletTransaction::where('client_transaction_id', $clientId)->first())) {
                return response()->json($this->payload($existing));
            }
            $approval = in_array($type, ['return', 'gift', 'supplier_return'], true) ? 'Pending' : null;
            $items = $data['items'] ?? [];
            $amount = (float) ($data['amount'] ?? $data['total'] ?? 0);
            $metadata = $data['metadata'] ?? [];
            if ($type === 'sales_order') {
                $promotion = $promotions->resolve($outlet->branch_id, $data['promotionCode'] ?? null);
                $calculated = $calculator->normalize(
                    $outlet->branch_id,
                    $items,
                    $promotion,
                    false,
                    $request->user()->role->slug === 'sales'
                        ? $request->user()->divisions()->pluck('sales_divisions.id')->whenEmpty(fn ($ids) => $ids->push($request->user()->sales_division_id))->all()
                        : [],
                );
                $items = $calculated['items'];
                $amount = $calculated['total'];
                $grossAmount = array_sum(array_map(fn (array $item) => (float) ($item['subtotal'] ?? 0) + (float) ($item['discount'] ?? 0), $items));
                if ($promotion) {
                    $promotions->assertEligible($promotion, $outlet, $request->user(), $grossAmount);
                }
                $this->assertSalesOrderPolicy($items, $amount);
                $paymentType = $metadata['paymentType'] ?? 'Cash';
                if (! in_array($paymentType, ['Cash', 'Credit'], true)) {
                    return response()->json(['message' => 'Tipe pembayaran tidak valid.', 'code' => 'PAYMENT_TYPE_INVALID'], 422);
                }
                $metadata['paymentType'] = $paymentType;
                $metadata['shipTo'] = $this->resolveShipTo(
                    $outlet,
                    (int) data_get($data, 'shipTo.id', 0),
                );
                if ($promotion) {
                    $metadata['promotion'] = ['code' => $promotion->code, 'name' => $promotion->name, 'benefitAmount' => round($grossAmount - $amount, 2)];
                }
            } elseif (in_array($type, ['purchase', 'return', 'gift', 'supplier_return'], true)) {
                $calculated = $calculator->normalizeMovementItems($outlet->branch_id, $items);
                $items = $calculated['items'];
                $amount = $type === 'gift' ? 0.0 : $calculated['total'];
            }
            if ($type === 'return') {
                $attachmentIds = $data['attachmentIds'];
                $this->assertFinalizedAttachments($attachmentIds, $request->user()->id, $outlet->branch_id);
                $metadata = array_merge($metadata, [
                    'condition' => $data['condition'],
                    'attachmentIds' => array_map('strval', $attachmentIds),
                ]);
            }
            if ($type === 'supplier_return') {
                $metadata['supplierName'] = $data['supplierName'];
            }
            $isPurchase = $type === 'purchase';
            $transaction = OutletTransaction::create(['client_transaction_id' => $clientId, 'document_number' => $this->documentNumber($type), 'branch_id' => $outlet->branch_id, 'outlet_id' => $outlet->id, 'sales_id' => $request->user()->id, 'type' => $type, 'status' => $isPurchase ? 'Committed' : 'Submitted', 'approval_status' => $approval, 'reason' => $data['reason'] ?? null, 'amount' => $amount, 'items' => $items, 'metadata' => $metadata, 'occurred_at' => now(), 'committed_at' => $isPurchase ? now() : null]);
            if ($isPurchase) {
                $calculator->adjustStock($outlet->branch_id, $items, 1);
            }
            if ($type === 'sales_order' && isset($promotion)) {
                $promotions->reserve($promotion, $transaction, (float) data_get($metadata, 'promotion.benefitAmount', 0));
            }
            if ($approval) {
                Approval::create(['branch_id' => $outlet->branch_id, 'type' => $type, 'entity_type' => OutletTransaction::class, 'entity_id' => $transaction->id, 'requested_by' => $request->user()->id, 'reason' => $data['reason'] ?? 'Menunggu approval']);
            }

            return response()->json($this->payload($transaction), 201);
        });
    }

    public function history(Request $request, Outlet $outlet)
    {
        abort_unless($outlet->branch_id === $this->branchId($request->user()), 403);

        return response()->json(['data' => OutletTransaction::where('outlet_id', $outlet->id)->latest('occurred_at')->paginate(min((int) $request->query('limit', 20), 100))->through(fn ($item) => $this->payload($item))->items()]);
    }

    public function changeSalesOrderStatus(Request $request, OutletTransaction $transaction, IdempotencyService $idempotency, SalesOrderCalculator $calculator, PromotionService $promotions, NotificationService $notifications, DeepLinkService $links)
    {
        abort_unless($transaction->branch_id === $this->branchId($request->user()) && $transaction->type === 'sales_order', 403);
        abort_unless(in_array($request->user()->role->slug, ['supervisor', 'branchManager'], true), 403);

        return $idempotency->handle($request, function () use ($request, $transaction, $calculator, $notifications, $links) {
            $data = $request->validate(['status' => ['required', 'in:Committed,Completed,Cancelled'], 'reason' => ['nullable', 'string', 'max:500']]);
            if ($transaction->status === 'Cancelled') {
                return response()->json(['message' => 'Order yang dibatalkan tidak dapat diubah.', 'code' => 'ORDER_STATUS_FINAL'], 422);
            }
            $locked = OutletTransaction::lockForUpdate()->findOrFail($transaction->id);
            if (in_array($data['status'], ['Committed', 'Completed'], true) && ! in_array($locked->status, ['Committed', 'Completed'], true)) {
                $calculator->commitStock($locked->branch_id, $locked->items ?? []);
                $promotions->consumeFor($locked);
                $locked->committed_at = now();
                $this->createCreditInvoice($locked);
            }
            if ($data['status'] === 'Cancelled' && $locked->status !== 'Cancelled') {
                $promotions->releaseFor($locked);
            }
            $locked->update(['status' => $data['status'], 'reason' => $data['reason'] ?? $locked->reason]);
            if ($locked->sales_id !== $request->user()->id) {
                $sales = User::find($locked->sales_id);
                if ($sales) {
                    $notifications->notify($sales, 'sales_order_status', 'Status sales order diperbarui', 'Order '.$locked->document_number.' berstatus '.$locked->status.'.', OutletTransaction::class, $locked->id, $links->outlet($locked->outlet_id));
                }
            }

            return response()->json($this->payload($locked));
        });
    }

    public function receipt(Request $request, OutletTransaction $transaction)
    {
        abort_unless($transaction->branch_id === $this->branchId($request->user()) && $transaction->type === 'sales_order', 403);
        abort_unless(in_array($transaction->status, ['Committed', 'Completed'], true), 422);

        $invoice = ReceivableInvoice::where('sales_order_id', $transaction->id)->first();

        return response()->json(['receiptNumber' => $transaction->document_number, 'order' => $this->payload($transaction), 'invoice' => $invoice ? ['invoiceNumber' => $invoice->invoice_number, 'dueDate' => $invoice->due_date->toDateString(), 'outstandingAmount' => (float) $invoice->original_amount - (float) $invoice->paid_amount] : null, 'issuedAt' => $transaction->committed_at?->toIso8601String()]);
    }

    public function receiptPdf(Request $request, OutletTransaction $transaction, ReceiptPdfService $receipts)
    {
        abort_unless($transaction->branch_id === $this->branchId($request->user()) && $transaction->type === 'sales_order', 403);
        abort_unless(in_array($transaction->status, ['Committed', 'Completed'], true), 422);
        $outlet = Outlet::findOrFail($transaction->outlet_id);

        return response($receipts->make($transaction, $outlet), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$transaction->document_number.'.pdf"',
        ]);
    }

    private function payload(OutletTransaction $t): array
    {
        return ['id' => $t->client_transaction_id ?? (string) $t->id, 'documentNumber' => $t->document_number, 'outletId' => (string) $t->outlet_id, 'type' => $t->type, 'status' => $t->status, 'approvalStatus' => $t->approval_status, 'amount' => (float) $t->amount, 'items' => $t->items ?? [], 'reason' => $t->reason, 'metadata' => $t->metadata ?? [], 'createdAt' => $t->occurred_at->toIso8601String(), 'committedAt' => $t->committed_at?->toIso8601String()];
    }

    /** @param array<int, int> $attachmentIds */
    private function assertFinalizedAttachments(array $attachmentIds, int $userId, int $branchId): void
    {
        $validCount = Attachment::whereIn('id', $attachmentIds)
            ->where('uploaded_by', $userId)
            ->where('branch_id', $branchId)
            ->where('status', 'Finalized')
            ->count();
        if ($validCount !== count(array_unique($attachmentIds))) {
            throw ValidationException::withMessages(['attachmentIds' => 'Lampiran retur tidak valid atau belum selesai diproses.']);
        }
    }

    private function createCreditInvoice(OutletTransaction $order): void
    {
        if (($order->metadata['paymentType'] ?? 'Cash') !== 'Credit') {
            return;
        }
        ReceivableInvoice::firstOrCreate(
            ['sales_order_id' => $order->id],
            [
                'branch_id' => $order->branch_id,
                'outlet_id' => $order->outlet_id,
                'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays((int) ($order->metadata['paymentTermDays'] ?? 30))->toDateString(),
                'original_amount' => $order->amount,
                'paid_amount' => 0,
            ],
        );
    }

    /** @param array<int, array<string, mixed>> $items */
    private function assertSalesOrderPolicy(array $items, float $amount): void
    {
        // Kebijakan order diberlakukan pada satuan dasar agar 1 dus tidak
        // dihitung sama dengan 1 pcs.
        $units = array_sum(array_map(fn (array $item) => (int) ($item['baseQuantity'] ?? $item['quantity'] ?? 0), $items));
        $minimumUnits = max(1, (int) config('salesgo.order.minimum_units', 1));
        $maximumUnits = max($minimumUnits, (int) config('salesgo.order.maximum_units', 1000));
        $minimumAmount = max(0, (float) config('salesgo.order.minimum_amount', 0));
        $maximumAmount = max(0, (float) config('salesgo.order.maximum_amount', 0));

        if ($units < $minimumUnits || $units > $maximumUnits) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => "Total unit order harus antara {$minimumUnits} dan {$maximumUnits}.",
            ]);
        }
        if ($amount < $minimumAmount || ($maximumAmount > 0 && $amount > $maximumAmount)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'total' => 'Nominal order tidak memenuhi batas minimum/maksimum yang berlaku.',
            ]);
        }
    }

    private function documentNumber(string $type): string
    {
        $prefix = match ($type) {
            'sales_order' => 'SO',
            'purchase' => 'PO',
            'return' => 'RT',
            'gift' => 'GF',
            'supplier_return' => 'SR',
            default => 'NT',
        };

        return $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    /**
     * Simpan salinan alamat tujuan di metadata order agar alamat master yang
     * berubah kemudian tidak mengubah tujuan pengiriman order historis.
     */
    private function resolveShipTo(Outlet $outlet, int $shipToId): array
    {
        if ($shipToId > 0) {
            $location = OutletShipToLocation::query()
                ->whereKey($shipToId)
                ->where('branch_id', $outlet->branch_id)
                ->where('outlet_id', $outlet->id)
                ->where('is_active', true)
                ->firstOrFail();

            return [
                'id' => (string) $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'address' => $location->address,
                'contactName' => $location->contact_name,
                'phone' => $location->phone,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
            ];
        }

        return [
            'id' => null,
            'code' => $outlet->code,
            'name' => $outlet->name,
            'address' => $outlet->address,
            'contactName' => $outlet->contact_name,
            'phone' => $outlet->phone,
            'latitude' => $outlet->latitude,
            'longitude' => $outlet->longitude,
        ];
    }
}
