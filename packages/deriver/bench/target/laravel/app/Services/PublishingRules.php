<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;

final class PublishingRules
{
    public function initialState(bool $review, string $role): string
    {
        return $review && $role === 'member' ? 'pending' : 'published';
    }
    public function nextState(string $state, string $action): string
    {
        return match ([$state, $action]) {
            ['pending','approve'], ['locked','unlock'], ['hidden','restore'] => 'published',
            ['published','lock'] => 'locked',
            ['published','hide'], ['locked','hide'] => 'hidden',
            ['locked','archive'] => 'archived',
            default => throw new DomainException('The requested publishing transition is not available.'),
        };
    }
    public function escalationHours(string $category): int
    {
        return match ($category) {
            'privacy' => 4, 'harassment' => 12, 'copyright' => 24, default => 48
        };
    }
}
