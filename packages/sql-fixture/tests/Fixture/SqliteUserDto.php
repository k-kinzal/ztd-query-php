<?php

declare(strict_types=1);

namespace Tests\Fixture;

/**
 * A user hydrated from a row generated against a live SQLite table.
 */
final class SqliteUserDto
{
    /**
     * Builds one from the row generated against the table.
     *
     * @param int $id Identifier the row carries
     * @param string $name Name the row carries
     * @param string $email Address the row carries
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }
}
