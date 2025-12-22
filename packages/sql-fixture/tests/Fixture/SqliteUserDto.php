<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class SqliteUserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }
}
