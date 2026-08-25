<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity naming its properties differently from the columns they hold.
 */
class TestEntityWithPropertyMapping
{
    public function __construct(
        public string $userName = '',
    ) {
    }
}
