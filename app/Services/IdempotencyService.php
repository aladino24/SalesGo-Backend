<?php

namespace App\Services;

use App\Models\IdempotencyRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IdempotencyService
{
    public function handle(Request $request, callable $operation): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        if (! $key) {
            return response()->json(['message' => 'Idempotency-Key wajib diisi.', 'code' => 'IDEMPOTENCY_KEY_REQUIRED'], 422);
        }
        $endpoint = $request->method().' '.$request->route()->uri();
        $hash = hash('sha256', $request->getContent());

        return DB::transaction(function () use ($request, $key, $endpoint, $hash, $operation) {
            $existing = IdempotencyRecord::where('user_id', $request->user()->id)->where('idempotency_key', $key)->where('endpoint', $endpoint)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    app(AuditLogger::class)->record($request->user()->id, $request->user()->branch_id, 'sync_conflict', null, null, ['endpoint' => $endpoint, 'idempotencyKey' => $key, 'reason' => 'payload_mismatch']);

                    return response()->json(['message' => 'Payload berbeda untuk Idempotency-Key yang sama.', 'code' => 'IDEMPOTENCY_PAYLOAD_MISMATCH'], 409);
                }
                app(AuditLogger::class)->record($request->user()->id, $request->user()->branch_id, 'idempotency_replay', null, null, ['endpoint' => $endpoint, 'idempotencyKey' => $key]);

                return response()->json($existing->response_body, $existing->status_code);
            }
            /** @var JsonResponse $response */
            $response = $operation();
            IdempotencyRecord::create(['user_id' => $request->user()->id, 'idempotency_key' => $key, 'endpoint' => $endpoint, 'request_hash' => $hash, 'status_code' => $response->status(), 'response_body' => $response->getData(true), 'expires_at' => now()->addDays(30)]);
            app(AuditLogger::class)->record($request->user()->id, $request->user()->branch_id, 'sync_accepted', null, null, ['endpoint' => $endpoint, 'idempotencyKey' => $key, 'statusCode' => $response->status()]);

            return $response;
        });
    }
}
