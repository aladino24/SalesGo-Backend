<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\UserDeviceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\CarbonImmutable;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'deviceId' => ['required', 'uuid'],
            'platform' => ['required', 'in:android,ios,web'],
        ]);
        $user = User::where('username', $data['username'])->orWhere('email', $data['username'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Username atau password tidak valid.', 'code' => 'INVALID_CREDENTIALS'], 401);
        }
        return \DB::transaction(function () use ($data, $user) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $expiresAt = $this->sessionExpiresAt();
            $this->revokeExpiredSessions($lockedUser->id);
            $session = UserDeviceSession::query()
                ->where('user_id', $lockedUser->id)
                ->where('device_id', $data['deviceId'])
                ->lockForUpdate()
                ->first();

            $activeOtherDevices = UserDeviceSession::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->when($session, fn ($query) => $query->whereKeyNot($session->id))
                ->count();
            $maximum = max(1, (int) $lockedUser->max_devices);
            if ($activeOtherDevices >= $maximum) {
                return response()->json([
                    'message' => "Batas {$maximum} perangkat aktif sudah tercapai. Logout dari perangkat yang masih aktif terlebih dahulu.",
                    'code' => 'DEVICE_LIMIT_REACHED',
                    'maxDevices' => $maximum,
                ], 409);
            }

            if ($session) {
                $this->revokeSession($session);
            }

            return $this->loginTokenResponse($lockedUser, $expiresAt, $data['deviceId'], $data['platform'], $session);
        });
    }

    public function refresh(Request $request)
    {
        $data = $request->validate(['refreshToken' => ['required', 'string']]);
        $row = \DB::table('refresh_tokens')->where('token_hash', hash('sha256', $data['refreshToken']))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if (! $row || ! ($user = User::find($row->user_id))) {
            return response()->json(['message' => 'Refresh token tidak valid.', 'code' => 'INVALID_REFRESH_TOKEN'], 401);
        }
        \DB::table('refresh_tokens')->where('id', $row->id)->update(['revoked_at' => now(), 'updated_at' => now()]);

        // Refresh hanya mengganti token akses. Ia tidak boleh memperpanjang
        // sesi melewati tengah malam/batas sesi awal.
        $session = UserDeviceSession::query()
            ->where('user_id', $user->id)
            ->where('refresh_token_id', $row->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if (! $session) {
            return response()->json(['message' => 'Sesi perangkat tidak lagi aktif.', 'code' => 'DEVICE_SESSION_REVOKED'], 401);
        }
        if ($session->access_token_id) {
            \DB::table('personal_access_tokens')->where('id', $session->access_token_id)->delete();
        }

        return $this->loginTokenResponse($user, CarbonImmutable::parse($row->expires_at), $session->device_id, $session->platform, $session);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $session = UserDeviceSession::where('user_id', $request->user()->id)
                ->where('access_token_id', $token->id)
                ->first();
            if ($session) {
                $this->revokeSession($session);
            } else {
                $token->delete();
            }
        }

        return response()->noContent();
    }

    private function loginTokenResponse(User $user, CarbonImmutable $expiresAt, string $deviceId, string $platform, ?UserDeviceSession $session = null)
    {
        $refresh = Str::random(80);
        $refreshTokenId = \DB::table('refresh_tokens')->insertGetId(['user_id' => $user->id, 'token_hash' => hash('sha256', $refresh), 'expires_at' => $expiresAt, 'created_at' => now(), 'updated_at' => now()]);
        $accessToken = $user->createToken('mobile:'.$deviceId, ['*'], $expiresAt);
        $session ??= new UserDeviceSession(['user_id' => $user->id, 'device_id' => $deviceId]);
        $session->fill(['platform' => $platform, 'access_token_id' => $accessToken->accessToken->id, 'refresh_token_id' => $refreshTokenId, 'last_seen_at' => now(), 'expires_at' => $expiresAt, 'revoked_at' => null]);
        $session->save();

        return response()->json(['accessToken' => $accessToken->plainTextToken, 'refreshToken' => $refresh, 'expiresAt' => $expiresAt->toIso8601String(), 'user' => $this->userPayload($user)]);
    }

    private function revokeExpiredSessions(int $userId): void
    {
        UserDeviceSession::query()->where('user_id', $userId)->whereNull('revoked_at')->where('expires_at', '<=', now())->get()->each(fn (UserDeviceSession $session) => $this->revokeSession($session));
    }

    private function revokeSession(UserDeviceSession $session): void
    {
        if ($session->access_token_id) {
            \DB::table('personal_access_tokens')->where('id', $session->access_token_id)->delete();
        }
        if ($session->refresh_token_id) {
            \DB::table('refresh_tokens')->where('id', $session->refresh_token_id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        }
        $session->update(['revoked_at' => now()]);
    }

    private function sessionExpiresAt(): CarbonImmutable
    {
        $now = CarbonImmutable::now('Asia/Jakarta');
        $nextMidnight = $now->addDay()->startOfDay();
        $twentyFourHours = $now->addDay();

        return $nextMidnight->lessThan($twentyFourHours) ? $nextMidnight : $twentyFourHours;
    }
}
