<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppRelease;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class InternalAppReleaseController extends Controller
{
    public function index(Request $request): View
    {
        return view('internal-app-releases.index', [
            'releases' => AppRelease::query()->latest('version_code')->paginate(12),
            'actor' => $request->attributes->get('internalReleaseUser'),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'apk' => ['required', 'file', 'max:204800', 'extensions:apk'],
            'version_name' => ['required', 'string', 'max:40'],
            'version_code' => ['required', 'integer', 'min:1', 'unique:app_releases,version_code'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
            'is_mandatory' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $uploader = $this->auditUser($request);
        $apk = $data['apk'];
        $disk = config('filesystems.private_disk', 'local');
        $path = $apk->store('app-releases/android/'.now()->format('Y/m'), $disk);
        $published = $request->boolean('is_published');
        $release = AppRelease::create([
            'platform' => 'android',
            'version_name' => $data['version_name'],
            'version_code' => $data['version_code'],
            'md5' => md5_file($apk->getRealPath()),
            'sha256' => hash_file('sha256', $apk->getRealPath()),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $apk->getClientOriginalName(),
            'size_bytes' => $apk->getSize(),
            'release_notes' => $data['release_notes'] ?? null,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'is_published' => $published,
            'uploaded_by' => $uploader->id,
            'published_at' => $published ? now() : null,
        ]);
        $audit->record($uploader->id, $uploader->branch_id, 'app_release_uploaded_web', AppRelease::class, $release->id, [
            'versionCode' => $release->version_code,
            'md5' => $release->md5,
            'published' => $release->is_published,
        ]);

        return to_route('internal.app-releases.index')->with('success', "APK versi {$release->version_name} ({$release->version_code}) berhasil disimpan.");
    }

    private function auditUser(Request $request): User
    {
        $user = $request->attributes->get('internalReleaseUser');
        abort_unless($user instanceof User && $user->role?->slug === 'it', 403);

        return $user;
    }
}
