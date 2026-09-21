<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function record(?int $userId, ?int $branchId, string $event, ?string $entityType = null, string|int|null $entityId = null, array $metadata = []): void
    {
        DB::table('audit_logs')->insert(['user_id' => $userId, 'branch_id' => $branchId, 'event' => $event, 'entity_type' => $entityType, 'entity_id' => $entityId === null ? null : (string) $entityId, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }
}
