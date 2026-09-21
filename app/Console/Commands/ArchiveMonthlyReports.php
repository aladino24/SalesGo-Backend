<?php

namespace App\Console\Commands;

use App\Models\OutletTransaction;
use App\Models\ReportArchive;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ArchiveMonthlyReports extends Command
{
    protected $signature = 'salesgo:archive-monthly-reports {--month= : Month in YYYY-MM, defaults to the previous month}';

    protected $description = 'Creates immutable monthly transaction summaries and removes expired archives.';

    public function handle(): int
    {
        try {
            $month = $this->option('month') ? CarbonImmutable::createFromFormat('!Y-m', $this->option('month')) : CarbonImmutable::now();
        } catch (\Throwable) {
            $this->error('Month must use YYYY-MM format.');

            return self::FAILURE;
        }
        $month = $this->option('month') ? $month : $month->subMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $rows = OutletTransaction::query()
            ->selectRaw("branch_id, SUM(CASE WHEN type = 'sales_order' AND status IN ('Committed', 'Completed') THEN amount ELSE 0 END) as committed_revenue, SUM(CASE WHEN type = 'sales_order' AND status IN ('Committed', 'Completed') THEN 1 ELSE 0 END) as committed_order_count, COUNT(*) as transaction_count")
            ->whereBetween('occurred_at', [$start, $end])
            ->groupBy('branch_id')
            ->get();
        foreach ($rows as $row) {
            ReportArchive::firstOrCreate(
                ['branch_id' => $row->branch_id, 'period_start' => $start->toDateString(), 'period_end' => $end->toDateString()],
                ['payload' => ['committedRevenue' => (float) $row->committed_revenue, 'committedOrderCount' => (int) $row->committed_order_count, 'transactionCount' => (int) $row->transaction_count], 'archived_at' => now(), 'expires_at' => now()->addDays((int) config('salesgo.report_archive_retention_days'))],
            );
        }
        $deleted = ReportArchive::whereNotNull('expires_at')->where('expires_at', '<', now())->delete();
        $this->info("Archived {$rows->count()} monthly branch reports; pruned {$deleted} expired archives.");

        return self::SUCCESS;
    }
}
