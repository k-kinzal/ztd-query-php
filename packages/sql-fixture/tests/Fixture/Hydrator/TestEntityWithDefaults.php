<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithDefaults
{
    public function __construct(
        public readonly int $id,
        public readonly string $name = 'default',
    ) {
    }
}
