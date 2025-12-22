<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\TableDefinition;

/**
 * Parses DDL SQL into a structured TableDefinition.
 */
interface SchemaParser
{
    /**
     * Parse a CREATE TABLE SQL statement into a TableDefinition.
     *
     * @return TableDefinition|null Null if the SQL cannot be parsed as a CREATE TABLE statement.
     */
    public function parse(string $createTableSql): ?TableDefinition;
}
