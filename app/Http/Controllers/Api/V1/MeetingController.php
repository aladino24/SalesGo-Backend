<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Meeting;
use App\Services\IdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MeetingController extends ApiController
{
    public function index(Request $request)
    {
        return response()->json(Meeting::where('branch_id', $this->branchId($request->user()))->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))->orderBy('starts_at')->get()->map(fn ($m) => $this->payload($m)));
    }

    public function store(Request $request, IdempotencyService $idempotency)
    {
        return $idempotency->handle($request, function () use ($request) {
            $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:1000'], 'startsAt' => ['required', 'date'], 'endsAt' => ['required', 'date', 'after:startsAt'], 'participantIds' => ['nullable', 'array']]);
            $meeting = Meeting::create(['branch_id' => $this->branchId($request->user()), 'created_by' => $request->user()->id, 'meeting_code' => strtoupper(Str::random(10)), 'title' => $data['title'], 'description' => $data['description'] ?? null, 'starts_at' => $data['startsAt'], 'ends_at' => $data['endsAt'], 'status' => 'Upcoming']);

            return response()->json($this->payload($meeting), 201);
        });
    }

    public function join(Request $request, Meeting $meeting)
    {
        abort_unless($meeting->branch_id === $this->branchId($request->user()), 403);
        abort_unless(in_array($meeting->status, ['Upcoming', 'Ongoing'], true), 422);

        return response()->json(['joinUrl' => $meeting->join_url]);
    }

    public function joinByCode(Request $request)
    {
        $data = $request->validate(['meetingId' => ['required', 'string']]);
        $meeting = Meeting::where('meeting_code', $data['meetingId'])->where('branch_id', $this->branchId($request->user()))->first();
        if (! $meeting) {
            return response()->json(['message' => 'Meeting tidak dapat diakses.', 'code' => 'MEETING_NOT_AVAILABLE'], 404);
        }

return $this->join($request, $meeting);
    }

    private function payload(Meeting $m): array
    {
        return ['id' => (string) $m->id, 'title' => $m->title, 'description' => $m->description, 'startsAt' => $m->starts_at->toIso8601String(), 'endsAt' => $m->ends_at->toIso8601String(), 'status' => $m->status, 'provider' => $m->provider, 'joinUrl' => $m->join_url, 'hostName' => optional($m->creator)->name, 'participantCount' => 0, 'agenda' => $m->agenda ?? []];
    }
}
