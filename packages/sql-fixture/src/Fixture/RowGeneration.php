<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\Schema\TableSchema;

/**
 * Generates a row independently of provider defaults and platform selection.
 */
interface RowGeneration
{
    /**
     * Generate fixture data from a parsed schema.
     *
     * @template T of object
     * @param TableSchema $schema Parsed table schema
     * @param array<mixed> $overrides Override values
     * @param class-string<T>|null $className Deserialization target class
     * @return ($className is null ? array<string, mixed> : T)
     */
    public function generate(
        TableSchema $schema,
        array $overrides = [],
        ?string $className = null,
    ): array|object;
}
