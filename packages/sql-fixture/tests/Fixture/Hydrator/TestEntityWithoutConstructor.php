<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithoutConstructor
{
    public function __construct(
        public int $id = 0,
        public string $name = '',
    ) {
    }
}
