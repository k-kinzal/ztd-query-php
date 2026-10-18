<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attachment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class PurgeAttachments implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        foreach (Attachment::whereNotNull('purge_requested_at')->lazyById(100) as $attachment) {
            if (!Storage::disk($attachment->disk)->delete($attachment->path)) {
                throw new RuntimeException('Private attachment deletion failed for ' . $attachment->id);
            }
            $attachment->delete();
        }
    }
}
