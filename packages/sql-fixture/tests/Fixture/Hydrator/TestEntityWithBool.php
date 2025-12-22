<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithBool
{
    public function __construct(
        public readonly bool $active,
    ) {
    }
}
