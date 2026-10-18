<?php

declare(strict_types=1);

namespace App\Message;

final readonly class TopicActivity
{
    public function __construct(public int $topicId, public int $actorId, public int $version, public string $event)
    {
    }
}
