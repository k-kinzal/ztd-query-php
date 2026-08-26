<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity holding a column read as a boolean.
 */
class TestEntityWithBool
{
    /**
     * @param bool $active Whether the row is active
     */
    public function __construct(
        public readonly bool $active,
    ) {
    }
}
