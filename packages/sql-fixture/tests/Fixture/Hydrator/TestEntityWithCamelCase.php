<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithCamelCase
{
    public function __construct(
        public readonly int $userId,
        public readonly string $fullName,
    ) {
    }
}
