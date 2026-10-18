<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Attachment, Post, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Gate, Storage};
use Throwable;

final class Attachments
{
    public function upload(Post $post, User $user, UploadedFile $file): Attachment
    {
        Gate::forUser($user)->authorize('update', $post);
        $path = $file->store('attachments/' . $post->id, 'local');
        abort_if($path === false, 503, 'Attachment storage is unavailable.');
        try {
            return DB::transaction(function () use ($post, $user, $file, $path): Attachment {
                $post = Post::whereKey($post->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('update', $post);
                abort_if($post->attachments()->count() >= 5, 422, 'Each post may contain at most five attachments.');
                return $post->attachments()->create(['user_id' => $user->id, 'disk' => 'local', 'path' => $path, 'name' => basename($file->getClientOriginalName()), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }
}
