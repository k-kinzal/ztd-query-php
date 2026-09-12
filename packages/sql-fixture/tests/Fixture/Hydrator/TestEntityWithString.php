<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * Fixture DTO used to verify object hydration.
 */
class TestEntityWithString
{
    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        public readonly string $value,
    ) {
    }
}
