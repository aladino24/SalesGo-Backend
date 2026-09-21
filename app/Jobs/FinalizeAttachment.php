<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class FinalizeAttachment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 120, 240, 480, 960];

    public function __construct(public int $attachmentId) {}

    public function handle(AuditLogger $audit): void
    {
        $attachment = Attachment::findOrFail($this->attachmentId);
        if ($attachment->status === 'Finalized') {
            return;
        }
        try {
            $disk = Storage::disk($attachment->disk);
            if (! $disk->exists($attachment->path)) {
                throw new \RuntimeException('Object attachment tidak ditemukan.');
            }
            $attachment->update(['sha256' => hash('sha256', $disk->get($attachment->path)), 'status' => 'Finalized', 'error_message' => null, 'finalized_at' => now()]);
            $audit->record($attachment->uploaded_by, $attachment->branch_id, 'attachment_finalized', Attachment::class, $attachment->id, ['sha256' => $attachment->sha256, 'sizeBytes' => $attachment->size_bytes]);
        } catch (\Throwable $error) {
            $attachment->update(['status' => 'Failed', 'error_message' => $error->getMessage()]);
            $audit->record($attachment->uploaded_by, $attachment->branch_id, 'attachment_failed', Attachment::class, $attachment->id, ['message' => $error->getMessage()]);
            throw $error;
        }
    }
}
