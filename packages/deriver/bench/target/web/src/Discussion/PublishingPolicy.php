<?php

declare(strict_types=1);

namespace Harbor\Discussion;

final class PublishingPolicy
{
    public function initialState(bool $reviewRequired, string $role): string
    {
        if ($reviewRequired && $role === 'member') {
            return 'pending';
        }
        return 'published';
    }

    public function editingWindow(string $role): int
    {
        return match ($role) {
            'owner', 'moderator' => 2592000,
            default => 1800,
        };
    }

    public function notificationText(string $event): string
    {
        return match ($event) {
            'reply' => 'A new reply was posted in a discussion you follow.',
            'published' => 'A discussion you follow has been published.',
            'locked' => 'A discussion you follow has been locked.',
            default => 'A discussion you follow has changed.',
        };
    }
}
