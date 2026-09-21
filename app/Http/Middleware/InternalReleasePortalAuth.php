<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class InternalReleasePortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUserId = $request->session()->get('internal_master_user_id');
        $sessionUser = $sessionUserId
            ? User::query()->with('role')->where('is_active', true)->find($sessionUserId)
            : null;
        if ($sessionUser?->role?->slug === 'it') {
            $request->attributes->set('internalReleaseUser', $sessionUser);

            return $next($request);
        }

        $providedUser = (string) $request->getUser();
        $providedPassword = (string) $request->getPassword();
        $user = User::query()
            ->with('role')
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('username', $providedUser)->orWhere('email', $providedUser))
            ->first();
        if (! $user || $user->role?->slug !== 'it' || ! Hash::check($providedPassword, $user->password)) {
            return response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="SalesGo Internal Release Portal"',
            ]);
        }
        // Controller memakai user yang sama untuk uploaded_by dan audit trail.
        $request->attributes->set('internalReleaseUser', $user);

        return $next($request);
    }
}
