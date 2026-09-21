<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SalesLocationPing;
use App\Models\User;
use App\Models\Visit;
use App\Services\AuditLogger;
use App\Services\IdempotencyService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MonitoringController extends ApiController
{
    public function recordLocation(Request $request, IdempotencyService $idempotency, AuditLogger $audit)
    {
        return $idempotency->handle($request, function () use ($request, $audit) {
            $data = $request->validate([
                'location.latitude' => ['required', 'numeric', 'between:-90,90'],
                'location.longitude' => ['required', 'numeric', 'between:-180,180'],
                'location.accuracyMeters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
                'recordedAt' => ['nullable', 'date'],
                'source' => ['nullable', 'in:foreground,background,check_in,check_out'],
            ]);
            $ping = SalesLocationPing::create([
                'branch_id' => $request->user()->branch_id,
                'sales_id' => $request->user()->id,
                'latitude' => $data['location']['latitude'],
                'longitude' => $data['location']['longitude'],
                'accuracy_meters' => $data['location']['accuracyMeters'] ?? null,
                'source' => $data['source'] ?? 'foreground',
                'recorded_at' => $data['recordedAt'] ?? now(),
            ]);
            $audit->record($request->user()->id, $request->user()->branch_id, 'sales_location_recorded', SalesLocationPing::class, $ping->id, ['source' => $ping->source]);

            return response()->json($this->locationPayload($ping), 201);
        });
    }

    public function team(Request $request)
    {
        $this->ensureMonitor($request);
        $members = $this->visibleMembers($request)
            ->with(['role', 'lastLocation'])
            ->when($request->query('search'), fn ($query, $search) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$search.'%')->orWhere('employee_code', 'like', '%'.$search.'%')))
            ->orderBy('name')
            ->get();
        $activeVisits = Visit::where('branch_id', $this->branchId($request->user()))
            ->whereIn('sales_id', $members->pluck('id'))
            ->where('status', 'In Progress')
            ->with('outlet:id,name')
            ->get()
            ->keyBy('sales_id');

        return response()->json($members->map(function (User $member) use ($activeVisits) {
            $visit = $activeVisits->get($member->id);

            return [
                'id' => (string) $member->id,
                'employeeCode' => $member->employee_code,
                'name' => $member->name,
                'role' => $member->role?->slug,
                'activeVisit' => $visit ? ['id' => (string) $visit->id, 'outletId' => (string) $visit->outlet_id, 'outletName' => $visit->outlet?->name, 'checkedInAt' => $visit->checked_in_at?->toIso8601String()] : null,
                'lastLocation' => $member->lastLocation ? $this->locationPayload($member->lastLocation) : null,
            ];
        })->values());
    }

    public function activities(Request $request)
    {
        $this->ensureMonitor($request);
        $memberIds = $this->visibleMembers($request)->pluck('id');
        $selectedUserId = (int) $request->query('salesId', 0);
        abort_if($selectedUserId > 0 && ! $memberIds->contains($selectedUserId), 403);
        $perPage = min(max((int) $request->query('perPage', 30), 1), 100);
        $logs = DB::table('audit_logs')
            ->join('users', 'users.id', '=', 'audit_logs.user_id')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('audit_logs.branch_id', $this->branchId($request->user()))
            ->whereIn('audit_logs.user_id', $memberIds)
            ->when($selectedUserId > 0, fn ($query) => $query->where('audit_logs.user_id', $selectedUserId))
            ->when($request->query('search'), fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('users.name', 'like', '%'.$search.'%')
                ->orWhere('users.employee_code', 'like', '%'.$search.'%')
                ->orWhere('audit_logs.event', 'like', '%'.$search.'%')))
            ->select(['audit_logs.id', 'audit_logs.user_id', 'audit_logs.event', 'audit_logs.metadata', 'audit_logs.created_at', 'users.name', 'users.employee_code', 'roles.slug as role'])
            ->latest('audit_logs.created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => collect($logs->items())->map(fn ($log) => [
                'id' => (string) $log->id,
                'userId' => (string) $log->user_id,
                'salesName' => $log->name,
                'employeeCode' => $log->employee_code,
                'role' => $log->role,
                'event' => $log->event,
                'metadata' => json_decode($log->metadata ?? '{}', true),
                'createdAt' => CarbonImmutable::parse($log->created_at)->toIso8601String(),
            ])->values(),
            'meta' => ['page' => $logs->currentPage(), 'perPage' => $logs->perPage(), 'total' => $logs->total(), 'lastPage' => $logs->lastPage()],
        ]);
    }

    public function locationHistory(Request $request, User $sales)
    {
        $this->ensureMonitor($request);
        abort_unless($this->visibleMembers($request)->pluck('id')->contains($sales->id), 403);
        $perPage = min(max((int) $request->query('perPage', 30), 1), 100);
        $pings = SalesLocationPing::where('branch_id', $sales->branch_id)
            ->where('sales_id', $sales->id)
            ->when($request->query('from'), fn ($query, $from) => $query->where('recorded_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->where('recorded_at', '<=', CarbonImmutable::parse($to)->endOfDay()))
            ->latest('recorded_at')
            ->paginate($perPage);

        return response()->json(['data' => collect($pings->items())->map(fn ($ping) => $this->locationPayload($ping))->values(), 'meta' => ['page' => $pings->currentPage(), 'perPage' => $pings->perPage(), 'total' => $pings->total(), 'lastPage' => $pings->lastPage()]]);
    }

    private function ensureMonitor(Request $request): void
    {
        abort_unless(in_array($request->user()->role->slug, ['supervisor', 'branchManager'], true), 403);
    }

    private function visibleMembers(Request $request)
    {
        $viewer = $request->user();
        $roles = $viewer->role->slug === 'supervisor' ? ['sales'] : ['sales', 'supervisor'];

        return User::query()
            ->where('branch_id', $this->branchId($viewer))
            ->where('is_active', true)
            ->where(function ($query) use ($roles, $viewer) {
                $query->whereHas('role', fn ($role) => $role->whereIn('slug', $roles));
                if ($viewer->role->slug === 'branchManager') {
                    $query->orWhere('users.id', $viewer->id);
                }
            });
    }

    private function locationPayload(SalesLocationPing $ping): array
    {
        return ['id' => (string) $ping->id, 'latitude' => $ping->latitude, 'longitude' => $ping->longitude, 'accuracyMeters' => $ping->accuracy_meters, 'source' => $ping->source, 'recordedAt' => $ping->recorded_at->toIso8601String()];
    }
}
