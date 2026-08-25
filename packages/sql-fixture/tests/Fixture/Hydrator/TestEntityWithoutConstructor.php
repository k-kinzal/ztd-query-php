<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity declaring no constructor, so it is built by assigning properties.
 */
class TestEntityWithoutConstructor
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
    ) {
    }
}
