<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Approval;
use App\Models\Journey;
use App\Models\OutletRouteAssignment;
use App\Models\Visit;
use App\Services\DeepLinkService;
use App\Services\AuditLogger;
use App\Services\IdempotencyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;

class JourneyController extends ApiController
{
    public function index(Request $request)
    {
        return response()->json(Journey::where('branch_id', $this->branchId($request->user()))->where('sales_id', $request->user()->id)->latest()->get()->map(fn ($j) => $this->payload($j)));
    }

    public function current(Request $request)
    {
        $date = CarbonImmutable::parse($request->query('date', now()->toDateString()))->startOfDay();
        $journey = Journey::where('branch_id', $this->branchId($request->user()))
            ->where('sales_id', $request->user()->id)
            ->where('status', 'Active')
            ->latest('actual_started_at')
            ->first();
        if (! $journey || $date->lt($journey->starts_at->startOfDay()) || $date->gt(($journey->ends_at ?? $journey->starts_at)->startOfDay())) {
            return response()->json(['journey' => null, 'visits' => [], 'downloadedAt' => now()->toIso8601String()]);
        }
        $visits = Visit::with('outlet:id,code,name,address,latitude,longitude')->where('journey_id', $journey->id)->whereDate('planned_for', $date)->orderByDesc('is_required')->get()->map(fn (Visit $visit) => $this->visitPayload($visit));

        return response()->json(['journey' => $this->payload($journey), 'visits' => $visits, 'downloadedAt' => now()->toIso8601String()]);
    }

    public function store(Request $request, IdempotencyService $idempotency, NotificationService $notifications, DeepLinkService $links)
    {
        return $idempotency->handle($request, function () use ($request, $notifications, $links) {
            $data = $request->validate(['id' => ['nullable', 'string'], 'type' => ['required', 'in:in_city,out_of_town'], 'destination' => ['required', 'string', 'max:200'], 'startsAt' => ['required', 'date'], 'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'], 'reason' => ['nullable', 'string', 'max:500']]);
            $startsAt = CarbonImmutable::parse($data['startsAt'])->startOfDay();
            $endsAt = CarbonImmutable::parse($data['endsAt'] ?? $data['startsAt'])->startOfDay();
            // Tipe perjalanan ditentukan sistem dari periode, agar Flutter dan
            // backend tidak memiliki aturan yang berbeda.
            $type = $endsAt->gt($startsAt) ? 'out_of_town' : 'in_city';
            // Approval hanya untuk override check-in di luar radius.
            $approval = 'Not Required';
            $journey = Journey::firstOrCreate(['client_journey_id' => $data['id'] ?? null], ['branch_id' => $this->branchId($request->user()), 'sales_id' => $request->user()->id, 'type' => $type, 'destination' => $data['destination'], 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'status' => 'Planned', 'approval_status' => $approval, 'reason' => $data['reason'] ?? null]);
            return response()->json($this->payload($journey), 201);
        });
    }

    public function status(Request $request, Journey $journey, IdempotencyService $idempotency, AuditLogger $audit)
    {
        abort_unless($journey->sales_id === $request->user()->id, 403);

        return $idempotency->handle($request, function () use ($request, $journey, $audit) {
            $data = $request->validate(['status' => ['required', 'in:Active,Completed,Cancelled'], 'reason' => ['nullable', 'string', 'max:500']]);
            if (! $this->canTransition($journey->status, $data['status'])) {
                return response()->json(['message' => 'Perubahan status journey tidak valid.', 'code' => 'JOURNEY_STATUS_INVALID'], 422);
            }
            if ($data['status'] === 'Cancelled' && blank($data['reason'] ?? null)) {
                return response()->json(['message' => 'Alasan pembatalan journey wajib diisi.', 'code' => 'JOURNEY_CANCEL_REASON_REQUIRED'], 422);
            }
            $journey->update(['status' => $data['status'], 'reason' => $data['reason'] ?? $journey->reason, 'actual_started_at' => $data['status'] === 'Active' ? now() : $journey->actual_started_at, 'actual_completed_at' => $data['status'] === 'Completed' ? now() : $journey->actual_completed_at]);
            if (in_array($data['status'], ['Active', 'Completed'], true)) {
                $audit->record($journey->sales_id, $journey->branch_id, 'journey_'.strtolower($data['status']), Journey::class, $journey->id, ['destination' => $journey->destination, 'type' => $journey->type]);
            }

            return response()->json($this->payload($journey));
        });
    }

    public function start(Request $request, Journey $journey, IdempotencyService $idempotency, AuditLogger $audit)
    {
        abort_unless($journey->sales_id === $request->user()->id, 403);

        return $idempotency->handle($request, function () use ($request, $journey, $audit) {
            if ($journey->status !== 'Planned') {
                return response()->json(['message' => 'Perjalanan hanya dapat dimulai dari status Planned.', 'code' => 'JOURNEY_STATUS_INVALID'], 422);
            }
            $anotherActiveJourney = Journey::where('branch_id', $journey->branch_id)
                ->where('sales_id', $journey->sales_id)
                ->where('status', 'Active')
                ->whereKeyNot($journey->id)
                ->exists();
            if ($anotherActiveJourney) {
                return response()->json(['message' => 'Selesaikan perjalanan aktif terlebih dahulu.', 'code' => 'JOURNEY_ACTIVE_EXISTS'], 422);
            }
            $from = CarbonImmutable::parse($journey->starts_at)->startOfDay();
            $until = CarbonImmutable::parse($journey->ends_at ?? $journey->starts_at)->startOfDay();
            if ($until->diffInDays($from) > 31) {
                return response()->json(['message' => 'Rentang perjalanan maksimal 31 hari.', 'code' => 'JOURNEY_PERIOD_INVALID'], 422);
            }
            for ($date = $from; $date->lte($until); $date = $date->addDay()) {
                $matches = OutletRouteAssignment::where('branch_id', $journey->branch_id)->where('sales_id', $journey->sales_id)->where('is_active', true)->where('day_of_week', $date->dayOfWeekIso)->where('week_of_month', min(4, intdiv($date->day - 1, 7) + 1))->pluck('outlet_id')->all();
                $allRoutes = OutletRouteAssignment::where('branch_id', $journey->branch_id)->where('sales_id', $journey->sales_id)->where('is_active', true)->distinct()->pluck('outlet_id')->all();
                foreach ($matches as $outletId) {
                    Visit::firstOrCreate(['journey_id' => $journey->id, 'outlet_id' => $outletId, 'planned_for' => $date->toDateString()], ['branch_id' => $journey->branch_id, 'sales_id' => $journey->sales_id, 'is_required' => true, 'status' => 'Planned', 'distance_km' => 0, 'metadata' => []]);
                }
                foreach (array_diff($allRoutes, $matches) as $outletId) {
                    Visit::firstOrCreate(['journey_id' => $journey->id, 'outlet_id' => $outletId, 'planned_for' => $date->toDateString()], ['branch_id' => $journey->branch_id, 'sales_id' => $journey->sales_id, 'is_required' => false, 'status' => 'Planned', 'distance_km' => 0, 'metadata' => []]);
                }
            }
            $journey->update(['status' => 'Active', 'actual_started_at' => now()]);
            $audit->record($journey->sales_id, $journey->branch_id, 'journey_started', Journey::class, $journey->id, ['destination' => $journey->destination, 'type' => $journey->type]);

            return response()->json($this->payload($journey));
        });
    }

    /**
     * Mengganti periode kunjungan yang salah tanpa menghapus rekam jejak.
     * Journey lama dan visit yang belum dikerjakan ditandai Cancelled, lalu
     * dibuat journey baru yang dapat diunduh/dimulai kembali.
     */
    public function resetVisits(Request $request, Journey $journey, IdempotencyService $idempotency, AuditLogger $audit)
    {
        abort_unless($journey->sales_id === $request->user()->id, 403);

        return $idempotency->handle($request, function () use ($request, $journey, $audit) {
            $data = $request->validate([
                'startsAt' => ['required', 'date'],
                'endsAt' => ['required', 'date', 'after_or_equal:startsAt'],
                'destination' => ['nullable', 'string', 'max:200'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);
            $activeVisit = Visit::query()
                ->where('journey_id', $journey->id)
                ->where('status', 'In Progress')
                ->exists();
            if ($activeVisit) {
                return response()->json([
                    'message' => 'Check-out, tunda, atau batalkan kunjungan aktif sebelum mereset periode perjalanan.',
                    'code' => 'JOURNEY_RESET_ACTIVE_VISIT',
                ], 422);
            }
            $startsAt = CarbonImmutable::parse($data['startsAt'])->startOfDay();
            $endsAt = CarbonImmutable::parse($data['endsAt'])->startOfDay();
            if ($endsAt->diffInDays($startsAt) > 31) {
                return response()->json(['message' => 'Rentang perjalanan maksimal 31 hari.', 'code' => 'JOURNEY_PERIOD_INVALID'], 422);
            }

            $replacement = \DB::transaction(function () use ($journey, $request, $data, $startsAt, $endsAt, $audit) {
                $pendingVisits = Visit::query()
                    ->where('journey_id', $journey->id)
                    ->whereIn('status', ['Planned', 'Pending'])
                    ->get();
                foreach ($pendingVisits as $visit) {
                    $visit->update(['status' => 'Cancelled']);
                    Approval::query()
                        ->where('entity_type', Visit::class)
                        ->where('entity_id', $visit->id)
                        ->where('status', 'Pending')
                        ->update([
                            'status' => 'Cancelled',
                            'comment' => 'Dibatalkan karena reset periode perjalanan.',
                            'decided_at' => now(),
                        ]);
                }
                $journey->update([
                    'status' => 'Cancelled',
                    'reason' => $data['reason'] ?? 'Reset periode kunjungan oleh pengguna.',
                    'actual_completed_at' => now(),
                ]);
                $newJourney = Journey::create([
                    'branch_id' => $journey->branch_id,
                    'sales_id' => $journey->sales_id,
                    'type' => $endsAt->gt($startsAt) ? 'out_of_town' : 'in_city',
                    'destination' => filled($data['destination'] ?? null) ? $data['destination'] : $journey->destination,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'Planned',
                    'approval_status' => 'Not Required',
                    'reason' => 'Hasil reset dari perjalanan #'.$journey->id,
                ]);
                $audit->record($request->user()->id, $journey->branch_id, 'journey_visits_reset', Journey::class, $newJourney->id, [
                    'replacesJourneyId' => $journey->id,
                    'cancelledUnstartedVisits' => $pendingVisits->count(),
                    'startsAt' => $startsAt->toDateString(),
                    'endsAt' => $endsAt->toDateString(),
                ]);

                return $newJourney;
            });

            return response()->json([
                'journey' => $this->payload($replacement),
                'message' => 'Periode kunjungan baru telah dibuat. Data kunjungan sebelumnya tetap tersimpan.',
            ], 201);
        });
    }

    private function payload(Journey $j): array
    {
        return ['id' => $j->client_journey_id ?? (string) $j->id, 'serverId' => (string) $j->id, 'type' => $j->type, 'destination' => $j->destination, 'startsAt' => $j->starts_at->toIso8601String(), 'endsAt' => $j->ends_at?->toIso8601String(), 'actualStartedAt' => $j->actual_started_at?->toIso8601String(), 'actualCompletedAt' => $j->actual_completed_at?->toIso8601String(), 'status' => $j->status, 'approvalStatus' => $j->approval_status, 'reason' => $j->reason];
    }

    private function canTransition(string $from, string $to): bool
    {
        return match ($from) {
            'Planned' => in_array($to, ['Active', 'Cancelled'], true),
            'Active' => in_array($to, ['Completed', 'Cancelled'], true),
            default => false,
        };
    }

    private function visitPayload(Visit $visit): array
    {
        return ['id' => $visit->client_visit_id ?? (string) $visit->id, 'journeyId' => (string) $visit->journey_id, 'outletId' => (string) $visit->outlet_id, 'outletName' => $visit->outlet?->name, 'outletCode' => $visit->outlet?->code, 'outletAddress' => $visit->outlet?->address, 'status' => $visit->status, 'isRequired' => $visit->is_required, 'plannedFor' => $visit->planned_for?->toDateString(), 'distanceKm' => (float) $visit->distance_km, 'latitude' => $visit->outlet?->latitude ?? $visit->latitude, 'longitude' => $visit->outlet?->longitude ?? $visit->longitude, 'salesName' => $visit->sales?->name, 'createdAt' => $visit->created_at->toIso8601String()];
    }
}
