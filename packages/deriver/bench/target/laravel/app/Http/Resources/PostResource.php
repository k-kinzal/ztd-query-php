<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'topic_id' => $this->topic_id, 'body' => $this->body, 'version' => $this->version, 'author' => ['id' => $this->author->id, 'name' => $this->author->name], 'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($file) => ['id' => $file->id, 'name' => $file->name, 'size' => $file->size, 'download_url' => route('attachments.show', $file)])), 'created_at' => $this->created_at->toIso8601String()];
    }
}
