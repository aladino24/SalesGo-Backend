<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\ImportantFile;
use App\Models\Journey;
use App\Models\Outlet;
use App\Models\Promotion;
use App\Models\Visit;
use App\Services\MasterRevisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncStateController extends ApiController
{
    public function show(Request $request, MasterRevisionService $revision)
    {
        $branchId = $this->branchId($request->user());
        $visits = Visit::with('outlet:id,code,name,address,latitude,longitude')->where('branch_id', $branchId)->where('sales_id', $request->user()->id)->get()->map(fn ($v) => ['id' => $v->client_visit_id ?? (string) $v->id, 'journeyId' => $v->journey_id ? (string) $v->journey_id : null, 'outletId' => (string) $v->outlet_id, 'outletName' => optional($v->outlet)->name, 'outletCode' => optional($v->outlet)->code, 'outletAddress' => optional($v->outlet)->address, 'status' => $v->status, 'isRequired' => (bool) $v->is_required, 'plannedFor' => $v->planned_for?->toDateString(), 'distanceKm' => (float) $v->distance_km, 'latitude' => optional($v->outlet)->latitude ?? $v->latitude, 'longitude' => optional($v->outlet)->longitude ?? $v->longitude, 'salesName' => $request->user()->name, 'createdAt' => $v->created_at->toIso8601String()])->values();
        $journeys = Journey::where('branch_id', $branchId)->where('sales_id', $request->user()->id)->latest()->get()->map(fn (Journey $journey) => ['id' => $journey->client_journey_id ?? (string) $journey->id, 'serverId' => (string) $journey->id, 'type' => $journey->type, 'destination' => $journey->destination, 'startsAt' => $journey->starts_at->toIso8601String(), 'endsAt' => ($journey->ends_at ?? $journey->starts_at)->toIso8601String(), 'status' => $journey->status, 'approvalStatus' => $journey->approval_status, 'createdAt' => $journey->created_at->toIso8601String()])->values();
        $journeyActivities = DB::table('audit_logs')->where('branch_id', $branchId)->where('user_id', $request->user()->id)->whereIn('event', ['journey_started', 'journey_completed'])->latest()->limit(50)->get()->map(fn ($entry) => ['id' => 'audit-'.$entry->id, 'journeyId' => (string) $entry->entity_id, 'event' => $entry->event === 'journey_started' ? 'Perjalanan dimulai' : 'Perjalanan diakhiri', 'description' => data_get(json_decode($entry->metadata ?? '{}', true), 'destination', 'Perjalanan Sales'), 'createdAt' => $entry->created_at])->values();

        $meta = $revision->forBranch($branchId);

        // Gunakan payload katalog yang sama dengan master download agar
        // pemulihan state tidak menghilangkan divisi, gambar, atau Multi-UOM.
        $products = app(MasterController::class)->products($request)->getData(true);

        return response()->json([...$meta, 'activeVisit' => $visits->firstWhere('status', 'In Progress'), 'dashboard' => ['monthlyRevenue' => 0, 'monthlyTarget' => 0, 'visitedOutlets' => $visits->where('status', 'Completed')->count(), 'totalOutlets' => Outlet::where('branch_id', $branchId)->count(), 'incentive' => 0, 'revenueGrowth' => 0], 'datasets' => ['products' => $products, 'outlets' => Outlet::where('branch_id', $branchId)->get(), 'visits' => $visits, 'salesOrders' => [], 'outletTransactions' => [], 'visitActions' => [], 'visitTimeline' => [], 'journeys' => $journeys, 'journeyActivities' => $journeyActivities, 'deliveryNotes' => [], 'meetings' => [], 'approvals' => Approval::where('branch_id', $branchId)->get(), 'promotions' => Promotion::where('branch_id', $branchId)->where('ends_at', '>=', now())->get(), 'files' => ImportantFile::where('branch_id', $branchId)->get()]]);
    }
}
