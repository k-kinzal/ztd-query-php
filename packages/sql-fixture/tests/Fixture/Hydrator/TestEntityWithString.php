<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity holding a column read as a string.
 */
class TestEntityWithString
{
    /**
     * @param string $value Value the row carries
     */
    public function __construct(
        public readonly string $value,
    ) {
    }
}
