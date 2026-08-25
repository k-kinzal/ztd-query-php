<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity holding a column read as a float.
 */
class TestEntityWithFloat
{
    /**
     * @param float $amount Amount the row carries
     */
    public function __construct(
        public readonly float $amount,
    ) {
    }
}
