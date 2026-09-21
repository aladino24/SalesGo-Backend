<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Attachment;
use App\Models\Branch;
use App\Models\CollectionActivity;
use App\Models\Outlet;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\ReceivableInvoice;
use App\Models\ReceivablePayment;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\DeepLinkService;
use App\Services\IdempotencyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentController extends ApiController
{
    private const METHODS = ['Cash', 'Transfer', 'VirtualAccount', 'Qris', 'Giro', 'Cheque', 'Deposit', 'CreditNote'];

    public function outletReceivables(Request $request, Outlet $outlet)
    {
        abort_unless($outlet->branch_id === $this->branchId($request->user()), 403);
        $invoices = ReceivableInvoice::query()
            ->where('branch_id', $outlet->branch_id)
            ->where('outlet_id', $outlet->id)
            ->orderByRaw('due_date < ? desc', [now()->toDateString()])
            ->orderBy('due_date')
            ->get();
        $outstanding = $invoices->sum(fn (ReceivableInvoice $invoice) => max(0, (float) $invoice->original_amount - (float) $invoice->paid_amount));
        $overdue = $invoices->filter(fn (ReceivableInvoice $invoice) => $invoice->due_date->isPast())
            ->sum(fn (ReceivableInvoice $invoice) => max(0, (float) $invoice->original_amount - (float) $invoice->paid_amount));

        return response()->json([
            'outlet' => ['id' => (string) $outlet->id, 'code' => $outlet->code, 'name' => $outlet->name, 'ownerName' => $outlet->owner_name, 'address' => $outlet->address],
            'summary' => [
                'creditLimit' => (float) $outlet->credit_limit,
                'creditStatus' => $outlet->credit_status,
                'outstandingAmount' => $outstanding,
                'overdueAmount' => $overdue,
                'availableCredit' => max(0, (float) $outlet->credit_limit - $outstanding),
                'openInvoices' => $invoices->filter(fn (ReceivableInvoice $invoice) => (float) $invoice->original_amount > (float) $invoice->paid_amount)->count(),
                'oldestInvoiceDate' => $invoices->first()?->invoice_date?->toDateString(),
                'updatedAt' => now()->toIso8601String(),
            ],
            'invoices' => $invoices->map(fn (ReceivableInvoice $invoice) => $this->invoicePayload($invoice))->values(),
        ]);
    }

    /**
     * Snapshot piutang untuk cache perangkat. Nilai tetap dibaca dari server
     * saat unduhan berlangsung; aplikasi tidak menjadikan cache sebagai sumber
     * saldo resmi ketika akan memverifikasi pembayaran.
     */
    public function receivablesSnapshot(Request $request)
    {
        $branchId = $this->branchId($request->user());
        $outlets = Outlet::query()
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'owner_name', 'address', 'credit_limit', 'credit_status']);
        $invoices = ReceivableInvoice::query()
            ->where('branch_id', $branchId)
            ->orderBy('due_date')
            ->get()
            ->groupBy('outlet_id');

        return response()->json([
            'generatedAt' => now()->toIso8601String(),
            'items' => $outlets->map(function (Outlet $outlet) use ($invoices) {
                $outletInvoices = $invoices->get($outlet->id, collect());
                $outstanding = $outletInvoices->sum(fn (ReceivableInvoice $invoice) => max(0, (float) $invoice->original_amount - (float) $invoice->paid_amount));
                $overdue = $outletInvoices
                    ->filter(fn (ReceivableInvoice $invoice) => $invoice->due_date->isPast())
                    ->sum(fn (ReceivableInvoice $invoice) => max(0, (float) $invoice->original_amount - (float) $invoice->paid_amount));

                return [
                    'outlet' => ['id' => (string) $outlet->id, 'code' => $outlet->code, 'name' => $outlet->name, 'ownerName' => $outlet->owner_name, 'address' => $outlet->address],
                    'summary' => [
                        'creditLimit' => (float) $outlet->credit_limit,
                        'creditStatus' => $outlet->credit_status,
                        'outstandingAmount' => $outstanding,
                        'overdueAmount' => $overdue,
                        'availableCredit' => max(0, (float) $outlet->credit_limit - $outstanding),
                        'openInvoices' => $outletInvoices->filter(fn (ReceivableInvoice $invoice) => (float) $invoice->original_amount > (float) $invoice->paid_amount)->count(),
                        'oldestInvoiceDate' => $outletInvoices->first()?->invoice_date?->toDateString(),
                        'updatedAt' => now()->toIso8601String(),
                    ],
                    'invoices' => $outletInvoices->map(fn (ReceivableInvoice $invoice) => $this->invoicePayload($invoice))->values(),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, IdempotencyService $idempotency, AuditLogger $audit)
    {
        return $idempotency->handle($request, function () use ($request, $audit) {
            $data = $request->validate([
                'id' => ['required', 'uuid'],
                'outletId' => ['required', 'integer'],
                'amount' => ['required', 'numeric', 'gt:0'],
                'components' => ['required', 'array', 'min:1'],
                'components.*.method' => ['required', 'in:'.implode(',', self::METHODS)],
                'components.*.amount' => ['required', 'numeric', 'gt:0'],
                'components.*.referenceNumber' => ['nullable', 'string', 'max:120'],
                'components.*.details' => ['nullable', 'array'],
                'components.*.attachmentIds' => ['nullable', 'array'],
                'components.*.attachmentIds.*' => ['integer'],
                'allocations' => ['nullable', 'array'],
                'allocations.*.invoiceId' => ['required', 'integer'],
                'allocations.*.amount' => ['required', 'numeric', 'gt:0'],
                'location.latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'location.longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'location.accuracyMeters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
                'payerName' => ['nullable', 'string', 'max:150'],
                'payerPhone' => ['nullable', 'string', 'max:50'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'confirmed' => ['accepted'],
            ]);
            $outlet = Outlet::whereKey($data['outletId'])->where('branch_id', $this->branchId($request->user()))->firstOrFail();
            $this->assertCollectionVisit($request, $outlet, $data['components']);
            if ($existing = PaymentTransaction::where('client_payment_id', $data['id'])->first()) {
                return response()->json($this->paymentPayload($existing->load(['components', 'allocations.invoice'])));
            }
            $componentTotal = round(collect($data['components'])->sum(fn (array $component) => (float) $component['amount']), 2);
            if (abs($componentTotal - (float) $data['amount']) > 0.009) {
                throw ValidationException::withMessages(['components' => 'Total metode pembayaran harus sama dengan jumlah pembayaran.']);
            }
            foreach ($data['components'] as $index => $component) {
                $method = $component['method'];
                $attachments = $component['attachmentIds'] ?? [];
                if ($method === 'Transfer' && $attachments === []) {
                    throw ValidationException::withMessages(["components.$index.attachmentIds" => 'Bukti transfer wajib dilampirkan.']);
                }
                if (in_array($method, ['VirtualAccount', 'Qris'], true)) {
                    throw ValidationException::withMessages(["components.$index.method" => 'VA dan QRIS memerlukan gateway pembayaran dan belum dapat dibuat manual.']);
                }
                $this->assertAttachments($attachments, $request->user()->id, $outlet->branch_id);
            }

            return DB::transaction(function () use ($data, $outlet, $request, $audit) {
                $allocations = $this->normalizeAllocations($outlet, $data['allocations'] ?? [], (float) $data['amount']);
                $allocated = round(collect($allocations)->sum('amount'), 2);
                $payment = PaymentTransaction::create([
                    'client_payment_id' => $data['id'],
                    'payment_number' => 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'branch_id' => $outlet->branch_id,
                    'outlet_id' => $outlet->id,
                    'received_by' => $request->user()->id,
                    'amount' => $data['amount'],
                    'allocated_amount' => $allocated,
                    'unallocated_amount' => max(0, (float) $data['amount'] - $allocated),
                    'status' => $this->initialStatus($data['components']),
                    'source' => 'mobile',
                    'latitude' => data_get($data, 'location.latitude'),
                    'longitude' => data_get($data, 'location.longitude'),
                    'accuracy_meters' => data_get($data, 'location.accuracyMeters'),
                    'occurred_at' => now(),
                    'submitted_at' => now(),
                    'notes' => $data['notes'] ?? null,
                    'metadata' => ['payerName' => $data['payerName'] ?? null, 'payerPhone' => $data['payerPhone'] ?? null],
                ]);
                foreach ($data['components'] as $component) {
                    $payment->components()->create(['method' => $component['method'], 'amount' => $component['amount'], 'status' => $payment->status, 'reference_number' => $component['referenceNumber'] ?? null, 'details' => $component['details'] ?? [], 'attachment_ids' => $component['attachmentIds'] ?? []]);
                }
                foreach ($allocations as $allocation) {
                    $payment->allocations()->create(['invoice_id' => $allocation['invoiceId'], 'amount' => $allocation['amount'], 'status' => 'PENDING']);
                }
                $audit->record($request->user()->id, $outlet->branch_id, 'payment_submitted', PaymentTransaction::class, $payment->id, ['paymentNumber' => $payment->payment_number, 'amount' => $payment->amount, 'methodCount' => count($data['components'])]);

                return response()->json($this->paymentPayload($payment->load(['components', 'allocations.invoice'])), 201);
            });
        });
    }

    public function index(Request $request)
    {
        $query = PaymentTransaction::with(['components', 'allocations.invoice', 'outlet:id,name,code'])
            ->where('branch_id', $this->branchId($request->user()));
        if (! in_array($request->user()->role->slug, ['branchManager', 'supervisor', 'it'], true)) {
            $query->where('received_by', $request->user()->id);
        }
        return response()->json($query->latest('occurred_at')->paginate(min(max((int) $request->query('limit', 20), 1), 100))->through(fn (PaymentTransaction $payment) => $this->paymentPayload($payment))->items());
    }

    public function verify(Request $request, PaymentTransaction $payment, AuditLogger $audit, NotificationService $notifications, DeepLinkService $links)
    {
        abort_unless($payment->branch_id === $this->branchId($request->user()), 403);
        abort_unless(in_array($request->user()->role->slug, ['branchManager', 'it', 'finance'], true), 403);
        $data = $request->validate(['status' => ['required', 'in:VERIFIED,REJECTED'], 'reason' => ['nullable', 'string', 'max:1000']]);

        return DB::transaction(function () use ($data, $payment, $request, $audit, $notifications, $links) {
            $locked = PaymentTransaction::with(['allocations.invoice', 'components'])->lockForUpdate()->findOrFail($payment->id);
            if (in_array($locked->status, ['VERIFIED', 'REJECTED', 'REVERSED'], true)) {
                throw ValidationException::withMessages(['status' => 'Pembayaran sudah memiliki keputusan akhir.']);
            }
            if ($data['status'] === 'VERIFIED') {
                foreach ($locked->allocations as $allocation) {
                    $invoice = ReceivableInvoice::lockForUpdate()->findOrFail($allocation->invoice_id);
                    $outstanding = (float) $invoice->original_amount - (float) $invoice->paid_amount;
                    if ($allocation->amount > $outstanding + 0.009) {
                        throw ValidationException::withMessages(['allocations' => "Faktur {$invoice->invoice_number} telah berubah atau sudah lunas. Tinjau ulang pembayaran."]);
                    }
                    $invoice->increment('paid_amount', $allocation->amount);
                    $allocation->update(['status' => 'VERIFIED']);
                    ReceivablePayment::create(['invoice_id' => $invoice->id, 'branch_id' => $invoice->branch_id, 'outlet_id' => $invoice->outlet_id, 'received_by' => $locked->received_by, 'amount' => $allocation->amount, 'paid_at' => now(), 'reference_number' => $locked->payment_number, 'notes' => 'Alokasi pembayaran '.$locked->payment_number]);
                }
                $locked->update(['status' => 'VERIFIED', 'verified_at' => now(), 'verified_by' => $request->user()->id, 'notes' => trim(($locked->notes ?? '').' '.($data['reason'] ?? ''))]);
            } else {
                $locked->allocations()->update(['status' => 'REJECTED']);
                $locked->update(['status' => 'REJECTED', 'verified_at' => now(), 'verified_by' => $request->user()->id, 'notes' => trim(($locked->notes ?? '').' '.($data['reason'] ?? ''))]);
            }
            $audit->record($request->user()->id, $locked->branch_id, 'payment_'.strtolower($data['status']), PaymentTransaction::class, $locked->id, ['reason' => $data['reason'] ?? null]);
            $receiver = $locked->receiver;
            if ($receiver) $notifications->notify($receiver, 'payment_'.$data['status'], $data['status'] === 'VERIFIED' ? 'Pembayaran terverifikasi' : 'Pembayaran ditolak', 'Pembayaran '.$locked->payment_number.' '.$data['status'].'.', PaymentTransaction::class, $locked->id, $links->outlet($locked->outlet_id));
            return response()->json($this->paymentPayload($locked->fresh()->load(['components', 'allocations.invoice'])));
        });
    }

    public function collectionActivity(Request $request, IdempotencyService $idempotency, AuditLogger $audit)
    {
        return $idempotency->handle($request, function () use ($request, $audit) {
            $data = $request->validate(['id' => ['required', 'uuid'], 'outletId' => ['required', 'integer'], 'type' => ['required', 'in:PROMISE_TO_PAY,COLLECTION_FAILED,INVOICE_DISPUTE'], 'reason' => ['nullable', 'string', 'max:200'], 'promiseDate' => ['nullable', 'date', 'after_or_equal:today'], 'promisedAmount' => ['nullable', 'numeric', 'gt:0'], 'invoiceIds' => ['nullable', 'array'], 'invoiceIds.*' => ['integer'], 'notes' => ['nullable', 'string', 'max:1000'], 'location.latitude' => ['nullable', 'numeric'], 'location.longitude' => ['nullable', 'numeric']]);
            if ($data['type'] === 'PROMISE_TO_PAY' && (! isset($data['promiseDate']) || ! isset($data['promisedAmount']))) throw ValidationException::withMessages(['promiseDate' => 'Tanggal dan nilai janji bayar wajib diisi.']);
            $outlet = Outlet::whereKey($data['outletId'])->where('branch_id', $this->branchId($request->user()))->firstOrFail();
            $activity = CollectionActivity::firstOrCreate(['client_activity_id' => $data['id']], ['branch_id' => $outlet->branch_id, 'outlet_id' => $outlet->id, 'created_by' => $request->user()->id, 'type' => $data['type'], 'reason' => $data['reason'] ?? null, 'promise_date' => $data['promiseDate'] ?? null, 'promised_amount' => $data['promisedAmount'] ?? null, 'invoice_ids' => $data['invoiceIds'] ?? [], 'latitude' => data_get($data, 'location.latitude'), 'longitude' => data_get($data, 'location.longitude'), 'notes' => $data['notes'] ?? null]);
            $audit->record($request->user()->id, $outlet->branch_id, 'collection_activity_created', CollectionActivity::class, $activity->id, ['type' => $activity->type]);
            return response()->json(['id' => (string) $activity->id, 'type' => $activity->type, 'status' => $activity->status], 201);
        });
    }

    private function normalizeAllocations(Outlet $outlet, array $requested, float $amount): array
    {
        $invoices = ReceivableInvoice::query()->where('branch_id', $outlet->branch_id)->where('outlet_id', $outlet->id)->lockForUpdate()->orderBy('due_date')->get()->keyBy('id');
        if ($requested === []) {
            $remaining = $amount;
            $requested = [];
            foreach ($invoices as $invoice) {
                $outstanding = max(0, (float) $invoice->original_amount - (float) $invoice->paid_amount);
                if ($outstanding <= 0 || $remaining <= 0) continue;
                $used = min($remaining, $outstanding);
                $requested[] = ['invoiceId' => $invoice->id, 'amount' => $used];
                $remaining -= $used;
            }
        }
        $total = 0.0;
        foreach ($requested as $index => $item) {
            $invoice = $invoices->get((int) $item['invoiceId']);
            if (! $invoice) throw ValidationException::withMessages(["allocations.$index.invoiceId" => 'Faktur tidak sesuai outlet.']);
            $outstanding = max(0, (float) $invoice->original_amount - (float) $invoice->paid_amount);
            if ((float) $item['amount'] > $outstanding + 0.009) throw ValidationException::withMessages(["allocations.$index.amount" => 'Alokasi melebihi sisa faktur.']);
            $total += (float) $item['amount'];
        }
        if ($total > $amount + 0.009) throw ValidationException::withMessages(['allocations' => 'Total alokasi tidak boleh melebihi jumlah pembayaran.']);
        return $requested;
    }

    private function initialStatus(array $components): string
    {
        $methods = collect($components)->pluck('method');
        if ($methods->every(fn ($method) => $method === 'Cash')) return 'WAITING_SETTLEMENT';
        return 'WAITING_VERIFICATION';
    }

    private function assertCollectionVisit(Request $request, Outlet $outlet, array $components): void
    {
        if (! config('salesgo.payment.require_check_in', true)) return;
        $active = Visit::query()
            ->where('branch_id', $outlet->branch_id)
            ->where('outlet_id', $outlet->id)
            ->where('sales_id', $request->user()->id)
            ->where('status', 'In Progress')
            ->latest('checked_in_at')
            ->first();
        if (! $active) {
            throw ValidationException::withMessages(['outletId' => 'Check-in outlet diperlukan sebelum mencatat pembayaran.']);
        }
        if (! collect($components)->contains(fn (array $component) => $component['method'] === 'Cash')) return;
        $radius = $outlet->geofence_radius_meters
            ?? Branch::find($outlet->branch_id)?->default_geofence_radius_meters
            ?? 100;
        if (($active->distance_km * 1000) > $radius) {
            throw ValidationException::withMessages(['components' => 'Pembayaran tunai wajib dicatat saat berada dalam radius outlet.']);
        }
    }

    private function assertAttachments(array $attachmentIds, int $userId, int $branchId): void
    {
        if ($attachmentIds === []) return;
        $count = Attachment::whereIn('id', $attachmentIds)->where('uploaded_by', $userId)->where('branch_id', $branchId)->where('status', 'Finalized')->count();
        if ($count !== count(array_unique($attachmentIds))) throw ValidationException::withMessages(['attachmentIds' => 'Bukti pembayaran tidak valid atau belum selesai diunggah.']);
    }

    private function invoicePayload(ReceivableInvoice $invoice): array
    {
        $outstanding = max(0, (float) $invoice->original_amount - (float) $invoice->paid_amount);
        return ['id' => (string) $invoice->id, 'invoiceNumber' => $invoice->invoice_number, 'invoiceDate' => $invoice->invoice_date->toDateString(), 'dueDate' => $invoice->due_date->toDateString(), 'originalAmount' => (float) $invoice->original_amount, 'paidAmount' => (float) $invoice->paid_amount, 'outstandingAmount' => $outstanding, 'daysOverdue' => $outstanding > 0 && $invoice->due_date->isPast() ? $invoice->due_date->diffInDays(now()) : 0, 'status' => $outstanding <= 0 ? 'Lunas' : ($invoice->due_date->isPast() ? 'Lewat Jatuh Tempo' : ((float) $invoice->paid_amount > 0 ? 'Dibayar Sebagian' : 'Terbuka'))];
    }

    private function paymentPayload(PaymentTransaction $payment): array
    {
        return ['id' => (string) $payment->id, 'clientPaymentId' => $payment->client_payment_id, 'paymentNumber' => $payment->payment_number, 'outletId' => (string) $payment->outlet_id, 'amount' => (float) $payment->amount, 'allocatedAmount' => (float) $payment->allocated_amount, 'unallocatedAmount' => (float) $payment->unallocated_amount, 'status' => $payment->status, 'occurredAt' => $payment->occurred_at->toIso8601String(), 'notes' => $payment->notes, 'components' => $payment->components->map(fn ($component) => ['method' => $component->method, 'amount' => (float) $component->amount, 'status' => $component->status, 'referenceNumber' => $component->reference_number, 'attachmentIds' => $component->attachment_ids ?? []])->values(), 'allocations' => $payment->allocations->map(fn ($allocation) => ['invoiceId' => (string) $allocation->invoice_id, 'invoiceNumber' => $allocation->invoice?->invoice_number, 'amount' => (float) $allocation->amount, 'status' => $allocation->status])->values()];
    }
}
