<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ImportantFile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\IdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ImportantFileController extends ApiController
{
    public function index(Request $request)
    {
        return response()->json(ImportantFile::where('branch_id', $this->branchId($request->user()))->latest('updated_at')->get()->map(fn (ImportantFile $file) => $this->payload($file))->values());
    }

    public function store(Request $request, IdempotencyService $idempotency, AuditLogger $audit)
    {
        $this->ensureManager($request);

        return $idempotency->handle($request, function () use ($request, $audit) {
            $data = $request->validate(['file' => ['required', 'file', 'max:51200', 'mimetypes:application/pdf,image/jpeg,image/png,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'name' => ['nullable', 'string', 'max:160'], 'type' => ['nullable', 'string', 'max:30'], 'description' => ['nullable', 'string', 'max:1000'], 'replaceFileId' => ['nullable', 'integer']]);
            $branchId = $this->branchId($request->user());
            $existing = isset($data['replaceFileId']) ? ImportantFile::whereKey($data['replaceFileId'])->where('branch_id', $branchId)->firstOrFail() : null;
            $upload = $data['file'];
            $disk = config('filesystems.private_disk', 'local');
            $path = $upload->store('important-files/'.$branchId.'/'.now()->format('Y/m'), $disk);
            $attributes = ['name' => $data['name'] ?? $upload->getClientOriginalName(), 'type' => $data['type'] ?? strtolower($upload->getClientOriginalExtension() ?: 'file'), 'disk' => $disk, 'path' => $path, 'size_bytes' => $upload->getSize(), 'version' => hash_file('sha256', $upload->getRealPath()), 'description' => $data['description'] ?? null];
            if ($existing) {
                $oldDisk = $existing->disk;
                $oldPath = $existing->path;
                $existing->update($attributes);
                Storage::disk($oldDisk)->delete($oldPath);
                $file = $existing->fresh();
            } else {
                $file = ImportantFile::create([...$attributes, 'branch_id' => $branchId, 'uploaded_by' => $request->user()->id]);
            }
            $audit->record($request->user()->id, $branchId, $existing ? 'important_file_replaced' : 'important_file_uploaded', ImportantFile::class, $file->id, ['version' => $file->version, 'sizeBytes' => $file->size_bytes]);

            return response()->json($this->payload($file), $existing ? 200 : 201);
        });
    }

    public function downloadUrl(Request $request, ImportantFile $importantFile, AuditLogger $audit)
    {
        abort_unless($importantFile->branch_id === $this->branchId($request->user()), 403);
        $expiresAt = now()->addMinutes(10);
        $url = URL::temporarySignedRoute('files.signed-download', $expiresAt, ['importantFile' => $importantFile->id, 'user' => $request->user()->id]);
        $audit->record($request->user()->id, $importantFile->branch_id, 'important_file_download_url_issued', ImportantFile::class, $importantFile->id, ['expiresAt' => $expiresAt->toIso8601String()]);

        return response()->json(['downloadUrl' => $url, 'expiresAt' => $expiresAt->toIso8601String(), 'version' => $importantFile->version]);
    }

    public function signedDownload(Request $request, ImportantFile $importantFile, User $user, AuditLogger $audit)
    {
        abort_unless($importantFile->branch_id === $user->branch_id && $user->is_active, 403);
        abort_unless(Storage::disk($importantFile->disk)->exists($importantFile->path), 404);
        $audit->record($user->id, $importantFile->branch_id, 'important_file_downloaded', ImportantFile::class, $importantFile->id, ['version' => $importantFile->version]);

        return Storage::disk($importantFile->disk)->download($importantFile->path, $importantFile->name, ['Content-Type' => $this->contentType($importantFile)]);
    }

    private function ensureManager(Request $request): void
    {
        abort_unless($request->user()->role->slug === 'branchManager', 403);
    }

    private function payload(ImportantFile $file): array
    {
        return ['id' => (string) $file->id, 'name' => $file->name, 'type' => $file->type, 'size' => $file->size_bytes, 'version' => $file->version, 'updatedAt' => $file->updated_at->toIso8601String()];
    }

    private function contentType(ImportantFile $file): string
    {
        return match ($file->type) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
