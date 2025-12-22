<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class UserDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}
