<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity built through its constructor, with a column per parameter.
 */
class TestEntity
{
    /**
     * @param int $id Identifier the row carries
     * @param string $name Name the row carries
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}
