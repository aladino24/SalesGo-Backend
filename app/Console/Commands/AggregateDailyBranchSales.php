<?php

namespace App\Console\Commands;

use App\Models\DailyBranchSalesAggregate;
use App\Models\OutletTransaction;
use Illuminate\Console\Command;

class AggregateDailyBranchSales extends Command
{
    protected $signature = 'salesgo:aggregate-daily-sales {--days=}';

    protected $description = 'Rebuilds daily committed-sales aggregates by branch.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('salesgo.report_aggregate_lookback_days'));
        if ($days < 1) {
            $this->error('Lookback days must be at least one.');

            return self::FAILURE;
        }
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = OutletTransaction::query()
            ->selectRaw('branch_id, DATE(occurred_at) as date, SUM(amount) as committed_revenue, COUNT(*) as committed_order_count, COUNT(DISTINCT outlet_id) as active_outlet_count')
            ->where('type', 'sales_order')
            ->whereIn('status', ['Committed', 'Completed'])
            ->where('occurred_at', '>=', $from)
            ->groupBy('branch_id', 'date')
            ->get();
        DailyBranchSalesAggregate::where('date', '>=', $from->toDateString())->delete();
        DailyBranchSalesAggregate::upsert($rows->map(fn ($row) => ['branch_id' => $row->branch_id, 'date' => $row->date, 'committed_revenue' => $row->committed_revenue, 'committed_order_count' => $row->committed_order_count, 'active_outlet_count' => $row->active_outlet_count, 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now()])->all(), ['branch_id', 'date'], ['committed_revenue', 'committed_order_count', 'active_outlet_count', 'generated_at', 'updated_at']);
        $this->info("Aggregated {$rows->count()} branch-day rows.");

        return self::SUCCESS;
    }
}
