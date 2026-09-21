<?php

namespace App\Jobs;

use App\Models\PushDelivery;
use App\Services\AuditLogger;
use App\Services\FirebaseMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 180, 600, 1800];

    public function __construct(public int $deliveryId) {}

    public function handle(AuditLogger $audit, FirebaseMessagingService $fcm): void
    {
        $delivery = PushDelivery::with(['notification', 'device'])->findOrFail($this->deliveryId);
        if (in_array($delivery->status, ['Delivered', 'Skipped'], true) || $delivery->device->disabled_at) {
            return;
        }
        if (! $fcm->isConfigured()) {
            $delivery->update(['status' => 'Skipped', 'provider_response' => ['reason' => 'FCM_ENABLED/FIREBASE_PROJECT_ID not configured']]);

            return;
        }
        try {
            $response = $fcm->send($delivery);
            $delivery->update(['status' => 'Delivered', 'attempts' => $delivery->attempts + 1, 'provider_response' => $response, 'delivered_at' => now(), 'failed_at' => null]);
            $audit->record($delivery->notification->user_id, $delivery->notification->branch_id, 'push_delivered', PushDelivery::class, $delivery->id, ['notificationId' => $delivery->notification_id]);
        } catch (\Throwable $error) {
            $delivery->update(['status' => 'Failed', 'attempts' => $delivery->attempts + 1, 'provider_response' => ['error' => $error->getMessage()], 'failed_at' => now()]);
            throw $error;
        }
    }
}
