<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppRelease;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AppUpdateController extends ApiController
{
    /**
     * Endpoint ringan untuk aplikasi Android. APK tidak dibuka untuk umum;
     * tautan download tetap membutuhkan token Sanctum pengguna aktif.
     */
    public function checkAndroid(Request $request)
    {
        $data = $request->validate([
            'versionCode' => ['nullable', 'integer', 'min:0'],
            'versionName' => ['nullable', 'string', 'max:40'],
            'md5' => ['nullable', 'string', 'size:32'],
        ]);
        $release = AppRelease::query()
            ->where('platform', 'android')
            ->where('is_published', true)
            ->latest('version_code')
            ->first();

        if (! $release) {
            return response()->json(['updateAvailable' => false]);
        }

        $installedCode = (int) ($data['versionCode'] ?? 0);
        $installedMd5 = strtolower((string) ($data['md5'] ?? ''));
        // Tidak menawarkan downgrade. Untuk versionCode yang sama, perbedaan
        // MD5 tetap ditawarkan agar artefak internal dapat dipulihkan.
        $updateAvailable = $release->version_code >= $installedCode
            && ! hash_equals($release->md5, $installedMd5);

        return response()->json([
            'updateAvailable' => $updateAvailable,
            'versionName' => $release->version_name,
            'versionCode' => $release->version_code,
            'md5' => $release->md5,
            'sha256' => $release->sha256,
            'sizeBytes' => $release->size_bytes,
            'mandatory' => $release->is_mandatory,
            'releaseNotes' => $release->release_notes,
            // Relative terhadap API_BASE_URL Flutter yang sudah berakhiran /api/v1.
            'downloadPath' => $updateAvailable ? '/app-updates/android/'.$release->id.'/download' : null,
        ]);
    }

    public function downloadAndroid(Request $request, AppRelease $release, AuditLogger $audit)
    {
        abort_unless($release->platform === 'android' && $release->is_published, 404);
        abort_unless(Storage::disk($release->disk)->exists($release->path), 404);

        $audit->record(
            $request->user()->id,
            $this->branchId($request->user()),
            'app_release_downloaded',
            AppRelease::class,
            $release->id,
            ['versionCode' => $release->version_code, 'md5' => $release->md5],
        );

        return Storage::disk($release->disk)->download(
            $release->path,
            $release->original_name,
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }

    /** Operasional internal: unggah hanya oleh Branch Manager. */
    public function storeAndroid(Request $request, AuditLogger $audit)
    {
        abort_unless($this->isSuperUser($request->user()), 403, 'Hanya role IT yang dapat mengunggah rilis APK.');
        $data = $request->validate([
            'apk' => ['required', 'file', 'max:204800', 'extensions:apk'],
            'versionName' => ['required', 'string', 'max:40'],
            'versionCode' => ['required', 'integer', 'min:1'],
            'releaseNotes' => ['nullable', 'string', 'max:5000'],
            'mandatory' => ['nullable', 'boolean'],
            'publish' => ['nullable', 'boolean'],
        ]);
        $apk = $data['apk'];
        $disk = config('filesystems.private_disk', 'local');
        $path = $apk->store('app-releases/android/'.now()->format('Y/m'), $disk);
        $published = $data['publish'] ?? true;
        $release = AppRelease::create([
            'platform' => 'android',
            'version_name' => $data['versionName'],
            'version_code' => $data['versionCode'],
            'md5' => md5_file($apk->getRealPath()),
            'sha256' => hash_file('sha256', $apk->getRealPath()),
            'disk' => $disk,
            'path' => $path,
            'original_name' => $apk->getClientOriginalName(),
            'size_bytes' => $apk->getSize(),
            'release_notes' => $data['releaseNotes'] ?? null,
            'is_mandatory' => $data['mandatory'] ?? false,
            'is_published' => $published,
            'uploaded_by' => $request->user()->id,
            'published_at' => $published ? now() : null,
        ]);
        $audit->record($request->user()->id, $this->branchId($request->user()), 'app_release_uploaded', AppRelease::class, $release->id, ['versionCode' => $release->version_code, 'md5' => $release->md5]);

        return response()->json($this->payload($release), 201);
    }

    private function payload(AppRelease $release): array
    {
        return [
            'id' => (string) $release->id,
            'platform' => $release->platform,
            'versionName' => $release->version_name,
            'versionCode' => $release->version_code,
            'md5' => $release->md5,
            'sizeBytes' => $release->size_bytes,
            'mandatory' => $release->is_mandatory,
            'published' => $release->is_published,
            'releaseNotes' => $release->release_notes,
        ];
    }
}
