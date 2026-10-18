<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'board_id' => $this->board_id, 'title' => $this->title, 'state' => $this->state->value, 'version' => $this->version, 'author' => ['id' => $this->author->id, 'name' => $this->author->name], 'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')), 'last_posted_at' => $this->last_posted_at->toIso8601String(), 'created_at' => $this->created_at->toIso8601String()];
    }
}
