<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithMixed
{
    public function __construct(
        public readonly mixed $value,
    ) {
    }
}
