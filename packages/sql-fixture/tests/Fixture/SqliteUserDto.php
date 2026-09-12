<?php

declare(strict_types=1);

namespace Tests\Fixture;

/**
 * Fixture DTO used to verify object hydration.
 */
final class SqliteUserDto
{
    /**
     * Initializes the collaborators and declared state for this object.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {
    }
}
