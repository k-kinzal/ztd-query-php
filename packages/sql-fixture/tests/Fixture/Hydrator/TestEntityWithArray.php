<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * Fixture DTO used to verify object hydration.
 */
class TestEntityWithArray
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        public readonly array $items,
    ) {
    }
}
