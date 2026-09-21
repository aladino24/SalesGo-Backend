<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Outlet;
use App\Models\VisitActivity;
use Illuminate\Http\Request;

class VisitActivityController extends ApiController
{
    public function outletHistory(Request $request, Outlet $outlet)
    {
        abort_unless($outlet->branch_id === $this->branchId($request->user()), 403);
        $entries = VisitActivity::where('outlet_id', $outlet->id)->latest('occurred_at')->paginate(min((int) $request->query('limit', 30), 100));

        return response()->json($entries->through(fn ($entry) => $this->payload($entry)));
    }

    private function payload(VisitActivity $entry): array
    {
        return ['id' => (string) $entry->id, 'visitId' => (string) $entry->visit_id, 'outletId' => (string) $entry->outlet_id, 'activity' => $entry->activity, 'description' => $entry->description, 'location' => $entry->location, 'metadata' => $entry->metadata, 'occurredAt' => $entry->occurred_at->toIso8601String()];
    }
}
