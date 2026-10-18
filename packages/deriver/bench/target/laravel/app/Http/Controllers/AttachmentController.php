<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UploadAttachmentRequest;
use App\Models\{Attachment, Post};
use App\Services\Attachments;
use Illuminate\Support\Facades\{Gate, Storage};

final class AttachmentController
{
    public function store(UploadAttachmentRequest $request, Post $post, Attachments $service)
    {
        $file = $service->upload($post, $request->user(), $request->file('attachment'));
        return response()->json(['id' => $file->id, 'name' => $file->name, 'size' => $file->size], 201);
    }
    public function show(Attachment $attachment)
    {
        Gate::authorize('view', $attachment->post);
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->name, ['X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'"]);
    }
}
