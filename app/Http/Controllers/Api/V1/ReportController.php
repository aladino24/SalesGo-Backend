<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\OutletTransaction;
use App\Models\ReportArchive;
use App\Models\Visit;
use App\Models\Branch;
use App\Services\BranchAccessService;
use App\Services\ReceiptPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    public function transactions(Request $request)
    {
        $this->ensureReporter($request);
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'salesId' => ['nullable', 'integer'],
            'outletId' => ['nullable', 'integer'],
            'type' => ['nullable', 'in:sales_order,purchase,return,supplier_return,gift,note'],
            'status' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->filteredTransactions($request, $data)->with(['outlet:id,name,code', 'sales:id,name,employee_code'])->latest('occurred_at');
        $paginator = $query->paginate($data['perPage'] ?? 20);

        return response()->json(['data' => collect($paginator->items())->map(fn (OutletTransaction $transaction) => $this->transactionPayload($transaction))->values(), 'meta' => ['page' => $paginator->currentPage(), 'perPage' => $paginator->perPage(), 'total' => $paginator->total(), 'lastPage' => $paginator->lastPage()]]);
    }

    public function summary(Request $request)
    {
        $this->ensureReporter($request);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'salesId' => ['nullable', 'integer'], 'outletId' => ['nullable', 'integer']]);
        $query = $this->filteredTransactions($request, $data);
        $committedSales = (clone $query)->where('type', 'sales_order')->whereIn('status', ['Committed', 'Completed']);

        return response()->json(['filters' => $data, 'committedRevenue' => (float) $committedSales->sum('amount'), 'committedOrderCount' => (clone $committedSales)->count(), 'transactionCount' => (clone $query)->count(), 'byType' => (clone $query)->selectRaw('type, COUNT(*) as count, SUM(amount) as amount')->groupBy('type')->get()->map(fn ($row) => ['type' => $row->type, 'count' => (int) $row->count, 'amount' => (float) $row->amount])->values()]);
    }

    public function exportCsv(Request $request)
    {
        $this->ensureReporter($request);
        $filters = $this->exportFilters($request);
        $query = $this->filteredTransactions($request, $filters)->with(['outlet:id,name,code', 'sales:id,name,employee_code'])->orderBy('id');

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Nomor', 'Tanggal', 'Tipe', 'Status', 'Outlet', 'Sales', 'Nilai']);
            $query->chunkById(500, function ($transactions) use ($stream): void {
                foreach ($transactions as $transaction) {
                    fputcsv($stream, [$transaction->document_number, $transaction->occurred_at->toDateTimeString(), $transaction->type, $transaction->status, $transaction->outlet?->name, $transaction->sales?->name, $transaction->amount]);
                }
            });
            fclose($stream);
        }, 'laporan-transaksi-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportVisitsCsv(Request $request)
    {
        $this->ensureReporter($request);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'salesId' => ['nullable', 'integer'], 'scope' => ['nullable', 'in:self,team']]);
        $branchId = $this->branchId($request->user());
        $branch = Branch::findOrFail($branchId);
        $query = Visit::with(['outlet:id,code,name,address', 'sales:id,name,employee_code'])
            // Scope cabang selalu berasal dari token login, tidak pernah dari
            // parameter client agar SPV/BM tidak dapat membaca cabang lain.
            ->where('branch_id', $branchId)
            ->when(($data['scope'] ?? 'team') === 'self', fn ($query) => $query->where('sales_id', $request->user()->id))
            ->when($data['salesId'] ?? null, fn ($query, $salesId) => $query->where('sales_id', $salesId))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('planned_for', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('planned_for', '<=', $to))
            ->orderByDesc('planned_for');

        return response()->streamDownload(function () use ($query, $branch): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Kode Cabang', 'Cabang', 'Tanggal Rencana', 'Outlet', 'Kode Outlet', 'Alamat', 'Sales', 'Status', 'Wajib', 'Check-in', 'Check-out', 'Jarak (km)']);
            $query->chunkById(500, function ($visits) use ($stream, $branch): void {
                foreach ($visits as $visit) {
                    fputcsv($stream, [$branch->code, $branch->name, $visit->planned_for?->toDateString(), $visit->outlet?->name, $visit->outlet?->code, $visit->outlet?->address, $visit->sales?->name, $visit->status, $visit->is_required ? 'Ya' : 'Tidak', $visit->checked_in_at?->toDateTimeString(), $visit->checked_out_at?->toDateTimeString(), $visit->distance_km]);
                }
            });
            fclose($stream);
        }, 'laporan-kunjungan-'.($data['scope'] ?? 'team').'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request, ReceiptPdfService $pdf)
    {
        $this->ensureReporter($request);
        $filters = $this->exportFilters($request);
        $transactions = $this->filteredTransactions($request, $filters)->with(['outlet:id,name', 'sales:id,name'])->latest('occurred_at')->get();
        $lines = $transactions->map(fn (OutletTransaction $transaction) => $transaction->occurred_at->format('d-m H:i').' | '.($transaction->document_number ?? '-').' | '.($transaction->outlet?->name ?? '-').' | Rp '.number_format((float) $transaction->amount, 0, ',', '.'))->all();
        if ($transactions->isEmpty()) {
            $lines[] = 'Tidak ada transaksi pada filter ini.';
        }

        return response($pdf->makeReport('SALES GO - LAPORAN TRANSAKSI', $lines), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="laporan-transaksi.pdf"']);
    }

    public function archives(Request $request, BranchAccessService $access)
    {
        $this->ensureReporter($request);
        $data = $request->validate(['branchId' => ['nullable', 'integer'], 'year' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'page' => ['nullable', 'integer', 'min:1'], 'perPage' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $allowed = $request->user()->role->slug === 'branchManager' ? $access->allowedBranchIds($request->user()) : [$this->branchId($request->user())];
        if (isset($data['branchId'])) {
            abort_unless(in_array((int) $data['branchId'], $allowed, true), 403);
            $allowed = [(int) $data['branchId']];
        }
        $paginator = ReportArchive::whereIn('branch_id', $allowed)
            ->when($data['year'] ?? null, fn ($query, $year) => $query->whereYear('period_start', $year))
            ->latest('period_start')
            ->paginate($data['perPage'] ?? 20);

        return response()->json(['data' => collect($paginator->items())->map(fn (ReportArchive $archive) => ['id' => (string) $archive->id, 'branchId' => (string) $archive->branch_id, 'periodStart' => $archive->period_start->toDateString(), 'periodEnd' => $archive->period_end->toDateString(), 'summary' => $archive->payload, 'archivedAt' => $archive->archived_at->toIso8601String(), 'expiresAt' => $archive->expires_at?->toIso8601String()])->values(), 'meta' => ['page' => $paginator->currentPage(), 'perPage' => $paginator->perPage(), 'total' => $paginator->total(), 'lastPage' => $paginator->lastPage()]]);
    }

    private function filteredTransactions(Request $request, array $filters)
    {
        return OutletTransaction::query()
            ->where('branch_id', $this->branchId($request->user()))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('occurred_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->when($filters['salesId'] ?? null, fn ($query, $salesId) => $query->where('sales_id', $salesId))
            ->when($filters['outletId'] ?? null, fn ($query, $outletId) => $query->where('outlet_id', $outletId))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested->where('document_number', 'like', '%'.$search.'%')->orWhereHas('outlet', fn ($outlet) => $outlet->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'))->orWhereHas('sales', fn ($sales) => $sales->where('name', 'like', '%'.$search.'%')->orWhere('employee_code', 'like', '%'.$search.'%'))));
    }

    private function ensureReporter(Request $request): void
    {
        abort_unless(in_array($request->user()->role->slug, ['supervisor', 'branchManager'], true), 403);
    }

    private function exportFilters(Request $request): array
    {
        return $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'salesId' => ['nullable', 'integer'], 'outletId' => ['nullable', 'integer'], 'type' => ['nullable', 'in:sales_order,purchase,return,supplier_return,gift,note'], 'status' => ['nullable', 'string', 'max:50'], 'search' => ['nullable', 'string', 'max:100']]);
    }

    private function transactionPayload(OutletTransaction $transaction): array
    {
        return ['id' => (string) $transaction->id, 'documentNumber' => $transaction->document_number, 'type' => $transaction->type, 'status' => $transaction->status, 'approvalStatus' => $transaction->approval_status, 'amount' => (float) $transaction->amount, 'outlet' => ['id' => (string) $transaction->outlet_id, 'name' => $transaction->outlet?->name, 'code' => $transaction->outlet?->code], 'sales' => ['id' => (string) $transaction->sales_id, 'name' => $transaction->sales?->name, 'employeeCode' => $transaction->sales?->employee_code], 'occurredAt' => $transaction->occurred_at->toIso8601String(), 'committedAt' => $transaction->committed_at?->toIso8601String()];
    }
}
