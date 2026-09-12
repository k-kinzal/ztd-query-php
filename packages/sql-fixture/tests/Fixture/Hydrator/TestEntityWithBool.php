<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * Fixture DTO used to verify object hydration.
 */
class TestEntityWithBool
{
    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        public readonly bool $active,
    ) {
    }
}
