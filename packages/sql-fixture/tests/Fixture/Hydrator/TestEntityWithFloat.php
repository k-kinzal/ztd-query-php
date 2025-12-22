<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

class TestEntityWithFloat
{
    public function __construct(
        public readonly float $amount,
    ) {
    }
}
