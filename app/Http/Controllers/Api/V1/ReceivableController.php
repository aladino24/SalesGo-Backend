<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Outlet;
use App\Models\ReceivableInvoice;
use App\Models\ReceivablePayment;
use App\Services\IdempotencyService;
use Illuminate\Http\Request;

class ReceivableController extends ApiController
{
    public function index(Request $request, Outlet $outlet)
    {
        abort_unless($outlet->branch_id === $this->branchId($request->user()), 403);

        return response()->json(ReceivableInvoice::with('payments')->where('branch_id', $outlet->branch_id)->where('outlet_id', $outlet->id)->latest('due_date')->get()->map(fn ($invoice) => $this->payload($invoice)));
    }

    public function pay(Request $request, ReceivableInvoice $invoice, IdempotencyService $idempotency)
    {
        abort_unless($invoice->branch_id === $this->branchId($request->user()), 403);

        return $idempotency->handle($request, function () use ($request, $invoice) {
            $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'referenceNumber' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500'], 'paidAt' => ['nullable', 'date']]);
            $locked = ReceivableInvoice::lockForUpdate()->findOrFail($invoice->id);
            $outstanding = (float) $locked->original_amount - (float) $locked->paid_amount;
            if ((float) $data['amount'] > $outstanding) {
                return response()->json(['message' => 'Pembayaran melebihi saldo invoice.', 'code' => 'PAYMENT_EXCEEDS_OUTSTANDING'], 422);
            }
            $payment = ReceivablePayment::create(['invoice_id' => $locked->id, 'branch_id' => $locked->branch_id, 'outlet_id' => $locked->outlet_id, 'received_by' => $request->user()->id, 'amount' => $data['amount'], 'paid_at' => $data['paidAt'] ?? now(), 'reference_number' => $data['referenceNumber'] ?? null, 'notes' => $data['notes'] ?? null]);
            $locked->increment('paid_amount', $payment->amount);

            return response()->json(['invoice' => $this->payload($locked->fresh()), 'payment' => ['id' => (string) $payment->id, 'amount' => (float) $payment->amount, 'paidAt' => $payment->paid_at->toIso8601String(), 'referenceNumber' => $payment->reference_number]], 201);
        });
    }

    private function payload(ReceivableInvoice $invoice): array
    {
        $outstanding = (float) $invoice->original_amount - (float) $invoice->paid_amount;

        return ['id' => (string) $invoice->id, 'salesOrderId' => $invoice->sales_order_id ? (string) $invoice->sales_order_id : null, 'invoiceNumber' => $invoice->invoice_number, 'invoiceDate' => $invoice->invoice_date->toDateString(), 'dueDate' => $invoice->due_date->toDateString(), 'originalAmount' => (float) $invoice->original_amount, 'paidAmount' => (float) $invoice->paid_amount, 'outstandingAmount' => $outstanding, 'isOverdue' => $outstanding > 0 && $invoice->due_date->isPast(), 'payments' => $invoice->relationLoaded('payments') ? $invoice->payments->map(fn ($payment) => ['id' => (string) $payment->id, 'amount' => (float) $payment->amount, 'paidAt' => $payment->paid_at->toIso8601String(), 'referenceNumber' => $payment->reference_number, 'notes' => $payment->notes])->values() : []];
    }
}
