<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithString
{
    public function __construct(
        public readonly string $value,
    ) {
    }
}
