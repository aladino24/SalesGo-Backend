<?php

namespace App\Services;

use App\Jobs\DeliverPushNotification;
use App\Models\AppNotification;
use App\Models\PushDelivery;
use App\Models\User;

class NotificationService
{
    public function notify(User $user, string $type, string $title, string $message, ?string $entityType = null, string|int|null $entityId = null, ?string $deepLink = null): AppNotification
    {
        $notification = AppNotification::create(['user_id' => $user->id, 'branch_id' => $user->branch_id, 'type' => $type, 'title' => $title, 'message' => $message, 'entity_type' => $entityType, 'entity_id' => $entityId === null ? null : (string) $entityId, 'deep_link' => $deepLink]);
        $this->dispatchPushes($notification);

        return $notification;
    }

    public function notifyBranchManagers(int $branchId, string $type, string $title, string $message, ?string $entityType = null, string|int|null $entityId = null, ?string $deepLink = null): void
    {
        User::with('role')->where('branch_id', $branchId)->whereHas('role', fn ($query) => $query->where('slug', 'branchManager'))->get()->each(fn (User $user) => $this->notify($user, $type, $title, $message, $entityType, $entityId, $deepLink));
    }

    public function notifyVisitApprovers(int $branchId, string $type, string $title, string $message, ?string $entityType = null, string|int|null $entityId = null, ?string $deepLink = null): void
    {
        User::with('role')->where('branch_id', $branchId)->whereHas('role', fn ($query) => $query->whereIn('slug', ['supervisor', 'branchManager']))->get()->each(fn (User $user) => $this->notify($user, $type, $title, $message, $entityType, $entityId, $deepLink));
    }

    /**
     * Buat delivery hanya sekali per notifikasi/perangkat. Method ini juga
     * digunakan saat perangkat baru login agar notifikasi unread yang dibuat
     * sebelum token FCM tersedia tetap dapat diterima.
     */
    public function dispatchPushes(AppNotification $notification): void
    {
        $notification->user->pushDevices()->whereNull('disabled_at')->get()->each(function ($device) use ($notification): void {
            $delivery = PushDelivery::firstOrCreate(
                ['notification_id' => $notification->id, 'device_id' => $device->id],
                ['status' => 'Pending', 'attempts' => 0],
            );
            if ($delivery->wasRecentlyCreated) {
                DeliverPushNotification::dispatch($delivery->id)->afterCommit();
            }
        });
    }
}
