<?php

declare(strict_types=1);

namespace Tests\Fixture;

/**
 * A user hydrated from a schema read out of a DDL directory.
 */
final class FileTestUser
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
