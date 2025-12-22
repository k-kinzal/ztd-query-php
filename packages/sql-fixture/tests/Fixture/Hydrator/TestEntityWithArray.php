<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

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
