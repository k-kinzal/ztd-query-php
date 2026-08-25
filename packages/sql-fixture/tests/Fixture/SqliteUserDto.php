<?php

declare(strict_types=1);

namespace Tests\Fixture;

/**
 * A user hydrated from a row generated against a live SQLite table.
 */
final class SqliteUserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }
}
