<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity whose constructor supplies a default for a column the row may not carry.
 */
class TestEntityWithDefaults
{
    /**
     * @param int $id Identifier the row carries
     * @param string $name Name the row carries
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name = 'default',
    ) {
    }
}
