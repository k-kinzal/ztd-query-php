<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * Fixture DTO used to verify object hydration.
 */
class TestEntityWithoutConstructor
{
    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        public int $id = 0,
        public string $name = '',
    ) {
    }
}
