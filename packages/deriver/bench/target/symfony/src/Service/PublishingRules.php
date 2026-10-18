<?php

declare(strict_types=1);

namespace App\Service;

final class PublishingRules
{
    public function initialState(bool $reviewRequired, string $role): string
    {
        return $reviewRequired && $role === 'member' ? 'pending' : 'published';
    }
    public function notificationMessage(string $event): string
    {
        return match($event) {
            'reply' => 'A new reply was posted in a discussion you follow.','approve' => 'A discussion you follow was approved.','lock' => 'A discussion you follow was locked.',default => 'A discussion you follow has changed.'
        };
    }
    public function reportDeadlineHours(string $category): int
    {
        return match($category) {
            'privacy' => 4,'harassment' => 12,'copyright' => 24,default => 48
        };
    }
}
