<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithNullable
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $name = null,
    ) {
    }
}
