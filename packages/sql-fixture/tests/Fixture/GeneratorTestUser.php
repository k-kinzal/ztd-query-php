<?php

declare(strict_types=1);

namespace Tests\Fixture;

/**
 * A user hydrated straight from a generated row.
 */
final class GeneratorTestUser
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
