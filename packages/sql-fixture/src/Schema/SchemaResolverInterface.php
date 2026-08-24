<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * Resolves a table schema by name.
 *
 * A plan names its tables, so generating from one needs a lookup by name
 * rather than by DDL or by connection.
 */
interface SchemaResolverInterface
{
    /**
     * @throws SchemaNotFoundException
     */
    public function resolve(string $tableName): TableSchema;

    public function has(string $tableName): bool;
}
