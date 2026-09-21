<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class InternalMasterAuthController extends Controller
{
    public function create(): View
    {
        return view('internal-master-data.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ]);
        $user = User::with('role')
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('username', $data['login'])->orWhere('email', $data['login']))
            ->first();
        if (! $user || ! in_array($user->role?->slug, ['operational', 'it'], true) || ! Hash::check($data['password'], $user->password)) {
            return back()->withInput()->withErrors(['login' => 'Username/email atau password tidak valid, atau akun tidak memiliki akses portal.']);
        }
        $request->session()->regenerate();
        $request->session()->put('internal_master_user_id', $user->id);

        return to_route('internal.master-data.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('internal.master-data.login');
    }
}
