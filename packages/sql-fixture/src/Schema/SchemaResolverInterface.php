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

    /**
     * Reports whether a table can be resolved.
     *
     * @param string $tableName Table to answer for
     *
     * @return bool True when this resolver knows it
     */
    public function has(string $tableName): bool;
}
