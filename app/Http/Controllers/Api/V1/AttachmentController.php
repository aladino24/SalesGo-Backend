<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\FinalizeAttachment;
use App\Models\Attachment;
use App\Services\AuditLogger;
use App\Services\IdempotencyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends ApiController
{
    public function store(Request $request, IdempotencyService $idempotency, AuditLogger $audit)
    {
        return $idempotency->handle($request, function () use ($request, $audit) {
            $data = $request->validate(['file' => ['required', 'file', 'max:10240', 'mimetypes:image/jpeg,image/png,application/pdf'], 'clientUuid' => ['nullable', 'uuid']]);
            if (! empty($data['clientUuid']) && ($existing = Attachment::where('client_uuid', $data['clientUuid'])->where('uploaded_by', $request->user()->id)->first())) {
                return response()->json($this->payload($existing));
            }
            $file = $data['file'];
            $disk = config('filesystems.private_disk', 'local');
            $path = $file->store('attachments/'.$request->user()->branch_id.'/'.now()->format('Y/m'), $disk);
            $attachment = Attachment::create(['client_uuid' => $data['clientUuid'] ?? null, 'branch_id' => $request->user()->branch_id, 'uploaded_by' => $request->user()->id, 'disk' => $disk, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size_bytes' => $file->getSize(), 'status' => 'Uploaded']);
            $audit->record($request->user()->id, $request->user()->branch_id, 'attachment_uploaded', Attachment::class, $attachment->id, ['mimeType' => $attachment->mime_type, 'sizeBytes' => $attachment->size_bytes]);
            FinalizeAttachment::dispatch($attachment->id)->afterCommit();

            return response()->json($this->payload($attachment), 202);
        });
    }

    public function show(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->branch_id === $request->user()->branch_id && $attachment->uploaded_by === $request->user()->id, 403);

        return response()->json($this->payload($attachment));
    }

    public function content(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->branch_id === $request->user()->branch_id, 403);
        abort_unless(str_starts_with($attachment->mime_type, 'image/'), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime_type]);
    }

    private function payload(Attachment $attachment): array
    {
        return ['id' => (string) $attachment->id, 'clientUuid' => $attachment->client_uuid, 'name' => $attachment->original_name, 'mimeType' => $attachment->mime_type, 'sizeBytes' => $attachment->size_bytes, 'status' => $attachment->status, 'sha256' => $attachment->sha256, 'error' => $attachment->error_message, 'finalizedAt' => $attachment->finalized_at?->toIso8601String()];
    }
}
