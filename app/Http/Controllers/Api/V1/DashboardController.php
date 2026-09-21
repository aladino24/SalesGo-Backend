<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\IncentiveRule;
use App\Models\Outlet;
use App\Models\OutletTransaction;
use App\Models\SalesTarget;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $period = CarbonImmutable::now('UTC')->startOfMonth();
        $previousPeriod = $period->subMonth();
        $revenue = $this->committedRevenue($user->branch_id, $user->id, $period);
        $previousRevenue = $this->committedRevenue($user->branch_id, $user->id, $previousPeriod);
        $target = $this->target($user->branch_id, $user->id, $period);
        $achievement = $target <= 0 ? 0 : round(($revenue / $target) * 100, 2);
        $growth = $previousRevenue <= 0 ? ($revenue > 0 ? 100.0 : 0.0) : round((($revenue - $previousRevenue) / $previousRevenue) * 100, 2);
        $incentive = $this->incentive($user->branch_id, $user->id, $achievement, $revenue);
        $visited = Visit::where('sales_id', $user->id)->where('status', 'Completed')->whereBetween('checked_out_at', [$period, $period->endOfMonth()])->count();
        $totalOutlets = Outlet::where('branch_id', $user->branch_id)->count();

        return response()->json([
            'monthlyRevenue' => $revenue,
            'monthlyTarget' => $target,
            'achievementPercent' => $achievement,
            'visitedOutlets' => $visited,
            'totalOutlets' => $totalOutlets,
            'incentive' => $incentive,
            'revenueGrowth' => $growth,
            'period' => $period->format('Y-m'),
            'chart' => $this->dailyChart($user->branch_id, $user->id, $period),
        ]);
    }

    private function committedRevenue(int $branchId, int $salesId, CarbonImmutable $period): float
    {
        return (float) OutletTransaction::where('branch_id', $branchId)->where('sales_id', $salesId)->where('type', 'sales_order')->whereIn('status', ['Committed', 'Completed'])->whereBetween('occurred_at', [$period, $period->endOfMonth()])->sum('amount');
    }

    private function target(int $branchId, int $salesId, CarbonImmutable $period): float
    {
        $target = SalesTarget::where('branch_id', $branchId)->where('sales_id', $salesId)->whereDate('period', $period)->value('revenue_target');

        return (float) ($target ?? SalesTarget::where('branch_id', $branchId)->whereNull('sales_id')->whereDate('period', $period)->value('revenue_target') ?? 0);
    }

    private function incentive(int $branchId, int $salesId, float $achievement, float $revenue): float
    {
        $rule = IncentiveRule::where('branch_id', $branchId)->where('is_active', true)->where(fn ($query) => $query->where('sales_id', $salesId)->orWhereNull('sales_id'))->where('minimum_achievement_percent', '<=', $achievement)->orderByDesc('minimum_achievement_percent')->orderByDesc('sales_id')->first();

        return $rule ? round(($revenue * ((float) $rule->incentive_rate_percent / 100)) + (float) $rule->fixed_amount, 2) : 0.0;
    }

    private function dailyChart(int $branchId, int $salesId, CarbonImmutable $period): array
    {
        return OutletTransaction::query()->selectRaw('DATE(occurred_at) as date, SUM(amount) as revenue')->where('branch_id', $branchId)->where('sales_id', $salesId)->where('type', 'sales_order')->whereIn('status', ['Committed', 'Completed'])->whereBetween('occurred_at', [$period, $period->endOfMonth()])->groupByRaw('DATE(occurred_at)')->orderBy('date')->get()->map(fn ($row) => ['date' => $row->date, 'revenue' => (float) $row->revenue])->values()->all();
    }
}
