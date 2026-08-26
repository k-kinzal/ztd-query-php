<?php

declare(strict_types=1);

namespace Tests\Fixture\Hydrator;

/**
 * An entity whose parameters are spelled in camelCase where its columns are not.
 */
class TestEntityWithCamelCase
{
    /**
     * @param int $userId Identifier the row carries, spelled as the entity spells it
     * @param string $fullName Name the row carries, spelled as the entity spells it
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $fullName,
    ) {
    }
}
