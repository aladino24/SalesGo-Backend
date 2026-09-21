<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\Attachment;
use App\Models\Branch;
use App\Models\Outlet;
use App\Models\Journey;
use App\Models\OutletRouteAssignment;
use App\Models\Visit;
use App\Services\IdempotencyService;
use App\Services\VisitActivityLogger;
use App\Services\NotificationService;
use App\Services\DeepLinkService;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;

class VisitController extends ApiController
{
    public function index(Request $request)
    {
        $date = CarbonImmutable::parse(
            $request->input('date', now()->toDateString()),
        )->startOfDay();
        $this->ensureJourneyVisitsForDate(
            $request,
            $date,
        );
        $query = Visit::with('outlet:id,code,name,address,latitude,longitude')
            ->where('branch_id', $this->branchId($request->user()))
            ->where('sales_id', $request->user()->id);
        if ($request->filled('journeyId')) {
            $query->where('journey_id', $request->input('journeyId'));
        } else {
            // Tanggal berasal dari perangkat, sehingga rute/approval tetap
            // konsisten pada pergantian hari atau timezone server berbeda.
            $query->whereDate('planned_for', $date->toDateString());
        }
        if ($request->filled('required')) {
            $query->where('is_required', filter_var($request->input('required'), FILTER_VALIDATE_BOOLEAN));
        }

        $visits = $query->orderByDesc('is_required')->orderBy('planned_for')->get()
            ->unique(fn (Visit $visit) => $visit->outlet_id.'|'.$visit->planned_for?->toDateString())
            ->values()
            ->map(fn (Visit $visit) => $this->payload($visit));

        return response()->json($visits);
    }

    /**
     * Rencana visit wajib adalah turunan dari master rute dan journey aktif.
     * Regenerasi idempoten ini memastikan cache yang dipulihkan tetap dapat
     * memuat jadwal hari ini tanpa sales harus menekan Mulai Perjalanan lagi.
     */
    private function ensureJourneyVisitsForDate(Request $request, CarbonImmutable $date): void
    {
        $journey = Journey::query()
            ->where('branch_id', $this->branchId($request->user()))
            ->where('sales_id', $request->user()->id)
            ->where('status', 'Active')
            ->whereDate('starts_at', '<=', $date->toDateString())
            ->whereDate('ends_at', '>=', $date->toDateString())
            ->latest('actual_started_at')
            ->first();
        if (! $journey) {
            return;
        }

        $routes = OutletRouteAssignment::query()
            ->where('branch_id', $journey->branch_id)
            ->where('sales_id', $journey->sales_id)
            ->where('is_active', true);
        $matches = (clone $routes)
            ->where('day_of_week', $date->dayOfWeekIso)
            ->where('week_of_month', min(4, intdiv($date->day - 1, 7) + 1))
            ->pluck('outlet_id')
            ->all();
        $allRoutes = (clone $routes)->distinct()->pluck('outlet_id')->all();

        foreach ($matches as $outletId) {
            Visit::firstOrCreate(
                ['journey_id' => $journey->id, 'outlet_id' => $outletId, 'planned_for' => $date->toDateString()],
                ['branch_id' => $journey->branch_id, 'sales_id' => $journey->sales_id, 'is_required' => true, 'status' => 'Planned', 'distance_km' => 0, 'metadata' => []],
            );
        }
        foreach (array_diff($allRoutes, $matches) as $outletId) {
            Visit::firstOrCreate(
                ['journey_id' => $journey->id, 'outlet_id' => $outletId, 'planned_for' => $date->toDateString()],
                ['branch_id' => $journey->branch_id, 'sales_id' => $journey->sales_id, 'is_required' => false, 'status' => 'Planned', 'distance_km' => 0, 'metadata' => []],
            );
        }
    }

    public function checkIn(Request $request, IdempotencyService $idempotency, VisitActivityLogger $timeline, NotificationService $notifications, DeepLinkService $links)
    {
        return $idempotency->handle($request, function () use ($request, $timeline, $notifications, $links) {
            $data = $request->validate(['visitId' => ['required', 'string', 'max:80'], 'outletId' => ['required'], 'photoId' => ['nullable', 'integer'], 'location.latitude' => ['required', 'numeric'], 'location.longitude' => ['required', 'numeric'], 'distanceMeters' => ['required', 'numeric', 'min:0'], 'isRequired' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:200'], 'outOfRadiusOverride.reason' => ['nullable', 'string', 'max:200']]);
            $existing = Visit::where('sales_id', $request->user()->id)->where(fn ($query) => $query->where('client_visit_id', $data['visitId'])->orWhere('id', $data['visitId']))->first();
            if ($existing && $existing->status !== 'Planned') {
                return response()->json($this->payload($existing));
            }
            $outlet = Outlet::where('id', $data['outletId'])->where('branch_id', $this->branchId($request->user()))->firstOrFail();
            if ($outlet->latitude === null || $outlet->longitude === null) {
                return response()->json(['message' => 'Koordinat outlet belum tersedia.', 'code' => 'OUTLET_LOCATION_REQUIRED'], 422);
            }
            $radius = $outlet->geofence_radius_meters ?? Branch::findOrFail($outlet->branch_id)->default_geofence_radius_meters;
            $actualDistance = $this->distanceMeters((float) $data['location']['latitude'], (float) $data['location']['longitude'], $outlet->latitude, $outlet->longitude);
            $isRequired = $existing?->is_required ?? ($data['isRequired'] ?? false);
            $photo = null;
            if (! empty($data['photoId'])) {
                $photo = Attachment::whereKey($data['photoId'])->where('branch_id', $this->branchId($request->user()))->where('uploaded_by', $request->user()->id)->firstOrFail();
                if ($photo->status !== 'Finalized') {
                    return response()->json(['message' => 'Foto belum selesai diproses.', 'code' => 'ATTACHMENT_NOT_FINALIZED'], 422);
                }
            }
            if ($isRequired && $actualDistance > $radius && ! $this->isSuperUser($request->user()) && ! $photo) {
                return response()->json(['message' => 'Foto outlet wajib untuk pengajuan kunjungan wajib di luar radius.', 'code' => 'VISIT_OVERRIDE_PHOTO_REQUIRED'], 422);
            }
            $override = $isRequired && $actualDistance > $radius && ! $this->isSuperUser($request->user());
            $visit = $existing ?? new Visit(['client_visit_id' => $data['visitId'], 'branch_id' => $outlet->branch_id, 'outlet_id' => $outlet->id, 'sales_id' => $request->user()->id, 'planned_for' => now()->toDateString(), 'is_required' => $isRequired]);
            $visit->fill(['status' => $override ? 'Pending' : 'In Progress', 'distance_km' => $actualDistance / 1000, 'latitude' => $data['location']['latitude'], 'longitude' => $data['location']['longitude'], 'checked_in_at' => now(), 'notes' => $data['notes'] ?? null, 'metadata' => ['photoId' => $photo?->id]])->save();
            $timeline->record($visit, 'check_in', $override ? 'Check-in wajib di luar radius menunggu approval' : ($actualDistance > $radius ? 'Check-in kunjungan tidak wajib di luar radius' : 'Check-in outlet'), $data['location'], ['photoId' => $photo?->id, 'distanceMeters' => $actualDistance, 'radiusMeters' => $radius, 'isRequired' => $isRequired]);
            if ($override) {
                $approval = Approval::firstOrCreate(['branch_id' => $outlet->branch_id, 'type' => 'visit_out_of_radius', 'entity_type' => Visit::class, 'entity_id' => $visit->id, 'status' => 'Pending'], ['requested_by' => $request->user()->id, 'reason' => $data['outOfRadiusOverride']['reason'] ?? 'Di luar radius']);
                if ($approval->wasRecentlyCreated) {
                    $notifications->notifyVisitApprovers($outlet->branch_id, 'approval_request', 'Override check-in luar radius', $request->user()->name.' mengajukan override untuk '.$outlet->name.'.', Approval::class, $approval->id, $links->approval($approval->id));
                }
            }

            return response()->json($this->payload($visit), 201);
        });
    }

    public function checkOut(Request $request, IdempotencyService $idempotency, VisitActivityLogger $timeline)
    {
        return $idempotency->handle($request, function () use ($request, $timeline) {
            $data = $request->validate(['visitId' => ['required'], 'notes' => ['nullable', 'string', 'max:200']]);
            $visit = $this->ownedVisit($request, $data['visitId']);
            $visit->update(['status' => 'Completed', 'checked_out_at' => now(), 'notes' => $data['notes'] ?? $visit->notes]);
            $timeline->record($visit, 'check_out', 'Check-out outlet');

            return response()->json($this->payload($visit));
        });
    }

    public function defer(Request $request, IdempotencyService $idempotency, VisitActivityLogger $timeline)
    {
        return $this->changeStatus($request, $idempotency, $timeline, 'Deferred');
    }

    public function cancel(Request $request, IdempotencyService $idempotency, VisitActivityLogger $timeline)
    {
        return $this->changeStatus($request, $idempotency, $timeline, 'Cancelled');
    }

    private function changeStatus(Request $request, IdempotencyService $idempotency, VisitActivityLogger $timeline, string $status)
    {
        return $idempotency->handle($request, function () use ($request, $status, $timeline) {
            $data = $request->validate(['visitId' => ['required'], 'reason' => ['required', 'string', 'max:200']]);
            $visit = $this->ownedVisit($request, $data['visitId']);
            $visit->update(['status' => $status, 'notes' => $data['reason']]);
            $timeline->record($visit, strtolower($status), $data['reason'], [], ['reason' => $data['reason']]);

            return response()->json($this->payload($visit));
        });
    }

    private function ownedVisit(Request $request, string $id): Visit
    {
        return Visit::where('sales_id', $request->user()->id)->where(fn ($q) => $q->where('id', $id)->orWhere('client_visit_id', $id))->firstOrFail();
    }

    private function payload(Visit $v): array
    {
        return ['id' => $v->client_visit_id ?? (string) $v->id, 'journeyId' => $v->journey_id ? (string) $v->journey_id : null, 'outletId' => (string) $v->outlet_id, 'outletName' => optional($v->outlet)->name, 'outletCode' => optional($v->outlet)->code, 'outletAddress' => optional($v->outlet)->address, 'status' => $v->status, 'isRequired' => $v->is_required, 'plannedFor' => $v->planned_for?->toDateString(), 'distanceKm' => (float) $v->distance_km, 'latitude' => $v->outlet?->latitude ?? $v->latitude, 'longitude' => $v->outlet?->longitude ?? $v->longitude, 'salesName' => optional($v->sales)->name, 'createdAt' => $v->created_at->toIso8601String()];
    }

    private function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadiusMeters = 6371000.0;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2 + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
