<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class DiscussionChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    public function __construct(public readonly int $topicId, public readonly int $version)
    {
    }
    public function broadcastOn(): array
    {
        return [new PrivateChannel('topics.' . $this->topicId)];
    }
    public function broadcastAs(): string
    {
        return 'discussion.changed';
    }
}
