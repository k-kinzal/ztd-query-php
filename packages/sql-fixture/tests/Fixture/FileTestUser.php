<?php

declare(strict_types=1);

namespace Tests\Fixture;

final class FileTestUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {
    }
}
