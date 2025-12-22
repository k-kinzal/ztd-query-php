<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

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
     * Return primary key column names for a table.
     *
     * @return array<int, string>
     */
    public function getPrimaryKeys(string $tableName): array;
}

class_alias(SchemaReflector::class, 'KKinzal\\ZtdQueryPhp\\Schema\\SchemaReflectorInterface');
