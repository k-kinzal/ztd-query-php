<?php

declare(strict_types=1);

namespace HarborOps\Moderation;

use InvalidArgumentException;

final class ReviewPolicy
{
    public function initialState(string $severity): string
    {
        return match ($severity) {
            'urgent' => 'quarantined', 'review' => 'published', default => throw new InvalidArgumentException('Unsupported moderation severity.')
        };
    }
    public function resolutionState(string $decision): string
    {
        return match ($decision) {
            'uphold' => 'removed','dismiss' => 'published',default => throw new InvalidArgumentException('Decision must be uphold or dismiss.')
        };
    }
}
