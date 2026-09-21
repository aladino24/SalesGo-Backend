<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\UserDeviceSession;
use Illuminate\Http\Request;

class UserDeviceController extends ApiController
{
    public function updateLimit(Request $request, User $user)
    {
        abort_unless($this->isSuperUser($request->user()), 403);
        abort_unless($user->branch_id === $this->branchId($request->user()), 403);
        $data = $request->validate(['maxDevices' => ['required', 'integer', 'min:1', 'max:10']]);
        $user->update(['max_devices' => $data['maxDevices']]);

        return response()->json([
            'id' => (string) $user->id,
            'maxDevices' => $user->max_devices,
        ]);
    }

    public function sessions(Request $request, User $user)
    {
        abort_unless($this->isSuperUser($request->user()) || $user->id === $request->user()->id, 403);
        abort_unless($user->branch_id === $this->branchId($request->user()), 403);

        return response()->json(UserDeviceSession::query()
            ->where('user_id', $user->id)
            ->latest('last_seen_at')
            ->get()
            ->map(fn (UserDeviceSession $session) => [
                'id' => (string) $session->id,
                'platform' => $session->platform,
                'lastSeenAt' => $session->last_seen_at->toIso8601String(),
                'expiresAt' => $session->expires_at->toIso8601String(),
                'isActive' => $session->revoked_at === null && $session->expires_at->isFuture(),
            ])->values());
    }
}
