<?php

namespace App\Services;

use App\Models\PushDelivery;
use Google\Auth\ApplicationDefaultCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FirebaseMessagingService
{
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return (bool) config('salesgo.fcm_enabled') && filled(config('salesgo.firebase_project_id'));
    }

    /** @return array<string, mixed> */
    public function send(PushDelivery $delivery): array
    {
        $projectId = (string) config('salesgo.firebase_project_id');
        $notification = $delivery->notification;
        $device = $delivery->device;
        $response = Http::timeout(15)->acceptJson()->withToken($this->accessToken())->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
            'message' => [
                'token' => $device->token,
                'notification' => ['title' => $notification->title, 'body' => $notification->message],
                'data' => ['notificationId' => (string) $delivery->notification_id, 'deepLink' => (string) ($notification->deep_link ?? '')],
                'android' => ['priority' => 'high'],
            ],
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('FCM responded '.$response->status().': '.$response->body());
        }

        return $response->json() ?? [];
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm.http_v1.access_token', now()->addMinutes(50), function (): string {
            $credentials = ApplicationDefaultCredentials::getCredentials([self::FCM_SCOPE]);
            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;
            if (! is_string($accessToken) || blank($accessToken)) {
                throw new \RuntimeException('Firebase service account tidak menghasilkan access token.');
            }

            return $accessToken;
        });
    }
}
