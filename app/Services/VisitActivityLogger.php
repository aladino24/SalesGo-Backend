<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\VisitActivity;

class VisitActivityLogger
{
    public function record(Visit $visit, string $activity, string $description, array $location = [], array $metadata = []): VisitActivity
    {
        $entry = VisitActivity::create(['branch_id' => $visit->branch_id, 'outlet_id' => $visit->outlet_id, 'visit_id' => $visit->id, 'sales_id' => $visit->sales_id, 'activity' => $activity, 'description' => $description, 'location' => $location ?: null, 'metadata' => $metadata ?: null, 'occurred_at' => now()]);
        app(AuditLogger::class)->record($visit->sales_id, $visit->branch_id, 'visit_'.$activity, Visit::class, $visit->id, ['activityId' => $entry->id, ...$metadata]);

        return $entry;
    }
}
