<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity holding a column read as an array.
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
