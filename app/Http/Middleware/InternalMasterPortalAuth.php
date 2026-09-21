<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalMasterPortalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->session()->get('internal_master_user_id');
        $user = $userId ? User::with(['role', 'branch'])->where('is_active', true)->find($userId) : null;
        if (! $user || ! in_array($user->role?->slug, ['operational', 'it'], true)) {
            $request->session()->forget('internal_master_user_id');
            return redirect()->route('internal.master-data.login');
        }

        $request->attributes->set('internalMasterUser', $user);

        return $next($request);
    }
}
