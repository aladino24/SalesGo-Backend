<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\DailyBranchSalesAggregate;
use App\Services\BranchAccessService;
use Illuminate\Http\Request;

class CrossBranchDashboardController extends ApiController
{
    public function show(Request $request, BranchAccessService $access)
    {
        abort_unless($request->user()->role->slug === 'branchManager', 403);
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'branchIds' => ['nullable', 'array'], 'branchIds.*' => ['integer']]);
        $allowed = $access->allowedBranchIds($request->user());
        $requested = $data['branchIds'] ?? $allowed;
        abort_unless(collect($requested)->every(fn ($branchId) => in_array((int) $branchId, $allowed, true)), 403);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();
        $aggregates = DailyBranchSalesAggregate::whereIn('branch_id', $requested)->whereBetween('date', [$from, $to])->get()->groupBy('branch_id');
        $branches = Branch::whereIn('id', $requested)->get();

        return response()->json(['from' => $from, 'to' => $to, 'branches' => $branches->map(function (Branch $branch) use ($aggregates) {
            $rows = $aggregates->get($branch->id, collect());

            return ['id' => (string) $branch->id, 'code' => $branch->code, 'name' => $branch->name, 'committedRevenue' => (float) $rows->sum('committed_revenue'), 'committedOrderCount' => (int) $rows->sum('committed_order_count'), 'activeOutletCount' => (int) $rows->sum('active_outlet_count'), 'lastAggregatedAt' => optional($rows->max('generated_at'))->toIso8601String()];
        })->sortByDesc('committedRevenue')->values(), 'generatedAt' => now()->toIso8601String()]);
    }
}
