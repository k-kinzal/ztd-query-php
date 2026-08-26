<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity holding a column whose type says nothing about how to read it.
 */
class TestEntityWithMixed
{
    /**
     * @param mixed $value Value the row carries
     */
    public function __construct(
        public readonly mixed $value,
    ) {
    }
}
