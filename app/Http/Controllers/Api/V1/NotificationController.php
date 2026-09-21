<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppNotification;
use App\Models\PushDevice;
use App\Services\AuditLogger;
use App\Services\IdempotencyService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $query = AppNotification::where('user_id', $request->user()->id)->latest();
        if ($request->boolean('unreadOnly')) {
            $query->whereNull('read_at');
        }

        return response()->json($query->paginate(min((int) $request->query('limit', 20), 100))->through(fn ($n) => ['id' => (string) $n->id, 'type' => $n->type, 'title' => $n->title, 'message' => $n->message, 'entityType' => $n->entity_type, 'entityId' => $n->entity_id, 'isRead' => $n->read_at !== null, 'createdAt' => $n->created_at->toIso8601String(), 'deepLink' => $n->deep_link]));
    }

    public function read(Request $request, AppNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function registerDevice(Request $request, IdempotencyService $idempotency, AuditLogger $audit, NotificationService $notifications)
    {
        return $idempotency->handle($request, function () use ($request, $audit, $notifications) {
            $data = $request->validate(['token' => ['required', 'string', 'min:20', 'max:1024'], 'platform' => ['required', 'in:android,ios,web']]);
            $tokenHash = hash('sha256', $data['token']);
            $device = PushDevice::updateOrCreate(['token_hash' => $tokenHash], ['user_id' => $request->user()->id, 'branch_id' => $request->user()->branch_id, 'platform' => $data['platform'], 'token' => $data['token'], 'last_seen_at' => now(), 'disabled_at' => null]);
            $audit->record($request->user()->id, $request->user()->branch_id, 'push_device_registered', PushDevice::class, $device->id, ['platform' => $device->platform]);
            // Token mungkin baru tersedia setelah approval dibuat. Replay
            // notifikasi unread terbaru tanpa membuat delivery duplikat.
            AppNotification::where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('id')
                ->get()
                ->each(fn (AppNotification $notification) => $notifications->dispatchPushes($notification));

            return response()->json(['id' => (string) $device->id, 'platform' => $device->platform, 'registeredAt' => $device->updated_at->toIso8601String()], 201);
        });
    }

    public function unregisterDevice(Request $request, PushDevice $device, IdempotencyService $idempotency, AuditLogger $audit)
    {
        abort_unless($device->user_id === $request->user()->id, 403);

        return $idempotency->handle($request, function () use ($request, $device, $audit) {
            $device->update(['disabled_at' => now()]);
            $audit->record($request->user()->id, $request->user()->branch_id, 'push_device_unregistered', PushDevice::class, $device->id);

            return response()->noContent();
        });
    }
}
