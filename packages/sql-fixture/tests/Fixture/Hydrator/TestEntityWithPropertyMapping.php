<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithPropertyMapping
{
    public function __construct(
        public string $userName = '',
    ) {
    }
}
