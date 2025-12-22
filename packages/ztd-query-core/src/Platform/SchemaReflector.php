<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

/**
 * Contract for obtaining schema metadata from a live database.
 */
interface SchemaReflector
{
    /**
     * Return the CREATE TABLE statement for a table, if available.
     */
    public function getCreateStatement(string $tableName): ?string;

    /**
     * Return all table names and their CREATE TABLE statements.
     *
     * @return array<string, string> Table name => CREATE TABLE SQL.
     */
    public function reflectAll(): array;
}
