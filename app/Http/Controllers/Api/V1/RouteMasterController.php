<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Outlet;
use App\Models\OutletRouteAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RouteMasterController extends ApiController
{
    public function sales(Request $request)
    {
        $this->ensureRouteManager($request);
        $users = User::query()
            ->with('role:id,slug')
            ->where('branch_id', $this->branchId($request->user()))
            ->where('is_active', true)
            // BM dapat tetap memiliki rute pribadi di cabangnya, selain
            // mengatur rute Sales dan Supervisor pada cabang yang sama.
            ->whereHas('role', fn ($query) => $query->whereIn('slug', ['sales', 'supervisor', 'branchManager', 'it']))
            ->orderBy('employee_code')
            ->get(['id', 'name', 'employee_code', 'role_id'])
            ->map(fn (User $user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'employeeCode' => $user->employee_code,
                'role' => $user->role?->slug,
            ]);

        return response()->json($users->values());
    }

    public function index(Request $request)
    {
        $this->ensureRouteViewer($request);
        $query = OutletRouteAssignment::with(['outlet:id,code,name,address,latitude,longitude', 'sales:id,name,employee_code'])
            ->where('branch_id', $this->branchId($request->user()));
        if (in_array($request->user()->role->slug, ['sales', 'supervisor'], true)) {
            $query->where('sales_id', $request->user()->id);
        } elseif ($request->filled('salesId')) {
            $query->where('sales_id', $request->integer('salesId'));
        }

        return response()->json($query->orderBy('sales_id')->orderBy('day_of_week')->orderBy('week_of_month')->get()->map(fn (OutletRouteAssignment $item) => $this->payload($item))->values());
    }

    public function updateSalesDivisions(Request $request, User $sales)
    {
        $this->ensureBranchManager($request);
        abort_unless($sales->branch_id === $this->branchId($request->user()), 403);
        $data = $request->validate(['divisionIds' => ['required', 'array', 'min:1'], 'divisionIds.*' => ['integer', 'distinct', 'exists:sales_divisions,id']]);
        // Divisi pada employee code tetap menjadi divisi utama dan tidak boleh
        // hilang saat BM menambahkan divisi tambahan.
        $sales->divisions()->sync(array_values(array_unique([...$data['divisionIds'], $sales->sales_division_id])));

        return response()->json(['id' => (string) $sales->id, 'divisionIds' => $sales->divisions()->pluck('sales_divisions.id')->values()]);
    }

    public function updateOutletDivisions(Request $request, Outlet $outlet)
    {
        $this->ensureBranchManager($request);
        abort_unless($outlet->branch_id === $this->branchId($request->user()), 403);
        $data = $request->validate(['divisionIds' => ['required', 'array', 'min:1'], 'divisionIds.*' => ['integer', 'distinct', 'exists:sales_divisions,id']]);
        $outlet->divisions()->sync($data['divisionIds']);

        return response()->json(['id' => (string) $outlet->id, 'divisionIds' => $outlet->divisions()->pluck('sales_divisions.id')->values()]);
    }

    public function store(Request $request)
    {
        $this->ensureRouteManager($request);
        $data = $request->validate(['outletId' => ['required', 'integer'], 'salesId' => ['nullable', 'integer'], 'dayOfWeek' => ['required', 'integer', 'between:1,7'], 'weekOfMonth' => ['required', 'integer', 'between:1,4'], 'isActive' => ['sometimes', 'boolean']]);
        $branchId = $this->branchId($request->user());
        $salesId = $request->user()->role->slug === 'supervisor' ? $request->user()->id : ($data['salesId'] ?? null);
        abort_unless($salesId !== null, 422, 'Pilih sales untuk master rute.');
        Outlet::whereKey($data['outletId'])->where('branch_id', $branchId)->where('status', 'Active')->firstOrFail();
        $sales = User::whereKey($salesId)->where('branch_id', $branchId)->firstOrFail();
        $this->attachOutletDivisions($data['outletId'], $sales);
        $this->assertScheduleAvailable($branchId, $data['outletId'], $salesId, $data['dayOfWeek'], $data['weekOfMonth']);
        $assignment = OutletRouteAssignment::create([
            'branch_id' => $branchId,
            'outlet_id' => $data['outletId'],
            'sales_id' => $salesId,
            'day_of_week' => $data['dayOfWeek'],
            'week_of_month' => $data['weekOfMonth'],
            'is_active' => $data['isActive'] ?? true,
        ]);

        return response()->json($this->payload($assignment->load(['outlet:id,code,name,address,latitude,longitude', 'sales:id,name,employee_code'])), 201);
    }

    public function storeBulk(Request $request)
    {
        $this->ensureRouteManager($request);
        $data = $request->validate([
            'outletId' => ['required', 'integer'],
            'salesId' => ['nullable', 'integer'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7', 'distinct'],
            'weeks' => ['required', 'array', 'min:1'],
            'weeks.*' => ['integer', 'between:1,4', 'distinct'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
        $branchId = $this->branchId($request->user());
        $salesId = $request->user()->role->slug === 'supervisor' ? $request->user()->id : ($data['salesId'] ?? null);
        abort_unless($salesId !== null, 422, 'Pilih sales untuk master rute.');
        Outlet::whereKey($data['outletId'])->where('branch_id', $branchId)->where('status', 'Active')->firstOrFail();
        $sales = User::whereKey($salesId)->where('branch_id', $branchId)->firstOrFail();
        $this->attachOutletDivisions($data['outletId'], $sales);

        $assignments = DB::transaction(function () use ($data, $branchId, $salesId) {
            $result = collect();
            foreach ($data['days'] as $day) {
                foreach ($data['weeks'] as $week) {
                    $this->assertScheduleAvailable($branchId, $data['outletId'], $salesId, $day, $week);
                    $result->push(OutletRouteAssignment::create([
                        'branch_id' => $branchId,
                        'outlet_id' => $data['outletId'],
                        'sales_id' => $salesId,
                        'day_of_week' => $day,
                        'week_of_month' => $week,
                        'is_active' => $data['isActive'] ?? true,
                    ]));
                }
            }

            return $result;
        });

        return response()->json($assignments->map(fn (OutletRouteAssignment $item) => $this->payload($item->load(['outlet:id,code,name,address,latitude,longitude', 'sales:id,name,employee_code'])))->values(), 201);
    }

    public function update(Request $request, OutletRouteAssignment $routeAssignment)
    {
        $this->ensureRouteManager($request);
        abort_unless($routeAssignment->branch_id === $this->branchId($request->user()), 403);
        if ($request->user()->role->slug === 'supervisor') {
            abort_unless($routeAssignment->sales_id === $request->user()->id, 403);
        }
        $data = $request->validate([
            'dayOfWeek' => ['required', 'integer', 'between:1,7'],
            'weekOfMonth' => ['required', 'integer', 'between:1,4'],
            'isActive' => ['sometimes', 'boolean'],
        ]);
        $this->assertScheduleAvailable($routeAssignment->branch_id, $routeAssignment->outlet_id, $routeAssignment->sales_id, $data['dayOfWeek'], $data['weekOfMonth'], $routeAssignment->id);
        $routeAssignment->update([
            'day_of_week' => $data['dayOfWeek'],
            'week_of_month' => $data['weekOfMonth'],
            'is_active' => $data['isActive'] ?? $routeAssignment->is_active,
        ]);

        return response()->json($this->payload($routeAssignment->fresh()->load(['outlet:id,code,name,address,latitude,longitude', 'sales:id,name,employee_code'])));
    }

    public function destroy(Request $request, OutletRouteAssignment $routeAssignment)
    {
        $this->ensureRouteManager($request);
        abort_unless($routeAssignment->branch_id === $this->branchId($request->user()), 403);
        if ($request->user()->role->slug === 'supervisor') {
            abort_unless($routeAssignment->sales_id === $request->user()->id, 403);
        }
        $routeAssignment->delete();

        return response()->noContent();
    }

    private function payload(OutletRouteAssignment $item): array
    {
        return ['id' => (string) $item->id, 'outletId' => (string) $item->outlet_id, 'salesId' => (string) $item->sales_id, 'dayOfWeek' => $item->day_of_week, 'weekOfMonth' => $item->week_of_month, 'isActive' => $item->is_active, 'outlet' => $item->outlet ? ['id' => (string) $item->outlet->id, 'code' => $item->outlet->code, 'name' => $item->outlet->name, 'address' => $item->outlet->address, 'latitude' => $item->outlet->latitude, 'longitude' => $item->outlet->longitude] : null, 'sales' => $item->sales ? ['id' => (string) $item->sales->id, 'name' => $item->sales->name, 'employeeCode' => $item->sales->employee_code] : null];
    }

    private function assertScheduleAvailable(int $branchId, int $outletId, int $salesId, int $day, int $week, ?int $exceptId = null): void
    {
        $query = OutletRouteAssignment::query()
            ->where('branch_id', $branchId)
            ->where('outlet_id', $outletId)
            ->where('day_of_week', $day)
            ->where('week_of_month', $week);
        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }
        $sameSales = (clone $query)->where('sales_id', $salesId)->first();
        if ($sameSales) {
            throw ValidationException::withMessages([
                'schedule' => ['Outlet ini sudah dijadwalkan untuk sales yang dipilih pada hari '.$day.' minggu ke-'.$week.'.'],
            ]);
        }
        if ($query->count() >= 10) {
            throw ValidationException::withMessages([
                'schedule' => ['Outlet sudah mencapai batas maksimal 10 sales pada hari '.$day.' minggu ke-'.$week.'.'],
            ]);
        }
    }

    private function ensureRouteManager(Request $request): void
    {
        abort_unless(in_array($request->user()->role->slug, ['supervisor', 'branchManager', 'it'], true), 403);
    }

    private function ensureBranchManager(Request $request): void
    {
        abort_unless(in_array($request->user()->role->slug, ['branchManager', 'it'], true), 403);
    }

    private function attachOutletDivisions(int $outletId, User $sales): void
    {
        $divisionIds = $sales->divisions()->pluck('sales_divisions.id')->all();
        if ($divisionIds === [] && $sales->sales_division_id) {
            $divisionIds = [$sales->sales_division_id];
        }
        if ($divisionIds !== []) {
            Outlet::findOrFail($outletId)->divisions()->syncWithoutDetaching($divisionIds);
        }
    }

    private function ensureRouteViewer(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role->slug, ['sales', 'supervisor', 'branchManager', 'it'], true),
            403,
        );
    }
}
