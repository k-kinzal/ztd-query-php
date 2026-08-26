<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity nothing can build, because it is abstract.
 *
 * A hydrator is handed a class name rather than an object, so it only learns
 * that the class cannot be built when it tries. This is the class that makes
 * it try.
 */
abstract class TestEntityNeverBuilt
{
    /**
     * @param int $id Identifier the row carries
     */
    public function __construct(
        public readonly int $id,
    ) {
    }
}
